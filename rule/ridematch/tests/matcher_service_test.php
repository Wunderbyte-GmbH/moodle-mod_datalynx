<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace datalynxrule_ridematch;

use advanced_testcase;
use mod_datalynx\datalynx;

/**
 * Tests for cross-matching ride offers against ride requests.
 *
 * @package    datalynxrule_ridematch
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxrule_ridematch\matcher_service
 */
final class matcher_service_test extends advanced_testcase {
    /** @var array Real places, so distances in the tests are meaningful. */
    const PLACES = [
        'wien' => [48.1852, 16.3775],
        'wienerneustadt' => [47.8149, 16.2372],
        'graz' => [47.0736, 15.4161],
        'linz' => [48.2907, 14.2921],
    ];

    /** @var string Label of the "offering" option, as an admin would type it. */
    const OFFER = 'Offer a ride';

    /** @var string Label of the "looking for" option. */
    const REQUEST = 'Looking for a ride';

    /** @var array Option label => the position a select field actually stores. */
    const STORED = [self::OFFER => '1', self::REQUEST => '2'];

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var \datalynxfield_itinerary\field */
    protected $itineraryfield;

    /** @var \stdClass */
    protected $typefield;

    /** @var int */
    protected int $ruleid;

    /** @var \stdClass */
    protected $driver;

    /** @var \stdClass */
    protected $passenger;

    /**
     * Build an activity with an itinerary field, a ride-type field and the rule.
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
            'param1' => self::OFFER . "\n" . self::REQUEST,
        ];
        $this->typefield->id = $DB->insert_record('datalynx_fields', $this->typefield);

        $this->itineraryfield = $this->dlx->get_fields(null, false, true)[$itinerary->id];

        $this->ruleid = (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'ridematch',
            'name' => 'Match rides',
            'description' => '',
            'enabled' => 1,
            'param1' => json_encode(['entry_created', 'entry_updated']),
            'param2' => $itinerary->id,
            'param3' => $this->typefield->id,
            'param4' => self::OFFER,
            'param5' => self::REQUEST,
            'param6' => 5,
            'param7' => 24,
        ]);
    }

    /**
     * Create a ride entry.
     *
     * @param \stdClass $user Author.
     * @param string $ridetype self::OFFER or self::REQUEST.
     * @param string[] $places Journey stops in travel order.
     * @param int|null $departure Timestamp on the first stop, null for none.
     * @return int Entry id.
     */
    protected function create_ride(\stdClass $user, string $ridetype, array $places, ?int $departure = null): int {
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
            'content' => self::STORED[$ridetype],
        ]);

        $this->set_journey($entryid, $places, $departure);

        return $entryid;
    }

    /**
     * Replace an entry's journey, keeping the index in step.
     *
     * @param int $entryid
     * @param string[] $places
     * @param int|null $departure
     */
    protected function set_journey(int $entryid, array $places, ?int $departure = null): void {
        global $DB;

        $waypoints = [];
        foreach ($places as $index => $place) {
            [$lat, $lng] = self::PLACES[$place];
            $waypoints[] = [
                'address' => $place,
                'lat' => $lat,
                'lng' => $lng,
                'time' => ($index === 0 && $departure !== null) ? $departure : null,
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
     * Run the sweep for one entry and return how many pairs it announced.
     *
     * @param int $entryid
     * @return int
     */
    protected function sweep(int $entryid): int {
        return matcher_service::create($this->dlx->id(), $this->ruleid)->process_entry($entryid);
    }

    /**
     * A request whose route the offer covers notifies both authors, once each.
     */
    public function test_matching_pair_notifies_both_authors(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz']);

        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($request));
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(2, $messages);
        $recipients = array_map(static fn($message) => (int) $message->useridto, $messages);
        sort($recipients);
        $expected = [(int) $this->driver->id, (int) $this->passenger->id];
        sort($expected);
        $this->assertEquals($expected, $recipients);
    }

    /**
     * The same pair is never announced twice, however often an entry is saved.
     *
     * Without this, every edit would re-notify everyone the entry already matched.
     */
    public function test_pair_is_announced_only_once(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($request));
        $this->assertEquals(0, $this->sweep($request));
        $this->assertEquals(0, $this->sweep($request));
        $this->assertCount(2, $sink->get_messages());
        $sink->close();
    }

    /**
     * Matching is directional: an offer going the other way is not a match.
     */
    public function test_opposite_direction_is_not_a_match(): void {
        $this->create_ride($this->driver, self::OFFER, ['graz', 'wien']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $sink = $this->redirectMessages();
        $this->assertEquals(0, $this->sweep($request));
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * Saving the offer side finds waiting requests just as well.
     */
    public function test_sweep_works_from_the_offer_side(): void {
        $this->create_ride($this->passenger, self::REQUEST, ['wienerneustadt', 'graz']);
        $offer = $this->create_ride($this->driver, self::OFFER, ['wien', 'wienerneustadt', 'graz']);

        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($offer));
        $this->assertCount(2, $sink->get_messages());
        $sink->close();
    }

    /**
     * Entries of the same kind never match each other.
     */
    public function test_same_kind_never_matches(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $other = $this->create_ride($this->passenger, self::OFFER, ['wien', 'graz']);

        $sink = $this->redirectMessages();
        $this->assertEquals(0, $this->sweep($other));
        $sink->close();
    }

    /**
     * A traveller is not matched with their own counterpart entry.
     */
    public function test_own_entries_are_ignored(): void {
        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->driver, self::REQUEST, ['wien', 'graz']);

        $sink = $this->redirectMessages();
        $this->assertEquals(0, $this->sweep($request));
        $sink->close();
    }

    /**
     * A journey on another day is not the same journey.
     */
    public function test_departures_outside_the_tolerance_do_not_match(): void {
        $monday = make_timestamp(2026, 9, 7, 8, 0);
        $friday = make_timestamp(2026, 9, 11, 8, 0);

        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz'], $monday);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz'], $friday);

        $sink = $this->redirectMessages();
        $this->assertEquals(0, $this->sweep($request));
        $sink->close();

        // The same pair an hour apart is the same journey.
        $this->set_journey($request, ['wien', 'graz'], $monday + HOURSECS);
        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($request));
        $sink->close();
    }

    /**
     * A pair that stops matching is forgotten, so a later re-match is news again.
     */
    public function test_pair_is_forgotten_when_it_stops_matching(): void {
        global $DB;

        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($request));
        $sink->close();
        $this->assertEquals(1, $DB->count_records('datalynx_ride_matches', ['ruleid' => $this->ruleid]));

        // The passenger changes their mind and now wants to go somewhere else.
        $this->set_journey($request, ['linz', 'wien']);
        $sink = $this->redirectMessages();
        $this->assertEquals(0, $this->sweep($request));
        $sink->close();
        $this->assertEquals(0, $DB->count_records('datalynx_ride_matches', ['ruleid' => $this->ruleid]));

        // Changing back is genuine news and is announced again.
        $this->set_journey($request, ['wien', 'graz']);
        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($request));
        $this->assertCount(2, $sink->get_messages());
        $sink->close();
    }

    /**
     * The rule accepts the option label as well as the stored position.
     *
     * Select and radio button fields store an option's position, so a rule
     * configured with the label an administrator sees would otherwise match
     * nothing at all while looking perfectly correct.
     */
    public function test_ride_type_accepts_label_or_stored_position(): void {
        global $DB;

        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        // The setUp() above configured the rule with the option labels.
        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($request), 'labels should resolve to stored positions');
        $sink->close();

        // Reconfiguring with the raw stored positions must behave identically.
        $DB->delete_records('datalynx_ride_matches', ['ruleid' => $this->ruleid]);
        $DB->set_field('datalynx_rules', 'param4', self::STORED[self::OFFER], ['id' => $this->ruleid]);
        $DB->set_field('datalynx_rules', 'param5', self::STORED[self::REQUEST], ['id' => $this->ruleid]);

        $sink = $this->redirectMessages();
        $this->assertEquals(1, $this->sweep($request), 'stored positions should work too');
        $sink->close();
    }

    /**
     * An unconfigured or disabled rule does nothing at all.
     */
    public function test_rule_must_be_enabled_and_configured(): void {
        global $DB;

        $DB->set_field('datalynx_rules', 'enabled', 0, ['id' => $this->ruleid]);
        $this->assertNull(matcher_service::create($this->dlx->id(), $this->ruleid));

        $DB->set_field('datalynx_rules', 'enabled', 1, ['id' => $this->ruleid]);
        $DB->set_field('datalynx_rules', 'param4', '', ['id' => $this->ruleid]);
        $this->assertNull(matcher_service::create($this->dlx->id(), $this->ruleid));
    }

    /**
     * Deleting an entry forgets its pairs, so ids cannot be reused misleadingly.
     */
    public function test_deleting_an_entry_forgets_its_pairs(): void {
        global $DB;

        $this->create_ride($this->driver, self::OFFER, ['wien', 'graz']);
        $request = $this->create_ride($this->passenger, self::REQUEST, ['wien', 'graz']);

        $sink = $this->redirectMessages();
        $this->sweep($request);
        $sink->close();
        $this->assertEquals(1, $DB->count_records('datalynx_ride_matches'));

        $rule = new rule($this->dlx, $DB->get_record('datalynx_rules', ['id' => $this->ruleid]));
        $event = \mod_datalynx\event\entry_deleted::create([
            'objectid' => $request,
            'context' => $this->dlx->context,
            'other' => ['dataid' => $this->dlx->id(), 'view' => 0],
        ]);
        $rule->trigger($event);

        $this->assertEquals(0, $DB->count_records('datalynx_ride_matches'));
    }
}
