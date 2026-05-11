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
use stdClass;

/**
 * External function tests for toggle_entry_approval.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\toggle_entry_approval
 * @runTestsInSeparateProcesses
 */
final class toggle_entry_approval_test extends advanced_testcase {
    /**
     * Create a minimal datalynx view for event payloads.
     *
     * @param datalynx $df
     * @return stdClass
     */
    private function create_view_record(datalynx $df, string $type = 'tabular', string $param2 = ''): stdClass {
        global $DB;

        $view = (object) [
            'dataid' => $df->id(),
            'type' => $type,
            'name' => 'Approval view',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => $param2,
        ];
        $view->id = $DB->insert_record('datalynx_views', $view);

        return $view;
    }

    /**
     * Create a minimal datalynx entry.
     *
     * @param datalynx $df
     * @param int $userid
     * @param int $approved
     * @return int
     */
    private function create_entry(datalynx $df, int $userid, int $approved = 0): int {
        global $DB;

        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $userid,
            'groupid' => 0,
            'approved' => $approved,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Test execute toggles approval on and off.
     *
     * @covers ::execute
     */
    public function test_execute_toggles_entry_approval(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->setUser($teacher);

        $instance = $generator->create_module('datalynx', [
            'course' => $course->id,
            'approval' => 1,
        ]);
        $df = new datalynx($instance->id);
        $view = $this->create_view_record($df);
        $entryid = $this->create_entry($df, $teacher->id, 0);

        $approvedresult = toggle_entry_approval::execute($instance->id, $view->id, $entryid, 'toggle-approval');
        $approvedresult = external_api::clean_returnvalue(toggle_entry_approval::execute_returns(), $approvedresult);

        $this->assertTrue($approvedresult['approved']);
        $this->assertSame($entryid, $approvedresult['entryid']);
        $this->assertStringContainsString('data-entryid="' . $entryid . '"', $approvedresult['controlhtml']);
        $this->assertStringContainsString('data-approved="1"', $approvedresult['controlhtml']);
        $this->assertStringContainsString('aria-checked="true"', $approvedresult['controlhtml']);
        $this->assertSame(1, (int) $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));

        $disapprovedresult = toggle_entry_approval::execute($instance->id, $view->id, $entryid, 'toggle-approval');
        $disapprovedresult = external_api::clean_returnvalue(toggle_entry_approval::execute_returns(), $disapprovedresult);

        $this->assertFalse($disapprovedresult['approved']);
        $this->assertSame($entryid, $disapprovedresult['entryid']);
        $this->assertStringContainsString('data-entryid="' . $entryid . '"', $disapprovedresult['controlhtml']);
        $this->assertStringContainsString('data-approved="0"', $disapprovedresult['controlhtml']);
        $this->assertStringContainsString('aria-checked="false"', $disapprovedresult['controlhtml']);
        $this->assertSame(0, (int) $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));
    }
}
