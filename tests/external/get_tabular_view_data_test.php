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
 * External function tests for get_tabular_view_data.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\get_tabular_view_data
 * @runTestsInSeparateProcesses
 */
final class get_tabular_view_data_test extends advanced_testcase {
    /**
     * Build a minimal datalynx fixture with a Tabular view, one text field, and one entry.
     *
     * @return array
     */
    private function create_tabular_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'tabular',
            'name' => 'Pilot Tabular',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => '',
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $field = (object) [
            'dataid' => $df->id(),
            'type' => 'text',
            'name' => 'Title',
            'description' => '',
            'param1' => '',
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
        $field->id = (int) $DB->insert_record('datalynx_fields', $field);

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $field->id,
            'entryid' => $entryid,
            'lineid' => 0,
            'content' => 'Hello External Tabular',
        ]);

        return [$df, $view, $entryid];
    }

    /**
     * The external function should return a cleaned Tabular browse payload.
     *
     * @covers ::execute
     */
    public function test_execute_returns_tabular_browse_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, $entryid] = $this->create_tabular_fixture();

        $result = get_tabular_view_data::execute($df->id(), $view->id);
        $result = external_api::clean_returnvalue(get_tabular_view_data::execute_returns(), $result);

        $this->assertSame((int) $df->id(), $result['datalynxid']);
        $this->assertSame($view->id, $result['viewid']);
        $this->assertSame('tabular', $result['viewtype']);
        $this->assertTrue($result['hasentries']);
        $this->assertCount(1, $result['groups']);
        $this->assertCount(1, $result['groups'][0]['rows']);
        $this->assertSame($entryid, $result['groups'][0]['rows'][0]['id']);
        $this->assertStringContainsString('Hello External Tabular', $result['groups'][0]['rows'][0]['cells'][2]['valuehtml']);
    }
}
