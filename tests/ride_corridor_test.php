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

namespace mod_datalynx;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/stub_router.php');

use advanced_testcase;
use mod_datalynx\local\map\router_factory;
use mod_datalynx\local\ride\itinerary;
use mod_datalynx\local\ride\matcher;
use mod_datalynx\local\ride\waypoint;

/**
 * Search and matching find travellers along the whole route, within the detour its driver accepts.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\ride\matcher
 * @covers     \mod_datalynx\local\ride\itinerary::corridor_points
 * @covers     \datalynxfield_itinerary\field::reindex_entry
 */
final class ride_corridor_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var int */
    protected int $routeid;

    /** @var int */
    protected int $detourid;

    /** @var \stdClass */
    protected \stdClass $driver;

    /** @var array Places beside the straight line Wien - Graz (the stub router has no track). */
    const BADEN = ['lat' => 48.0075, 'lng' => 16.2342];

    /** @var array About 14 km off that line. */
    const NEUSTADT = ['lat' => 47.8149, 'lng' => 16.2372];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        router_factory::set_router_for_testing(new stub_router());
        \datalynxfield_itinerary\field::reset_server_routes();
        [$course, $this->dlx] = $this->create_mobile_instance();
        $this->detourid = $this->add_mobile_field($this->dlx, 'number', 'Umweg', ['param5' => 1, 'param6' => 20]);
        $this->routeid = $this->add_mobile_field($this->dlx, 'itinerary', 'Route', ['param1' => 8, 'param5' => 10,
            'param9' => json_encode(['corridorfield' => $this->detourid])]);
        $this->driver = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->dlx = new datalynx($this->dlx->id());
    }

    protected function tearDown(): void {
        router_factory::set_router_for_testing(null);
        parent::tearDown();
    }

    /**
     * A ride Wien - Graz with a detour.
     *
     * @param string|null $detour km, null for none
     * @return int
     */
    protected function ride(?string $detour): int {
        $entryid = $this->add_mobile_entry($this->dlx, $this->driver->id, $detour === null ? [] : [$this->detourid => $detour]);
        $this->dlx->get_field_from_id($this->routeid, true)->update_content((object) ['id' => $entryid], [
            'waypoints' => json_encode([
                ['address' => 'Karlsplatz, Wien', 'lat' => 48.2004, 'lng' => 16.3696],
                ['address' => 'Hauptplatz, Graz', 'lat' => 47.0710, 'lng' => 15.4385],
            ]),
        ]);
        return $entryid;
    }

    /**
     * Rides that carry someone from one place to another.
     *
     * @param array $from
     * @param array $to
     * @return int[]
     */
    protected function found(array $from, array $to): array {
        $found = matcher::find_matching_entries(
            $this->routeid,
            waypoint::from_array($from),
            waypoint::from_array($to),
            $this->dlx->get_field_from_id($this->routeid, true)->get_match_radius()
        );
        sort($found);
        return $found;
    }

    /**
     * Getting on and off beside the route, not at a stop, within each ride's own detour; in the
     * direction of travel only.
     */
    public function test_corridor_is_the_drivers_detour(): void {
        $wide = $this->ride('20');
        $narrow = $this->ride('5');
        $default = $this->ride(null);

        // Baden lies 2 km off the route, Wiener Neustadt 14 km: neither is a stop.
        $this->assertSame([$wide], $this->found(self::BADEN, self::NEUSTADT));
        // Up to the search radius (10 km) where a ride has no detour.
        $graz = ['lat' => 47.0710, 'lng' => 15.4385];
        $this->assertSame([$wide, $narrow, $default], $this->found(self::BADEN, $graz));
        // The other way round is not this ride.
        $this->assertSame([], $this->found($graz, self::BADEN));
    }

    /**
     * The search of a view asks the same.
     */
    public function test_search(): void {
        global $DB;
        $wide = $this->ride('20');
        $this->ride('5');
        $field = $this->dlx->get_field_from_id($this->routeid, true);
        [$sql, $params] = $field->get_search_sql(['', '', ['fromlat' => self::BADEN['lat'], 'fromlng' => self::BADEN['lng'],
            'tolat' => self::NEUSTADT['lat'], 'tolng' => self::NEUSTADT['lng']]]);
        $found = $DB->get_fieldset_sql("SELECT e.id FROM {datalynx_entries} e WHERE $sql", $params);
        $this->assertSame([$wide], array_map('intval', $found));
    }

    /**
     * Points about every 2 km, the stops included, along the track or the straight legs.
     */
    public function test_corridor_points(): void {
        $journey = itinerary::from_json(json_encode([
            ['address' => 'A', 'lat' => 48.0, 'lng' => 16.0],
            ['address' => 'B', 'lat' => 48.0, 'lng' => 16.2],
        ]));
        $points = $journey->corridor_points();
        // About 14.9 km: seven points in between and the two stops.
        $this->assertCount(9, $points);
        $this->assertSame('A', $points[0]->address);
        $this->assertSame('B', end($points)->address);
        for ($i = 1; $i < count($points); $i++) {
            $this->assertLessThanOrEqual(2.01, $points[$i - 1]->distance_to($points[$i]));
        }
        // A dense track is thinned out.
        $track = [];
        for ($i = 0; $i <= 100; $i++) {
            $track[] = [48.0, 16.0 + $i * 0.002];
        }
        $this->assertLessThan(12, count($journey->corridor_points($track)));
    }
}
