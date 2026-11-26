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
use datalynxfield_teammemberselect\membership;
use mod_datalynx\external\team_subscription;
use mod_datalynx\local\map\router_factory;
use mod_datalynx\local\ride\itinerary;

/**
 * Passengers choose their pickup point when asking for a seat (mod_datalynx#327).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_teammemberselect\field
 * @covers     \datalynxfield_itinerary\field
 * @covers     \mod_datalynx\local\ride\itinerary
 */
final class ride_pickup_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var int[] */
    protected array $ids = [];

    /** @var \stdClass */
    protected \stdClass $driver;

    /** @var \stdClass[] */
    protected array $passengers = [];

    /** @var int The ride Wien → Graz, 10 km detour. */
    protected int $ride;

    /** @var array A pickup point near Wiener Neustadt, between Wien and Graz. */
    const NEUSTADT = ['address' => 'Hauptplatz, Wiener Neustadt', 'lat' => 47.8149, 'lng' => 16.2372];

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        router_factory::set_router_for_testing(new stub_router());
        \datalynxfield_itinerary\field::reset_server_routes();
        [$course, $this->dlx] = $this->create_mobile_instance();
        $this->ids['route'] = $this->add_mobile_field($this->dlx, 'itinerary', 'Route', ['param1' => 8, 'param5' => 5,
            'param6' => 'approximate']);
        $this->ids['detour'] = $this->add_mobile_field($this->dlx, 'number', 'Umweg');
        $this->ids['seats'] = $this->add_mobile_field($this->dlx, 'number', 'Plätze');
        $this->ids['team'] = $this->add_mobile_field($this->dlx, 'teammemberselect', 'Passagiere', [
            'param1' => 8,
            'param2' => json_encode([datalynx::PERMISSION_STUDENT]),
            'param8' => 1,
            'param10' => json_encode(['limitfield' => $this->ids['seats'], 'approval' => 1,
                'pickupfield' => $this->ids['route'], 'detourfield' => $this->ids['detour']]),
        ]);
        $this->driver = $this->getDataGenerator()->create_and_enrol($course, 'student');
        for ($i = 0; $i < 2; $i++) {
            $this->passengers[] = $this->getDataGenerator()->create_and_enrol($course, 'student');
        }
        $this->dlx = new datalynx($this->dlx->id());
        // Wiener Neustadt lies 14 km off the straight line Wien - Graz (the stub router has no track).
        $this->ride = $this->add_mobile_entry($this->dlx, $this->driver->id, [$this->ids['seats'] => '3',
            $this->ids['detour'] => '20']);
        $this->route()->update_content((object) ['id' => $this->ride], ['waypoints' => json_encode([
            ['address' => 'Karlsplatz, Wien', 'lat' => 48.2004, 'lng' => 16.3696],
            ['address' => 'Hauptplatz, Graz', 'lat' => 47.0710, 'lng' => 15.4385],
        ])]);
    }

    protected function tearDown(): void {
        router_factory::set_router_for_testing(null);
        parent::tearDown();
    }

    /**
     * The route field.
     *
     * @return \datalynxfield_itinerary\field
     */
    protected function route(): \datalynxfield_itinerary\field {
        return (new datalynx($this->dlx->id()))->get_field_from_id($this->ids['route'], true);
    }

    /**
     * The ride with its stored route, as the edit form has it.
     *
     * @return \stdClass
     */
    protected function stored_entry(): \stdClass {
        global $DB;
        $row = $DB->get_record('datalynx_contents', ['entryid' => $this->ride, 'fieldid' => $this->ids['route']]);
        $entry = (object) ['id' => $this->ride, "c{$this->ids['route']}_id" => $row->id];
        foreach (['', '1', '2', '3', '4'] as $suffix) {
            $entry->{"c{$this->ids['route']}_content{$suffix}"} = $row->{"content{$suffix}"};
        }
        return $entry;
    }

    /**
     * Act through the web service.
     *
     * @param \stdClass $actor
     * @param string $action
     * @param \stdClass|null $subject
     * @param array $data
     * @return array
     */
    protected function act(\stdClass $actor, string $action, ?\stdClass $subject = null, array $data = []): array {
        $this->setUser($actor);
        return team_subscription::execute(
            $this->dlx->id(),
            $this->ride,
            $this->ids['team'],
            ($subject ?? $actor)->id,
            $action,
            json_encode($data)
        );
    }

    /**
     * Approving puts the pickup point into the route, between start and destination; leaving takes it out.
     */
    public function test_pickup_joins_and_leaves_the_route(): void {
        global $DB;
        [$anna] = $this->passengers;
        $this->assertTrue($this->act($anna, 'request', null, ['pickup' => self::NEUSTADT])['success']);
        $this->assertCount(2, $this->route()->stored_journey($this->ride)->waypoints(), 'a request does not change the route');

        $this->assertTrue($this->act($this->driver, 'approve', $anna)['success']);
        $stops = $this->route()->stored_journey($this->ride)->waypoints();
        $this->assertCount(3, $stops);
        $this->assertSame((int) $anna->id, $stops[1]->userid, 'the pickup stop sits between start and destination');
        $this->assertSame('Hauptplatz, Wiener Neustadt', $stops[1]->address);
        // The route was calculated again: two legs of 100 km with the stub router.
        $content4 = $DB->get_field('datalynx_contents', 'content4', ['entryid' => $this->ride, 'fieldid' => $this->ids['route']]);
        $this->assertEquals(200.0, \mod_datalynx\local\map\route_result::from_json($content4)->distance_km());
        $this->assertSame(3, $DB->count_records('datalynx_waypoints', ['entryid' => $this->ride]));

        $this->assertTrue($this->act($anna, 'withdraw')['success']);
        $this->assertCount(2, $this->route()->stored_journey($this->ride)->waypoints());
    }

    /**
     * A request needs a pickup point within the detour.
     */
    public function test_pickup_checks(): void {
        [$anna] = $this->passengers;
        $none = $this->act($anna, 'request');
        $this->assertFalse($none['success']);
        $this->assertSame(get_string('pickuprequired', 'datalynxfield_teammemberselect'), $none['error']);

        $innsbruck = ['address' => 'Innsbruck', 'lat' => 47.2654, 'lng' => 11.3927];
        $outside = $this->act($anna, 'request', null, ['pickup' => $innsbruck]);
        $this->assertFalse($outside['success']);
        $this->assertSame(get_string('pickupoutside', 'datalynxfield_teammemberselect', '20'), $outside['error']);
    }

    /**
     * Removing a pickup stop from the route in the form removes the passenger from the team.
     */
    public function test_removing_the_stop_removes_the_passenger(): void {
        [$anna] = $this->passengers;
        $this->act($anna, 'request', null, ['pickup' => self::NEUSTADT]);
        $this->act($this->driver, 'approve', $anna);

        // The driver saves the route without the stop (the browser does not know its passenger).
        $journey = $this->route()->stored_journey($this->ride)->without_pickup((int) $anna->id);
        $this->setUser($this->driver);
        $this->route()->update_content($this->stored_entry(), ['waypoints' => $journey->to_json()]);
        $team = (new datalynx($this->dlx->id()))->get_field_from_id($this->ids['team'], true)->membership($this->ride);
        $this->assertSame(membership::NONE, $team->status((int) $anna->id));

        // Saving the route with the stop again keeps the passenger: the stop is recognised by its position.
        $this->act($anna, 'request', null, ['pickup' => self::NEUSTADT]);
        $this->act($this->driver, 'approve', $anna);
        $stops = array_map(
            fn($stop) => ['address' => $stop->address, 'lat' => $stop->lat, 'lng' => $stop->lng],
            $this->route()->stored_journey($this->ride)->waypoints()
        );
        $this->route()->update_content($this->stored_entry(), ['waypoints' => json_encode($stops)]);
        $this->assertSame((int) $anna->id, $this->route()->stored_journey($this->ride)->waypoints()[1]->userid);
    }

    /**
     * Others see a pickup stop as "Pickup" at a rounded position; the passenger sees their own exactly.
     */
    public function test_precision(): void {
        [$anna, $ben] = $this->passengers;
        $this->act($anna, 'request', null, ['pickup' => self::NEUSTADT]);
        $this->act($this->driver, 'approve', $anna);
        $journey = $this->route()->stored_journey($this->ride);

        $forben = $journey->export(false, (int) $ben->id)[1];
        $this->assertSame(get_string('pickupstop', 'datalynxfield_itinerary'), $forben['address']);
        $this->assertSame(47.81, $forben['lat']);
        $this->assertArrayNotHasKey('userid', $forben);
        $foranna = $journey->export(false, (int) $anna->id)[1];
        $this->assertSame('Hauptplatz, Wiener Neustadt', $foranna['address']);
        $this->assertSame(47.8149, $foranna['lat']);
    }

    /**
     * Deleting the data of a passenger takes their pickup stop out of other people's routes.
     */
    public function test_privacy(): void {
        [$anna] = $this->passengers;
        $this->act($anna, 'request', null, ['pickup' => self::NEUSTADT]);
        $this->act($this->driver, 'approve', $anna);

        $this->setAdminUser();
        $list = new \core_privacy\local\request\approved_contextlist($anna, 'mod_datalynx', [$this->dlx->context->id]);
        privacy\provider::delete_data_for_user($list);
        $this->assertCount(2, $this->route()->stored_journey($this->ride)->waypoints());
        $this->assertNotContains((int) $anna->id, array_map(
            fn($stop) => $stop->userid,
            $this->route()->stored_journey($this->ride)->waypoints()
        ));
    }

    /**
     * Inserting at the cheapest position, and the corridor distance.
     */
    public function test_itinerary_geometry(): void {
        $journey = itinerary::from_json(json_encode([
            ['address' => 'A', 'lat' => 48.0, 'lng' => 16.0],
            ['address' => 'B', 'lat' => 48.0, 'lng' => 17.0],
            ['address' => 'C', 'lat' => 48.0, 'lng' => 18.0],
        ]));
        $near = \mod_datalynx\local\ride\waypoint::from_array(['address' => 'P', 'lat' => 48.01, 'lng' => 17.5, 'userid' => 5]);
        $this->assertSame('P', $journey->with_pickup($near)->waypoints()[2]->address);
        $this->assertLessThan(2, $journey->distance_from($near));

        // The routed track: Google's example polyline (38.5,-120.2), (40.7,-120.95), (43.252,-126.453).
        $track = new \mod_datalynx\local\map\route_result(1.0, 1.0, '_p~iF~ps|U_ulLnnqC_mqNvxq' . chr(96) . '@');
        $this->assertSame([[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]], $track->points());
        $this->assertSame([], (new \mod_datalynx\local\map\route_result(1.0, 1.0, 'stub'))->points());
    }
}
