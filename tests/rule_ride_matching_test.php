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

/**
 * Ride matching expressed as an event notification rule.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxrule_eventnotification\rule;
use mod_datalynx\local\rule\match_compiler;

/**
 * Pairing ride offers with ride requests, which used to be a rule type of its own.
 *
 * Two rules cover it: one fires when an offer is saved and looks for the requests its route can
 * carry, the other fires when a request is saved and looks for the offers that can carry it. They
 * share one record of announced pairs, so a pair is introduced once between them.
 *
 * @group mod_datalynx
 * @covers \datalynxrule_eventnotification\rule
 * @covers \mod_datalynx\local\rule\match_compiler
 * @covers \mod_datalynx\local\rule\match_ledger
 */
final class rule_ride_matching_test extends advanced_testcase {
    /** @var array Real places, so distances in the tests are meaningful. */
    const PLACES = [
        'wien' => [48.1852, 16.3775],
        'wienerneustadt' => [47.8149, 16.2372],
        'graz' => [47.0736, 15.4161],
        'linz' => [48.2907, 14.2921],
    ];

    /** @var string The stored position of the "offering" option. */
    const OFFER = '1';

    /** @var string The stored position of the "looking for" option. */
    const REQUEST = '2';

    /** @var string The token both rules share, so a pair is announced once between them. */
    const SCOPE = 'rideshare';

    /** @var string The day the rides in these tests happen on, unless one says otherwise. */
    const DEFAULT_DATE = '2026-09-07';

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var \datalynxfield_itinerary\field */
    protected $itineraryfield;

    /** @var \stdClass */
    protected $typefield;

    /** @var \datalynxfield_schedule\field When a ride happens. */
    protected $schedulefield;

    /** @var int Rule fired by an offer, looking for the requests its route can carry. */
    protected int $offerruleid;

    /** @var int Rule fired by a request, looking for the offers that can carry it. */
    protected int $requestruleid;

    /** @var \stdClass */
    protected $driver;

    /** @var \stdClass */
    protected $passenger;

    /**
     * Build an activity with an itinerary field, a ride-type field and the two rules.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        // Messages are inspected, so the test cannot run inside a rolled back transaction.
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $this->driver = $this->getDataGenerator()->create_and_enrol($course);
        $this->passenger = $this->getDataGenerator()->create_and_enrol($course);

        $itinerary = (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Journey',
            'type' => 'itinerary',
            'description' => '',
            'param1' => 10,
            'param5' => 5,
        ];
        $itinerary->id = $DB->insert_record('datalynx_fields', $itinerary);

        $this->typefield = (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Ride type',
            'type' => 'select',
            'description' => '',
            'param1' => "Offer a ride\nLooking for a ride",
        ];
        $this->typefield->id = $DB->insert_record('datalynx_fields', $this->typefield);

        $schedule = (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Termin',
            'type' => 'schedule',
            'description' => '',
            'param1' => 30,
            'param2' => \datalynxfield_schedule\field::TOLERANCE_WHOLE_DAY,
        ];
        $schedule->id = $DB->insert_record('datalynx_fields', $schedule);

        $fields = $this->dlx->get_fields(null, false, true);
        $this->itineraryfield = $fields[$itinerary->id];
        $this->schedulefield = $fields[$schedule->id];

        $this->offerruleid = $this->make_rule('Offers', self::OFFER, match_compiler::DIRECTION_REVERSE);
        $this->requestruleid = $this->make_rule('Requests', self::REQUEST, match_compiler::DIRECTION_FORWARD);
    }

    /**
     * Insert one side of the ride matching setup.
     *
     * @param string $name
     * @param string $side the stored ride-type value this rule fires for
     * @param string $direction which route has to be able to carry which
     * @param array $overrides values to merge into the matching configuration
     * @return int rule id
     */
    protected function make_rule(string $name, string $side, string $direction, array $overrides = []): int {
        global $DB;

        $param9 = [
            $this->typefield->id => ['AND' => [['', 'ANY_OF', [$side]]]],
            rule::MATCH_KEY => $overrides + [
                'criteria' => [
                    ['fieldid' => $this->typefield->id, 'op' => match_compiler::OP_DIFFERENT],
                    ['fieldid' => $this->itineraryfield->id(), 'op' => match_compiler::OP_ROUTE,
                        'radius' => 5, 'direction' => $direction],
                    ['fieldid' => $this->schedulefield->id(), 'op' => match_compiler::OP_WITHIN,
                        'tolerance' => \datalynxfield_schedule\field::TOLERANCE_WHOLE_DAY, 'unit' => MINSECS],
                ],
                'requireapproved' => 1,
                'dedupe' => self::SCOPE,
                'forgetstale' => 1,
                'maxmatches' => rule::DEFAULT_MAX_MATCHES,
            ],
        ];

        return (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'eventnotification',
            'name' => $name,
            'description' => '',
            'enabled' => 1,
            'param1' => json_encode(['entry_created', 'entry_updated', 'entry_deleted']),
            'param2' => rule::FROM_AUTHOR,
            'param3' => json_encode(['matchauthors' => 1, 'subjectauthorpermatch' => 1]),
            'param9' => json_encode($param9),
        ]);
    }

    /**
     * Create a ride entry.
     *
     * @param \stdClass $user Author.
     * @param string $ridetype self::OFFER or self::REQUEST.
     * @param string[] $places Journey stops in travel order.
     * @param string|null $date ISO date for a one-off ride, null to use the default.
     * @param int[] $weekdays ISO weekdays for a recurring ride; wins over $date when given.
     * @return int Entry id.
     */
    protected function create_ride(
        \stdClass $user,
        string $ridetype,
        array $places,
        ?string $date = null,
        array $weekdays = []
    ): int {
        global $DB;

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => $user->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        // A select field stores the option's position, not its label.
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $this->typefield->id,
            'entryid' => $entryid,
            'content' => $ridetype,
        ]);

        $this->set_journey($entryid, $places);
        $this->set_schedule($entryid, $date, $weekdays);

        return $entryid;
    }

    /**
     * Replace an entry's journey, keeping the index in step.
     *
     * @param int $entryid
     * @param string[] $places
     */
    protected function set_journey(int $entryid, array $places): void {
        global $DB;

        $waypoints = [];
        foreach ($places as $index => $place) {
            [$lat, $lng] = self::PLACES[$place];
            $waypoints[] = [
                'address' => $place,
                'lat' => $lat,
                'lng' => $lng,
            ];
        }

        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        // Mirror what the entry form supplies, so the derived columns and the
        // waypoint index are produced exactly as they are in production.
        $existing = $DB->get_record('datalynx_contents', [
            'fieldid' => $this->itineraryfield->id(),
            'entryid' => $entryid,
        ]);
        if ($existing) {
            $fieldid = $this->itineraryfield->id();
            $entry->{"c{$fieldid}_id"} = $existing->id;
            $entry->{"c{$fieldid}_content"} = $existing->content;
        }
        $this->itineraryfield->update_content($entry, ['waypoints' => json_encode($waypoints)]);
    }

    /**
     * Set an entry's schedule.
     *
     * Every ride needs one: the rules match on it, and a ride that says nothing about when it
     * happens should match nothing rather than everything.
     *
     * @param int $entryid
     * @param string|null $date ISO date of a one-off ride
     * @param int[] $weekdays ISO weekdays of a recurring ride; wins over $date when given
     * @param int $minute departure, minutes after midnight
     */
    protected function set_schedule(int $entryid, ?string $date, array $weekdays = [], int $minute = 480): void {
        global $DB;

        $value = $weekdays
            ? ['mode' => 'weekly', 'days' => $weekdays, 'start' => $minute]
            : ['mode' => 'once', 'date' => $date ?? self::DEFAULT_DATE, 'start' => $minute];

        $fieldid = (int) $this->schedulefield->id();
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $existing = $DB->get_record('datalynx_contents', ['entryid' => $entryid, 'fieldid' => $fieldid]);
        if ($existing) {
            $entry->{"c{$fieldid}_id"} = $existing->id;
            $entry->{"c{$fieldid}_content"} = $existing->content;
        }
        $this->schedulefield->update_content($entry, ['schedule' => json_encode($value)]);
    }

    /**
     * Run both rules for one saved entry and return the messages they produced.
     *
     * Standing in for the event dispatch and the ad-hoc task, which are exercised end to end in
     * {@see self::test_saving_an_entry_notifies_through_the_event()}.
     *
     * @param int $entryid
     * @return \stdClass[] the messages sent
     */
    protected function sweep(int $entryid): array {
        global $DB;

        $sink = $this->redirectMessages();
        foreach ([$this->offerruleid, $this->requestruleid] as $ruleid) {
            $rule = new rule($this->dlx, $DB->get_record('datalynx_rules', ['id' => $ruleid]));
            $conditions = (string) $DB->get_field('datalynx_rules', 'param9', ['id' => $ruleid]);
            $decoded = (array) json_decode($conditions, true);
            unset($decoded[rule::MATCH_KEY]);
            // The trigger conditions decide which of the two rules this entry belongs to; the
            // event dispatch applies them before queueing the sweep.
            if (!$this->entry_matches($entryid, $decoded)) {
                continue;
            }
            $rule->run_matching([
                'eventname' => 'entry_created',
                'entryid' => $entryid,
                'objectid' => $entryid,
                'teamfieldid' => 0,
            ]);
        }
        $this->run_pending_message_tasks();
        $messages = $sink->get_messages();
        $sink->close();

        return $messages;
    }

    /**
     * Whether an entry satisfies a customsearch, using the same engine the rules do.
     *
     * @param int $entryid
     * @param array $conditions
     * @return bool
     */
    private function entry_matches(int $entryid, array $conditions): bool {
        global $DB;

        if (!$conditions) {
            return true;
        }
        $fieldid = (int) array_key_first($conditions);
        $wanted = $conditions[$fieldid]['AND'][0][2][0];

        return (string) $DB->get_field(
            'datalynx_contents',
            'content',
            ['entryid' => $entryid, 'fieldid' => $fieldid],
            IGNORE_MISSING
        ) === (string) $wanted;
    }

    /**
     * Run the queued message tasks, which is what actually sends the notifications.
     */
    private function run_pending_message_tasks(): void {
        // The sender traces every message it sends, which would make each test risky.
        ob_start();
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }
        ob_end_clean();
    }

    /**
     * The user ids a batch of messages went to.
     *
     * @param array $messages
     * @return int[] sorted, with duplicates kept
     */
    private function recipients(array $messages): array {
        $recipients = array_map(static fn($message) => (int) $message->useridto, $messages);
        sort($recipients);

        return $recipients;
    }

    /**
     * A request whose route the offer covers notifies both authors, once each.
     */
    public function test_matching_pair_notifies_both_authors(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz']);

        $messages = $this->sweep($request);

        $this->assertCount(2, $messages);
        $expected = [(int) $this->driver->id, (int) $this->passenger->id];
        sort($expected);
        $this->assertEquals($expected, $this->recipients($messages));
    }

    /**
     * Saving an entry really does reach the notifications, through the event and the ad-hoc task.
     */
    public function test_saving_an_entry_notifies_through_the_event(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz']);

        $sink = $this->redirectMessages();
        \mod_datalynx\event\entry_created::create([
            'context' => $this->dlx->context,
            'objectid' => $request,
            'other' => ['dataid' => $this->dlx->id()],
        ])->trigger();
        $this->run_pending_message_tasks();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(2, $messages);
    }

    /**
     * The same pair is never announced twice, however often an entry is saved.
     */
    public function test_pair_is_announced_only_once(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $this->assertCount(2, $this->sweep($request));
        $this->assertCount(0, $this->sweep($request));
        $this->assertCount(0, $this->sweep($request));
    }

    /**
     * The two rules share one record of announced pairs, so saving the other side of a pair that
     * has already been introduced says nothing further.
     */
    public function test_the_two_sides_share_the_record_of_announced_pairs(): void {
        $offer = $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $this->assertCount(2, $this->sweep($request));
        $this->assertCount(0, $this->sweep($offer));
    }

    /**
     * Matching is directional: an offer going the other way is not a match.
     */
    public function test_opposite_direction_is_not_a_match(): void {
        $this->create_ride($this->driver, self::OFFER, ['graz', 'wien']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $this->assertCount(0, $this->sweep($request));
    }

    /**
     * Saving the offer side finds waiting requests just as well.
     */
    public function test_sweep_works_from_the_offer_side(): void {
        $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz']);
        $offer = $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz']);

        $this->assertCount(2, $this->sweep($offer));
    }

    /**
     * Entries of the same kind never match each other.
     */
    public function test_same_kind_never_matches(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $other = $this->create_ride($this->passenger, self::OFFER, ['wien', 'graz']);

        $this->assertCount(0, $this->sweep($other));
    }

    /**
     * A traveller is not matched with their own counterpart entry.
     */
    public function test_own_entries_are_ignored(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->driver, self::REQUEST, ['wien', 'graz']);

        $this->assertCount(0, $this->sweep($request));
    }

    /**
     * A journey on another day is not the same journey.
     */
    public function test_rides_on_different_days_do_not_match(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz'], '2026-09-07');
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz'], '2026-09-11');

        $this->assertCount(0, $this->sweep($request));

        // The same pair on the same day is the same journey, whatever the hour.
        $this->set_schedule($request, '2026-09-07', [], 17 * 60);
        $this->assertCount(2, $this->sweep($request));
    }

    /**
     * The case the four separate fields could never reach: a request for one particular Monday
     * meets the commute that runs every Monday.
     */
    public function test_a_one_off_request_matches_a_weekly_offer(): void {
        // 7 September 2026 is a Monday.
        $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz'], null, [1]);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz'], '2026-09-07');

        $this->assertCount(2, $this->sweep($request));
    }

    /**
     * And a request on a day the commute does not run finds nothing.
     */
    public function test_a_one_off_request_misses_a_weekly_offer_on_another_weekday(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz'], null, [1]);
        // 9 September 2026 is a Wednesday.
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz'], '2026-09-09');

        $this->assertCount(0, $this->sweep($request));
    }

    /**
     * A pair that stops matching is forgotten, so a later re-match is news again.
     */
    public function test_pair_is_forgotten_when_it_stops_matching(): void {
        global $DB;

        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $this->assertCount(2, $this->sweep($request));
        $this->assertEquals(1, $DB->count_records('datalynx_rule_matches', ['scope' => self::SCOPE]));

        // The passenger changes their mind and now wants to go somewhere else.
        $this->set_journey($request, ['linz', 'wien']);
        $this->assertCount(0, $this->sweep($request));
        $this->assertEquals(0, $DB->count_records('datalynx_rule_matches', ['scope' => self::SCOPE]));

        // Changing back is genuine news and is announced again.
        $this->set_journey($request, ['wien', 'graz']);
        $this->assertCount(2, $this->sweep($request));
    }

    /**
     * A disabled rule does nothing, and neither does one with no criteria.
     */
    public function test_rule_must_be_enabled_and_configured(): void {
        global $DB;

        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $DB->set_field('datalynx_rules', 'enabled', 0, ['id' => $this->offerruleid]);
        $DB->set_field('datalynx_rules', 'param9', null, ['id' => $this->requestruleid]);

        // A rule with no criteria is an ordinary notification rule again, and this one has no
        // recipients configured beyond the matching ones, so nothing is sent.
        $this->assertCount(0, $this->sweep($request));
    }

    /**
     * Deleting an entry forgets its pairs, so ids cannot be reused misleadingly.
     */
    public function test_deleting_an_entry_forgets_its_pairs(): void {
        global $DB;

        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $this->sweep($request);
        $this->assertEquals(1, $DB->count_records('datalynx_rule_matches'));

        $rule = new rule($this->dlx, $DB->get_record('datalynx_rules', ['id' => $this->requestruleid]));
        $rule->trigger(\mod_datalynx\event\entry_deleted::create([
            'objectid' => $request,
            'context' => $this->dlx->context,
            'other' => ['dataid' => $this->dlx->id(), 'view' => 0],
        ]));

        $this->assertEquals(0, $DB->count_records('datalynx_rule_matches'));
    }

    /**
     * One rule matching in either direction covers both sides on its own.
     */
    public function test_a_single_rule_can_cover_both_directions(): void {
        global $DB;

        $DB->delete_records('datalynx_rules', ['id' => $this->offerruleid]);
        $DB->delete_records('datalynx_rules', ['id' => $this->requestruleid]);
        // No trigger condition: this rule fires for offers and requests alike.
        $this->offerruleid = $this->requestruleid = (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'eventnotification',
            'name' => 'Both sides',
            'description' => '',
            'enabled' => 1,
            'param1' => json_encode(['entry_created', 'entry_updated']),
            'param2' => rule::FROM_AUTHOR,
            'param3' => json_encode(['matchauthors' => 1, 'subjectauthorpermatch' => 1]),
            'param9' => json_encode([
                rule::MATCH_KEY => [
                    'criteria' => [
                        ['fieldid' => $this->typefield->id, 'op' => match_compiler::OP_DIFFERENT],
                        ['fieldid' => $this->itineraryfield->id(), 'op' => match_compiler::OP_ROUTE,
                            'radius' => 5, 'direction' => match_compiler::DIRECTION_EITHER],
                    ],
                    'requireapproved' => 1,
                    'dedupe' => self::SCOPE,
                    'forgetstale' => 1,
                    'maxmatches' => rule::DEFAULT_MAX_MATCHES,
                ],
            ]),
        ]);

        // Saved from the request side: the offer's route carries it.
        $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz']);
        $messages = $this->sweep($request);

        // The single rule is run twice by the helper, so the pair is found once and the record of
        // announced pairs keeps the second run quiet.
        $this->assertCount(2, $messages);
    }
}
