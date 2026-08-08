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

namespace mod_datalynx\local\map;

use advanced_testcase;
use mod_datalynx\local\map\router\google;
use mod_datalynx\local\map\router\osrm;

/**
 * Tests for the routing adapters and the engine the site resolves to.
 *
 * The HTTP transport is stubbed, so these cover exactly what each adapter owns:
 * how it asks and how it reads the answer.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\map\router\osrm
 * @covers     \mod_datalynx\local\map\router\google
 * @covers     \mod_datalynx\local\map\router_factory
 * @covers     \mod_datalynx\local\map\route_result
 */
final class router_test extends advanced_testcase {
    /** @var array Vienna and Graz, so the request looks like a real journey. */
    const WIEN = [48.1852, 16.3775];

    /** @var array Second stop of the test journey. */
    const GRAZ = [47.0736, 15.4161];

    /**
     * Reset between tests: everything here reads site configuration.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/../../fixtures/stub_http_client.php');
    }

    /**
     * OSRM wants lng,lat in the path, and answers in metres and seconds.
     */
    public function test_osrm_builds_the_request_and_reads_the_answer(): void {
        $client = new stub_http_client();
        $client->response = [
            'code' => 'Ok',
            'routes' => [[
                'distance' => 195300.4,
                'duration' => 7830.2,
                'geometry' => 'yzoiHwzkeA',
            ]],
        ];

        $router = new osrm('https://router.example.org', '', $client);
        $route = $router->route([self::WIEN, self::GRAZ]);

        $this->assertNotNull($route);
        $this->assertEqualsWithDelta(195.3, $route->distance_km(), 0.01);
        $this->assertSame(131, $route->duration_minutes());
        $this->assertSame('yzoiHwzkeA', $route->polyline);

        [$url, $params] = $client->requests[0];
        $this->assertSame(
            'https://router.example.org/route/v1/driving/16.3775,48.1852;15.4161,47.0736',
            $url,
            'OSRM takes lng,lat in the path, the opposite order to the rest of the plugin.'
        );
        $this->assertSame('simplified', $params['overview']);
        $this->assertSame('polyline', $params['geometries']);
    }

    /**
     * A journey the engine cannot route is not an error, it is simply no route.
     */
    public function test_osrm_returns_null_when_no_route_exists(): void {
        $client = new stub_http_client();
        $client->response = ['code' => 'NoRoute', 'routes' => []];

        $router = new osrm('https://router.example.org', '', $client);

        $this->assertNull($router->route([self::WIEN, self::GRAZ]));
    }

    /**
     * Half-filled stops are a normal state of the picker and are skipped, and a
     * single stop is not a journey at all, so nothing is requested.
     */
    public function test_incomplete_journeys_are_not_requested(): void {
        $client = new stub_http_client();
        $router = new osrm('https://router.example.org', '', $client);

        $this->assertNull($router->route([self::WIEN]));
        $this->assertNull($router->route([self::WIEN, ['lat' => null, 'lng' => null]]));
        $this->assertNull($router->route([[999, 999], [1000, 1000]]));
        $this->assertSame([], $client->requests);
    }

    /**
     * Intermediate stops ride along as "via:", and the legs are summed.
     */
    public function test_google_sums_the_legs(): void {
        $client = new stub_http_client();
        $client->response = [
            'status' => 'OK',
            'routes' => [[
                'legs' => [
                    ['distance' => ['value' => 100000], 'duration' => ['value' => 4000]],
                    ['distance' => ['value' => 95300], 'duration' => ['value' => 3830]],
                ],
                'overview_polyline' => ['points' => 'abcdef'],
            ]],
        ];

        $router = new google('https://maps.example.org/maps/api', 'secret-key', $client);
        $route = $router->route([self::WIEN, [47.8149, 16.2372], self::GRAZ]);

        $this->assertNotNull($route);
        $this->assertEqualsWithDelta(195.3, $route->distance_km(), 0.01);
        $this->assertSame(131, $route->duration_minutes());
        $this->assertSame('abcdef', $route->polyline);

        [$url, $params] = $client->requests[0];
        $this->assertSame('https://maps.example.org/maps/api/directions/json', $url);
        $this->assertSame('48.1852,16.3775', $params['origin']);
        $this->assertSame('47.0736,15.4161', $params['destination']);
        $this->assertSame('via:47.8149,16.2372', $params['waypoints']);
        $this->assertSame('secret-key', $params['key']);
    }

    /**
     * The factory follows the site setting, and refuses configurations that cannot work.
     */
    public function test_factory_resolves_the_configured_engine(): void {
        set_config('router', provider_config::ROUTER_OSRM, 'mod_datalynx');
        set_config('routerurl', '', 'mod_datalynx');
        $this->assertInstanceOf(osrm::class, router_factory::instance());

        // Google without a key would be refused by the API on every request.
        set_config('router', provider_config::ROUTER_GOOGLE, 'mod_datalynx');
        set_config('routerkey', '', 'mod_datalynx');
        $this->assertNull(router_factory::instance());

        set_config('routerkey', 'a-key', 'mod_datalynx');
        $this->assertInstanceOf(google::class, router_factory::instance());

        set_config('router', provider_config::ROUTER_NONE, 'mod_datalynx');
        $this->assertNull(router_factory::instance());
    }

    /**
     * An unset URL falls back to the engine's public endpoint, which the settings
     * page then flags as a shared community service.
     */
    public function test_public_endpoint_is_the_default_and_is_flagged_as_shared(): void {
        set_config('router', provider_config::ROUTER_OSRM, 'mod_datalynx');
        set_config('routerurl', '', 'mod_datalynx');

        $this->assertSame('https://router.project-osrm.org', provider_config::router_url());
        $this->assertTrue(provider_config::uses_shared_endpoint());

        set_config('routerurl', 'https://osrm.example.org/', 'mod_datalynx');
        $this->assertSame('https://osrm.example.org', provider_config::router_url());
    }

    /**
     * The stored summary survives a round trip, and rubbish decodes to nothing.
     */
    public function test_route_result_round_trips_through_storage(): void {
        $route = new route_result(195300.4, 7830.2, 'abc');
        $restored = route_result::from_json($route->to_json());

        $this->assertNotNull($restored);
        $this->assertEqualsWithDelta(195300.4, $restored->distancem, 0.1);
        $this->assertEqualsWithDelta(7830, $restored->durations, 0.5);
        $this->assertSame('abc', $restored->polyline);

        $this->assertNull(route_result::from_json(''));
        $this->assertNull(route_result::from_json('not json'));
        $this->assertNull(route_result::from_json('{"distance": 10}'));
    }
}
