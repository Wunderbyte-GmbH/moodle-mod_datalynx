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
 * External function tests for get_email_view_data.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\get_email_view_data
 * @runTestsInSeparateProcesses
 */
final class get_email_view_data_test extends advanced_testcase {
    /**
     * Create an Email view fixture with two entries.
     *
     * @return array
     */
    private function create_email_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'email',
            'name' => 'Email view',
            'description' => '',
            'visible' => 1,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '##entries##',
            'param2' => '<p>##notificationentrylink##</p><p>##entryid##</p>',
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return [$df, $view, $entryid];
    }

    /**
     * The external function should return a cleaned Email payload.
     *
     * @covers ::execute
     */
    public function test_execute_returns_email_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, $entryid] = $this->create_email_fixture();

        $result = get_email_view_data::execute(
            $df->id(),
            $view->id,
            $entryid,
            '',
            '<a href="https://example.invalid/entry">Entry</a>'
        );
        $result = external_api::clean_returnvalue(get_email_view_data::execute_returns(), $result);

        $this->assertSame((int) $df->id(), $result['datalynxid']);
        $this->assertSame($view->id, $result['viewid']);
        $this->assertSame('email', $result['viewtype']);
        $this->assertSame($entryid, $result['entryid']);
        $this->assertTrue($result['hascontent']);
        $this->assertStringContainsString('<a href="https://example.invalid/entry">Entry</a>', $result['bodyhtml']);
        $this->assertStringContainsString('<p>' . $entryid . '</p>', $result['bodyhtml']);
    }
}
