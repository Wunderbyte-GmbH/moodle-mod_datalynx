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

use core_text;
use core_user;
use mod_datalynx\datalynx;
use mod_datalynx\local\ride\itinerary;
use mod_datalynx\local\ride\matcher;
use moodle_url;
use stdClass;

/**
 * Finds and announces ride matches for one saved entry.
 *
 * Separated from the ad-hoc task so it can be driven directly from tests without
 * going through the scheduler.
 *
 * The sweep is deliberately conservative about notifying: a pair is announced once
 * and then remembered, so editing an entry never re-notifies people who already
 * know about each other. A pair that stops matching is forgotten again, so a
 * genuine later re-match does get announced.
 *
 * @package    datalynxrule_ridematch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class matcher_service {
    /** @var array Resolved ride-type values, keyed by the configured value. */
    protected array $storedvalues = [];

    /**
     * Constructor.
     *
     * @param datalynx $dlx
     * @param rule $rule
     */
    protected function __construct(
        /** @var datalynx The activity the entries belong to. */
        protected datalynx $dlx,
        /** @var rule The configured rule. */
        protected rule $rule
    ) {
    }

    /**
     * Build the service, or null when the activity or rule no longer exists.
     *
     * @param int $dataid
     * @param int $ruleid
     * @return self|null
     */
    public static function create(int $dataid, int $ruleid): ?self {
        global $DB;

        if (!$DB->record_exists('datalynx', ['id' => $dataid])) {
            return null;
        }

        $dlx = new datalynx($dataid);
        $record = $DB->get_record('datalynx_rules', ['id' => $ruleid, 'dataid' => $dataid]);
        if (!$record) {
            return null;
        }

        $rule = new rule($dlx, $record);
        if (!$rule->is_enabled() || !$rule->is_configured()) {
            return null;
        }

        return new self($dlx, $rule);
    }

    /**
     * Find matches for one entry and notify the authors of each new pair.
     *
     * @param int $entryid
     * @return int Number of pairs newly announced.
     */
    public function process_entry(int $entryid): int {
        $subject = $this->load_ride($entryid);
        if ($subject === null) {
            return 0;
        }

        $counterparts = $this->load_counterparts($subject);
        $announced = 0;

        foreach ($counterparts as $other) {
            [$offer, $request] = $subject->isoffer ? [$subject, $other] : [$other, $subject];

            $matches = matcher::entry_covers_request(
                $this->rule->get_itinerary_fieldid(),
                $offer->entryid,
                $request->journey,
                $this->rule->get_radius_km()
            );

            if ($matches) {
                if ($this->remember_pair($offer->entryid, $request->entryid)) {
                    $this->notify_pair($offer, $request);
                    $announced++;
                }
            } else {
                // Forget a pair that no longer matches, so that if a later edit
                // brings them back together it counts as news again.
                $this->forget_pair($offer->entryid, $request->entryid);
            }
        }

        return $announced;
    }

    /**
     * Translate a configured ride-type value into what the field actually stores.
     *
     * Option-based fields (select, radio button) store the option's **position**,
     * not its label: choosing "Offer a ride" writes `1`. An administrator
     * configuring this rule naturally types the label, so accept either and
     * resolve labels to the stored position here. Without this, a rule that looks
     * correctly configured would silently never match anything.
     *
     * @param string $configured The value entered in the rule settings.
     * @return string The value to compare against `datalynx_contents.content`.
     */
    protected function stored_value_for(string $configured): string {
        $configured = trim($configured);
        if ($configured === '') {
            return $configured;
        }

        if (!array_key_exists($configured, $this->storedvalues)) {
            $this->storedvalues[$configured] = $configured;

            $field = $this->dlx->get_field_from_id($this->rule->get_type_fieldid());
            if ($field && method_exists($field, 'get_options')) {
                foreach ($field->get_options() as $position => $label) {
                    if (core_text::strtolower(trim((string) $label)) === core_text::strtolower($configured)) {
                        $this->storedvalues[$configured] = (string) $position;
                        break;
                    }
                }
            }
        }

        return $this->storedvalues[$configured];
    }

    /**
     * Load one entry as a ride: its journey, its author and which side it is on.
     *
     * @param int $entryid
     * @return stdClass|null
     */
    protected function load_ride(int $entryid): ?stdClass {
        global $DB;

        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid, 'dataid' => $this->dlx->id()]);
        if (!$entry) {
            return null;
        }

        $ridetype = (string) $DB->get_field(
            'datalynx_contents',
            'content',
            ['entryid' => $entryid, 'fieldid' => $this->rule->get_type_fieldid()],
            IGNORE_MISSING
        );

        $isoffer = $ridetype === $this->stored_value_for($this->rule->get_offer_value());
        $isrequest = $ridetype === $this->stored_value_for($this->rule->get_request_value());
        if (!$isoffer && !$isrequest) {
            // Neither side: not a ride as far as this rule is concerned.
            return null;
        }

        $content = $DB->get_record(
            'datalynx_contents',
            ['entryid' => $entryid, 'fieldid' => $this->rule->get_itinerary_fieldid()],
            'content, content1, content3',
            IGNORE_MISSING
        );
        if (!$content) {
            return null;
        }

        $journey = itinerary::from_json($content->content);
        if (!$journey->is_route()) {
            return null;
        }

        return (object) [
            'entryid' => $entryid,
            'userid' => (int) $entry->userid,
            'isoffer' => $isoffer,
            'journey' => $journey,
            'bbox' => itinerary::parse_bbox($content->content1),
            'departure' => $content->content3 === '' ? null : (int) $content->content3,
        ];
    }

    /**
     * Candidate rides on the other side that are worth testing properly.
     *
     * Cheap filters first: the right side, approved, someone else's, bounding boxes
     * that overlap, and a departure within tolerance. Only survivors reach the
     * corridor query.
     *
     * @param stdClass $subject
     * @return stdClass[]
     */
    protected function load_counterparts(stdClass $subject): array {
        global $DB;

        $wantedtype = $this->stored_value_for(
            $subject->isoffer ? $this->rule->get_request_value() : $this->rule->get_offer_value()
        );

        $sql = "SELECT e.id, e.userid, tc.content AS ridetype,
                       ic.content AS waypoints, ic.content1 AS bbox, ic.content3 AS departure
                  FROM {datalynx_entries} e
                  JOIN {datalynx_contents} tc ON tc.entryid = e.id AND tc.fieldid = :typefield
                  JOIN {datalynx_contents} ic ON ic.entryid = e.id AND ic.fieldid = :itinfield
                 WHERE e.dataid = :dataid
                   AND e.id <> :subjectid
                   AND e.userid <> :subjectuser
                   AND e.approved = 1
                   AND " . $DB->sql_compare_text('tc.content', 255) . " = " . $DB->sql_compare_text(':wantedtype', 255);

        $records = $DB->get_records_sql($sql, [
            'typefield' => $this->rule->get_type_fieldid(),
            'itinfield' => $this->rule->get_itinerary_fieldid(),
            'dataid' => $this->dlx->id(),
            'subjectid' => $subject->entryid,
            'subjectuser' => $subject->userid,
            'wantedtype' => $wantedtype,
        ]);

        $radius = $this->rule->get_radius_km();
        $tolerance = $this->rule->get_time_tolerance();
        $candidates = [];

        foreach ($records as $record) {
            $journey = itinerary::from_json($record->waypoints);
            if (!$journey->is_route()) {
                continue;
            }

            // Journeys that share no ground at all cannot match, and this avoids a
            // corridor query per entry.
            $bbox = itinerary::parse_bbox($record->bbox);
            if ($subject->bbox && $bbox && !itinerary::bbox_overlaps($subject->bbox, $bbox, $radius)) {
                continue;
            }

            // A lift next Friday is no use to someone travelling on Tuesday. When
            // either side gave no time, fall through rather than guess.
            $departure = ($record->departure === '' || $record->departure === null)
                ? null : (int) $record->departure;
            if (
                $subject->departure !== null && $departure !== null
                && abs($subject->departure - $departure) > $tolerance
            ) {
                continue;
            }

            $candidates[] = (object) [
                'entryid' => (int) $record->id,
                'userid' => (int) $record->userid,
                'isoffer' => !$subject->isoffer,
                'journey' => $journey,
                'bbox' => $bbox,
                'departure' => $departure,
            ];
        }

        return $candidates;
    }

    /**
     * Record a pair, returning false when it was already known.
     *
     * The unique index does the deciding, so two concurrent sweeps cannot both
     * announce the same pair.
     *
     * @param int $offerentryid
     * @param int $requestentryid
     * @return bool True when this pair is news.
     */
    protected function remember_pair(int $offerentryid, int $requestentryid): bool {
        global $DB;

        $key = [
            'ruleid' => (int) $this->rule->get_id(),
            'offerentryid' => $offerentryid,
            'requestentryid' => $requestentryid,
        ];

        if ($DB->record_exists(rule::MATCH_TABLE, $key)) {
            return false;
        }

        try {
            $DB->insert_record(rule::MATCH_TABLE, (object) ($key + ['timenotified' => time()]));
        } catch (\dml_exception $e) {
            // Lost a race against another sweep; the other one is notifying.
            return false;
        }

        return true;
    }

    /**
     * Forget a pair that no longer matches.
     *
     * @param int $offerentryid
     * @param int $requestentryid
     */
    protected function forget_pair(int $offerentryid, int $requestentryid): void {
        global $DB;

        $DB->delete_records(rule::MATCH_TABLE, [
            'ruleid' => (int) $this->rule->get_id(),
            'offerentryid' => $offerentryid,
            'requestentryid' => $requestentryid,
        ]);
    }

    /**
     * Tell both authors about each other.
     *
     * @param stdClass $offer
     * @param stdClass $request
     */
    protected function notify_pair(stdClass $offer, stdClass $request): void {
        $this->send_notification($offer, $request);
        $this->send_notification($request, $offer);
    }

    /**
     * Send one author a notification about the counterpart ride.
     *
     * @param stdClass $recipientride The ride belonging to the person being told.
     * @param stdClass $counterpartride The ride they are being told about.
     */
    protected function send_notification(stdClass $recipientride, stdClass $counterpartride): void {
        $recipient = core_user::get_user($recipientride->userid);
        if (!$recipient || !empty($recipient->deleted) || !empty($recipient->suspended)) {
            return;
        }

        $counterparturl = new moodle_url('/mod/datalynx/view.php', [
            'd' => $this->dlx->id(),
            'eids' => $counterpartride->entryid,
        ]);

        $placeholders = (object) [
            'datalynxname' => format_string($this->dlx->name()),
            'coursename' => format_string($this->dlx->course->fullname ?? ''),
            'yourroute' => $this->describe($recipientride->journey),
            'matchedroute' => $this->describe($counterpartride->journey),
            'url' => $counterparturl->out(false),
        ];

        $subjectkey = $recipientride->isoffer ? 'messagesubjectoffer' : 'messagesubjectrequest';

        $message = new \core\message\message();
        $message->component = 'datalynxrule_ridematch';
        $message->name = 'ridematch';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = get_string($subjectkey, 'datalynxrule_ridematch', $placeholders);
        $message->fullmessage = get_string('messagebody', 'datalynxrule_ridematch', $placeholders);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('messagebodyhtml', 'datalynxrule_ridematch', $placeholders);
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = $counterparturl->out(false);
        $message->contexturlname = get_string('viewmatchedride', 'datalynxrule_ridematch');

        message_send($message);
    }

    /**
     * A one-line "A to B" description of a journey.
     *
     * Only the two ends: the intermediate stops belong on the entry page, and a
     * notification should not become a list of everywhere someone is going.
     *
     * @param itinerary $journey
     * @return string
     */
    protected function describe(itinerary $journey): string {
        $first = $journey->first();
        $last = $journey->last();

        return get_string('routesummary', 'datalynxrule_ridematch', (object) [
            'from' => $first ? $first->address : '?',
            'to' => $last ? $last->address : '?',
        ]);
    }
}
