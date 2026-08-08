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
use mod_datalynx\local\map\geocoder_factory;
use mod_datalynx\local\map\provider_config;
use MoodleQuickForm;
use stdClass;

/**
 * Location field renderer class.
 *
 * All map markup is produced by Mustache templates and configured through data
 * attributes; mod_datalynx/location then initialises the widgets. Nothing relies
 * on inline script tags, because view types such as Grid fetch their entries
 * through a web service where neither $PAGE->requires nor inline scripts survive.
 *
 * Which map services are used is a site setting, not a field setting: see
 * {@see provider_config}. No provider URL or API key is ever sent to the browser.
 *
 * @package    datalynxfield_location
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends datalynxfield_renderer {
    // This is a datalynx field renderer, not a core_renderer: $this->page and
    // $this->output do not exist here.
    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Request the client side initialiser for the current page.
     *
     * Harmless when the field is rendered inside a web service call: the page
     * requirements of that request are simply discarded, and the browse regions
     * are initialised by mod_datalynx/viewbrowser instead.
     */
    protected function require_js(): void {
        global $PAGE;

        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $PAGE->requires->js_call_amd('mod_datalynx/location', 'init');
    }

    /**
     * Zoom level configured for this field, falling back to the site default.
     *
     * @return int
     */
    protected function get_zoom(): int {
        $zoom = (int) ($this->field->get('param4') ?? 0);

        return $zoom > 0 ? $zoom : provider_config::default_view()['zoom'];
    }

    /**
     * Basemap settings the browser needs, as template context.
     *
     * @return array
     */
    protected function get_tile_context(): array {
        return provider_config::tile_config();
    }

    /**
     * Address lookup capabilities of the configured service, as template context.
     *
     * @return array
     */
    protected function get_geocoder_context(): array {
        $geocoder = geocoder_factory::instance();

        return [
            'hasgeocoder' => $geocoder !== null,
            'typeahead' => $geocoder !== null && $geocoder->supports_typeahead(),
            'minlength' => $geocoder !== null ? $geocoder->minimum_query_length() : 3,
        ];
    }

    /**
     * Read the three stored content parts of this field from an entry.
     *
     * @param stdClass $entry
     * @return array [address, latitude, longitude] as strings.
     */
    protected function get_content(stdClass $entry): array {
        $fieldid = $this->field->id();

        return [
            (string) ($entry->{"c{$fieldid}_content"} ?? ''),
            (string) ($entry->{"c{$fieldid}_content1"} ?? ''),
            (string) ($entry->{"c{$fieldid}_content2"} ?? ''),
        ];
    }

    /**
     * Render the field in entry edit mode.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        global $OUTPUT;

        $this->require_js();

        $fieldid = $this->field->id();
        $fieldname = "field_{$fieldid}_{$entry->id}";
        [$address, $lat, $lng] = $this->get_content($entry);

        $addressid = "{$fieldname}_address";
        $latid = "{$fieldname}_lat";
        $lngid = "{$fieldname}_lng";

        // The address text plus the two coordinates are real form elements so
        // that the entry form collects and validates them as usual.
        $mform->addElement('text', "{$fieldname}_address", null, [
            'id' => $addressid,
            'placeholder' => get_string('address', 'datalynxfield_location'),
            'autocomplete' => 'off',
        ]);
        $mform->setType("{$fieldname}_address", PARAM_TEXT);
        $mform->setDefault("{$fieldname}_address", $address);

        $mform->addElement('hidden', "{$fieldname}_lat", $lat, ['id' => $latid]);
        $mform->setType("{$fieldname}_lat", PARAM_RAW);

        $mform->addElement('hidden', "{$fieldname}_lng", $lng, ['id' => $lngid]);
        $mform->setType("{$fieldname}_lng", PARAM_RAW);

        if (!empty($options['required'])) {
            $mform->addRule("{$fieldname}_address", null, 'required', null, 'client');
        }

        $defaultview = provider_config::default_view();

        // The map chrome is plain markup: adding it as a form element would wrap
        // it in another .fitem grid row.
        $context = array_merge($this->get_tile_context(), $this->get_geocoder_context(), [
            'fieldid' => $fieldid,
            'addressid' => $addressid,
            'latid' => $latid,
            'lngid' => $lngid,
            'zoom' => $this->get_zoom(),
            'lat' => $lat,
            'lng' => $lng,
            'defaultlat' => $defaultview['lat'] ?? '',
            'defaultlng' => $defaultview['lng'] ?? '',
            'defaultzoom' => $defaultview['zoom'],
        ]);

        $mform->addElement(
            'html',
            $OUTPUT->render_from_template('mod_datalynx/field_location_picker', $context)
        );
    }

    /**
     * Render the field in display mode.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string HTML
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        global $OUTPUT;

        [$address, $lat, $lng] = $this->get_content($entry);

        if ($address === '') {
            return html_writer::span(
                get_string('no_location_selected', 'datalynxfield_location'),
                'datalynx-location datalynx-location--empty text-muted font-italic'
            );
        }

        $this->require_js();

        $hascoords = is_numeric($lat) && is_numeric($lng);
        $displaymode = $this->field->get('param6') ?: 'map_mini';

        $context = array_merge($this->get_tile_context(), [
            'address' => $address,
            'hasmap' => $hascoords && $displaymode === 'map_mini',
            'hasroute' => $hascoords && $displaymode === 'route_link',
            'routeurl' => $hascoords ? $this->get_route_url((float) $lat, (float) $lng) : '',
            'lat' => $lat,
            'lng' => $lng,
            'zoom' => $this->get_zoom(),
        ]);

        return $OUTPUT->render_from_template('mod_datalynx/field_location_display', $context);
    }

    /**
     * Build a routing link to a coordinate pair on openstreetmap.org.
     *
     * The empty part before the semicolon is the route origin, which the routing
     * engine fills in from the browser location.
     *
     * @param float $lat
     * @param float $lng
     * @return string
     */
    protected function get_route_url(float $lat, float $lng): string {
        $destination = rawurlencode(sprintf('%F,%F', $lat, $lng));

        return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car'
            . '&route=' . rawurlencode(';') . $destination;
    }

    /**
     * Normalise a stored filter value into the address/lat/lng/radius parts.
     *
     * A saved location filter is an array, but the filter and behavior forms JSON
     * encode array values before they reach the renderer, and a plain string may
     * arrive from a quick search.
     *
     * @param string|array|null $value
     * @return array
     */
    protected function decode_search_value($value): array {
        if (is_array($value)) {
            return $value;
        }

        $value = (string) ($value ?? '');
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : ['address' => $value];
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
        global $OUTPUT;

        $this->require_js();

        $fieldid = $this->field->id();
        $fieldname = "f_{$i}_{$fieldid}";

        $search = $this->decode_search_value($value);
        $address = (string) ($search['address'] ?? '');
        $lat = (string) ($search['lat'] ?? '');
        $lng = (string) ($search['lng'] ?? '');
        $radius = !empty($search['radius'])
            ? (int) $search['radius']
            : (int) ($this->field->get('param5') ?: 5);

        $addressid = "{$fieldname}_address";
        $latid = "{$fieldname}_lat";
        $lngid = "{$fieldname}_lng";

        $elements = [];

        $elements[] = $mform->createElement('text', "{$fieldname}_address", null, [
            'id' => $addressid,
            'placeholder' => get_string('address', 'datalynxfield_location'),
            'autocomplete' => 'off',
            'size' => 32,
        ]);
        $mform->setType("{$fieldname}_address", PARAM_TEXT);
        $mform->setDefault("{$fieldname}_address", $address);

        $elements[] = $mform->createElement('hidden', "{$fieldname}_lat", $lat, ['id' => $latid]);
        $mform->setType("{$fieldname}_lat", PARAM_RAW);

        $elements[] = $mform->createElement('hidden', "{$fieldname}_lng", $lng, ['id' => $lngid]);
        $mform->setType("{$fieldname}_lng", PARAM_RAW);

        $radii = [];
        foreach ([1, 2, 5, 10, 20, 50, 100] as $km) {
            $radii[$km] = get_string('radiuskm', 'datalynxfield_location', $km);
        }
        $elements[] = $mform->createElement(
            'select',
            "{$fieldname}_radius",
            get_string('search_radius_label', 'datalynxfield_location'),
            $radii
        );
        $mform->setType("{$fieldname}_radius", PARAM_INT);
        $mform->setDefault("{$fieldname}_radius", $radius);

        $context = array_merge($this->get_geocoder_context(), [
            'fieldid' => $fieldid,
            'addressid' => $addressid,
            'latid' => $latid,
            'lngid' => $lngid,
        ]);

        $elements[] = $mform->createElement(
            'static',
            "{$fieldname}_config",
            '',
            $OUTPUT->render_from_template('mod_datalynx/field_location_search', $context)
        );

        return [$elements, [' ', ' ', ' ']];
    }
}
