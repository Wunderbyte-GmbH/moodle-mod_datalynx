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
use mod_datalynx\local\map\geocoder\google;
use mod_datalynx\local\map\geocoder\nominatim;
use mod_datalynx\local\map\geocoder\photon;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/stub_http_client.php');

/**
 * Tests for the geocoding adapters.
 *
 * These exercise request building and response parsing against canned payloads,
 * so they never touch the network.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\map\geocoder\base
 */
final class geocoder_test extends advanced_testcase {
    /** @var stub_http_client */
    protected stub_http_client $client;

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->client = new stub_http_client();
    }

    /**
     * Photon composes a label out of the structured address components.
     */
    public function test_photon_builds_a_label_from_components(): void {
        $this->client->response = ['features' => [[
            'geometry' => ['coordinates' => [16.391983, 48.228712]],
            'properties' => [
                'name' => 'Nordbahn Saal',
                'street' => 'Bruno-Marek-Allee',
                'housenumber' => '5',
                'postcode' => '1020',
                'city' => 'Vienna',
                'state' => 'Vienna',
                'country' => 'Austria',
                'countrycode' => 'AT',
            ],
        ]]];

        $places = (new photon('https://photon.example.org', '', $this->client))->search('Bruno-Marek-Allee');

        $this->assertCount(1, $places);
        // Vienna appears as both city and state here, but must not be repeated.
        $this->assertEquals(
            'Nordbahn Saal, Bruno-Marek-Allee 5, 1020 Vienna, Austria',
            $places[0]->address
        );
        $this->assertEquals(48.228712, $places[0]->lat);
        $this->assertEquals(16.391983, $places[0]->lng);
        $this->assertEquals('at', $places[0]->countrycode);
    }

    /**
     * Photon has no country parameter, so filtering happens on the results.
     */
    public function test_photon_filters_countries_locally(): void {
        $feature = static function (string $name, string $code): array {
            return [
                'geometry' => ['coordinates' => [10.0, 50.0]],
                'properties' => ['name' => $name, 'countrycode' => $code],
            ];
        };
        $this->client->response = ['features' => [
            $feature('Wien', 'AT'),
            $feature('Wien', 'US'),
            $feature('Wien', 'DE'),
        ]];

        $geocoder = new photon('https://photon.example.org', '', $this->client);
        $places = $geocoder->search('Wien', ['countries' => 'de,at']);

        $this->assertCount(2, $places);
        $this->assertEquals(['at', 'de'], array_column(array_map(
            static fn($place) => $place->to_array(),
            $places
        ), 'countrycode'));

        // Confirm no country parameter is sent to the service.
        [, $params] = $this->client->requests[0];
        $this->assertArrayNotHasKey('countrycodes', $params);
    }

    /**
     * Nominatim receives the country restriction server side.
     */
    public function test_nominatim_sends_country_restriction(): void {
        $this->client->response = [];
        $geocoder = new nominatim('https://nominatim.example.org', '', $this->client);
        $geocoder->search('Stephansplatz', ['countries' => 'at']);

        [$url, $params] = $this->client->requests[0];
        $this->assertEquals('https://nominatim.example.org/search', $url);
        $this->assertEquals('at', $params['countrycodes']);
    }

    /**
     * Nominatim returns a list when searching and a bare object when reversing.
     */
    public function test_nominatim_parses_both_response_shapes(): void {
        $geocoder = new nominatim('https://nominatim.example.org', '', $this->client);

        $this->client->response = [[
            'display_name' => 'Stephansplatz, Vienna, Austria',
            'lat' => '48.2084639',
            'lon' => '16.3720438',
            'address' => ['country_code' => 'at'],
        ]];
        $places = $geocoder->search('Stephansplatz');
        $this->assertCount(1, $places);
        $this->assertEquals('Stephansplatz, Vienna, Austria', $places[0]->address);

        $this->client->response = [
            'display_name' => 'Bruno-Marek-Allee 5, Vienna',
            'lat' => '48.228712',
            'lon' => '16.391983',
            'address' => ['country_code' => 'at'],
        ];
        $place = $geocoder->reverse(48.228712, 16.391983);
        $this->assertNotNull($place);
        $this->assertEquals('Bruno-Marek-Allee 5, Vienna', $place->address);
    }

    /**
     * Google reports failures in a status field rather than an HTTP error.
     */
    public function test_google_honours_the_status_field(): void {
        $geocoder = new google('https://maps.example.org/maps/api', 'key', $this->client);

        $this->client->response = ['status' => 'ZERO_RESULTS', 'results' => []];
        $this->assertSame([], $geocoder->search('nowhere at all'));

        $this->client->response = ['status' => 'OK', 'results' => [[
            'formatted_address' => 'Stephansplatz, 1010 Wien, Austria',
            'geometry' => ['location' => ['lat' => 48.2084639, 'lng' => 16.3720438]],
            'address_components' => [['types' => ['country'], 'short_name' => 'AT']],
        ]]];
        $places = $geocoder->search('Stephansplatz');
        $this->assertCount(1, $places);
        $this->assertEquals('Stephansplatz, 1010 Wien, Austria', $places[0]->address);
        $this->assertEquals('at', $places[0]->countrycode);
    }

    /**
     * Only Photon may be queried while the user is still typing.
     */
    public function test_typeahead_capability_per_engine(): void {
        $this->assertTrue((new photon('https://p.example.org', '', $this->client))->supports_typeahead());
        $this->assertFalse((new nominatim('https://n.example.org', '', $this->client))->supports_typeahead());
        $this->assertFalse((new google('https://g.example.org', 'k', $this->client))->supports_typeahead());
    }

    /**
     * A query shorter than the engine's minimum never leaves the server.
     */
    public function test_short_queries_are_not_sent(): void {
        $geocoder = new nominatim('https://nominatim.example.org', '', $this->client);

        $this->assertSame([], $geocoder->search('ab'));
        $this->assertSame([], $this->client->requests);
    }

    /**
     * Impossible coordinates are rejected before a request is made.
     */
    public function test_out_of_range_coordinates_are_rejected(): void {
        $geocoder = new photon('https://photon.example.org', '', $this->client);

        $this->assertNull($geocoder->reverse(91.0, 0.0));
        $this->assertNull($geocoder->reverse(0.0, 181.0));
        $this->assertSame([], $this->client->requests);
    }

    /**
     * A failing service degrades to no results rather than an exception.
     */
    public function test_service_failure_is_not_fatal(): void {
        $this->client->response = null;
        $geocoder = new photon('https://photon.example.org', '', $this->client);

        $this->assertSame([], $geocoder->search('Stephansplatz'));
        $this->assertNull($geocoder->reverse(48.2, 16.3));
    }

    /**
     * The number of results honours the requested limit.
     */
    public function test_limit_is_applied(): void {
        $features = [];
        for ($i = 0; $i < 10; $i++) {
            $features[] = [
                'geometry' => ['coordinates' => [16.0 + $i, 48.0]],
                'properties' => ['name' => "Place {$i}"],
            ];
        }
        $this->client->response = ['features' => $features];

        $geocoder = new photon('https://photon.example.org', '', $this->client);
        $this->assertCount(3, $geocoder->search('Place', ['limit' => 3]));
    }
}
