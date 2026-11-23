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

namespace mod_datalynx\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use core_external\external_api;
use externallib_advanced_testcase;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;
use moodle_exception;
use required_capability_exception;

/**
 * Tests for adding and editing entries through a web service (#266).
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\external\submit_entry
 */
final class submit_entry_test extends externallib_advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by name */
    private array $fieldids = [];

    /** @var int a view to add and edit entries through */
    private int $viewid;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        \mod_datalynx\local\field\datalynxfield_behavior::reset_condition_cache();

        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->fieldids['Title'] = $this->add_mobile_field($this->dlx, 'text', 'Title');
        $this->fieldids['Choice'] = $this->add_mobile_field($this->dlx, 'select', 'Choice', ['param1' => "One\nTwo"]);
        $this->fieldids['Tags'] = $this->add_mobile_field($this->dlx, 'multiselect', 'Tags', ['param1' => "Red\nBlue"]);
        $this->fieldids['Body'] = $this->add_mobile_field($this->dlx, 'editor', 'Body');
        $this->fieldids['Secret'] = $this->add_mobile_field($this->dlx, 'text', 'Secret');
        $this->fieldids['Elsewhere'] = $this->add_mobile_field($this->dlx, 'text', 'Elsewhere');
        // Editable by students: for a new entry the author permission does not apply yet.
        $this->add_mobile_behavior($this->dlx, 'Required', ['required' => 1, 'editableby' => [
            datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR,
        ]]);
        $this->add_mobile_behavior($this->dlx, 'Read only', ['editable' => 0, 'editableby' => []]);
        $this->viewid = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Edit',
            '[[Title|Required]] [[Choice]] [[Tags]] [[Body]] [[Secret|Read only]] ##edit##',
            ['section' => '##addnewentry## ##entries##']
        );
    }

    /**
     * Call the service and clean the result against its returns.
     *
     * @param array $data structured values
     * @param int $entryid
     * @param int|null $viewid
     * @return array
     */
    private function call(array $data, int $entryid = 0, ?int $viewid = null): array {
        $result = submit_entry::execute($this->dlx->id(), $viewid ?? $this->viewid, $entryid, $data);

        return external_api::clean_returnvalue(submit_entry::execute_returns(), $result);
    }

    /**
     * One structured value.
     *
     * @param string $field field name
     * @param mixed $value a string, or a list/structure that is sent as JSON
     * @param string $name the input of the field
     * @return array
     */
    private function value(string $field, $value, string $name = ''): array {
        return ['fieldid' => (string) $this->fieldids[$field], 'name' => $name,
            'value' => is_array($value) ? json_encode($value) : (string) $value];
    }

    /**
     * The stored contents of an entry, by field name.
     *
     * @param int $entryid
     * @return array
     */
    private function contents(int $entryid): array {
        global $DB;
        $names = array_flip($this->fieldids);
        $contents = [];
        foreach ($DB->get_records('datalynx_contents', ['entryid' => $entryid]) as $content) {
            $contents[$names[$content->fieldid]] = $content->content;
        }
        ksort($contents);

        return $contents;
    }

    /**
     * The function is in the Moodle App's service.
     */
    public function test_function_is_in_the_mobile_service(): void {
        global $DB;
        $service = $DB->get_record('external_services', ['shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE], '*', MUST_EXIST);

        $this->assertTrue($DB->record_exists('external_services_functions', [
            'externalserviceid' => $service->id, 'functionname' => 'mod_datalynx_submit_entry',
        ]));
    }

    /**
     * A student adds an entry; what the view does not let them edit is not stored.
     */
    public function test_student_adds_an_entry(): void {
        global $DB;
        $student = $this->create_mobile_student($this->course);
        $this->setUser($student);

        $result = $this->call([
            $this->value('Title', 'From the app'),
            $this->value('Choice', '2'),
            $this->value('Tags', [-1 => '-999', 0 => '1', 1 => '2']),
            $this->value('Body', ['text' => '<p>Hello</p>', 'format' => FORMAT_HTML], 'editor'),
            $this->value('Secret', 'Injected'),
            $this->value('Elsewhere', 'Not in the view'),
        ]);

        $this->assertTrue($result['status'], json_encode($result));
        $this->assertSame([], $result['errors']);
        $entry = $DB->get_record('datalynx_entries', ['id' => $result['entryid']], '*', MUST_EXIST);
        $this->assertEquals($student->id, $entry->userid);
        $contents = $this->contents($result['entryid']);
        $this->assertSame('From the app', $contents['Title']);
        $this->assertSame('2', $contents['Choice']);
        $this->assertSame('#1#,#2#', $contents['Tags']);
        $this->assertStringContainsString('Hello', $contents['Body']);
        $this->assertArrayNotHasKey('Secret', $contents);
        $this->assertArrayNotHasKey('Elsewhere', $contents);
    }

    /**
     * A required field left out is an error, with its field; nothing is saved.
     */
    public function test_required_field_left_out(): void {
        global $DB;
        $before = $DB->count_records('datalynx_entries', ['dataid' => $this->dlx->id()]);

        $result = $this->call([$this->value('Choice', '1')]);

        $this->assertFalse($result['status']);
        $this->assertSame(0, $result['entryid']);
        $this->assertSame((string) $this->fieldids['Title'], $result['errors'][0]['fieldid']);
        $this->assertSame(get_string('fieldrequired', 'datalynx'), $result['errors'][0]['message']);
        $this->assertSame($before, $DB->count_records('datalynx_entries', ['dataid' => $this->dlx->id()]));
    }

    /**
     * The author edits their entry; another user cannot.
     */
    public function test_edit_own_entry_only(): void {
        $author = $this->create_mobile_student($this->course);
        $other = $this->create_mobile_student($this->course);
        $this->setUser($author);
        $created = $this->call([$this->value('Title', 'First')]);
        $this->assertTrue($created['status'], json_encode($created));
        $entryid = $created['entryid'];

        $result = $this->call([$this->value('Title', 'Changed')], $entryid);
        $this->assertTrue($result['status'], json_encode($result));
        $this->assertSame($entryid, $result['entryid']);
        $this->assertSame('Changed', $this->contents($entryid)['Title']);

        $this->setUser($other);
        $result = $this->call([$this->value('Title', 'Taken over')], $entryid);
        $this->assertFalse($result['status']);
        $this->assertSame('notsaved', $result['warnings'][0]['warningcode']);
        $this->assertSame('Changed', $this->contents($entryid)['Title']);
    }

    /**
     * A view without an edit action does not let entries be edited through it.
     */
    public function test_view_without_edit_action(): void {
        $entryid = $this->call([$this->value('Title', 'First')])['entryid'];
        $readonly = $this->add_mobile_view($this->dlx, 'grid', 'Read', '[[Title]]');

        $this->expectException(moodle_exception::class);
        $this->call([$this->value('Title', 'Changed')], $entryid, $readonly);
    }

    /**
     * Writing entries requires the capability.
     */
    public function test_requires_writeentry(): void {
        global $DB;
        $student = $this->create_mobile_student($this->course);
        $role = $DB->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('mod/datalynx:writeentry', CAP_PROHIBIT, $role, $this->dlx->context->id, true);
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        $this->call([$this->value('Title', 'No')]);
    }

    /**
     * Malformed input is refused rather than guessed at.
     */
    public function test_malformed_input_is_refused(): void {
        try {
            $this->call([$this->value('Tags', 'not json')]);
            $this->fail('A list input takes JSON');
        } catch (invalid_parameter_exception $e) {
            $this->assertStringContainsString('JSON', $e->debuginfo);
        }
        try {
            $this->call([['fieldid' => '999999', 'name' => '', 'value' => 'x']]);
            $this->fail('An unknown field is refused');
        } catch (invalid_parameter_exception $e) {
            $this->assertStringContainsString('Unknown field', $e->debuginfo);
        }
        $this->expectException(invalid_parameter_exception::class);
        $this->call([$this->value('Title', 'a'), $this->value('Title', 'b')]);
    }

    /**
     * Subfields of a fieldgroup are submitted line by line.
     */
    public function test_fieldgroup_lines(): void {
        global $DB;
        $amountid = $this->add_mobile_field($this->dlx, 'number', 'Amount');
        $groupid = $this->add_mobile_field($this->dlx, 'fieldgroup', 'Rows', [
            'param1' => json_encode(["$amountid:||"]),
            'param2' => 3,
            'param3' => 1,
            'param4' => 0,
        ]);
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Rows', '[[Title]] [[Rows]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $line = fn(int $i, string $value) => ['fieldid' => (string) $amountid, 'name' => '', 'fieldgroupid' => $groupid,
            'line' => $i, 'value' => $value];

        $result = $this->call([$this->value('Title', 'Rows'), $line(0, '5'), $line(1, '7')], 0, $viewid);
        $this->assertTrue($result['status'], json_encode($result));
        $amounts = $DB->get_fieldset_select(
            'datalynx_contents',
            'content',
            'entryid = ? AND fieldid = ?',
            [$result['entryid'], $amountid]
        );
        sort($amounts);
        $this->assertSame(['5', '7'], $amounts);

        $result = $this->call([$this->value('Title', 'Rows'), $line(0, 'five')], 0, $viewid);
        $this->assertFalse($result['status']);
        $located = array_intersect_key($result['errors'][0], array_flip(['fieldid', 'name', 'fieldgroupid', 'line']));
        $this->assertSame(['fieldid' => (string) $amountid, 'name' => '', 'fieldgroupid' => $groupid, 'line' => 0], $located);
    }

    /**
     * A client cannot add options to a multiple choice field that does not allow it; where the tag
     * allows adding options (:addnew), new ones are added.
     */
    public function test_options_are_added_only_where_allowed(): void {
        global $DB;
        $tagsid = $this->fieldids['Tags'];

        $result = $this->call([$this->value('Title', 'x'), $this->value('Tags', ['2', '7', 'Green'])]);
        $this->assertTrue($result['status']);
        $this->assertSame('#2#', $this->contents($result['entryid'])['Tags']);
        $this->assertSame("Red\nBlue", $DB->get_field('datalynx_fields', 'param1', ['id' => $tagsid]));

        $DB->set_field('datalynx_fields', 'param6', 1, ['id' => $tagsid]);
        \mod_datalynx\local\definition_cache::purge();
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Add new', '[[Title]] [[Tags:addnew]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $result = $this->call([$this->value('Title', 'y'), $this->value('Tags', ['1', 'Green'])], 0, $viewid);
        $this->assertTrue($result['status'], json_encode($result));
        $this->assertSame("Red\nBlue\nGreen", $DB->get_field('datalynx_fields', 'param1', ['id' => $tagsid]));
    }

    /**
     * Internal fields take a value only through a tag that is an input: a manager can reassign the
     * author through an author format set to "edit", not through a view that only shows ##author:name##.
     */
    public function test_internal_fields_only_through_input_tags(): void {
        global $DB;
        $student = $this->create_mobile_student($this->course);
        $display = $this->add_mobile_view($this->dlx, 'grid', 'Shows author', '[[Title]] ##author:name## ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        \mod_datalynx\local\field_format\manager::save_format((object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Owner',
            'fieldtype' => 'entryauthor',
            'settings' => json_encode(['option' => 'edit']),
        ]);
        $edit = $this->add_mobile_view($this->dlx, 'grid', 'Edits author', '[[Title]] ##author:Owner## ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        // The internal field the edit tag belongs to, as the entry form names its element.
        $editview = $this->dlx->get_view('grid', $DB->get_record('datalynx_views', ['id' => $edit]));
        $authorfield = null;
        foreach ($editview->get_entry_form_patterns() as $fieldid => $tags) {
            if (in_array('##author:Owner##', (array) $tags, true)) {
                $authorfield = (string) $fieldid;
            }
        }
        $this->assertNotNull($authorfield);
        $author = fn(int $viewid) => $this->call([
            $this->value('Title', 'x'),
            ['fieldid' => $authorfield, 'name' => '', 'value' => (string) $student->id],
        ], 0, $viewid)['entryid'];

        $this->assertEquals(get_admin()->id, $DB->get_field('datalynx_entries', 'userid', ['id' => $author($display)]));
        $this->assertEquals($student->id, $DB->get_field('datalynx_entries', 'userid', ['id' => $author($edit)]));
    }

    /**
     * A key that only contains "field_" is not taken for a field value by process_entries().
     */
    public function test_process_entries_reads_only_field_keys(): void {
        $view = $this->dlx->get_view('grid', $GLOBALS['DB']->get_record('datalynx_views', ['id' => $this->viewid]));
        $titleid = $this->fieldids['Title'];

        [, $ids] = $view->get_entries_manager()->process_entries('update', '-1', (object) [
            "field_{$titleid}_-1" => 'Kept',
            "xfield_{$this->fieldids['Elsewhere']}_-1" => 'Not a field value',
        ], true);

        $this->assertSame(['Title' => 'Kept'], $this->contents((int) reset($ids)));
    }

    /**
     * The count of a user's entries includes an entry added in the same request.
     */
    public function test_entry_count_is_current(): void {
        $student = $this->create_mobile_student($this->course);
        $this->setUser($student);
        $this->assertSame(0, $this->dlx->user_num_entries());

        $this->call([$this->value('Title', 'One')]);

        $this->assertSame(1, $this->dlx->user_num_entries());
    }

    /**
     * Empty items of a list are no options (#272).
     */
    public function test_list_ignores_empty_items(): void {
        $result = $this->call([$this->value('Title', 'List'), $this->value('Tags', ['1', '', '2'])]);

        $this->assertTrue($result['status'], json_encode($result));
        $this->assertSame('#1#,#2#', $this->contents($result['entryid'])['Tags']);
    }

    /**
     * An empty submission is an error at the required field, without a PHP warning.
     */
    public function test_empty_submission(): void {
        $this->expectOutputString('');

        $result = $this->call([]);

        $this->assertFalse($result['status']);
        $this->assertSame((string) $this->fieldids['Title'], $result['errors'][0]['fieldid']);
    }

    /**
     * Adding fires entry_created, editing entry_updated: rules and notifications run as on the web.
     */
    public function test_events(): void {
        $sink = $this->redirectEvents();
        $entryid = $this->call([$this->value('Title', 'First')])['entryid'];
        $this->call([$this->value('Title', 'Changed')], $entryid);
        $events = array_map(fn($event) => get_class($event), $sink->get_events());
        $sink->close();

        $this->assertContains(\mod_datalynx\event\entry_created::class, $events);
        $this->assertContains(\mod_datalynx\event\entry_updated::class, $events);
    }

    /**
     * Draft to final submission stamps timesubmitted once; the final submission is locked for its
     * author.
     */
    public function test_final_submission(): void {
        global $DB;

        $statusview = $this->add_mobile_view($this->dlx, 'grid', 'Submit', '[[Title|Required]] ##status## ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $status = fn(int $value) => ['fieldid' => 'status', 'name' => '', 'value' => (string) $value];
        $student = $this->create_mobile_student($this->course);
        $this->setUser($student);

        $entryid = $this->call([$this->value('Title', 'Draft'), $status(1)], 0, $statusview)['entryid'];
        $this->assertEmpty($DB->get_field('datalynx_entries', 'timesubmitted', ['id' => $entryid]));

        $result = $this->call([$this->value('Title', 'Final'), $status(2)], $entryid, $statusview);
        $this->assertTrue($result['status'], json_encode($result));
        $submitted = $DB->get_field('datalynx_entries', 'timesubmitted', ['id' => $entryid]);
        $this->assertNotEmpty($submitted);

        // Locked for the author now.
        $result = $this->call([$this->value('Title', 'Changed after'), $status(2)], $entryid, $statusview);
        $this->assertFalse($result['status']);
        $this->assertSame('Final', $this->contents($entryid)['Title']);

        // A manager edits it, still final: submitted stays the first time.
        $this->setAdminUser();
        $DB->set_field('datalynx_entries', 'timesubmitted', $submitted - 100, ['id' => $entryid]);
        $this->assertTrue($this->call([$this->value('Title', 'Corrected'), $status(2)], $entryid, $statusview)['status']);
        $this->assertEquals($submitted - 100, $DB->get_field('datalynx_entries', 'timesubmitted', ['id' => $entryid]));
    }
}
