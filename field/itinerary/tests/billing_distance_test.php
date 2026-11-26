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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/stub_router.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\map\route_result;
use mod_datalynx\local\map\router_factory;
use mod_datalynx\mobile_test_trait;
use mod_datalynx\stub_router;

/**
 * A billing-relevant distance: computed on the server, doubled for a return trip, and more km asked
 * for need a reason (mod_datalynx#328).
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_itinerary\field
 * @covers     \datalynxfield_itinerary\renderer
 */
final class billing_distance_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    protected datalynx $dlx;

    /** @var int[] Field ids. */
    protected array $ids = [];

    /** @var string Two stops: the stub router makes 100 km of one leg. */
    const STOPS = '[{"address":"Wien","lat":48.2,"lng":16.37},{"address":"Graz","lat":47.07,"lng":15.44}]';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        router_factory::set_router_for_testing(new stub_router());
        field::reset_server_routes();
        [, $this->dlx] = $this->create_mobile_instance();
        $this->ids['return'] = $this->add_mobile_field($this->dlx, 'checkbox', 'Rückfahrt', ['param1' => 'Rückfahrt']);
        $this->ids['requested'] = $this->add_mobile_field($this->dlx, 'number', 'Beantragte km');
        $this->ids['reason'] = $this->add_mobile_field($this->dlx, 'textarea', 'Begründung');
        $this->ids['route'] = $this->add_mobile_field($this->dlx, 'itinerary', 'Route', [
            'param1' => 8,
            'param9' => json_encode(['billing' => 1, 'returnfield' => $this->ids['return'],
                'requestedfield' => $this->ids['requested'], 'justificationfield' => $this->ids['reason']]),
        ]);
        $this->dlx = new datalynx($this->dlx->id());
    }

    protected function tearDown(): void {
        router_factory::set_router_for_testing(null);
        parent::tearDown();
    }

    /**
     * The route field.
     *
     * @return field
     */
    protected function route(): field {
        return $this->dlx->get_field_from_id($this->ids['route'], true);
    }

    /**
     * A forged route from the browser is ignored: the server routes, and says which router and when.
     */
    public function test_forged_route_is_ignored(): void {
        global $DB;
        $entryid = $this->add_mobile_entry($this->dlx, get_admin()->id, []);
        $forged = (new route_result(1000.0, 60.0))->to_json();
        $this->route()->update_content((object) ['id' => $entryid], ['waypoints' => self::STOPS, 'route' => $forged]);

        $row = $DB->get_record('datalynx_contents', ['entryid' => $entryid, 'fieldid' => $this->ids['route']]);
        $this->assertEquals(100.0, route_result::from_json($row->content4)->distance_km());
        $this->assertArrayHasKey('provider', json_decode($row->content3, true));
    }

    /**
     * Without a router, the entry cannot be saved.
     */
    public function test_router_failure(): void {
        router_factory::set_router_for_testing(new class implements \mod_datalynx\local\map\router {
            /**
             * No route.
             *
             * @param array $stops
             * @return route_result|null
             */
            public function route(array $stops): ?route_result {
                return null;
            }

            /**
             * Stops a route may have.
             *
             * @return int
             */
            public function max_stops(): int {
                return 25;
            }
        });
        field::reset_server_routes();
        $name = "field_{$this->ids['route']}_1_waypoints";
        $errors = $this->route()->renderer()->validate(1, [], (object) [$name => self::STOPS]);
        $this->assertSame([$name => get_string('errnoroute', 'datalynxfield_itinerary')], $errors);
    }

    /**
     * A return trip doubles the billed distance; more km asked for than billed need a reason.
     */
    public function test_return_trip_and_requested_km(): void {
        $entryid = $this->add_mobile_entry($this->dlx, get_admin()->id, []);
        $this->route()->update_content((object) ['id' => $entryid], ['waypoints' => self::STOPS]);
        $this->assertEquals(100.0, $this->route()->billed_km($entryid));

        $route = "field_{$this->ids['route']}_{$entryid}_waypoints";
        $return = "field_{$this->ids['return']}_{$entryid}";
        $requested = "field_{$this->ids['requested']}_{$entryid}";
        $reason = "field_{$this->ids['reason']}_{$entryid}";
        $validate = fn(array $data) => $this->route()->renderer()->validate(
            $entryid,
            [],
            (object) ($data + [$route => self::STOPS])
        );

        // 150 km asked for, 100 km billed: a reason is needed.
        $this->assertArrayHasKey($reason, $validate([$requested => '150']));
        $this->assertSame([], $validate([$requested => '150', $reason => 'Umleitung wegen Baustelle']));
        // With the return trip the billed distance is 200 km: 150 need no reason.
        $this->assertSame([], $validate([$requested => '150', $return => ['1']]));
        $this->assertSame([], $validate([$requested => '100']));

        $this->add_content($entryid, $this->ids['return'], '#1#');
        $this->assertEquals(200.0, $this->route()->billed_km($entryid));
    }

    /**
     * Without the setting, the route the browser sends is kept, as before.
     */
    public function test_setting_off(): void {
        global $DB;
        $DB->set_field('datalynx_fields', 'param9', '', ['id' => $this->ids['route']]);
        \mod_datalynx\local\definition_cache::invalidate($this->dlx->id());
        $this->dlx = new datalynx($this->dlx->id());
        $entryid = $this->add_mobile_entry($this->dlx, get_admin()->id, []);
        $this->route()->update_content(
            (object) ['id' => $entryid],
            ['waypoints' => self::STOPS, 'route' => (new route_result(1000.0, 60.0))->to_json()]
        );
        $content4 = $DB->get_field('datalynx_contents', 'content4', ['entryid' => $entryid, 'fieldid' => $this->ids['route']]);
        $this->assertEquals(1.0, route_result::from_json($content4)->distance_km());
    }

    /**
     * Store a content row.
     *
     * @param int $entryid
     * @param int $fieldid
     * @param string $content
     */
    protected function add_content(int $entryid, int $fieldid, string $content): void {
        global $DB;
        $DB->insert_record('datalynx_contents', (object) ['entryid' => $entryid, 'fieldid' => $fieldid, 'content' => $content]);
    }
}
