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
use mod_datalynx\local\map\provider_config;
use moodle_url;

/**
 * Location field configuration form class.
 *
 * Only presentation and behaviour are configured per field. Which map services
 * are used is a site setting, because the choice carries rate limits, API keys
 * and data protection consequences that belong with the administrator rather
 * than with whoever adds a field. {@see provider_config}
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
            get_string('locationsettings', 'datalynxfield_location')
        );

        // Tell the editor which services their entries will actually use, and
        // where to change them, instead of asking for a provider here.
        $mform->addElement(
            'static',
            'activeprovider',
            get_string('activeservices', 'datalynxfield_location'),
            $this->describe_active_services()
        );

        // Param4: default zoom level.
        $zoomoptions = array_combine(range(1, 20), range(1, 20));
        $mform->addElement('select', 'param4', get_string('default_zoom', 'datalynxfield_location'), $zoomoptions);
        $mform->setType('param4', PARAM_INT);
        $mform->setDefault('param4', 13);

        // Param5: default search radius in kilometres.
        $mform->addElement('text', 'param5', get_string('default_radius', 'datalynxfield_location'), ['size' => 10]);
        $mform->setType('param5', PARAM_INT);
        $mform->setDefault('param5', 5);

        // Param6: display format.
        $displayformats = [
            'address_only' => get_string('display_address_only', 'datalynxfield_location'),
            'map_mini' => get_string('display_map_mini', 'datalynxfield_location'),
            'route_link' => get_string('display_route_link', 'datalynxfield_location'),
        ];
        $mform->addElement('select', 'param6', get_string('display_format', 'datalynxfield_location'), $displayformats);
        $mform->setDefault('param6', 'map_mini');

        // Param7: country restriction, overriding the site default.
        $mform->addElement('text', 'param7', get_string('country_restriction', 'datalynxfield_location'), ['size' => 30]);
        $mform->setType('param7', PARAM_TEXT);
        $mform->addHelpButton('param7', 'country_restriction', 'datalynxfield_location');
    }

    /**
     * Describe the site's configured map services for the form.
     *
     * @return string HTML
     */
    protected function describe_active_services(): string {
        $engine = provider_config::geocoder_engine();
        $lines = [
            get_string('activegeocoder', 'datalynxfield_location', (object) [
                'name' => get_string('geocoder_' . $engine, 'datalynx'),
                'url' => provider_config::geocoder_url() ?: '-',
            ]),
            get_string('activebasemap', 'datalynxfield_location', (object) [
                'url' => provider_config::tile_config()['tileurl'],
            ]),
        ];

        $description = \html_writer::alist($lines);

        if (has_capability('moodle/site:config', \context_system::instance())) {
            $description .= \html_writer::link(
                new moodle_url('/admin/settings.php', ['section' => 'modsettingdatalynx']),
                get_string('changemapservices', 'datalynxfield_location')
            );
        }

        return $description;
    }
}
