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
 * External function tests for get_grid_view_data.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\get_grid_view_data
 * @runTestsInSeparateProcesses
 */
final class get_grid_view_data_test extends advanced_testcase {
    /**
     * Build a minimal datalynx fixture with a Grid view, one text field, and one entry.
     *
     * @return array
     */
    private function create_grid_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'grid',
            'name' => 'Pilot Grid',
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
            'content' => 'Hello External Grid',
        ]);

        return [$df, $view, $entryid];
    }

    /**
     * Build a grid fixture with a fieldgroup containing a teammemberselect subfield.
     *
     * @return array
     */
    private function create_grid_fieldgroup_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'grid',
            'name' => 'Pilot Grid',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => '[[Teamgroup]]',
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);

        $teammemberfield = (object) [
            'dataid' => $df->id(),
            'type' => 'teammemberselect',
            'name' => 'Team member select',
            'description' => '',
            'param1' => '20',
            'param2' => json_encode([0, 1, 2, 3]),
            'param3' => '0',
            'param4' => '4',
            'param5' => '0',
            'param6' => '0',
            'param7' => '0',
            'param8' => '0',
            'param9' => '',
            'param10' => '',
        ];
        $teammemberfield->id = (int) $DB->insert_record('datalynx_fields', $teammemberfield);

        $fieldgroup = (object) [
            'dataid' => $df->id(),
            'type' => 'fieldgroup',
            'name' => 'Teamgroup',
            'description' => '',
            'param1' => json_encode([$teammemberfield->id]),
            'param2' => '4',
            'param3' => '4',
            'param4' => '',
            'param5' => '',
            'param6' => '',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $fieldgroup->id = (int) $DB->insert_record('datalynx_fields', $fieldgroup);

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $teacher->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $teammemberfield->id,
            'entryid' => $entryid,
            'lineid' => 0,
            'content' => json_encode([$student1->id, $student2->id]),
        ]);

        return [$df, $view, $entryid, $student1, $student2];
    }

    /**
     * Build a grid fixture with a multiselect field and multiple entries.
     *
     * @return array
     */
    private function create_grid_multiselect_fixture(): array {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $view = (object) [
            'dataid' => $df->id(),
            'type' => 'grid',
            'name' => 'Pilot Grid',
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

        $textfield = (object) [
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
        $textfield->id = (int) $DB->insert_record('datalynx_fields', $textfield);

        $multiselectfield = (object) [
            'dataid' => $df->id(),
            'type' => 'multiselect',
            'name' => 'Tags',
            'description' => '',
            'param1' => "Opt1\nOpt2\nOpt3",
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
        $multiselectfield->id = (int) $DB->insert_record('datalynx_fields', $multiselectfield);

        $entryone = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $entrytwo = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $df->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time() + 1,
            'timemodified' => time() + 1,
        ]);

        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $textfield->id,
            'entryid' => $entryone,
            'lineid' => 0,
            'content' => 'Entry one',
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $textfield->id,
            'entryid' => $entrytwo,
            'lineid' => 0,
            'content' => 'Entry two',
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $multiselectfield->id,
            'entryid' => $entryone,
            'lineid' => 0,
            'content' => '#1#',
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $multiselectfield->id,
            'entryid' => $entrytwo,
            'lineid' => 0,
            'content' => '#2#,#3#',
        ]);

        return [$df, $view, $multiselectfield->id, $entryone, $entrytwo];
    }

    /**
     * The external function should return a cleaned Grid browse payload.
     *
     * @covers ::execute
     */
    public function test_execute_returns_grid_browse_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, $entryid] = $this->create_grid_fixture();

        $result = get_grid_view_data::execute($df->id(), $view->id);
        $result = external_api::clean_returnvalue(get_grid_view_data::execute_returns(), $result);

        $this->assertSame((int) $df->id(), $result['datalynxid']);
        $this->assertSame($view->id, $result['viewid']);
        $this->assertSame('grid', $result['viewtype']);
        $this->assertTrue($result['hasentries']);
        $this->assertCount(1, $result['groups']);
        $this->assertCount(1, $result['groups'][0]['entries']);
        $this->assertSame($entryid, $result['groups'][0]['entries'][0]['id']);
        $this->assertStringContainsString(
            'Hello External Grid',
            $result['groups'][0]['entries'][0]['fields'][0]['valuehtml']
        );
    }

    /**
     * Fieldgroup renderers in grid AJAX responses must fetch their nested subfield content.
     *
     * @covers ::execute
     */
    public function test_execute_returns_fieldgroup_teammember_content(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, , $student1, $student2] = $this->create_grid_fieldgroup_fixture();

        $result = get_grid_view_data::execute($df->id(), $view->id);
        $result = external_api::clean_returnvalue(get_grid_view_data::execute_returns(), $result);

        $fieldhtml = implode('', array_column($result['groups'][0]['entries'][0]['fields'], 'valuehtml'));
        $entryhtml = $result['groups'][0]['entries'][0]['entryhtml'] . $fieldhtml;

        $this->assertStringContainsString(fullname($student1), $entryhtml);
        $this->assertStringContainsString(fullname($student2), $entryhtml);
        $this->assertStringContainsString('team-member-list', $entryhtml);
    }

    /**
     * Grid AJAX payloads must preserve active custom search state.
     *
     * @covers ::execute
     */
    public function test_execute_applies_customsearch_filter_options(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$df, $view, $fieldid, $entryone, $entrytwo] = $this->create_grid_multiselect_fixture();
        $customsearch = serialize([
            $fieldid => [
                'AND' => [['', 'ANY_OF', ['2']]],
            ],
        ]);

        $result = get_grid_view_data::execute($df->id(), $view->id, 0, 0, 0, '', '', '', '', 0, '', $customsearch);
        $result = external_api::clean_returnvalue(get_grid_view_data::execute_returns(), $result);

        $this->assertCount(1, $result['groups'][0]['entries']);
        $this->assertSame($entrytwo, $result['groups'][0]['entries'][0]['id']);
        $this->assertNotSame($entryone, $result['groups'][0]['entries'][0]['id']);
        $this->assertStringContainsString('Opt2', $result['groups'][0]['entries'][0]['fields'][1]['valuehtml']);
    }
}
