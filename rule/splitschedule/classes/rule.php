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

namespace datalynxrule_splitschedule;

use mod_datalynx\local\ride\schedule;
use mod_datalynx\local\rule\base;
use stdClass;

/**
 * Split a weekly schedule into one entry per date (mod_datalynx#342).
 *
 * When an entry whose schedule field holds a weekly pattern triggers the rule, the entry becomes the
 * entry of the first date, and a copy is made for every further date, at most param3 months ahead
 * and never beyond the end of the pattern. A copy has the author, group, approval and status of the
 * entry and its contents as stored ({@see \mod_datalynx\local\field\datalynxfield_base::copy_content_to_new_entry()}:
 * no team members, no files), and a one-off schedule on its date. Each copy fires entry_created and
 * the entry fires entry_updated, so other rules (e.g. matching) run for every date.
 *
 * Writes go straight through the fields, never through process_entries(). The rule only acts on
 * weekly schedules and makes one-off ones, so it does not act on its own output; a request-scoped
 * guard stops it acting twice on an entry.
 *
 * Params: param2 the schedule field id, param3 the most months ahead (default 6), param9 the
 * trigger conditions (shared with the other rule types).
 *
 * @package    datalynxrule_splitschedule
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule extends base {
    /** @var string Rule type */
    public $type = 'splitschedule';

    /** @var int Default of the most months ahead. */
    const DEFAULT_MONTHS = 6;

    /** @var array "{ruleid}:{entryid}" keys being processed in this request. */
    protected static array $processing = [];

    /**
     * The dates of a weekly schedule, from today (or its start, if later) to its end, at most the
     * given number of months ahead.
     *
     * @param schedule $schedule
     * @param int $months
     * @param int|null $now
     * @return string[] ISO dates in order
     */
    public static function dates(schedule $schedule, int $months, ?int $now = null): array {
        if ($schedule->mode() !== schedule::MODE_WEEKLY || !$schedule->days()) {
            return [];
        }
        $today = schedule::day_start(schedule::date_of($now ?? time()));
        $start = $today;
        if ($schedule->from() !== '' && ($from = schedule::day_start($schedule->from())) !== null) {
            $start = max($start, $from);
        }
        $end = strtotime('+' . max(1, $months) . ' months', $today);
        if ($schedule->until() !== '' && ($until = schedule::day_start($schedule->until())) !== null) {
            $end = min($end, $until);
        }
        $dates = [];
        for ($day = $start; $day <= $end; $day = strtotime('+1 day', $day)) {
            $date = schedule::date_of($day);
            if (in_array(schedule::weekday_of($date), $schedule->days(), true)) {
                $dates[] = $date;
            }
        }
        return $dates;
    }

    /**
     * Trigger: split the entry's weekly schedule into dated entries.
     *
     * @param \core\event\base $event
     * @return bool whether entries were made
     */
    public function trigger(\core\event\base $event): bool {
        global $DB;

        $entryid = $this->resolve_entryid($event);
        if (!$entryid) {
            return false;
        }
        $conditions = $this->get_trigger_conditions();
        if ($conditions && !$this->entry_matches_conditions($entryid, $conditions)) {
            return false;
        }
        $onchangeids = $this->get_onlyonchange_fieldids();
        if (
            $onchangeids && $event instanceof \mod_datalynx\event\entry_updated
            && !$this->change_constraint_satisfied($onchangeids, $event)
        ) {
            return false;
        }

        $key = $this->rule->id . ':' . $entryid;
        if (!empty(self::$processing[$key])) {
            return false;
        }
        self::$processing[$key] = true;
        try {
            return $this->split($entryid);
        } finally {
            unset(self::$processing[$key]);
        }
    }

    /**
     * Whether the entry meets the trigger conditions of the rule (e.g. a published offer).
     *
     * @param int $entryid
     * @return bool
     */
    public function applies_to(int $entryid): bool {
        $conditions = $this->get_trigger_conditions();
        return !$conditions || $this->entry_matches_conditions($entryid, $conditions);
    }

    /**
     * Split one entry.
     *
     * @param int $entryid
     * @return bool whether entries were made
     */
    public function split(int $entryid): bool {
        global $DB;
        $dlx = $this->dlx();
        $schedulefield = $dlx->get_field_from_id((int) ($this->rule->param2 ?? 0));
        if (!$schedulefield || $schedulefield->type !== 'schedule') {
            return false;
        }
        $row = $DB->get_record(
            'datalynx_contents',
            ['fieldid' => $schedulefield->id(), 'entryid' => $entryid],
            '*',
            IGNORE_MULTIPLE
        );
        $pattern = schedule::from_json($row->content ?? null);
        $months = (int) ($this->rule->param3 ?? 0) ?: self::DEFAULT_MONTHS;
        $dates = self::dates($pattern, $months);
        if (!$dates) {
            return false;
        }
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid], '*', MUST_EXIST);
        $once = fn(string $date): string => (new schedule(
            schedule::MODE_ONCE,
            $date,
            [],
            $pattern->start_minute(),
            $pattern->end_minute()
        ))->to_json();
        $setschedule = function (int $id, ?stdClass $current, string $json) use ($schedulefield): void {
            $target = (object) ['id' => $id];
            if ($current) {
                $target->{"c{$schedulefield->id()}_id"} = $current->id;
                $target->{"c{$schedulefield->id()}_content"} = $current->content;
            }
            $schedulefield->update_content($target, ['schedule' => $json]);
        };

        // The entry becomes the entry of the first date.
        $setschedule($entryid, $row ?: null, $once(array_shift($dates)));

        // A copy for every further date.
        $fields = array_filter($dlx->get_fields(), fn($field) => $field->id() > 0 && $field->id() !== $schedulefield->id());
        $copies = [];
        foreach ($dates as $date) {
            $copy = clone $entry;
            unset($copy->id);
            $copy->timecreated = $copy->timemodified = time();
            $copyid = (int) $DB->insert_record('datalynx_entries', $copy);
            foreach ($fields as $field) {
                $field->copy_content_to_new_entry($entryid, $copyid);
            }
            $setschedule($copyid, null, $once($date));
            $copies[] = $copyid;
        }

        // Other rules run for every date: the entry changed, the copies are new.
        \mod_datalynx\event\entry_updated::create([
            'context' => $dlx->context,
            'objectid' => $entryid,
            'other' => ['dataid' => $dlx->id(), 'changed_field_ids' => [$schedulefield->id()]],
        ])->trigger();
        foreach ($copies as $copyid) {
            \mod_datalynx\event\entry_created::create([
                'context' => $dlx->context,
                'objectid' => $copyid,
                'other' => ['dataid' => $dlx->id()],
            ])->trigger();
        }
        return true;
    }
}
