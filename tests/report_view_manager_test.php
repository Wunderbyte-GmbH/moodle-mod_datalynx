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

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\view\manager\report_view_manager;

/**
 * Tests for the Report view browse payload manager.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\local\view\manager\report_view_manager
 */
final class report_view_manager_test extends advanced_testcase {
    /**
     * Build a report fixture with two students, one select field and three entries.
     *
     * @param string $mode
     * @return array
     */
    private function create_report_fixture(string $mode = 'nosums'): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $dlx->id(),
            'type' => 'report',
            'name' => 'Author report',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param1' => 0,
            'param2' => $mode,
            'param3' => 'sumoffield',
            'param4' => -1,
            'param5' => 0,
            'param10' => 0,
            'section' => '',
        ];

        $selectfield = (object) [
            'dataid' => $dlx->id(),
            'type' => 'select',
            'name' => 'Choice',
            'description' => '',
            'param1' => "Option A\nOption B",
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
            'param6' => '',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $selectfield->id = (int) $DB->insert_record('datalynx_fields', $selectfield);

        $view->param1 = $selectfield->id;
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $entryone = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $student1->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => strtotime('2026-04-15 12:00:00'),
            'timemodified' => strtotime('2026-04-15 12:00:00'),
        ]);
        $entrytwo = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $student1->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => strtotime('2026-04-16 12:00:00'),
            'timemodified' => strtotime('2026-04-16 12:00:00'),
        ]);
        $entrythree = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $student2->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => strtotime('2026-04-17 12:00:00'),
            'timemodified' => strtotime('2026-04-17 12:00:00'),
        ]);

        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $selectfield->id,
            'entryid' => $entryone,
            'lineid' => 0,
            'content' => '1',
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $selectfield->id,
            'entryid' => $entrytwo,
            'lineid' => 0,
            'content' => '2',
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $selectfield->id,
            'entryid' => $entrythree,
            'lineid' => 0,
            'content' => '1',
        ]);

        return [$dlx, $view, $teacher, $student1, $student2];
    }

    /**
     * The manager should return flat aggregated rows for a non-monthly author report.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_returns_flat_author_report_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, , $student1, $student2] = $this->create_report_fixture();

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);

        $this->assertSame($dlx->id(), $payload['datalynxid']);
        $this->assertSame($view->id, $payload['viewid']);
        $this->assertTrue($payload['hasdata']);
        $this->assertFalse($payload['ismonthly']);
        $this->assertCount(2, $payload['rows']);
        $this->assertSame(fullname($student1), $payload['rows'][0]['user']['fullname']);
        $this->assertSame('2026-04', $payload['rows'][0]['month']);
        $this->assertSame(2, $payload['rows'][0]['totalentries']);
        $this->assertSame(1, $payload['rows'][0]['optioncells'][0]['count']);
        $this->assertSame(1, $payload['rows'][0]['optioncells'][1]['count']);
        $this->assertSame(0, $payload['rows'][0]['notyetanswered']);
        $this->assertSame(fullname($student2), $payload['rows'][1]['user']['fullname']);
        $this->assertSame(1, $payload['rows'][1]['totalentries']);
    }

    /**
     * The manager should return monthly sections and overall totals for month mode.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_returns_month_sections(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view] = $this->create_report_fixture('month');

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);

        $this->assertTrue($payload['ismonthly']);
        $this->assertTrue($payload['hasmonthlysections']);
        $this->assertCount(1, $payload['monthlysections']);
        $this->assertSame('Month: 2026-04', $payload['monthlysections'][0]['heading']);
        $this->assertSame(3, $payload['monthlysections'][0]['totalentries']);
        $this->assertTrue($payload['hasoverall']);
        $this->assertSame(3, $payload['overall']['totalentries']);
        $this->assertSame(2, $payload['overall']['optioncells'][0]['count']);
        $this->assertSame(1, $payload['overall']['optioncells'][1]['count']);
    }

    /**
     * The payload should expose native chart descriptors with valid serialized data.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_includes_charts(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view] = $this->create_report_fixture();

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);

        $this->assertTrue($payload['hascharts']);
        // Option distribution, entries over time and per-user totals.
        $this->assertCount(3, $payload['charts']);

        $titles = array_column($payload['charts'], 'title');
        $this->assertContains(get_string('optiondistribution', 'datalynxview_report'), $titles);
        $this->assertContains(get_string('entriesovertime', 'datalynxview_report'), $titles);
        $this->assertContains(get_string('usertotals', 'datalynxview_report'), $titles);

        foreach ($payload['charts'] as $chart) {
            $this->assertNotEmpty($chart['uniqid']);
            $this->assertTrue($chart['withtable']);
            // The chartdata must be valid JSON understood by core/chart.
            $decoded = json_decode($chart['chartdata'], true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('series', $decoded);
        }
    }

    /**
     * A year scope should keep entries within the year and drop everything else.
     *
     * @covers ::get_browse_payload
     */
    public function test_timescope_year_filters_entries(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view] = $this->create_report_fixture();
        $manager = new report_view_manager();

        // All three entries are in 2026.
        $payload = $manager->get_browse_payload($dlx->id(), $view->id, [
            'timescope' => ['field' => 'timecreated', 'mode' => 'year', 'year' => 2026],
        ]);
        $this->assertTrue($payload['hasdata']);
        $this->assertSame(3, array_sum(array_column($payload['rows'], 'totalentries')));

        // No entries exist in 2025.
        $payload = $manager->get_browse_payload($dlx->id(), $view->id, [
            'timescope' => ['field' => 'timecreated', 'mode' => 'year', 'year' => 2025],
        ]);
        $this->assertFalse($payload['hasdata']);
        $this->assertSame([], $payload['rows']);
    }

    /**
     * A custom range should keep only entries with a creation time inside the bounds.
     *
     * @covers ::get_browse_payload
     */
    public function test_timescope_range_filters_entries(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view, , $student1, $student2] = $this->create_report_fixture();
        $manager = new report_view_manager();

        // Entries are on 2026-04-15, 16 and 17. Keep 16-17 only (student1 x1, student2 x1).
        $payload = $manager->get_browse_payload($dlx->id(), $view->id, [
            'timescope' => [
                'field' => 'timecreated',
                'mode' => 'range',
                'fromdate' => '2026-04-16',
                'todate' => '2026-04-17',
            ],
        ]);

        $this->assertTrue($payload['hasdata']);
        $total = array_sum(array_column($payload['rows'], 'totalentries'));
        $this->assertSame(2, $total);
        $fullnames = array_map(fn($r) => $r['user']['fullname'], $payload['rows']);
        $this->assertContains(fullname($student1), $fullnames);
        $this->assertContains(fullname($student2), $fullnames);
    }

    /**
     * Scoping on the modified time should use timemodified, not timecreated.
     *
     * @covers ::get_browse_payload
     */
    public function test_timescope_modified_field(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view] = $this->create_report_fixture();

        // Re-stamp every entry's modification time into 2026-05 while creation stays 2026-04.
        $newtimemodified = strtotime('2026-05-10 12:00:00');
        $DB->set_field('datalynx_entries', 'timemodified', $newtimemodified, ['dataid' => $dlx->id()]);

        $manager = new report_view_manager();

        // Created-time May filter excludes everything (creation is April).
        $payload = $manager->get_browse_payload($dlx->id(), $view->id, [
            'timescope' => ['field' => 'timecreated', 'mode' => 'month', 'year' => 2026, 'month' => 5],
        ]);
        $this->assertFalse($payload['hasdata']);

        // Modified-time May filter includes all three entries.
        $payload = $manager->get_browse_payload($dlx->id(), $view->id, [
            'timescope' => ['field' => 'timemodified', 'mode' => 'month', 'year' => 2026, 'month' => 5],
        ]);
        $this->assertTrue($payload['hasdata']);
        $this->assertSame(3, array_sum(array_column($payload['rows'], 'totalentries')));
    }

    /**
     * The exporter should validate the payload and expose the new structure blocks.
     *
     * @covers \mod_datalynx\output\report_view_browser::export
     */
    public function test_exporter_export_matches_read_structure(): void {
        global $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();

        [$dlx, $view] = $this->create_report_fixture();

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($dlx->id(), $view->id);

        $exporter = new \mod_datalynx\output\report_view_browser($payload, $dlx->context);
        $data = $exporter->export($PAGE->get_renderer('core'));

        $this->assertSame($dlx->id(), $data->datalynxid);
        $this->assertTrue($data->hasdata);
        $this->assertObjectHasProperty('charts', $data);
        $this->assertObjectHasProperty('scope', $data);
        $this->assertSame('timecreated', $data->scope['field']);
        $this->assertNotEmpty($data->scope['modes']);

        // The web service structure must be derived from the same definition.
        $structure = \mod_datalynx\output\report_view_browser::get_read_structure();
        $this->assertArrayHasKey('charts', $structure->keys);
        $this->assertArrayHasKey('scope', $structure->keys);
        $this->assertArrayHasKey('rows', $structure->keys);

        // The Mustache template (incl. the core/chart and user partials) must render.
        $html = $PAGE->get_renderer('core')->render_from_template('mod_datalynx/report_view_browser', $data);
        $this->assertStringContainsString('mod-datalynx-report-view-browser', $html);
        $this->assertStringContainsString('chart-area-', $html);
        $this->assertStringContainsString('data-report-control="mode"', $html);
    }
}
