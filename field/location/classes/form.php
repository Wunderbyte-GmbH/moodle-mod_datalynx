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

namespace datalynxfield_location;

use mod_datalynx\form\datalynxfield_form;

/**
 * Location field configuration form class.
 *
 * @package    datalynxfield_location
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends datalynxfield_form {
    /**
     * Define the field configuration form attributes.
     *
     * @return void
     */
    public function field_definition() {
        $mform = &$this->_form;

        $mform->addElement(
            'header',
            'locationfieldhdr',
            get_string('map_provider', 'datalynxfield_location')
        );

        // Param1: Map Provider (osm / google).
        $providers = [
            'osm' => get_string('provider_osm', 'datalynxfield_location'),
            'google' => get_string('provider_google', 'datalynxfield_location'),
        ];
        $mform->addElement('select', 'param1', get_string('map_provider', 'datalynxfield_location'), $providers);
        $mform->addHelpButton('param1', 'map_provider', 'datalynxfield_location');
        $mform->setDefault('param1', 'osm');

        // Param2: Geocoding API Base URL.
        $mform->addElement('text', 'param2', get_string('api_url', 'datalynxfield_location'), ['size' => 60]);
        $mform->setType('param2', PARAM_URL);
        $mform->setDefault('param2', 'https://nominatim.openstreetmap.org');
        $mform->addHelpButton('param2', 'api_url', 'datalynxfield_location');

        // Param3: API Key (Google Maps API key or Tile Server key).
        $mform->addElement('text', 'param3', get_string('api_key', 'datalynxfield_location'), ['size' => 60]);
        $mform->setType('param3', PARAM_TEXT);
        $mform->addHelpButton('param3', 'api_key', 'datalynxfield_location');

        // Param4: Default Zoom level.
        $zoomoptions = array_combine(range(1, 18), range(1, 18));
        $mform->addElement('select', 'param4', get_string('default_zoom', 'datalynxfield_location'), $zoomoptions);
        $mform->setDefault('param4', 13);
        $mform->setType('param4', PARAM_INT);

        // Param5: Default Search Radius (km).
        $mform->addElement('text', 'param5', get_string('default_radius', 'datalynxfield_location'), ['size' => 10]);
        $mform->setType('param5', PARAM_INT);
        $mform->setDefault('param5', 5);

        // Param6: Display Format.
        $displayformats = [
            'address_only' => get_string('display_address_only', 'datalynxfield_location'),
            'map_mini' => get_string('display_map_mini', 'datalynxfield_location'),
            'route_link' => get_string('display_route_link', 'datalynxfield_location'),
        ];
        $mform->addElement('select', 'param6', get_string('display_format', 'datalynxfield_location'), $displayformats);
        $mform->setDefault('param6', 'map_mini');

        // Param7: Country restriction ISO codes.
        $mform->addElement('text', 'param7', get_string('country_restriction', 'datalynxfield_location'), ['size' => 30]);
        $mform->setType('param7', PARAM_TEXT);
        $mform->addHelpButton('param7', 'country_restriction', 'datalynxfield_location');
        $mform->setDefault('param7', 'de,at,ch');
    }
}
