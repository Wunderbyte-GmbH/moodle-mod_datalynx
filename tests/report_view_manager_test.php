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
        $df = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $df->id(),
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
            'dataid' => $df->id(),
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
            'dataid' => $df->id(),
            'userid' => $student1->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => strtotime('2026-04-15 12:00:00'),
            'timemodified' => strtotime('2026-04-15 12:00:00'),
        ]);
        $entrytwo = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $student1->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => strtotime('2026-04-16 12:00:00'),
            'timemodified' => strtotime('2026-04-16 12:00:00'),
        ]);
        $entrythree = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
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

        return [$df, $view, $teacher, $student1, $student2];
    }

    /**
     * The manager should return flat aggregated rows for a non-monthly author report.
     *
     * @covers ::get_browse_payload
     */
    public function test_get_browse_payload_returns_flat_author_report_rows(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, , $student1, $student2] = $this->create_report_fixture();

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($df->id(), $view->id);

        $this->assertSame($df->id(), $payload['datalynxid']);
        $this->assertSame($view->id, $payload['viewid']);
        $this->assertTrue($payload['hasdata']);
        $this->assertFalse($payload['ismonthly']);
        $this->assertCount(2, $payload['rows']);
        $this->assertStringContainsString(fullname($student1), $payload['rows'][0]['userhtml']);
        $this->assertSame('2026-04', $payload['rows'][0]['month']);
        $this->assertSame(2, $payload['rows'][0]['totalentries']);
        $this->assertSame(1, $payload['rows'][0]['optioncells'][0]['count']);
        $this->assertSame(1, $payload['rows'][0]['optioncells'][1]['count']);
        $this->assertSame(0, $payload['rows'][0]['notyetanswered']);
        $this->assertStringContainsString(fullname($student2), $payload['rows'][1]['userhtml']);
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

        [$df, $view] = $this->create_report_fixture('month');

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($df->id(), $view->id);

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
}
