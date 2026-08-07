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

use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;
use moodle_url;
use stdClass;

/**
 * Location field renderer class.
 *
 * @package    datalynxfield_location
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends datalynxfield_renderer {

    /**
     * Load Leaflet CSS and JS dependencies into the Moodle page header.
     */
    protected function enqueue_dependencies() {
        global $PAGE;

        $provider = $this->field->get('param1') ?: 'osm';
        $apikey   = $this->field->get('param3') ?: '';

        if ($provider === 'google' && !empty($apikey)) {
            $googleurl = new moodle_url("https://maps.googleapis.com/maps/api/js", ['key' => $apikey, 'libraries' => 'places']);
            $PAGE->requires->js($googleurl, true);
        } else {
            // Default: Leaflet 1.9.4 CSS only — preload in head if possible.
            // Leaflet JS is loaded dynamically by the AMD module (mod_datalynx/location)
            // via direct <script> injection to guarantee window.L is available.
            // Using $PAGE->requires->js() would wrap Leaflet in Moodle's AMD shim
            // preventing it from exporting to window.L.
            if (!$PAGE->headerprinted) {
                $PAGE->requires->css(new moodle_url('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'));
            }
        }
    }

    /**
     * Render the field in entry edit mode.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        global $PAGE;

        $this->enqueue_dependencies();

        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $address = $entry->{"c{$fieldid}_content"}  ?? '';
        $lat     = $entry->{"c{$fieldid}_content1"} ?? '';
        $lng     = $entry->{"c{$fieldid}_content2"} ?? '';

        $provider   = $field->get('param1') ?: 'osm';
        $apiurl     = $field->get('param2') ?: 'https://nominatim.openstreetmap.org';
        $apikey     = $field->get('param3') ?: '';
        $zoom       = (int) ($field->get('param4') ?: 13);
        $countries  = $field->get('param7') ?: 'de,at,ch';

        $containerid = "location_picker_{$fieldid}_{$entryid}";
        $mapid       = "map_{$fieldid}_{$entryid}";

        // 1. Address text input — registered as a proper QuickForm element so get_data() captures it.
        $attraddress = [
            'id'           => "{$fieldname}_address",
            'placeholder'  => get_string('address', 'datalynxfield_location'),
            'class'        => 'form-control datalynx-location-address-input shadow-sm',
            'autocomplete' => 'off',
            'style'        => 'width: 100%;',
        ];
        $mform->addElement('text', "{$fieldname}_address", null, $attraddress);
        $mform->setType("{$fieldname}_address", PARAM_TEXT);
        $mform->setDefault("{$fieldname}_address", $address);

        // 2. Hidden Latitude & Longitude — registered as proper QuickForm hidden elements.
        $mform->addElement('hidden', "{$fieldname}_lat", $lat, ['id' => "{$fieldname}_lat"]);
        $mform->setType("{$fieldname}_lat", PARAM_RAW);

        $mform->addElement('hidden', "{$fieldname}_lng", $lng, ['id' => "{$fieldname}_lng"]);
        $mform->setType("{$fieldname}_lng", PARAM_RAW);

        // 3. Visual UI markup — Leaflet CSS link, autocomplete suggestions, geolocate button, map container.
        //    Output as raw HTML so it doesn't create extra .fitem wrappers.
        $maphtml = html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        ]);
        $maphtml .= html_writer::start_div('datalynx-location-picker-wrapper w-100 mb-3', ['id' => $containerid]);

        // Autocomplete suggestions dropdown.
        $maphtml .= html_writer::div('', 'list-group location-suggestions shadow d-none', ['id' => "{$fieldname}_suggestions"]);

        // Geolocate button.
        $maphtml .= html_writer::start_div('d-flex justify-content-between align-items-center mb-2');
        $maphtml .= html_writer::tag('button',
            html_writer::tag('i', '', ['class' => 'fa fa-crosshairs me-1']) . get_string('use_current_location', 'datalynxfield_location'),
            [
                'type'  => 'button',
                'class' => 'btn btn-outline-primary btn-sm btn-geolocate shadow-sm',
                'id'    => "{$fieldname}_geolocate_btn",
            ]
        );
        $maphtml .= html_writer::end_div();

        // Leaflet map container.
        $maphtml .= html_writer::div('', 'location-map-container rounded border shadow-sm', [
            'id'    => $mapid,
            'style' => 'height: 320px; width: 100%;',
        ]);

        $maphtml .= html_writer::end_div(); // .datalynx-location-picker-wrapper

        $mform->addElement('html', $maphtml);

        if (!empty($options['required'])) {
            $mform->addRule("{$fieldname}_address", null, 'required', null, 'client');
        }

        // Initialize Javascript AMD module.
        $jsoptions = [
            'fieldid'     => $fieldid,
            'entryid'     => $entryid,
            'provider'    => $provider,
            'apiurl'      => $apiurl,
            'apikey'      => $apikey,
            'zoom'        => $zoom,
            'countries'   => $countries,
            'initialLat'  => $lat ? (float) $lat : 52.52,
            'initialLng'  => $lng ? (float) $lng : 13.405,
            'initialAddr' => $address,
        ];

        $PAGE->requires->js_call_amd('mod_datalynx/location', 'initPicker', [$jsoptions]);
    }

    /**
     * Render the field in display mode.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string HTML
     */
    public function render_display_mode(stdClass $entry, array $options): string {

        $field = $this->field;
        $fieldid = $field->id();

        $address = $entry->{"c{$fieldid}_content"}  ?? '';
        $lat     = $entry->{"c{$fieldid}_content1"} ?? '';
        $lng     = $entry->{"c{$fieldid}_content2"} ?? '';

        if (empty($address)) {
            return html_writer::tag('span', get_string('no_location_selected', 'datalynxfield_location'), ['class' => 'text-muted fst-italic']);
        }

        $displaymode = $field->get('param6') ?: 'map_mini';

        if ($displaymode === 'address_only') {
            return html_writer::span(s($address), 'location-address-text');
        }

        if ($displaymode === 'route_link') {
            $routeurl = "https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=;{$lat}%2C{$lng}";
            if (!empty($lat) && !empty($lng)) {
                return html_writer::link($routeurl, s($address), ['target' => '_blank', 'class' => 'location-route-link']);
            }
            return html_writer::span(s($address), 'location-address-text');
        }

        // Default displaymode: map_mini.
        // Note: enqueue_dependencies() is NOT called here — Leaflet JS is loaded
        // dynamically by the AMD module. Only CSS is injected via inline <link>.

        $minimapid = "minimap_{$fieldid}_{$entry->id}";
        $html = html_writer::empty_tag('link', [
            'rel' => 'stylesheet',
            'href' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        ]);
        $html .= html_writer::start_div('datalynx-location-display');
        $html .= html_writer::div(
            html_writer::tag('i', '', ['class' => 'fa fa-map-marker-alt text-danger me-1']) . html_writer::span(s($address), 'fw-bold'),
            'mb-1'
        );

        if (!empty($lat) && !empty($lng)) {
            // Embed map config as data attributes so the JS can read them.
            $html .= html_writer::div('', 'location-minimap-container rounded border', [
                'id'              => $minimapid,
                'data-lat'        => (float) $lat,
                'data-lng'        => (float) $lng,
                'data-address'    => $address,
                'data-zoom'       => (int) ($field->get('param4') ?: 13),
                'style'           => 'height: 180px; width: 100%; max-width: 400px;',
            ]);

            // Use an inline script to trigger AMD initialization immediately after
            // the container element is in the DOM. $PAGE->requires->js_call_amd()
            // can have timing issues in display mode views.
            $jsoptions = json_encode([
                'containerId' => $minimapid,
                'lat'         => (float) $lat,
                'lng'         => (float) $lng,
                'address'     => $address,
                'zoom'        => (int) ($field->get('param4') ?: 13),
            ], JSON_UNESCAPED_UNICODE);
            $html .= html_writer::script(
                "require(['mod_datalynx/location'], function(loc) { loc.initMiniMap({$jsoptions}); });"
            );
        }

        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Render the field in search/filter mode.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string|array $value
     * @return array [$elements, $separators]
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, $value = '') {
        global $PAGE;

        $this->enqueue_dependencies();

        $field = $this->field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";

        $address = is_array($value) ? ($value['address'] ?? '') : (string) $value;
        $lat     = is_array($value) ? ($value['lat'] ?? '') : '';
        $lng     = is_array($value) ? ($value['lng'] ?? '') : '';
        $radius  = is_array($value) ? ($value['radius'] ?? 5) : 5;

        $elements = [];

        // Address input.
        $attraddress = [
            'placeholder'  => get_string('address', 'datalynxfield_location'),
            'class'        => 'form-control form-control-sm datalynx-search-address',
            'id'           => "{$fieldname}_address",
            'autocomplete' => 'off',
        ];
        $elements[] = &$mform->createElement('text', "{$fieldname}_address", null, $attraddress);
        $mform->setType("{$fieldname}_address", PARAM_TEXT);
        $mform->setDefault("{$fieldname}_address", $address);

        // Lat/Lng hidden fields.
        $elements[] = &$mform->createElement('hidden', "{$fieldname}_lat", $lat, ['id' => "{$fieldname}_lat"]);
        $mform->setType("{$fieldname}_lat", PARAM_RAW);

        $elements[] = &$mform->createElement('hidden', "{$fieldname}_lng", $lng, ['id' => "{$fieldname}_lng"]);
        $mform->setType("{$fieldname}_lng", PARAM_RAW);

        // Radius dropdown.
        $radii = [
            '1'  => '1 km',
            '2'  => '2 km',
            '5'  => '5 km',
            '10' => '10 km',
            '20' => '20 km',
            '50' => '50 km',
        ];
        $elements[] = &$mform->createElement('select', "{$fieldname}_radius", get_string('search_radius_label', 'datalynxfield_location'), $radii, ['class' => 'form-select form-select-sm']);
        $mform->setType("{$fieldname}_radius", PARAM_INT);
        $mform->setDefault("{$fieldname}_radius", (int) $radius);

        $separators = [' ', ' '];

        // Initialize search JS autocomplete.
        $jsoptions = [
            'fieldid'   => $fieldid,
            'index'     => $i,
            'fieldname' => $fieldname,
            'apiurl'    => $field->get('param2') ?: 'https://nominatim.openstreetmap.org',
            'countries' => $field->get('param7') ?: 'de,at,ch',
        ];
        $PAGE->requires->js_call_amd('mod_datalynx/location', 'initSearch', [$jsoptions]);

        return [$elements, $separators];
    }
}
