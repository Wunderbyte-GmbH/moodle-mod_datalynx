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

namespace mod_datalynx\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use advanced_testcase;
use core_external\external_api;
use mod_datalynx\datalynx;

/**
 * External function tests for get_report_view_data.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\get_report_view_data
 * @runTestsInSeparateProcesses
 */
final class get_report_view_data_test extends advanced_testcase {
    /**
     * Build a report fixture with two students, one select field and three entries.
     *
     * @return array
     */
    private function create_report_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

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

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'report',
            'name' => 'Author report',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param1' => $selectfield->id,
            'param2' => 'nosums',
            'param3' => 'sumoffield',
            'param4' => -1,
            'param5' => 0,
            'param10' => 0,
            'section' => '',
        ];
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
            'userid' => $student2->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => strtotime('2026-04-16 12:00:00'),
            'timemodified' => strtotime('2026-04-16 12:00:00'),
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

        return [$df, $view, $student1, $student2];
    }

    /**
     * The external function should return a cleaned Report browse payload.
     *
     * @covers ::execute
     */
    public function test_execute_returns_report_browse_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, $student1, $student2] = $this->create_report_fixture();

        $result = get_report_view_data::execute($df->id(), $view->id);
        $result = external_api::clean_returnvalue(get_report_view_data::execute_returns(), $result);

        $this->assertSame((int) $df->id(), $result['datalynxid']);
        $this->assertSame($view->id, $result['viewid']);
        $this->assertSame('report', $result['viewtype']);
        $this->assertTrue($result['hasdata']);
        $this->assertCount(2, $result['rows']);
        $this->assertStringContainsString(fullname($student1), $result['rows'][0]['userhtml']);
        $this->assertStringContainsString(fullname($student2), $result['rows'][1]['userhtml']);
        $this->assertSame('2026-04', $result['rows'][0]['month']);
        $this->assertSame(1, $result['rows'][0]['optioncells'][0]['count']);
        $this->assertSame(1, $result['rows'][1]['optioncells'][1]['count']);
    }
}
