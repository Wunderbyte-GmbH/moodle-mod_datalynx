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

namespace datalynxfield_itinerary;

use context_system;
use html_writer;
use mod_datalynx\form\datalynxfield_form;
use mod_datalynx\local\map\provider_config;
use moodle_url;

/**
 * Itinerary field configuration form.
 *
 * Which map services are used is a site setting, not a field setting — see
 * {@see provider_config}. Only presentation and matching behaviour live here.
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends datalynxfield_form {
    /**
     * Define the field configuration form attributes.
     *
     * @return void
     */
    public function field_definition(): void {
        $mform = &$this->_form;

        $mform->addElement(
            'header',
            'itineraryfieldhdr',
            get_string('itinerarysettings', 'datalynxfield_itinerary')
        );

        $mform->addElement(
            'static',
            'activeservices',
            get_string('activeservices', 'datalynxfield_itinerary'),
            $this->describe_active_services()
        );

        // Param1: maximum number of stops.
        $mform->addElement('text', 'param1', get_string('maxwaypoints', 'datalynxfield_itinerary'), ['size' => 6]);
        $mform->setType('param1', PARAM_INT);
        $mform->setDefault('param1', field::DEFAULT_MAX_WAYPOINTS);
        $mform->addHelpButton('param1', 'maxwaypoints', 'datalynxfield_itinerary');

        // Param2: rows shown before the user adds more.
        $mform->addElement('text', 'param2', get_string('initialwaypoints', 'datalynxfield_itinerary'), ['size' => 6]);
        $mform->setType('param2', PARAM_INT);
        $mform->setDefault('param2', 2);

        // Param3: display format.
        $mform->addElement('select', 'param3', get_string('display_format', 'datalynxfield_itinerary'), [
            'both' => get_string('display_both', 'datalynxfield_itinerary'),
            'map' => get_string('display_map', 'datalynxfield_itinerary'),
            'list' => get_string('display_list', 'datalynxfield_itinerary'),
        ]);
        $mform->setDefault('param3', 'both');

        // Param4: default zoom.
        $zoomoptions = array_combine(range(1, 20), range(1, 20));
        $mform->addElement('select', 'param4', get_string('default_zoom', 'datalynxfield_itinerary'), $zoomoptions);
        $mform->setType('param4', PARAM_INT);
        $mform->setDefault('param4', provider_config::default_view()['zoom']);

        // Param5: default corridor radius.
        $mform->addElement('text', 'param5', get_string('matchradius', 'datalynxfield_itinerary'), ['size' => 6]);
        $mform->setType('param5', PARAM_INT);
        $mform->setDefault('param5', 5);
        $mform->addHelpButton('param5', 'matchradius', 'datalynxfield_itinerary');

        // Corridor width per entry from a number field (param9 JSON, e.g. the detour a driver accepts).
        $numbers = [0 => get_string('none')];
        foreach ($this->dlx->get_fields() as $fieldid => $field) {
            if ($field->type === 'number') {
                $numbers[$fieldid] = $field->field->name;
            }
        }
        $mform->addElement('select', 'corridorfield', get_string('corridorfield', 'datalynxfield_itinerary'), $numbers);
        $mform->setType('corridorfield', PARAM_INT);
        $mform->addHelpButton('corridorfield', 'corridorfield', 'datalynxfield_itinerary');

        // Param6: coordinate precision shown to people who are not participants.
        $mform->addElement('select', 'param6', get_string('precision', 'datalynxfield_itinerary'), [
            'approximate' => get_string('precision_approximate', 'datalynxfield_itinerary'),
            'exact' => get_string('precision_exact', 'datalynxfield_itinerary'),
        ]);
        $mform->setDefault('param6', 'approximate');
        $mform->addHelpButton('param6', 'precision', 'datalynxfield_itinerary');

        // Param7: country restriction override for the address lookup.
        $mform->addElement('text', 'param7', get_string('country_restriction', 'datalynxfield_location'), ['size' => 30]);
        $mform->setType('param7', PARAM_TEXT);
        $mform->addHelpButton('param7', 'country_restriction', 'datalynxfield_location');

        // Billing (param9, JSON, with the corridor field): the distance is computed on the server only (mod_datalynx#328).
        $mform->addElement('header', 'billinghdr', get_string('billing', 'datalynxfield_itinerary'));
        $mform->addElement('advcheckbox', 'billing', get_string('billingdistance', 'datalynxfield_itinerary'));
        $mform->addHelpButton('billing', 'billingdistance', 'datalynxfield_itinerary');
        $fieldsof = function (array $types): array {
            $menu = [0 => get_string('none')];
            foreach ($this->dlx->get_fields() as $fieldid => $field) {
                if (in_array($field->type, $types, true)) {
                    $menu[$fieldid] = $field->field->name;
                }
            }
            return $menu;
        };
        foreach (
            [
            'returnfield' => ['checkbox'],
            'requestedfield' => ['number'],
            'justificationfield' => ['textarea', 'text'],
            ] as $name => $types
        ) {
            $mform->addElement('select', $name, get_string($name, 'datalynxfield_itinerary'), $fieldsof($types));
            $mform->setType($name, PARAM_INT);
            $mform->hideIf($name, 'billing', 'notchecked');
        }
        $mform->addHelpButton('justificationfield', 'justificationfield', 'datalynxfield_itinerary');
    }

    /**
     * Unpack the billing settings from param9.
     *
     * @param \stdClass|array $data
     */
    public function set_data($data) {
        $data = (object) $data;
        $settings = json_decode((string) ($data->param9 ?? ''), true) ?: [];
        foreach (['billing', 'returnfield', 'requestedfield', 'justificationfield', 'corridorfield'] as $name) {
            $data->$name = (int) ($settings[$name] ?? 0);
        }
        parent::set_data($data);
    }

    /**
     * Pack the billing settings into param9.
     *
     * @return \stdClass|null
     */
    public function get_data() {
        if ($data = parent::get_data()) {
            $settings = [];
            foreach (['billing', 'returnfield', 'requestedfield', 'justificationfield'] as $name) {
                $settings[$name] = (int) ($data->$name ?? 0);
                unset($data->$name);
            }
            if (!$settings['billing']) {
                $settings = [];
            }
            if (!empty($data->corridorfield)) {
                $settings['corridorfield'] = (int) $data->corridorfield;
            }
            unset($data->corridorfield);
            $data->param9 = $settings ? json_encode($settings) : '';
        }
        return $data;
    }

    /**
     * Describe the site's configured map services for the form.
     *
     * @return string HTML
     */
    protected function describe_active_services(): string {
        $engine = provider_config::geocoder_engine();
        $description = html_writer::alist([
            get_string('activegeocoder', 'datalynxfield_location', (object) [
                'name' => get_string('geocoder_' . $engine, 'datalynx'),
                'url' => provider_config::geocoder_url() ?: '-',
            ]),
            get_string('activebasemap', 'datalynxfield_location', (object) [
                'url' => provider_config::tile_config()['tileurl'],
            ]),
        ]);

        if (has_capability('moodle/site:config', context_system::instance())) {
            $description .= html_writer::link(
                new moodle_url('/admin/settings.php', ['section' => 'modsettingdatalynx']),
                get_string('changemapservices', 'datalynxfield_location')
            );
        }

        return $description;
    }
}
