<?php
// This file is part of mod_datalynx for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace datalynxrule_ridematch;

use core\task\manager as task_manager;
use datalynxrule_ridematch\task\find_matches;
use mod_datalynx\event\entry_deleted;
use mod_datalynx\local\rule\base;

/**
 * Notifies the authors of a ride offer and a ride request when their journeys match.
 *
 * The activity holds both kinds of entry, told apart by a radio button or select
 * field. Whenever one is saved this rule looks for journeys of the *other* kind
 * that overlap, and tells both authors about each new pair it finds.
 *
 * Matching itself is deliberately not done here. Datalynx dispatches rules
 * synchronously from inside the entry save
 * ({@see \mod_datalynx\local\rule\manager::trigger_rules()}), so a sweep over every
 * counterpart entry plus a batch of notifications would slow down every save and
 * put failures in the save path. {@see trigger()} therefore only queues an ad-hoc
 * task and returns; {@see \datalynxrule_ridematch\task\find_matches} does the work.
 *
 * @package    datalynxrule_ridematch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule extends base {
    /** @var string Rule type */
    public $type = 'ridematch';

    /** @var string Table pairing entries whose authors have already been notified. */
    const MATCH_TABLE = 'datalynx_ride_matches';

    /** @var float Departure tolerance used when the rule configures none, in hours. */
    const DEFAULT_TIME_TOLERANCE = 24.0;

    /**
     * Queue the match sweep for the entry this event concerns.
     *
     * @param \core\event\base $event
     * @return bool
     */
    public function trigger(\core\event\base $event) {
        $entryid = $this->resolve_entryid($event);
        if (!$entryid) {
            return false;
        }

        // A deleted entry can no longer match anything, and its recorded pairs have
        // to go so that a later re-match is treated as new. Cheap enough to do
        // inline, and it must not wait for cron in case the ids get reused.
        if ($event instanceof entry_deleted) {
            $this->forget_entry($entryid);

            return true;
        }

        if (!$this->is_configured()) {
            return false;
        }

        $task = new find_matches();
        $task->set_component('datalynxrule_ridematch');
        $task->set_custom_data([
            'ruleid' => (int) $this->rule->id,
            'dataid' => (int) $this->dlx->id(),
            'entryid' => $entryid,
        ]);
        task_manager::queue_adhoc_task($task, true);

        return true;
    }

    /**
     * Whether the rule has been told which fields to work with.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->rule->param2)
            && !empty($this->rule->param3)
            && ($this->rule->param4 ?? '') !== ''
            && ($this->rule->param5 ?? '') !== '';
    }

    /**
     * The itinerary field id this rule matches on.
     *
     * @return int
     */
    public function get_itinerary_fieldid(): int {
        return (int) ($this->rule->param2 ?? 0);
    }

    /**
     * The field id that says whether an entry offers or requests a ride.
     *
     * @return int
     */
    public function get_type_fieldid(): int {
        return (int) ($this->rule->param3 ?? 0);
    }

    /**
     * The value of the ride-type field that means "I am offering a ride".
     *
     * @return string
     */
    public function get_offer_value(): string {
        return (string) ($this->rule->param4 ?? '');
    }

    /**
     * The value of the ride-type field that means "I am looking for a ride".
     *
     * @return string
     */
    public function get_request_value(): string {
        return (string) ($this->rule->param5 ?? '');
    }

    /**
     * How far from a route either end of a journey may be, in kilometres.
     *
     * @return float
     */
    public function get_radius_km(): float {
        $radius = (float) ($this->rule->param6 ?? 0);
        if ($radius > 0) {
            return $radius;
        }

        // Fall back to whatever the itinerary field offers its own searchers.
        $field = $this->dlx->get_field_from_id($this->get_itinerary_fieldid());

        return ($field && method_exists($field, 'get_match_radius')) ? $field->get_match_radius() : 5.0;
    }

    /**
     * How far apart two departures may be and still be the same journey, in seconds.
     *
     * @return int
     */
    public function get_time_tolerance(): int {
        $hours = (float) ($this->rule->param7 ?? 0);

        return (int) round(($hours > 0 ? $hours : self::DEFAULT_TIME_TOLERANCE) * HOURSECS);
    }

    /**
     * The view used to render the notification body, or 0 for the plain default.
     *
     * @return int
     */
    public function get_template_viewid(): int {
        return (int) ($this->rule->param8 ?? 0);
    }

    /**
     * Drop every recorded pair involving an entry.
     *
     * @param int $entryid
     */
    protected function forget_entry(int $entryid): void {
        global $DB;

        $DB->delete_records_select(
            self::MATCH_TABLE,
            'ruleid = :ruleid AND (offerentryid = :offer OR requestentryid = :request)',
            ['ruleid' => (int) $this->rule->id, 'offer' => $entryid, 'request' => $entryid]
        );
    }
}
