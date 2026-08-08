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

namespace datalynxfield_itinerary;

use advanced_testcase;
use mod_datalynx\datalynx;
use stdClass;

/**
 * Tests for the routed summary stored beside a journey.
 *
 * The summary is a cache of what a routing service said about one particular set
 * of stops, so the interesting behaviour is what happens when those stops change.
 *
 * @package    datalynxfield_itinerary
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_itinerary\field::resolve_route
 * @covers     \datalynxfield_itinerary\field::get_route
 */
final class route_storage_test extends advanced_testcase {
    /** @var array Two real places, so the journey is a plausible one. */
    const PLACES = [
        'wien' => [48.1852, 16.3775],
        'graz' => [47.0736, 15.4161],
        'linz' => [48.2907, 14.2921],
    ];

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var field */
    protected $field;

    /**
     * An activity with one itinerary field.
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
        ];
        $record->id = $DB->insert_record('datalynx_fields', $record);
        $this->field = $this->dlx->get_fields(null, false, true)[$record->id];
    }

    /**
     * Create an empty entry.
     *
     * @return int
     */
    protected function create_entry(): int {
        global $DB;

        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => 2,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Save a journey the way the entry form does.
     *
     * @param int $entryid
     * @param string[] $places
     * @param ?string $route JSON route summary the picker would have sent.
     */
    protected function save(int $entryid, array $places, ?string $route): void {
        global $DB;

        $waypoints = [];
        foreach ($places as $place) {
            [$lat, $lng] = self::PLACES[$place];
            $waypoints[] = ['address' => $place, 'lat' => $lat, 'lng' => $lng, 'time' => null];
        }

        $entry = $this->load_entry($entryid);
        $values = ['waypoints' => json_encode($waypoints)];
        if ($route !== null) {
            $values['route'] = $route;
        }

        $this->field->update_content($entry, $values);
    }

    /**
     * Read an entry back with this field's content columns attached.
     *
     * @param int $entryid
     * @return stdClass
     */
    protected function load_entry(int $entryid): stdClass {
        global $DB;

        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $fieldid = $this->field->id();
        $content = $DB->get_record('datalynx_contents', ['fieldid' => $fieldid, 'entryid' => $entryid]);
        if ($content) {
            $entry->{"c{$fieldid}_id"} = $content->id;
            foreach (['', '1', '2', '3', '4'] as $suffix) {
                $entry->{"c{$fieldid}_content{$suffix}"} = $content->{"content{$suffix}"};
            }
        }

        return $entry;
    }

    /**
     * A journey saved with a routed summary keeps it.
     */
    public function test_route_is_stored_with_the_journey(): void {
        $entryid = $this->create_entry();
        $this->save($entryid, ['wien', 'graz'], json_encode([
            'distance' => 195300,
            'duration' => 7830,
            'polyline' => 'abc',
        ]));

        $route = $this->field->get_route($this->load_entry($entryid));

        $this->assertNotNull($route);
        $this->assertEqualsWithDelta(195.3, $route->distance_km(), 0.01);
        $this->assertSame(131, $route->duration_minutes());
        $this->assertSame('abc', $route->polyline);
    }

    /**
     * Re-saving the same stops without a new summary keeps the one already stored:
     * a CSV import or a rule must not throw away a perfectly good route.
     */
    public function test_route_survives_a_save_that_does_not_change_the_stops(): void {
        $entryid = $this->create_entry();
        $this->save($entryid, ['wien', 'graz'], json_encode(['distance' => 195300, 'duration' => 7830]));
        $this->save($entryid, ['wien', 'graz'], null);

        $this->assertNotNull($this->field->get_route($this->load_entry($entryid)));
    }

    /**
     * Changing the stops drops the summary, because a route for the old journey
     * would be worse than showing no travel time at all.
     */
    public function test_route_is_dropped_when_the_stops_change(): void {
        $entryid = $this->create_entry();
        $this->save($entryid, ['wien', 'graz'], json_encode(['distance' => 195300, 'duration' => 7830]));
        $this->save($entryid, ['wien', 'linz'], null);

        $this->assertNull($this->field->get_route($this->load_entry($entryid)));
    }

    /**
     * A journey saved without routing behaves exactly as it did before routing existed.
     */
    public function test_unrouted_journey_stores_nothing_extra(): void {
        $entryid = $this->create_entry();
        $this->save($entryid, ['wien', 'graz'], null);

        $entry = $this->load_entry($entryid);
        $fieldid = $this->field->id();

        $this->assertNull($this->field->get_route($entry));
        $this->assertNotEmpty($entry->{"c{$fieldid}_content"});
        $this->assertNotEmpty($entry->{"c{$fieldid}_content1"}, 'The bounding box must still be derived.');
    }
}
