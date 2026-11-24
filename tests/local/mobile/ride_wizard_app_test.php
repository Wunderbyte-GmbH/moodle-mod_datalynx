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
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/stub_router.php');

use advanced_testcase;
use core_external\external_api;
use datalynxfield_itinerary\field as itinerary_field;
use datalynxfield_schedule\field as schedule_field;
use mod_datalynx\datalynx;
use mod_datalynx\external\get_entry_edit_form;
use mod_datalynx\external\submit_entry;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\local\map\geocoder_factory;
use mod_datalynx\local\map\router_factory;
use mod_datalynx\mobile_test_trait;
use mod_datalynx\output\mobile;
use mod_datalynx\stub_geocoder;
use mod_datalynx\stub_router;
use stdClass;

/**
 * Posting a ride in the Moodle App: the offer wizard (a chain of views), the request page, and
 * the route and schedule inputs (#279).
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\output\mobile::mobile_edit_entry
 * @covers     \mod_datalynx\local\entry\entry_edit_form
 * @covers     \mod_datalynx\local\view\base::get_next_step
 * @covers     \mod_datalynx\external\submit_entry
 * @covers     \datalynxfield_itinerary\field::resolve_stops
 * @covers     \datalynxfield_itinerary\renderer::get_edit_control
 * @covers     \datalynxfield_schedule\renderer::get_edit_control
 * @covers     \datalynxfield_submit\renderer::get_button_label
 */
final class ride_wizard_app_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var array Places the stub geocoder knows: lower case query => [label, lat, lng]. */
    const PLACES = [
        'wien' => ['Hauptbahnhof, 1100 Wien', 48.1853, 16.3779],
        'wiener neustadt' => ['Hauptplatz, 2700 Wiener Neustadt', 47.8149, 16.2372],
        'graz' => ['Hauptplatz, 8010 Graz', 47.0710, 15.4385],
        'klagenfurt' => ['Neuer Platz, 9020 Klagenfurt', 46.6240, 14.3077],
    ];

    /** @var stdClass */
    private stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var stdClass */
    private stdClass $student;

    /** @var stub_geocoder */
    private stub_geocoder $geocoder;

    /** @var int[] field ids by name */
    private array $ids = [];

    /** @var int[] view ids by name */
    private array $views = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->geocoder = new stub_geocoder(self::PLACES);
        geocoder_factory::set_geocoder_for_testing($this->geocoder);
        router_factory::set_router_for_testing(new stub_router());
        itinerary_field::reset_resolved_addresses();
        \mod_datalynx\local\field\datalynxfield_behavior::reset_condition_cache();

        [$this->course, $dlx] = $this->create_mobile_instance();
        $this->ids['Fahrttyp'] = $this->add_mobile_field($dlx, 'radiobutton', 'Fahrttyp', ['param1' => "Angebot\nGesuch"]);
        $this->ids['Route'] = $this->add_mobile_field($dlx, 'itinerary', 'Route', ['param1' => 3]);
        $this->ids['Termin'] = $this->add_mobile_field($dlx, 'schedule', 'Termin');
        $this->ids['Bemerkungen'] = $this->add_mobile_field($dlx, 'textarea', 'Bemerkungen');
        $students = [datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR];
        $this->add_mobile_behavior($dlx, 'pflicht', ['required' => 1, 'editableby' => $students]);
        $this->add_mobile_behavior($dlx, 'nurlesen', ['editable' => 0, 'editableby' => []]);

        // The offer pathway: route, date, summary, then the list of one's rides.
        $this->views['mine'] = $this->add_mobile_view($dlx, 'grid', 'Meine Fahrten', '[[Route]] [[Termin]]');
        $this->views['step3'] = $this->add_mobile_view($dlx, 'grid', 'Schritt 3', '[[Fahrttyp|nurlesen]] [[Route|nurlesen]] '
            . '[[Termin|nurlesen]] [[Bemerkungen]] ##entryid## ##submit:text=Fahrt_speichern##', [
            'param10' => $this->views['mine'],
        ]);
        $this->views['step2'] = $this->add_mobile_view($dlx, 'grid', 'Schritt 2', '[[Termin|pflicht]] ##submit:text=Weiter##', [
            'param9' => 1, 'param10' => $this->views['step3'],
        ]);
        $this->views['step1'] = $this->add_mobile_view($dlx, 'grid', 'Schritt 1', '[[Fahrttyp|pflicht]] [[Route|pflicht]] '
            . '##submit:text=Weiter_zum_Termin##', [
            'param9' => 1, 'param10' => $this->views['step2'],
        ]);
        // The request pathway: one page.
        $this->views['request'] = $this->add_mobile_view($dlx, 'grid', 'Gesuch', '[[Route|pflicht]] [[Termin|pflicht]] '
            . '##submit:text=Gesuch_speichern##', [
            'param10' => $this->views['mine'],
        ]);
        $this->dlx = $this->set_mobile_default_view($dlx, $this->views['mine']);
        $this->student = $this->create_mobile_student($this->course);
        $this->setUser($this->student);
    }

    #[\Override]
    protected function tearDown(): void {
        geocoder_factory::set_geocoder_for_testing(null);
        router_factory::set_router_for_testing(null);
        itinerary_field::reset_resolved_addresses();
        parent::tearDown();
    }

    /**
     * One rule decides the next view: the view itself (used by the web redirect), submit_entry
     * and the app agree for every view of both pathways.
     */
    public function test_one_next_step_rule(): void {
        $expected = [
            'step1' => [$this->views['step2'], true],
            'step2' => [$this->views['step3'], true],
            'step3' => [$this->views['mine'], false],
            'request' => [$this->views['mine'], false],
            'mine' => [0, false],
        ];
        $dlx = new datalynx($this->dlx->id());
        foreach ($expected as $name => [$viewid, $continue]) {
            $view = $dlx->get_view_from_id($this->views[$name]);
            $this->assertSame(['viewid' => $viewid, 'continueediting' => $continue], $view->get_next_step(), $name);
        }

        // The service reports it after a save.
        $result = external_api::clean_returnvalue(submit_entry::execute_returns(), submit_entry::execute(
            $this->dlx->id(),
            $this->views['step2'],
            0,
            [
                ['fieldid' => (string) $this->ids['Termin'], 'name' => 'mode', 'value' => 'weekly'],
                ['fieldid' => (string) $this->ids['Termin'], 'name' => 'day1', 'value' => '1'],
                ['fieldid' => (string) $this->ids['Termin'], 'name' => 'start', 'value' => '420'],
            ]
        ));
        $this->assertTrue($result['status']);
        $this->assertSame($this->views['step3'], $result['nextviewid']);
        $this->assertTrue($result['continueediting']);
    }

    /**
     * An offer through the whole chain in the app: each save opens the next step for the same
     * entry, the last opens the list of one's rides. The route typed as addresses is looked up,
     * routed and indexed, so the corridor search finds it.
     */
    public function test_offer_through_the_wizard(): void {
        global $DB;

        // Step 1: ride type and route, typed as addresses.
        $state = $this->open('step1');
        $this->assertTrue($state['supported'], 'the route has a control now');
        $this->assertSame('Weiter zum Termin', $state['submitlabel']);
        $route = $this->field($state, $this->ids['Route']);
        $this->assertSame('itinerary', $route['control']);
        $empty = ['address' => '', 'lat' => '', 'lng' => ''];
        $this->assertSame([$empty, $empty], $route['stops']);
        $this->assertSame(3, $route['maxstops']);

        $result = $this->save('step1', 0, $state, [
            $this->ids['Fahrttyp'] => ['' => '1'],
            $this->ids['Route'] => ['stops' => ['Wien', 'Wiener Neustadt', 'Graz']],
        ]);
        $entryid = (int) $DB->get_field('datalynx_entries', 'id', ['dataid' => $this->dlx->id()], MUST_EXIST);
        $this->assert_next($result, $this->views['step2'], 'mobile_edit_entry', $entryid);

        $content = $DB->get_record('datalynx_contents', ['entryid' => $entryid, 'fieldid' => $this->ids['Route']]);
        $this->assertSame(
            ['Hauptbahnhof, 1100 Wien', 'Hauptplatz, 2700 Wiener Neustadt', 'Hauptplatz, 8010 Graz'],
            array_column(json_decode($content->content, true), 'address')
        );
        $this->assertSame(2, json_decode($content->content4, true)['distance'] / 100000, 'routed with the router');
        $this->assertSame(3, $DB->count_records('datalynx_waypoints', ['entryid' => $entryid]), 'indexed');
        $this->assertSame([$entryid], $this->corridor_search('wiener neustadt', 'graz'));

        // Step 2: the schedule; it opens with the confirmation of step 1.
        $state = $this->open('step2', $entryid, ['saved' => 1]);
        $this->assertTrue($state['saved']);
        $this->assertSame('schedule', $this->field($state, $this->ids['Termin'])['control']);
        $result = $this->save('step2', $entryid, $state, [
            $this->ids['Termin'] => ['mode' => 'weekly', 'days' => '1,3', 'start' => '420', 'from' => '', 'until' => ''],
        ]);
        $this->assert_next($result, $this->views['step3'], 'mobile_edit_entry', $entryid);

        // Step 3: the summary shows the route as the app does (no map), and the last save lists the rides.
        $state = $this->open('step3', $entryid);
        $this->assertSame('Fahrt speichern', $state['submitlabel']);
        $this->assertSame([], array_values(array_intersect(['submit', 'entry'], array_column($state['fields'], 'fieldid'))));
        $summary = $this->field($state, $this->ids['Route']);
        $this->assertSame('static', $summary['control']);
        $this->assertStringContainsString('Hauptplatz, 8010 Graz', $summary['html']);
        $this->assertStringNotContainsString('datalynx-itinerary-map', $summary['html']);
        $result = $this->save('step3', $entryid, $state, [$this->ids['Bemerkungen'] => ['' => 'Gerne mit Musik']]);
        $this->assert_next($result, $this->views['mine'], 'mobile_course_view', 0);
    }

    /**
     * The request page saves route and schedule on one page and lists the rides after.
     */
    public function test_request_on_one_page(): void {
        $state = $this->open('request');
        $this->assertTrue($state['supported']);
        $result = $this->save('request', 0, $state, [
            $this->ids['Route'] => ['stops' => ['Wiener Neustadt', 'Graz']],
            $this->ids['Termin'] => ['mode' => 'once', 'date' => '2026-11-30', 'start' => '480'],
        ]);
        $this->assert_next($result, $this->views['mine'], 'mobile_course_view', 0);
    }

    /**
     * Addresses the geocoder does not know are an error at the route, with what was typed kept;
     * a single stop is not a journey; stops beyond the field's maximum are not read.
     */
    public function test_route_errors(): void {
        global $DB;

        $state = $this->save_and_stay('request', 0, [
            $this->ids['Route'] => ['stops' => ['Wien', 'Atlantis']],
            $this->ids['Termin'] => ['mode' => 'once', 'date' => '2026-11-30', 'start' => '480'],
        ]);
        $route = $this->field($state, $this->ids['Route']);
        $this->assertSame(get_string('stopnotfound', 'datalynxfield_itinerary', 'Atlantis'), $route['error']);
        $this->assertSame(['Wien', 'Atlantis'], array_column($route['stops'], 'address'), 'kept as typed');
        $this->assertSame(0, $DB->count_records('datalynx_entries', ['dataid' => $this->dlx->id()]));

        $state = $this->save_and_stay('request', 0, [
            $this->ids['Route'] => ['stops' => ['Wien']],
            $this->ids['Termin'] => ['mode' => 'once', 'date' => '2026-11-30', 'start' => '480'],
        ]);
        $this->assertSame(
            get_string('needtworoutepoints', 'datalynxfield_itinerary'),
            $this->field($state, $this->ids['Route'])['error']
        );

        // The form has three rows (the field's maximum); a fourth is not read.
        $state = $this->open('request');
        $result = $this->save('request', 0, $state, [
            $this->ids['Route'] => ['stops' => ['Wien', 'Wiener Neustadt', 'Graz', 'Klagenfurt']],
            $this->ids['Termin'] => ['mode' => 'once', 'date' => '2026-11-30', 'start' => '480'],
        ]);
        $this->assertStringContainsString('this.updateContent(', $result['javascript']);
        $entryid = (int) $DB->get_field('datalynx_entries', 'id', ['dataid' => $this->dlx->id()], MUST_EXIST);
        $this->assertSame(3, $DB->count_records('datalynx_waypoints', ['entryid' => $entryid]));
    }

    /**
     * Saving the stops unchanged keeps their positions without looking them up again.
     */
    public function test_unchanged_stops_are_not_looked_up_again(): void {
        global $DB;

        $this->save('request', 0, $this->open('request'), [
            $this->ids['Route'] => ['stops' => ['Wien', 'Graz']],
            $this->ids['Termin'] => ['mode' => 'once', 'date' => '2026-11-30', 'start' => '480'],
        ]);
        $entryid = (int) $DB->get_field('datalynx_entries', 'id', ['dataid' => $this->dlx->id()], MUST_EXIST);
        $before = $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $this->ids['Route']]);
        itinerary_field::reset_resolved_addresses();
        $this->geocoder->queries = [];

        // The form shows the stops by the names of the places found.
        $state = $this->open('request', $entryid);
        $stops = array_column($this->field($state, $this->ids['Route'])['stops'], 'address');
        $this->assertSame(['Hauptbahnhof, 1100 Wien', 'Hauptplatz, 8010 Graz'], $stops);
        $this->save('request', $entryid, $state, [
            $this->ids['Route'] => ['stops' => $stops],
            $this->ids['Termin'] => ['mode' => 'once', 'date' => '2026-12-01', 'start' => '480'],
        ]);

        $this->assertSame([], $this->geocoder->queries);
        $this->assertSame(
            $before,
            $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $this->ids['Route']])
        );
    }

    /**
     * A place picked from the suggestions is saved with its position, without a lookup; an
     * address typed without one is looked up, and a position out of range is ignored.
     */
    public function test_picked_places_keep_their_position(): void {
        global $DB;

        $this->save('request', 0, $this->open('request'), [
            $this->ids['Route'] => ['stops' => [['Westbahnhof, 1150 Wien', '48.1967', '16.3378'], ['Graz', '91', '15']]],
            $this->ids['Termin'] => ['mode' => 'once', 'date' => '2026-11-30', 'start' => '480'],
        ]);

        $this->assertSame(['graz'], array_map('strtolower', $this->geocoder->queries));
        $entryid = (int) $DB->get_field('datalynx_entries', 'id', ['dataid' => $this->dlx->id()], MUST_EXIST);
        $stops = json_decode($DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid,
            'fieldid' => $this->ids['Route']]), true);
        $this->assertSame(['Westbahnhof, 1150 Wien', 'Hauptplatz, 8010 Graz'], array_column($stops, 'address'));
        $this->assertEqualsWithDelta(48.1967, $stops[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(16.3378, $stops[0]['lng'], 0.0001);

        // The form shows the stops with their positions, so saving again keeps them.
        $route = $this->field($this->open('request', $entryid), $this->ids['Route']);
        $this->assertEqualsWithDelta(48.1967, $route['stops'][0]['lat'], 0.0001);
    }

    /**
     * The edit page brings the script of the address suggestions, which asks the server's
     * geocoder through a function of the app's service.
     */
    public function test_edit_page_suggests_addresses(): void {
        global $DB;

        $result = mobile::mobile_edit_entry(array_map('strval', ['cmid' => $this->dlx->cm->id,
            'courseid' => $this->course->id, 'viewid' => $this->views['request']]));

        $this->assertStringContainsString("'mod_datalynx_geocode_search'", $result['javascript']);
        $this->assertStringContainsString('dlxSuggest(f, s, $event.detail.value)', $result['templates'][0]['html']);
        $serviceid = $DB->get_field('external_services', 'id', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        $this->assertTrue($DB->record_exists('external_services_functions', ['externalserviceid' => $serviceid,
            'functionname' => 'mod_datalynx_geocode_search']));
    }

    /**
     * The schedule from the app is stored as the web form stores the same choices; a weekly one
     * without days is an error at the field, with the web's message.
     */
    public function test_schedule_as_the_web_stores_it(): void {
        global $DB;

        $this->save('request', 0, $this->open('request'), [
            $this->ids['Route'] => ['stops' => ['Wien', 'Graz']],
            $this->ids['Termin'] => ['mode' => 'weekly', 'days' => '1,5', 'start' => '450', 'from' => '2026-11-30',
                'until' => '2027-06-30'],
        ]);
        $entryid = (int) $DB->get_field('datalynx_entries', 'id', ['dataid' => $this->dlx->id()], MUST_EXIST);
        $web = schedule_field::schedule_from_values([
            'mode' => 'weekly', 'day1' => 1, 'day5' => 1, 'start' => 450,
            'from' => make_timestamp(2026, 11, 30), 'until' => make_timestamp(2027, 6, 30),
        ]);
        $this->assertSame(
            $web->to_json(),
            $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $this->ids['Termin']])
        );

        // Shown again as stored.
        $termin = $this->field($this->open('request', $entryid), $this->ids['Termin']);
        $this->assertSame('weekly', $termin['mode']);
        $this->assertSame(['1', '5'], $termin['days']);
        $this->assertSame('2026-11-30', $termin['show']['from']);

        $state = $this->save_and_stay('request', $entryid, [
            $this->ids['Route'] => ['stops' => ['Wien', 'Graz']],
            $this->ids['Termin'] => ['mode' => 'weekly', 'days' => '', 'start' => '450'],
        ]);
        // As on the web: a schedule that says nothing usable counts as missing.
        $this->assertSame(
            get_string('errnoschedule', 'datalynxfield_schedule'),
            $this->field($state, $this->ids['Termin'])['error']
        );
    }

    /**
     * The edit form service describes both controls for other clients.
     */
    public function test_edit_form_service_describes_the_controls(): void {
        $form = external_api::clean_returnvalue(
            get_entry_edit_form::execute_returns(),
            get_entry_edit_form::execute($this->dlx->id(), $this->views['request'], 0)
        );
        $this->assertSame('Gesuch speichern', $form['submitlabel']);
        $controls = array_column($form['fields'], null, 'fieldid');
        $this->assertSame(3, $controls[(string) $this->ids['Route']]['maxstops']);
        $termin = $controls[(string) $this->ids['Termin']];
        $this->assertSame(['once', 'weekly'], array_column($termin['modes'], 'value'));
        $this->assertCount(7, $termin['weekdays']);
        $this->assertSame('420', $termin['times'][14]['value']);
    }

    /**
     * Without a geocoder the route cannot be typed in the app: the form opens on the web.
     */
    public function test_without_geocoder_the_form_opens_on_the_web(): void {
        geocoder_factory::set_geocoder_for_testing(null);
        set_config('geocoder', 'none', 'mod_datalynx');

        $state = $this->open('request');
        $this->assertFalse($state['supported']);
        $this->assertSame(['Route'], $state['unsupportedfields']);
    }

    /**
     * Open the edit page of a view as the app does.
     *
     * @param string $view
     * @param int $entryid
     * @param array $args further arguments
     * @return array the page state
     */
    private function open(string $view, int $entryid = 0, array $args = []): array {
        $result = mobile::mobile_edit_entry(array_map('strval', $args + [
            'cmid' => $this->dlx->cm->id, 'courseid' => $this->course->id, 'viewid' => $this->views[$view],
            'entryid' => $entryid,
        ]));

        return json_decode($result['otherdata']['state'], true);
    }

    /**
     * Save the form of a page as the app sends it.
     *
     * @param string $view
     * @param int $entryid
     * @param array $state the page state, for the names of the inputs
     * @param array $values fieldid => input name => value; 'stops' => addresses for an itinerary,
     *                      'days' => "1,3" for a schedule
     * @return array the content method's result
     */
    private function save(string $view, int $entryid, array $state, array $values): array {
        $args = ['cmid' => $this->dlx->cm->id, 'courseid' => $this->course->id, 'viewid' => $this->views[$view],
            'entryid' => $entryid, 'save' => 1];
        foreach ($values as $fieldid => $inputs) {
            $field = $this->field($state, $fieldid);
            foreach ($inputs as $name => $value) {
                if ($name === 'stops') {
                    foreach ($value as $n => $stop) {
                        // A place picked from the suggestions: [label, lat, lng].
                        [$address, $lat, $lng] = is_array($stop) ? $stop : [$stop, '', ''];
                        $args[$field['formnames']['waypoints'] . '__' . $n] = $address;
                        $args[$field['formnames']['waypoints'] . '__' . $n . '__lat'] = $lat;
                        $args[$field['formnames']['waypoints'] . '__' . $n . '__lng'] = $lng;
                    }
                } else if ($name === 'days') {
                    $args[$field['daysname']] = $value;
                } else {
                    $args[$field['formnames'][$name]] = $value;
                }
            }
        }

        return mobile::mobile_edit_entry(array_map('strval', $args));
    }

    /**
     * Save a form that is expected to come back with errors.
     *
     * @param string $view
     * @param int $entryid
     * @param array $values see save()
     * @return array the page state
     */
    private function save_and_stay(string $view, int $entryid, array $values): array {
        $result = $this->save($view, $entryid, $this->open($view, $entryid), $values);
        $this->assertSame(mobile::EDIT_JAVASCRIPT, $result['javascript']);

        return json_decode($result['otherdata']['state'], true);
    }

    /**
     * Assert a save opened the next step.
     *
     * @param array $result the content method's result
     * @param int $viewid
     * @param string $method
     * @param int $entryid 0 for a list
     */
    private function assert_next(array $result, int $viewid, string $method, int $entryid): void {
        $next = json_decode($result['otherdata']['state'], true)['nextstep'] ?? null;
        $this->assertNotNull($next, 'no next step: ' . $result['otherdata']['state']);
        $this->assertSame($viewid, $next['viewid']);
        $this->assertSame($method, $next['method']);
        $this->assertSame($entryid, $next['entryid'] ?? 0);
        $this->assertStringContainsString('this.updateContent(', $result['javascript']);
        $this->assertStringContainsString('"' . $method . '"', $result['javascript']);
        $this->assertStringNotContainsString('"save"', $result['javascript'], 'a refresh must not save again');
    }

    /**
     * One field of a page state.
     *
     * @param array $state
     * @param int $fieldid
     * @return array
     */
    private function field(array $state, int $fieldid): array {
        foreach ($state['fields'] as $field) {
            if ((string) $field['fieldid'] === (string) $fieldid) {
                return $field;
            }
        }
        $this->fail("No field $fieldid in " . json_encode(array_column($state['fields'], 'fieldid')));
    }

    /**
     * The entries the web's route search finds between two known places.
     *
     * @param string $from key of PLACES
     * @param string $to key of PLACES
     * @return int[]
     */
    private function corridor_search(string $from, string $to): array {
        $value = [
            'fromlat' => self::PLACES[$from][1], 'fromlng' => self::PLACES[$from][2],
            'tolat' => self::PLACES[$to][1], 'tolng' => self::PLACES[$to][2],
            'fromaddress' => $from, 'toaddress' => $to, 'radius' => 10,
        ];
        $filter = new datalynx_filter((object) ['dataid' => $this->dlx->id(), 'id' => 0,
            'customsearch' => [$this->ids['Route'] => ['AND' => [['', '', $value]]]]]);
        $entries = new datalynx_entries(new datalynx($this->dlx->id()), $filter);
        $entries->set_content();

        return array_map('intval', array_keys($entries->entries() ?: []));
    }
}
