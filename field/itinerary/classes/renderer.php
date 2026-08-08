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

use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use mod_datalynx\local\map\geocoder_factory;
use mod_datalynx\local\map\provider_config;
use mod_datalynx\local\ride\itinerary;
use MoodleQuickForm;
use stdClass;

/**
 * Itinerary field renderer.
 *
 * Markup comes from Mustache templates configured entirely through data
 * attributes, and mod_datalynx/itinerary initialises the widgets. Nothing relies
 * on inline script tags, because view types such as Grid fetch their entries
 * through a web service where neither $PAGE->requires nor inline scripts survive.
 *
 * @package    datalynxfield_itinerary
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
     * A no-op inside a web service call, where the page requirements are
     * discarded; those regions are initialised by mod_datalynx/viewbrowser.
     */
    protected function require_js(): void {
        global $PAGE;

        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $PAGE->requires->js_call_amd('mod_datalynx/itinerary', 'init');
    }

    /**
     * Whether this viewer may see the exact stop coordinates.
     *
     * The entry author always may. Everyone else sees coarsened positions when
     * the field is configured that way.
     *
     * Note: confirmed passengers should also qualify. That check needs the seat
     * field, which arrives with the ride-offer work; wiring it in here is a
     * one-line change once a ride knows who its passengers are.
     *
     * @param stdClass $entry
     * @return bool
     */
    protected function viewer_sees_exact(stdClass $entry): bool {
        global $USER;

        if (!$this->field->coarsens_coordinates()) {
            return true;
        }

        if (!empty($entry->userid) && (int) $entry->userid === (int) $USER->id) {
            return true;
        }

        return has_capability('mod/datalynx:manageentries', $this->field->dlx()->context);
    }

    /**
     * Basemap settings plus the site default view, as template context.
     *
     * @return array
     */
    protected function get_map_context(): array {
        $defaultview = provider_config::default_view();

        return array_merge(provider_config::tile_config(), [
            'defaultlat' => $defaultview['lat'] ?? '',
            'defaultlng' => $defaultview['lng'] ?? '',
            'defaultzoom' => $defaultview['zoom'],
            'zoom' => (int) ($this->field->get('param4') ?: $defaultview['zoom']),
        ]);
    }

    /**
     * Address lookup capabilities of the configured service.
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
     * Render the field in entry edit mode.
     *
     * The whole waypoint array travels in one hidden input as JSON; the visible
     * rows are managed client side. Repeating a *value* is not something
     * MoodleQuickForm models, and a JSON payload keeps the server contract to a
     * single field that {@see field::format_content()} validates strictly.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        global $OUTPUT;

        $this->require_js();

        $fieldid = $this->field->id();
        $elementname = "field_{$fieldid}_{$entry->id}_waypoints";
        $inputid = "id_{$elementname}";
        $journey = $this->field->get_itinerary($entry);

        $mform->addElement('hidden', $elementname, $journey->to_json(), ['id' => $inputid]);
        $mform->setType($elementname, PARAM_RAW);

        $context = array_merge($this->get_map_context(), $this->get_geocoder_context(), [
            'fieldid' => $fieldid,
            'inputid' => $inputid,
            'maxwaypoints' => $this->field->get_max_waypoints(),
            'initialwaypoints' => max(2, (int) ($this->field->get('param2') ?: 2)),
            'requiretimes' => !empty($this->field->get('param8')),
            'required' => !empty($options['required']),
        ]);

        $mform->addElement(
            'html',
            $OUTPUT->render_from_template('mod_datalynx/field_itinerary_picker', $context)
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

        $journey = $this->field->get_itinerary($entry);
        if (!$journey->is_route()) {
            return html_writer::span(
                get_string('nowaypoints', 'datalynxfield_itinerary'),
                'datalynx-itinerary datalynx-itinerary--empty text-muted font-italic'
            );
        }

        $this->require_js();

        $exact = $this->viewer_sees_exact($entry);
        $displaymode = $this->field->get('param3') ?: 'both';
        $waypoints = $journey->export($exact);

        $context = array_merge($this->get_map_context(), [
            'fieldid' => $this->field->id(),
            'waypoints' => $waypoints,
            'waypointsjson' => json_encode($waypoints, JSON_UNESCAPED_UNICODE),
            'showmap' => $displaymode !== 'list',
            'showlist' => $displaymode !== 'map',
            'approximate' => !$exact,
            'lengthlabel' => get_string(
                'journeylength',
                'datalynxfield_itinerary',
                format_float($journey->length_km(), 1)
            ),
        ]);

        return $OUTPUT->render_from_template('mod_datalynx/field_itinerary_display', $context);
    }

    /**
     * Render the corridor search: where the traveller wants to go, and how far
     * from a route they are willing to reach a stop.
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
        $prefix = "f_{$i}_{$fieldid}";
        $search = $this->decode_search_value($value);
        $elements = [];

        foreach (['from', 'to'] as $end) {
            $addressname = "{$prefix}_{$end}address";
            $elements[] = $mform->createElement('text', $addressname, null, [
                'id' => $addressname,
                'placeholder' => get_string('search' . $end, 'datalynxfield_itinerary'),
                'autocomplete' => 'off',
                'size' => 26,
            ]);
            $mform->setType($addressname, PARAM_TEXT);
            $mform->setDefault($addressname, (string) ($search["{$end}address"] ?? ''));

            foreach (['lat', 'lng'] as $part) {
                $coordname = "{$prefix}_{$end}{$part}";
                $elements[] = $mform->createElement(
                    'hidden',
                    $coordname,
                    (string) ($search["{$end}{$part}"] ?? ''),
                    ['id' => $coordname]
                );
                $mform->setType($coordname, PARAM_RAW);
            }
        }

        $radii = [];
        foreach ([1, 2, 5, 10, 20, 50] as $km) {
            $radii[$km] = get_string('radiuskm', 'datalynxfield_location', $km);
        }
        $radiusname = "{$prefix}_radius";
        $elements[] = $mform->createElement(
            'select',
            $radiusname,
            get_string('searchradius', 'datalynxfield_itinerary'),
            $radii
        );
        $mform->setType($radiusname, PARAM_INT);
        $mform->setDefault($radiusname, (int) ($search['radius'] ?? $this->field->get_match_radius()));

        $context = array_merge($this->get_geocoder_context(), [
            'fieldid' => $fieldid,
            'prefix' => $prefix,
        ]);
        $elements[] = $mform->createElement(
            'static',
            "{$prefix}_config",
            '',
            $OUTPUT->render_from_template('mod_datalynx/field_itinerary_search', $context)
        );

        return [$elements, [' ', ' ', ' ', ' ', ' ', ' ']];
    }

    /**
     * Normalise a stored filter value into its parts.
     *
     * The filter and behavior forms JSON encode array values before they reach a
     * renderer, so both shapes have to be accepted.
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

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Validate the submitted itinerary.
     *
     * @param int $entryid
     * @param array $tags
     * @param stdClass $formdata
     * @return array
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->field->id();
        $elementname = "field_{$fieldid}_{$entryid}_waypoints";
        $errors = [];

        if (!isset($formdata->$elementname)) {
            return $errors;
        }

        $journey = itinerary::from_json($formdata->$elementname, $this->field->get_max_waypoints());

        // An empty value is only an error when the field is required, which the
        // behavior layer enforces separately; a single stop is always an error,
        // because it cannot describe a journey.
        if ($journey->count() === 1) {
            $errors[$elementname] = get_string('needtworoutepoints', 'datalynxfield_itinerary');
        } else if ($journey->is_route() && !empty($this->field->get('param8')) && !$journey->has_all_times()) {
            $errors[$elementname] = get_string('requiretimes', 'datalynxfield_itinerary');
        }

        return $errors;
    }
}
