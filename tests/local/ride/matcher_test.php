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

namespace mod_datalynx\local\ride;

use advanced_testcase;
use mod_datalynx\datalynx;
use stdClass;

/**
 * Tests for corridor matching between journeys.
 *
 * Entries are created through the field's own save path, so these also cover the
 * derived waypoint index the matcher reads.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\ride\matcher
 */
final class matcher_test extends advanced_testcase {
    /** @var array Real places, so distances in the tests are meaningful. */
    const PLACES = [
        'wien' => [48.1852, 16.3775],
        'wienerneustadt' => [47.8149, 16.2372],
        'graz' => [47.0736, 15.4161],
        'linz' => [48.2907, 14.2921],
    ];

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var \datalynxfield_itinerary\field */
    protected $field;

    /**
     * Set up a datalynx instance with one itinerary field.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $record = (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Journey',
            'type' => 'itinerary',
            'description' => '',
            'param1' => 10,
            'param5' => 5,
        ];
        $record->id = $DB->insert_record('datalynx_fields', $record);

        $fields = $this->dlx->get_fields(null, false, true);
        $this->field = $fields[$record->id];
    }

    /**
     * Create an entry whose itinerary runs through the named places.
     *
     * @param string[] $places Keys of self::PLACES, in travel order.
     * @return int Entry id.
     */
    protected function create_journey(array $places): int {
        global $DB;

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => 2,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $waypoints = [];
        foreach ($places as $place) {
            [$lat, $lng] = self::PLACES[$place];
            $waypoints[] = ['address' => $place, 'lat' => $lat, 'lng' => $lng, 'time' => null];
        }

        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $this->field->update_content($entry, ['waypoints' => json_encode($waypoints)]);

        return $entryid;
    }

    /**
     * Build a waypoint at a named place, optionally nudged away from it.
     *
     * @param string $place
     * @param float $offsetlat Degrees to shift north.
     * @return waypoint
     */
    protected function place(string $place, float $offsetlat = 0.0): waypoint {
        [$lat, $lng] = self::PLACES[$place];

        return new waypoint($place, $lat + $offsetlat, $lng);
    }

    /**
     * Find matches for a journey between two named places.
     *
     * @param string $from
     * @param string $to
     * @param float $radius
     * @return int[]
     */
    protected function findmatches(string $from, string $to, float $radius = 5.0): array {
        return matcher::find_matching_entries(
            (int) $this->field->id(),
            $this->place($from),
            $this->place($to),
            $radius
        );
    }

    /**
     * Saving an itinerary populates the derived index in travel order.
     */
    public function test_save_populates_the_waypoint_index(): void {
        global $DB;

        $entryid = $this->create_journey(['wien', 'wienerneustadt', 'graz']);

        $rows = $DB->get_records(
            'datalynx_waypoints',
            ['entryid' => $entryid, 'fieldid' => $this->field->id()],
            'seq ASC'
        );

        $this->assertCount(3, $rows);
        $this->assertEquals([0, 1, 2], array_values(array_map(
            static fn($row) => (int) $row->seq,
            $rows
        )));
        $first = reset($rows);
        $this->assertEqualsWithDelta(48.1852, (float) $first->lat, 0.0001);
    }

    /**
     * A journey carries a traveller heading the same way.
     */
    public function test_matches_a_traveller_going_the_same_way(): void {
        $entryid = $this->create_journey(['wien', 'graz']);

        $this->assertEquals([$entryid], $this->findmatches('wien', 'graz'));
    }

    /**
     * The same stops travelled backwards are not a match.
     *
     * This is the property that makes matching directional, and the reason the
     * query joins on an increasing seq rather than merely on proximity.
     */
    public function test_does_not_match_the_opposite_direction(): void {
        $this->create_journey(['wien', 'graz']);

        $this->assertSame([], $this->findmatches('graz', 'wien'));
    }

    /**
     * Someone living along the route is picked up, which endpoint matching misses.
     */
    public function test_matches_a_traveller_boarding_part_way(): void {
        $entryid = $this->create_journey(['wien', 'wienerneustadt', 'graz']);

        $this->assertEquals([$entryid], $this->findmatches('wienerneustadt', 'graz'));
    }

    /**
     * A stop beyond the radius is not a match.
     */
    public function test_respects_the_radius(): void {
        $this->create_journey(['wien', 'graz']);

        // Roughly 33 km north of the route's start.
        $far = $this->place('wien', 0.3);

        $this->assertSame([], matcher::find_matching_entries(
            (int) $this->field->id(),
            $far,
            $this->place('graz'),
            5.0
        ));
        $this->assertNotEmpty(matcher::find_matching_entries(
            (int) $this->field->id(),
            $far,
            $this->place('graz'),
            50.0
        ));
    }

    /**
     * A journey that goes somewhere else entirely is not a match.
     */
    public function test_ignores_unrelated_journeys(): void {
        $this->create_journey(['wien', 'linz']);

        $this->assertSame([], $this->findmatches('wien', 'graz'));
    }

    /**
     * Only the requested entries are considered when a restriction is given.
     */
    public function test_restricting_to_specific_entries(): void {
        $wanted = $this->create_journey(['wien', 'graz']);
        $other = $this->create_journey(['wien', 'wienerneustadt', 'graz']);

        $both = $this->findmatches('wien', 'graz');
        sort($both);
        $this->assertEquals([$wanted, $other], $both);

        $this->assertEquals([$other], matcher::find_matching_entries(
            (int) $this->field->id(),
            $this->place('wien'),
            $this->place('graz'),
            5.0,
            [$other]
        ));

        $this->assertSame([], matcher::find_matching_entries(
            (int) $this->field->id(),
            $this->place('wien'),
            $this->place('graz'),
            5.0,
            []
        ));
    }

    /**
     * entry_covers_request() answers the offer-side question.
     */
    public function test_entry_covers_request(): void {
        $offer = $this->create_journey(['wien', 'wienerneustadt', 'graz']);
        $fieldid = (int) $this->field->id();

        $wanted = new itinerary([$this->place('wienerneustadt'), $this->place('graz')]);
        $this->assertTrue(matcher::entry_covers_request($fieldid, $offer, $wanted, 5.0));

        $backwards = new itinerary([$this->place('graz'), $this->place('wienerneustadt')]);
        $this->assertFalse(matcher::entry_covers_request($fieldid, $offer, $backwards, 5.0));

        $elsewhere = new itinerary([$this->place('linz'), $this->place('graz')]);
        $this->assertFalse(matcher::entry_covers_request($fieldid, $offer, $elsewhere, 5.0));

        // A single stop is not a journey and cannot be covered.
        $notaroute = new itinerary([$this->place('wien')]);
        $this->assertFalse(matcher::entry_covers_request($fieldid, $offer, $notaroute, 5.0));
    }

    /**
     * Deleting the value clears its index rows.
     */
    public function test_delete_clears_the_index(): void {
        global $DB;

        $entryid = $this->create_journey(['wien', 'graz']);
        $this->assertTrue($DB->record_exists('datalynx_waypoints', ['entryid' => $entryid]));

        $this->field->delete_content($entryid);

        $this->assertFalse($DB->record_exists('datalynx_waypoints', ['entryid' => $entryid]));
    }

    /**
     * The index can be rebuilt from the stored values alone.
     */
    public function test_rebuild_index(): void {
        global $DB;

        $this->create_journey(['wien', 'graz']);
        $this->create_journey(['wien', 'wienerneustadt', 'graz']);

        $DB->delete_records('datalynx_waypoints', ['fieldid' => $this->field->id()]);
        $this->assertEquals(0, $DB->count_records('datalynx_waypoints'));

        $this->assertEquals(2, $this->field->rebuild_index());
        $this->assertEquals(5, $DB->count_records('datalynx_waypoints'));
    }
}
