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

namespace mod_datalynx\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;

/**
 * The edit screen of the Moodle App (mod_datalynx#273).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\output\mobile::mobile_edit_entry
 * @covers     \mod_datalynx\local\entry\entry_edit_form::submission
 */
final class mobile_edit_entry_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by name */
    private array $ids = [];

    /** @var int */
    private int $viewid;

    /** @var \stdClass */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        \mod_datalynx\local\field\datalynxfield_behavior::reset_condition_cache();

        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->ids['Title'] = $this->add_mobile_field($this->dlx, 'text', 'Title');
        $this->ids['Tags'] = $this->add_mobile_field($this->dlx, 'multiselect', 'Tags', ['param1' => "Red\nBlue\nGreen"]);
        $this->ids['When'] = $this->add_mobile_field($this->dlx, 'time', 'When', ['param1' => 1]);
        $this->ids['Length'] = $this->add_mobile_field($this->dlx, 'duration', 'Length');
        $this->ids['Notes'] = $this->add_mobile_field($this->dlx, 'editor', 'Notes');
        $students = [datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR];
        $this->add_mobile_behavior($this->dlx, 'Required', ['required' => 1, 'editableby' => $students]);
        $this->viewid = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Edit',
            '[[Title|Required]] [[Tags]] [[When]] [[Length]] ##status## ##edit##',
            ['section' => '##addnewentry## ##entries##']
        );
        $this->student = $this->create_mobile_student($this->course);
        $this->dlx = new datalynx($this->dlx->id());
        $this->setUser($this->student);
    }

    /**
     * Open (or submit) the edit screen as the app does; arguments are strings.
     *
     * @param array $args
     * @return array the page state
     */
    private function page(array $args): array {
        $result = mobile::mobile_edit_entry(array_map('strval', $args + [
            'cmid' => $this->dlx->cm->id, 'courseid' => $this->course->id, 'viewid' => $this->viewid, 'entryid' => 0,
        ]));
        $this->assertSame('main', $result['templates'][0]['id']);
        $this->assertStringNotContainsString('<%', $result['templates'][0]['html']);

        return json_decode($result['otherdata']['state'], true);
    }

    /**
     * The form's input names by field name.
     *
     * @param array $state
     * @return array field name => input name => form name
     */
    private static function names(array $state): array {
        $names = [];
        foreach ($state['fields'] as $field) {
            foreach ($field['inputs'] as $input) {
                $names[$field['name']][$input['name']] = $input['formname'];
            }
        }
        return $names;
    }

    /**
     * Adding: the form with a day input, minutes for a duration, draft and final.
     */
    public function test_add_form(): void {
        $state = $this->page([]);

        $this->assertTrue($state['allowed']);
        $this->assertTrue($state['supported']);
        $this->assertTrue($state['hasstatus']);
        $this->assertSame(['Title', 'Tags', 'When', 'Length', 'Status'], array_column($state['fields'], 'name'));
        $this->assertSame("ef_{$this->ids['Title']}__", self::names($state)['Title']['']);
        $this->assertFalse($state['saved']);
    }

    /**
     * Saving a new entry: lists, a day, minutes and the status as the app sends them.
     */
    public function test_save_new_entry(): void {
        global $DB;
        $names = self::names($this->page([]));

        $state = $this->page([
            'save' => 1, 'status' => 1,
            $names['Title'][''] => 'Pear',
            $names['Tags'][''] => '1,3',
            $names['When'][''] => '2026-01-10',
            $names['Length'][''] => '90',
        ]);

        $this->assertTrue($state['saved'], json_encode($state['message']));
        $this->assertGreaterThan(0, $state['entryid']);
        $contents = $DB->get_records_menu('datalynx_contents', ['entryid' => $state['entryid']], '', 'fieldid, content');
        $this->assertSame('Pear', $contents[$this->ids['Title']]);
        $this->assertSame('#1#,#3#', $contents[$this->ids['Tags']]);
        $this->assertEquals(make_timestamp(2026, 1, 10, 0, 0, 0), $contents[$this->ids['When']]);
        $this->assertEquals(5400, $contents[$this->ids['Length']]);
        $this->assertEquals(
            \datalynxfield_status\field::STATUS_DRAFT,
            $DB->get_field('datalynx_entries', 'status', ['id' => $state['entryid']])
        );
        // The saved draft is shown again for editing, its values as the inputs show them.
        $fields = array_column($state['fields'], null, 'name');
        $this->assertSame('2026-01-10', $fields['When']['inputs'][0]['show']);
        $this->assertSame('90', $fields['Length']['inputs'][0]['show']);
        $this->assertSame(['1', '3'], $fields['Tags']['inputs'][0]['show']);
    }

    /**
     * Submit: the entry becomes a final submission, and is then locked for its author.
     */
    public function test_submit_final(): void {
        global $DB;
        $names = self::names($this->page([]));

        $state = $this->page(['save' => 1, 'status' => 2, $names['Title'][''] => 'Final']);

        $this->assertTrue($state['saved'], json_encode($state['message']));
        $entry = $DB->get_record('datalynx_entries', ['id' => $state['entryid']]);
        $this->assertEquals(\datalynxfield_status\field::STATUS_FINAL_SUBMISSION, $entry->status);
        $this->assertNotEmpty($entry->timesubmitted);
        $this->assertFalse($state['allowed']);
    }

    /**
     * A required field left empty: nothing is saved, the error is at the field, and the values
     * entered stay.
     */
    public function test_save_with_error_keeps_values(): void {
        global $DB;
        $names = self::names($this->page([]));
        $before = $DB->count_records('datalynx_entries');

        $state = $this->page(['save' => 1, 'status' => 1, $names['Title'][''] => '', $names['Tags'][''] => '2']);

        $this->assertFalse($state['saved']);
        $this->assertSame($before, $DB->count_records('datalynx_entries'));
        $this->assertSame(get_string('mobilecheckentry', 'datalynx'), $state['message']);
        $fields = array_column($state['fields'], null, 'name');
        $this->assertNotSame('', $fields['Title']['error']);
        $this->assertSame(['2'], $fields['Tags']['inputs'][0]['show']);
    }

    /**
     * Editing someone else's entry is not allowed; a required field without a control sends the
     * user to the web.
     */
    public function test_not_allowed_and_unsupported(): void {
        $other = $this->create_mobile_student($this->course);
        $theirs = $this->add_mobile_entry($this->dlx, $other->id, [$this->ids['Title'] => 'Theirs']);

        $state = $this->page(['entryid' => $theirs]);
        $this->assertFalse($state['allowed']);
        $this->assertNotSame('', $state['denyreason']);

        // Saving is refused as well.
        $state = $this->page(['entryid' => $theirs, 'save' => 1, "ef_{$this->ids['Title']}__" => 'Taken']);
        $this->assertFalse($state['saved']);

        $notes = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Notes',
            '[[Title]] [[Notes|Required]] ##edit##',
            ['section' => '##addnewentry## ##entries##']
        );
        $state = $this->page(['viewid' => $notes]);
        $this->assertFalse($state['supported']);
        $this->assertSame('Notes', $state['unsupportedlist']);
    }
}
