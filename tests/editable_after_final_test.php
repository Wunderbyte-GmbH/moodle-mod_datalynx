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

/**
 * Tests for the "editable after final submission" per-field behavior flag.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_status\field as datalynxfield_status;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\field\datalynxfield_behavior;
use mod_datalynx\local\filter\datalynx_filter;

/**
 * @covers \mod_datalynx\local\field\datalynxfield_behavior::is_editable_after_final
 * @covers \mod_datalynx\local\view\base::get_editable_after_final_fieldids
 * @covers \mod_datalynx\local\datalynx_entries::process_entries
 */
final class editable_after_final_test extends advanced_testcase {
    /** @var datalynx */
    private $dlx;

    /** @var \stdClass */
    private $course;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $this->course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * The flag round-trips through the form/db converters and is off by default and when not editable.
     */
    public function test_behavior_flag_roundtrip(): void {
        global $DB;

        $formdata = (object) [
            'id' => 0, 'd' => $this->dlx->id(), 'name' => 'editfinal', 'description' => '',
            'visibletopermission' => [1, 2, 4], 'visibletouser' => [], 'visibletoteammember' => [],
            'editableby' => [16], 'editable' => 1, 'required' => 0, 'editableafterfinal' => 1,
            'conditions' => ['match' => 'all', 'rules' => []],
        ];
        $record = datalynxfield_behavior::form_to_db($formdata);
        $this->assertEquals(1, $record->editableafterfinal);

        $record->id = (int) $DB->insert_record('datalynx_behaviors', $record);
        $behavior = datalynxfield_behavior::from_id($record->id);
        $this->assertTrue($behavior->is_editable_after_final());

        // Off by default.
        $formdata->editableafterfinal = 0;
        $this->assertEquals(0, datalynxfield_behavior::form_to_db($formdata)->editableafterfinal);

        // Forced off when the field is not editable at all.
        $formdata->editable = 0;
        $formdata->editableafterfinal = 1;
        $this->assertEquals(0, datalynxfield_behavior::form_to_db($formdata)->editableafterfinal);

        // The default behavior is never editable after final.
        $this->assertFalse(datalynxfield_behavior::get_default_behavior($this->dlx)->is_editable_after_final());
    }

    /**
     * The view helper returns only the fields rendered with a flagged behavior (never plain/internal ones).
     */
    public function test_view_lists_only_flagged_fields(): void {
        global $DB;

        $behavior = datalynxfield_behavior::form_to_db((object) [
            'id' => 0, 'd' => $this->dlx->id(), 'name' => 'editfinal', 'description' => '',
            'visibletopermission' => [1], 'visibletouser' => [], 'visibletoteammember' => [],
            'editableby' => [16], 'editable' => 1, 'required' => 0, 'editableafterfinal' => 1,
            'conditions' => ['match' => 'all', 'rules' => []],
        ]);
        $DB->insert_record('datalynx_behaviors', $behavior);

        $noteid = $this->create_field('text', 'Note');
        $plainid = $this->create_field('text', 'Plain');
        $viewrecord = $this->create_grid_view('<div>[[Note|editfinal]] [[Plain]] ##status##</div>');

        $view = $this->dlx->get_view('grid', $viewrecord);
        $allowed = $view->get_editable_after_final_fieldids();

        $this->assertSame([$noteid], $allowed);
        $this->assertNotContains($plainid, $allowed);
    }

    /**
     * Save gate: a non-manager author may update only the flagged field, never the unflagged one or status.
     */
    public function test_save_gate_persists_only_flagged_fields_for_author(): void {
        global $DB;

        $flagged = $this->create_field('text', 'Flagged');
        $unflagged = $this->create_field('text', 'Unflagged');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $entryid = $this->create_final_entry($student->id, [$flagged => 'oldflagged', $unflagged => 'oldunflagged']);

        $this->setUser($student);
        $result = $this->run_update($entryid, [
            "field_{$flagged}_{$entryid}" => 'newflagged',
            "field_{$unflagged}_{$entryid}" => 'newunflagged',
            'field_status_' . $entryid => datalynxfield_status::STATUS_DRAFT,
            'editablefinalfieldids' => [$flagged],
        ]);
        $this->assertNotEmpty($result);

        $this->assertEquals('newflagged', $this->content($flagged, $entryid));
        $this->assertEquals('oldunflagged', $this->content($unflagged, $entryid));
        $this->assertEquals(
            datalynxfield_status::STATUS_FINAL_SUBMISSION,
            (int) $DB->get_field('datalynx_entries', 'status', ['id' => $entryid])
        );
    }

    /**
     * Save gate: with no flagged fields the entry stays fully locked (historical behavior).
     */
    public function test_save_gate_blocks_when_no_field_flagged(): void {
        $field = $this->create_field('text', 'Locked');
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $entryid = $this->create_final_entry($student->id, [$field => 'original']);

        $this->setUser($student);
        $this->run_update($entryid, [
            "field_{$field}_{$entryid}" => 'changed',
            'editablefinalfieldids' => [],
        ]);

        $this->assertEquals('original', $this->content($field, $entryid));
    }

    /**
     * Save gate: a manager bypasses the lock entirely and can change every field and the status.
     */
    public function test_manager_bypasses_lock(): void {
        global $DB;

        $field = $this->create_field('text', 'Any');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $entryid = $this->create_final_entry($teacher->id, [$field => 'original']);

        $this->setUser($teacher);
        $this->run_update($entryid, [
            "field_{$field}_{$entryid}" => 'managerchanged',
            'field_status_' . $entryid => datalynxfield_status::STATUS_DRAFT,
            'editablefinalfieldids' => [],
        ]);

        $this->assertEquals('managerchanged', $this->content($field, $entryid));
        $this->assertEquals(
            datalynxfield_status::STATUS_DRAFT,
            (int) $DB->get_field('datalynx_entries', 'status', ['id' => $entryid])
        );
    }

    // --- helpers ---------------------------------------------------------------------------------

    /**
     * Run a confirmed update through the entries processor and return its result.
     *
     * @param int $entryid
     * @param array $data Posted form data (plus the editablefinalfieldids whitelist).
     * @return array
     */
    private function run_update(int $entryid, array $data): array {
        $filter = new datalynx_filter((object) ['dataid' => $this->dlx->id(), 'eids' => (string) $entryid]);
        // Load the entry together with its existing content so updates target existing rows.
        $filter->contentfields = array_values(array_filter(array_keys($this->dlx->get_fields()), 'is_numeric'));
        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();
        return $entries->process_entries('update', (string) $entryid, (object) $data, true);
    }

    /**
     * Create a field and return its id.
     *
     * @param string $type
     * @param string $name
     * @return int
     */
    private function create_field(string $type, string $name): int {
        global $DB;
        $record = (object) ['dataid' => $this->dlx->id(), 'type' => $type, 'name' => $name, 'description' => ''];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = '';
        }
        $id = (int) $DB->insert_record('datalynx_fields', $record);
        $this->dlx->get_fields(null, false, true);
        return $id;
    }

    /**
     * Create an approved, final-submission entry with the given text content.
     *
     * @param int $userid Author.
     * @param array $content fieldid => text content.
     * @return int Entry id.
     */
    private function create_final_entry(int $userid, array $content): int {
        global $DB;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $userid, 'groupid' => 0,
            'approved' => 1, 'status' => datalynxfield_status::STATUS_FINAL_SUBMISSION,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach ($content as $fieldid => $value) {
            $DB->insert_record('datalynx_contents',
                (object) ['fieldid' => $fieldid, 'entryid' => $entryid, 'lineid' => 0, 'content' => $value]);
        }
        return $entryid;
    }

    /**
     * Build a grid view with the given entry template (param2) and return its record.
     *
     * @param string $template
     * @return \stdClass
     */
    private function create_grid_view(string $template): \stdClass {
        global $DB;
        $view = (object) [
            'dataid' => $this->dlx->id(), 'type' => 'grid', 'name' => 'Grid', 'description' => '',
            'visible' => 7, 'filter' => 0, 'perpage' => 0, 'groupby' => '',
            'section' => '', 'param2' => $template, 'param5' => 0, 'param10' => 0,
        ];
        $view->id = (int) $DB->insert_record('datalynx_views', $view);
        return $view;
    }

    /**
     * Read a field's stored text content for an entry.
     *
     * @param int $fieldid
     * @param int $entryid
     * @return string|false
     */
    private function content(int $fieldid, int $entryid) {
        global $DB;
        return $DB->get_field('datalynx_contents', 'content',
            ['fieldid' => $fieldid, 'entryid' => $entryid, 'lineid' => 0]);
    }
}
