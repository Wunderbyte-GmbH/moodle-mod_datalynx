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

/**
 * Tests for the site level map service configuration.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\map\provider_config
 */
final class provider_config_test extends advanced_testcase {
    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Out of the box the site uses Photon at its public endpoint.
     */
    public function test_defaults(): void {
        $this->assertEquals(provider_config::GEOCODER_PHOTON, provider_config::geocoder_engine());
        $this->assertEquals('https://photon.komoot.io', provider_config::geocoder_url());
        $this->assertEquals(provider_config::DEFAULT_TILE_URL, provider_config::tile_config()['tileurl']);
    }

    /**
     * Selecting an engine switches the default endpoint with it.
     */
    public function test_engine_selects_its_public_endpoint(): void {
        set_config('geocoder', provider_config::GEOCODER_NOMINATIM, 'mod_datalynx');

        $this->assertEquals('https://nominatim.openstreetmap.org', provider_config::geocoder_url());
    }

    /**
     * A configured URL overrides the public endpoint, and loses its trailing slash.
     */
    public function test_configured_url_wins(): void {
        set_config('geocoderurl', 'https://geocode.example.org/photon/', 'mod_datalynx');

        $this->assertEquals('https://geocode.example.org/photon', provider_config::geocoder_url());
    }

    /**
     * An unknown engine disables geocoding rather than guessing.
     */
    public function test_unknown_engine_disables_geocoding(): void {
        set_config('geocoder', 'notaprovider', 'mod_datalynx');

        $this->assertEquals(provider_config::GEOCODER_NONE, provider_config::geocoder_engine());
        $this->assertNull(geocoder_factory::instance());
    }

    /**
     * Country restrictions are lower cased, de-duplicated and shape checked.
     */
    public function test_clean_countries(): void {
        $this->assertEquals('de,at,ch', provider_config::clean_countries(' DE , at , ch , at '));
        $this->assertEquals('de', provider_config::clean_countries('de,deu,d,,'));
        $this->assertEquals('', provider_config::clean_countries('  '));
    }

    /**
     * The tile API key is substituted into the URL placeholder.
     */
    public function test_tile_key_substitution(): void {
        set_config('map_tileurl', 'https://tiles.example.org/{z}/{x}/{y}.png?key={key}', 'mod_datalynx');
        set_config('map_tilekey', 'se cret/1', 'mod_datalynx');

        $this->assertEquals(
            'https://tiles.example.org/{z}/{x}/{y}.png?key=se%20cret%2F1',
            provider_config::tile_config()['tileurl']
        );
    }

    /**
     * A valid default centre is parsed; anything else falls back to a world view.
     */
    public function test_default_view(): void {
        set_config('map_defaultcentre', '48.2082, 16.3738', 'mod_datalynx');
        set_config('map_defaultzoom', 14, 'mod_datalynx');
        $view = provider_config::default_view();
        $this->assertEquals(48.2082, $view['lat']);
        $this->assertEquals(16.3738, $view['lng']);
        $this->assertEquals(14, $view['zoom']);

        // Out of range latitude is not a location.
        set_config('map_defaultcentre', '148.2,16.3', 'mod_datalynx');
        $this->assertNull(provider_config::default_view()['lat']);

        set_config('map_defaultcentre', 'Vienna', 'mod_datalynx');
        $this->assertNull(provider_config::default_view()['lat']);
    }

    /**
     * The User-Agent names the site and offers a contact, as OSM requires.
     */
    public function test_user_agent_identifies_the_site(): void {
        global $CFG;

        set_config('map_contactemail', 'maps@example.org', 'mod_datalynx');
        $agent = provider_config::user_agent();

        $this->assertStringContainsString('mod_datalynx', $agent);
        $this->assertStringContainsString($CFG->wwwroot, $agent);
        $this->assertStringContainsString('maps@example.org', $agent);
    }

    /**
     * Free community endpoints are detected so the admin page can warn about them.
     */
    public function test_shared_endpoint_detection(): void {
        // Default configuration uses Photon and OSM tiles: both are shared.
        $this->assertTrue(provider_config::uses_shared_endpoint());

        set_config('geocoderurl', 'https://geocode.example.org', 'mod_datalynx');
        set_config('map_tileurl', 'https://tiles.example.org/{z}/{x}/{y}.png', 'mod_datalynx');
        $this->assertFalse(provider_config::uses_shared_endpoint());

        // A subdomain of a shared host still counts.
        set_config('map_tileurl', 'https://a.tile.openstreetmap.org/{z}/{x}/{y}.png', 'mod_datalynx');
        $this->assertTrue(provider_config::uses_shared_endpoint());
    }

    /**
     * Google needs a key: without one it is not a usable geocoder.
     */
    public function test_google_without_key_is_not_usable(): void {
        set_config('geocoder', provider_config::GEOCODER_GOOGLE, 'mod_datalynx');
        $this->assertNull(geocoder_factory::instance());

        set_config('geocoderkey', 'test-key', 'mod_datalynx');
        $this->assertInstanceOf(geocoder\google::class, geocoder_factory::instance());
    }
}
