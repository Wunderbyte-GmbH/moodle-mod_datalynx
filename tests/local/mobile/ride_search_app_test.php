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
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/stub_geocoder.php');

use advanced_testcase;
use datalynxfield_itinerary\field as itinerary_field;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\local\filter\search_criteria;
use mod_datalynx\local\map\geocoder_factory;
use mod_datalynx\mobile_test_trait;
use mod_datalynx\stub_geocoder;
use mod_datalynx\output\mobile;
use stdClass;

/**
 * "Fahrt suchen" in the Moodle App: the route and the date of a custom filter, searched from the
 * app's inputs exactly as the web searches its own elements (#277).
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_itinerary\field::customfilter_value
 * @covers     \datalynxfield_itinerary\field::clean_composite_search
 * @covers     \datalynxfield_schedule\field::customfilter_value
 * @covers     \datalynxfield_schedule\field::clean_composite_search
 * @covers     \mod_datalynx\local\filter\customfilter_controls
 * @covers     \mod_datalynx\local\mobile\entries_payload_builder
 */
final class ride_search_app_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var array Places the stub geocoder knows: lower case query => [label, lat, lng]. */
    const PLACES = [
        'wien' => ['Hauptbahnhof, 1100 Wien', 48.1853, 16.3779],
        'wiener neustadt' => ['Hauptplatz, 2700 Wiener Neustadt', 47.8149, 16.2372],
        'mödling' => ['Bahnhof, 2340 Mödling', 48.0856, 16.2875],
        'graz' => ['Hauptplatz, 8010 Graz', 47.0710, 15.4385],
        'klagenfurt' => ['Neuer Platz, 9020 Klagenfurt', 46.6240, 14.3077],
    ];

    /** @var datalynx */
    private datalynx $dlx;

    /** @var stdClass */
    private stdClass $course;

    /** @var int[] field ids by name */
    private array $ids = [];

    /** @var int */
    private int $viewid;

    /** @var int */
    private int $customfilterid;

    /** @var int[] entry ids by ride name */
    private array $rides = [];

    #[\Override]
    protected function setUp(): void {
        global $DB, $USER;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        geocoder_factory::set_geocoder_for_testing(new stub_geocoder(self::PLACES));
        itinerary_field::reset_resolved_addresses();

        [$this->course, $dlx] = $this->create_mobile_instance();
        $this->ids['Fahrttyp'] = $this->add_mobile_field($dlx, 'select', 'Fahrttyp', ['param1' => "Angebot\nGesuch"]);
        $this->ids['Route'] = $this->add_mobile_field($dlx, 'itinerary', 'Route', ['param5' => '5']);
        $this->ids['Termin'] = $this->add_mobile_field($dlx, 'schedule', 'Termin');
        $offers = $this->add_mobile_filter($dlx, 'Nur Angebote', [
            'customsearch' => serialize([$this->ids['Fahrttyp'] => ['AND' => [['', 'ANY_OF', ['1']]]]]),
        ]);
        // Like the ridesharing board: the offers filter until the visitor searches, all filters allowed.
        $this->viewid = $this->add_mobile_view($dlx, 'grid', 'Fahrt suchen', '[[Route]]', [
            'section' => '##customfilter:Fahrt suchen## ##entries##', 'filter' => $offers, 'param5' => 1,
        ]);
        $this->dlx = $this->set_mobile_default_view($dlx, $this->viewid);

        $listed = fn(string $name) => ['name' => $name, 'sortable' => 0];
        $this->customfilterid = (int) $DB->insert_record('datalynx_customfilters', (object) [
            'dataid' => $dlx->id(), 'name' => 'Fahrt suchen', 'description' => '', 'visible' => 1,
            'fulltextsearch' => 0, 'authorsearch' => 0, 'approve' => 0, 'status' => 0,
            'timecreated' => 0, 'timecreatedsortable' => 0, 'timemodified' => 0, 'timemodifiedsortable' => 0,
            'fieldlist' => json_encode([
                $this->ids['Fahrttyp'] => $listed('Fahrttyp'),
                $this->ids['Route'] => $listed('Route'),
                $this->ids['Termin'] => $listed('Termin'),
            ]),
        ]);
        \mod_datalynx\local\definition_cache::invalidate($dlx->id());

        // Monday commute Wien - Wiener Neustadt - Graz, the seeded ride of the acceptance test.
        $weekly = fn(int $day, int $minute): array => ['mode' => 'weekly', 'days' => [$day], 'start' => $minute];
        $this->rides['commute'] = $this->ride(1, ['wien', 'wiener neustadt', 'graz'], $weekly(1, 420));
        $this->rides['south'] = $this->ride(1, ['graz', 'klagenfurt'], $weekly(1, 480));
        $this->rides['back'] = $this->ride(1, ['wiener neustadt', 'mödling', 'wien'], $weekly(2, 1020));
        // A request on the same route: the offers filter hides it until the visitor searches.
        $this->rides['request'] = $this->ride(2, ['wiener neustadt', 'graz'], $weekly(1, 420));
        $this->dlx = new datalynx($dlx->id());
        $this->setUser($USER);
    }

    #[\Override]
    protected function tearDown(): void {
        geocoder_factory::set_geocoder_for_testing(null);
        itinerary_field::reset_resolved_addresses();
        parent::tearDown();
    }

    /**
     * Wiener Neustadt to Graz from the app finds the commute through both places, and the same
     * rides as the web's search with the coordinates its address picker fills in.
     */
    public function test_route_from_addresses_matches_the_web_search(): void {
        // The request is found too: a search replaces the view's offers filter, as on the web.
        $state = $this->page($this->search(['_from' => 'Wiener Neustadt', '_to' => 'Graz', '_radius' => '10']));
        $this->assertSame(['commute', 'request'], $this->names($state));

        [, $wnlat, $wnlng] = self::PLACES['wiener neustadt'];
        [, $grazlat, $grazlng] = self::PLACES['graz'];
        $web = $this->payload_with_criteria([$this->ids['Route'] => ['AND' => [['', '', [
            'fromlat' => $wnlat, 'fromlng' => $wnlng, 'tolat' => $grazlat, 'tolng' => $grazlng,
            'fromaddress' => 'Wiener Neustadt', 'toaddress' => 'Graz', 'radius' => 10,
        ]]]]]);
        $this->assertSame($this->names($web), $this->names($state));
    }

    /**
     * The route control shows what each address was matched to, and an address that is not found
     * gives a note instead of an error; with no end found there is no route search.
     */
    public function test_route_control_shows_matches_and_notes(): void {
        $state = $this->page($this->search(['_from' => 'Wiener Neustadt', '_to' => 'Atlantis']));
        $route = $this->control($state, $this->ids['Route']);

        $this->assertSame('route', $route['control']);
        $matched = get_string('searchmatched', 'datalynxfield_itinerary', 'Hauptplatz, 2700 Wiener Neustadt');
        $this->assertSame($matched, $route['fromplace']);
        $this->assertSame('', $route['toplace']);
        $this->assertSame([get_string('searchnotfound', 'datalynxfield_itinerary', 'Atlantis')], $route['notes']);
        // The end that was found still searches: rides leaving from Wiener Neustadt.
        $this->assertSame(['commute', 'back', 'request'], $this->names($state));

        $state = $this->page($this->search(['_from' => 'Atlantis']));
        $this->assertSame(['commute', 'south', 'back'], $this->names($state), 'no search: the offers filter');
    }

    /**
     * A date on a Monday finds the Monday rides, like the web search with the same day.
     */
    public function test_schedule_from_inputs_matches_the_web_search(): void {
        $monday = '2026-11-30';
        $state = $this->page($this->search(['_date' => $monday], 'Termin'));
        $this->assertSame(['commute', 'south', 'request'], $this->names($state));

        $day = \mod_datalynx\local\date_input::to_day($monday);
        $web = $this->payload_with_criteria([$this->ids['Termin'] => ['AND' => [['', '', [
            'date' => $day, 'minute' => '', 'tolerance' => 1440,
        ]]]]]);
        $this->assertSame($this->names($web), $this->names($state));

        $control = $this->control($state, $this->ids['Termin']);
        $this->assertSame('schedule', $control['control']);
        $this->assertSame($monday, $control['date']);
    }

    /**
     * Route and date together: Wiener Neustadt to Graz on a Tuesday finds nothing, on a Monday
     * the commute and the request.
     */
    public function test_route_and_date_together(): void {
        $route = ['_from' => 'Wiener Neustadt', '_to' => 'Graz'];
        $tuesday = $this->search($route) + $this->search(['_date' => '2026-12-01'], 'Termin');
        $this->assertSame([], $this->names($this->page($tuesday)));

        $monday = $this->search($route) + $this->search(['_date' => '2026-11-30'], 'Termin');
        $this->assertSame(['commute', 'request'], $this->names($this->page($monday)));
    }

    /**
     * Without a search the view filter applies, after a search it does not, as on the web; an
     * empty form ("clear") shows the view filter's list again.
     */
    public function test_view_filter_until_a_search(): void {
        $base = ['cmid' => $this->dlx->cm->id, 'courseid' => $this->course->id, 'viewid' => $this->viewid];
        $this->assertSame(['commute', 'south', 'back'], $this->names($this->page($base)));

        $searched = $this->page($this->search(['_from' => 'Wiener Neustadt', '_to' => 'Graz']));
        $this->assertContains('request', $this->names($searched));

        $cleared = $this->page($base + ['customfilterid' => $this->customfilterid, 'cfapply' => 1]);
        $this->assertSame(['commute', 'south', 'back'], $this->names($cleared));
    }

    /**
     * Route and schedule values from outside are checked: coordinates, radius, address length,
     * day, time and tolerance.
     */
    public function test_invalid_values_are_rejected(): void {
        $route = fn(array $value) => [$this->ids['Route'] => ['AND' => [['', '', $value]]]];
        $schedule = fn(array $value) => [$this->ids['Termin'] => ['AND' => [['', '', $value]]]];
        $invalid = [
            'latitude' => $route(['fromlat' => 91, 'fromlng' => 16]),
            'longitude' => $route(['tolat' => 47, 'tolng' => 'east']),
            'radius zero' => $route(['fromlat' => 47, 'fromlng' => 16, 'radius' => 0]),
            'radius large' => $route(['fromlat' => 47, 'fromlng' => 16, 'radius' => 500]),
            'address' => $route(['fromlat' => 47, 'fromlng' => 16, 'fromaddress' => str_repeat('a', 256)]),
            'minute' => $schedule(['date' => 0, 'minute' => 1440]),
            'tolerance' => $schedule(['date' => 1795000000, 'tolerance' => 0]),
            'date' => $schedule(['date' => -5]),
        ];
        foreach ($invalid as $case => $criteria) {
            try {
                search_criteria::decode_search(json_encode($criteria), $this->dlx);
                $this->fail("$case: not rejected");
            } catch (invalid_parameter_exception $e) {
                $this->assertNotEmpty($e->getMessage(), $case);
            }
        }

        $valid = $route(['fromlat' => '47.8', 'fromlng' => '16.2', 'tolat' => '', 'tolng' => '', 'radius' => '10']);
        $this->assertNotEmpty(search_criteria::decode_search(json_encode($valid), $this->dlx));
    }

    /**
     * Without a geocoder the app cannot search by address: the route is listed as web only.
     */
    public function test_without_geocoder_the_route_is_web_only(): void {
        geocoder_factory::set_geocoder_for_testing(null);
        set_config('geocoder', 'none', 'mod_datalynx');
        $state = $this->page($this->search([]));

        $this->assertSame('unsupported', $this->control($state, $this->ids['Route'])['control']);
        $this->assertContains('Route', $state['customfilter']['unsupported']);
        $this->assertSame('schedule', $this->control($state, $this->ids['Termin'])['control']);
    }

    /**
     * The arguments of an applied search, with inputs of one field.
     *
     * @param array $inputs input suffix => value
     * @param string $field the field the inputs belong to
     * @return array
     */
    private function search(array $inputs, string $field = 'Route'): array {
        $args = ['cmid' => $this->dlx->cm->id, 'courseid' => $this->course->id, 'viewid' => $this->viewid,
            'customfilterid' => $this->customfilterid, 'cfapply' => 1];
        foreach ($inputs as $suffix => $value) {
            $args['cf_' . $this->ids[$field] . $suffix] = $value;
        }

        return $args;
    }

    /**
     * Open the browse page as the app does; arguments are strings.
     *
     * @param array $args
     * @return array the page state
     */
    private function page(array $args): array {
        $result = mobile::mobile_course_view(array_map('strval', $args));

        return json_decode($result['otherdata']['state'], true);
    }

    /**
     * The entries the web finds for criteria: its custom filter form makes a fresh filter holding
     * only the search (datalynx_filter_manager::set_user_filter()), not the view's filter.
     *
     * @param array $criteria
     * @return array ['entries' => [['id' => …], …]] like a page state
     */
    private function payload_with_criteria(array $criteria): array {
        $filter = new datalynx_filter((object) ['dataid' => $this->dlx->id(), 'id' => 0, 'customsearch' => $criteria]);
        $entries = new datalynx_entries(new datalynx($this->dlx->id()), $filter);
        $entries->set_content();

        return ['entries' => array_map(fn($id) => ['id' => (int) $id], array_keys($entries->entries() ?: []))];
    }

    /**
     * The ride names of a page state or payload, in the order listed.
     *
     * @param array $state
     * @return string[]
     */
    private function names(array $state): array {
        $names = array_flip($this->rides);
        $listed = array_map(fn($entry) => $names[$entry['id']], $state['entries']);
        $order = array_flip(array_keys($this->rides));
        usort($listed, fn($a, $b) => $order[$a] <=> $order[$b]);

        return $listed;
    }

    /**
     * One control of the custom filter in a page state.
     *
     * @param array $state
     * @param int $fieldid
     * @return array
     */
    private function control(array $state, int $fieldid): array {
        foreach ($state['customfilter']['controls'] as $control) {
            if ($control['key'] === (string) $fieldid) {
                return $control;
            }
        }
        $this->fail("No control for field $fieldid");
    }

    /**
     * Add a ride: its type, stops and schedule, saved through the fields.
     *
     * @param int $type 1 offer, 2 request
     * @param string[] $stops keys of PLACES
     * @param array $schedule the schedule as stored
     * @return int entry id
     */
    private function ride(int $type, array $stops, array $schedule): int {
        global $DB, $USER;
        $entryid = $this->add_mobile_entry($this->dlx, (int) $USER->id, [$this->ids['Fahrttyp'] => (string) $type]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $dlx = new datalynx($this->dlx->id());
        $waypoints = array_map(fn(string $stop) => [
            'address' => self::PLACES[$stop][0], 'lat' => self::PLACES[$stop][1], 'lng' => self::PLACES[$stop][2],
        ], $stops);
        $dlx->get_field_from_id($this->ids['Route'])->update_content($entry, ['waypoints' => json_encode($waypoints)]);
        $dlx->get_field_from_id($this->ids['Termin'])->update_content($entry, ['schedule' => json_encode($schedule)]);

        return $entryid;
    }
}
