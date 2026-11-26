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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/stub_router.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\external\team_subscription;
use mod_datalynx\local\map\router_factory;
use mod_datalynx\local\ride\board_builder;
use mod_datalynx\local\ride\schedule;
use mod_datalynx\stub_router;

/**
 * Joining a ride in the Moodle App (mod_datalynx#278): the team's actions reach the app as action
 * parts of the entry, the same actions the web shows as buttons.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\mobile\app_links
 * @covers     \datalynxfield_teammemberselect\renderer::team_action
 */
final class team_actions_app_test extends advanced_testcase {
    /** @var board_builder */
    protected board_builder $builder;

    /** @var int[] */
    protected array $ids;

    /** @var \stdClass */
    protected \stdClass $driver;

    /** @var \stdClass */
    protected \stdClass $passenger;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        router_factory::set_router_for_testing(new stub_router());
        \datalynxfield_itinerary\field::reset_server_routes();
        $course = $this->getDataGenerator()->create_course();
        $this->builder = new board_builder($course, board_builder::VARIANT_BILLING);
        $this->builder->build();
        $this->ids = $this->builder->get_fieldids_by_name();
        $this->driver = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->passenger = $this->getDataGenerator()->create_and_enrol($course, 'student');
    }

    protected function tearDown(): void {
        router_factory::set_router_for_testing(null);
        parent::tearDown();
    }

    /**
     * A ride of the driver next week, with two seats and a detour of 20 km.
     *
     * @return int
     */
    protected function ride(): int {
        global $DB;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) ['dataid' => $this->builder->get_dataid(),
            'userid' => $this->driver->id, 'groupid' => 0, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        $dlx = new datalynx($this->builder->get_dataid());
        $dlx->get_field_from_id($this->ids['Termin'], true)->update_content(
            (object) ['id' => $entryid],
            ['schedule' => (new schedule(schedule::MODE_ONCE, date('Y-m-d', strtotime('7 days')), [], 480))->to_json()]
        );
        $route = $dlx->get_field_from_id($this->ids['Route'], true);
        $route->update_content((object) ['id' => $entryid], ['waypoints' => json_encode([
            ['address' => 'Wien', 'lat' => 48.2, 'lng' => 16.37], ['address' => 'Graz', 'lat' => 47.07, 'lng' => 15.44]])]);
        foreach (['Fahrttyp' => '1', 'Freie Plätze' => '2', 'Umweg' => '20', 'Veröffentlicht' => '#1#'] as $name => $content) {
            $DB->insert_record('datalynx_contents', (object) ['fieldid' => $this->ids[$name], 'entryid' => $entryid,
                'content' => $content]);
        }
        return $entryid;
    }

    /**
     * Start a new request: rendering set up the page, which a web service sets up again.
     */
    protected function new_request(): void {
        global $PAGE;
        $PAGE = new \moodle_page();
    }

    /**
     * The actions of an entry in "Alle Fahrten" as the app gets them, and the HTML around them.
     *
     * @param int $entryid
     * @return array [actions, html]
     */
    protected function app_entry(int $entryid): array {
        global $DB;
        $viewid = (int) $DB->get_field('datalynx_views', 'id', ['dataid' => $this->builder->get_dataid(),
            'name' => 'Alle Fahrten']);
        $payload = (new entries_payload_builder(new datalynx($this->builder->get_dataid())))->build(['viewid' => $viewid]);
        $entry = current(array_filter($payload['entries'], fn(array $e): bool => (int) $e['id'] === $entryid));
        $this->assertNotFalse($entry);
        return [
            array_values(array_column(array_filter($entry['parts'], fn(array $p): bool => isset($p['action'])), 'action')),
            implode('', array_column($entry['parts'], 'html')),
        ];
    }

    /**
     * Request with the pickup point, then approve or decline: buttons of the app, no web buttons.
     */
    public function test_request_and_approve_in_the_app(): void {
        $ride = $this->ride();

        $this->setUser($this->passenger);
        [$actions, $html] = $this->app_entry($ride);
        $this->assertCount(1, $actions);
        $this->assertSame('request', $actions[0]['action']);
        $this->assertSame($this->ids['Route'], $actions[0]['pickupfield']);
        $this->assertSame('Mitfahrt beantragen', $actions[0]['label']);
        $this->assertSame((int) $this->passenger->id, $actions[0]['userid']);
        $this->assertStringNotContainsString('data-dlx-team-action', $html);

        // The app sends what the action carries, with the pickup point (a request of its own).
        $this->new_request();
        $result = team_subscription::execute(
            $actions[0]['d'],
            $actions[0]['entryid'],
            $actions[0]['fieldid'],
            $actions[0]['userid'],
            $actions[0]['action'],
            json_encode(['pickup' => ['address' => 'Graz', 'lat' => 47.07,
            'lng' => 15.44]])
        );
        $this->assertTrue($result['success']);
        $this->new_request();
        [$actions] = $this->app_entry($ride);
        $this->assertSame(['withdraw'], array_column($actions, 'action'));
        $this->assertSame(0, $actions[0]['pickupfield']);

        $this->setUser($this->driver);
        $this->new_request();
        [$actions] = $this->app_entry($ride);
        $this->assertSame(['approve', 'decline'], array_column($actions, 'action'));
        $this->assertSame([(int) $this->passenger->id, (int) $this->passenger->id], array_column($actions, 'userid'));
        $this->assertSame(['success', 'danger'], array_column($actions, 'color'));
        $this->assertSame(['solid', 'outline'], array_column($actions, 'fill'));
    }

    /**
     * A link to view.php?d= (a notification's link to a ride) opens the ride in the app (#280).
     */
    public function test_entry_link_opens_in_the_app(): void {
        global $DB;
        $ride = $this->ride();
        $this->ride();
        $init = \mod_datalynx\output\mobile::mobile_init([]);
        $this->assertStringContainsString('CoreContentLinksDelegateService.registerHandler', $init['javascript']);
        $this->assertStringContainsString("'/mod/datalynx/view\\\\.php.*[?&]d=\\\\d+'", $init['javascript']);

        $this->setUser($this->passenger);
        $viewid = (int) $DB->get_field('datalynx_views', 'id', ['dataid' => $this->builder->get_dataid(),
            'name' => 'Alle Fahrten']);
        // What the handler passes: the instance, the view and the entries of the link.
        $result = \mod_datalynx\output\mobile::mobile_course_view(['d' => (string) $this->builder->get_dataid(),
            'viewid' => (string) $viewid, 'eids' => (string) $ride]);
        $state = json_decode($result['otherdata']['state'], true);
        $this->assertSame([$ride], array_map('intval', array_column($state['entries'], 'id')));
    }

    /**
     * The service is part of the app's web services.
     */
    public function test_service_for_the_app(): void {
        global $DB;
        $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE]);
        $this->assertTrue($DB->record_exists('external_services_functions', ['externalserviceid' => $service->id,
            'functionname' => 'mod_datalynx_team_subscription']));
    }
}
