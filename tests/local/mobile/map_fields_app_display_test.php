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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;
use stdClass;

/**
 * Itinerary and location fields in the Moodle App: no map, a link to the maps app, and only the
 * coordinates the viewer may see (#276).
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_itinerary\renderer
 * @covers     \datalynxfield_location\renderer
 * @covers     \mod_datalynx\local\map\map_links
 * @covers     \mod_datalynx\local\mobile\entries_payload_builder
 */
final class map_fields_app_display_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var stdClass The author of the ride. */
    private stdClass $author;

    /** @var stdClass Another student. */
    private stdClass $viewer;

    /** @var int */
    private int $viewid;

    /** @var int */
    private int $routeid;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $dlx] = $this->create_mobile_instance();
        // The itinerary coarsens positions for everyone but the author (the default precision).
        $this->routeid = $this->add_mobile_field($dlx, 'itinerary', 'Route', ['param3' => 'both']);
        $placeid = $this->add_mobile_field($dlx, 'location', 'Ort', ['param6' => 'map_mini']);
        $this->viewid = $this->add_mobile_view($dlx, 'grid', 'Rides', '<div class="ride">[[Route]] [[Ort]]</div>');
        $this->dlx = $this->set_mobile_default_view($dlx, $this->viewid);
        $this->author = $this->create_mobile_student($course);
        $this->viewer = $this->create_mobile_student($course);

        $entryid = $this->add_mobile_entry($this->dlx, (int) $this->author->id, [
            $this->routeid => json_encode([
                ['address' => 'Wien Hauptbahnhof', 'lat' => 48.185123, 'lng' => 16.377456],
                ['address' => 'Wiener Neustadt', 'lat' => 47.811987, 'lng' => 16.234321],
                ['address' => 'HLFS', 'lat' => 47.071234, 'lng' => 15.437891],
            ]),
            $placeid => 'Bruno-Marek-Allee 5, 1020 Wien',
        ]);
        $route = json_encode(['distance' => 196000, 'duration' => 7500, 'polyline' => 'abc']);
        $DB->set_field('datalynx_contents', 'content4', $route, ['entryid' => $entryid, 'fieldid' => $this->routeid]);
        $DB->set_field('datalynx_contents', 'content1', '48.228712', ['entryid' => $entryid, 'fieldid' => $placeid]);
        $DB->set_field('datalynx_contents', 'content2', '16.391983', ['entryid' => $entryid, 'fieldid' => $placeid]);
    }

    /**
     * Another student sees the stops as entered, rounded coordinates in the link only, and no map.
     */
    public function test_non_author_gets_rounded_link_and_no_map(): void {
        $this->setUser($this->viewer);
        $entry = $this->entry_payload();
        // Links in the HTML have their & escaped as &amp;.
        $json = str_replace('&amp;', '&', json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        // The addresses stay as entered; the destination is a public building.
        $this->assertStringContainsString('Wien Hauptbahnhof', $json);
        $this->assertStringContainsString('HLFS', $json);
        // Start and destination, rounded, in the OpenStreetMap link.
        $this->assertStringContainsString(
            'openstreetmap.org/directions?engine=fossgis_osrm_car&route=48.19%2C16.38%3B47.07%2C15.44',
            $json
        );
        // No exact coordinate, no map container, no script anywhere in the payload.
        foreach (['48.185123', '16.377456', '47.811987', '47.071234', '15.437891', 'abc"'] as $exact) {
            $this->assertStringNotContainsString($exact, $json, $exact);
        }
        foreach (['<script', 'data-waypoints', 'data-polyline', 'datalynx-itinerary-map', 'datalynx-location-map'] as $web) {
            $this->assertStringNotContainsString($web, $json, $web);
        }

        $route = $this->field_value($entry, $this->routeid);
        $this->assertSame('Wien Hauptbahnhof → Wiener Neustadt → HLFS (196 km, 2 h 5 min)', $route['valuetext']);
        $this->assertStringContainsString(get_string('openinmaps', 'datalynxfield_itinerary'), $route['valuehtml']);
    }

    /**
     * The author's own ride links the exact positions.
     */
    public function test_author_gets_exact_link(): void {
        $this->setUser($this->author);
        $json = json_encode($this->entry_payload(), JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('route=48.185123%2C16.377456%3B47.071234%2C15.437891', $json);
    }

    /**
     * The location field has no precision setting: its address and a directions link to it.
     */
    public function test_location_links_directions(): void {
        $this->setUser($this->viewer);
        $json = json_encode($this->entry_payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Bruno-Marek-Allee 5, 1020 Wien', $json);
        $this->assertStringContainsString('route=%3B48.228712%2C16.391983', $json);
        $this->assertStringContainsString(get_string('openinmaps', 'datalynxfield_location'), $json);
    }

    /**
     * A site that routes with Google links to Google Maps, with the stops in between as waypoints.
     */
    public function test_google_links_carry_waypoints(): void {
        set_config('router', 'google', 'mod_datalynx');
        $this->setUser($this->viewer);
        $json = json_encode($this->entry_payload(), JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString(
            'https://www.google.com/maps/dir/?api=1&origin=48.19%2C16.38&destination=47.07%2C15.44'
                . '&travelmode=driving&waypoints=47.81%2C16.23',
            str_replace('&amp;', '&', $json)
        );
    }

    /**
     * The web display keeps its map.
     */
    public function test_web_display_is_unchanged(): void {
        $this->setUser($this->viewer);
        $dlx = new datalynx($this->dlx->id());
        $field = $dlx->get_field_from_id($this->routeid);
        $entry = $this->load_entry();

        $html = $field->renderer()->render_display_mode($entry, []);

        $this->assertStringContainsString('data-region="datalynx-itinerary-map"', $html);
        $this->assertStringNotContainsString(get_string('openinmaps', 'datalynxfield_itinerary'), $html);
    }

    /**
     * The payload of the only entry of the view, as the current user gets it.
     *
     * @return array
     */
    private function entry_payload(): array {
        $payload = (new entries_payload_builder(new datalynx($this->dlx->id())))->build(['viewid' => $this->viewid]);
        $this->assertCount(1, $payload['entries']);

        return $payload['entries'][0];
    }

    /**
     * One field of an entry payload.
     *
     * @param array $entry
     * @param int $fieldid
     * @return array
     */
    private function field_value(array $entry, int $fieldid): array {
        foreach ($entry['fields'] as $value) {
            if ($value['fieldid'] === $fieldid) {
                return $value;
            }
        }
        $this->fail("No value for field $fieldid");
    }

    /**
     * The entry with its contents, as views load it.
     *
     * @return stdClass
     */
    private function load_entry(): stdClass {
        global $DB;
        $entry = $DB->get_record('datalynx_entries', ['dataid' => $this->dlx->id()], '*', MUST_EXIST);
        foreach ($DB->get_records('datalynx_contents', ['entryid' => $entry->id]) as $content) {
            foreach (['content', 'content1', 'content2', 'content3', 'content4'] as $column) {
                $entry->{"c{$content->fieldid}_{$column}"} = $content->$column;
            }
        }

        return $entry;
    }
}
