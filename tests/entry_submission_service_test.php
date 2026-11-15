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

namespace mod_datalynx;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');
require_once($CFG->libdir . '/formslib.php');

use advanced_testcase;
use mod_datalynx\local\entry\entry_submission_service;
use mod_datalynx\local\view\base;
use mod_datalynx\local\view\datalynxview_entries_form;
use moodle_exception;
use moodle_url;
use ReflectionClass;

/**
 * The entry submission service decides for the web form and the web services what is saved (#266).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\entry\entry_submission_service
 * @covers     \mod_datalynx\local\view\datalynxview_entries_form::validation
 */
final class entry_submission_service_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by name */
    private array $fieldids = [];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        // Ids repeat from test to test; the per-request condition cache must not carry over.
        \mod_datalynx\local\field\datalynxfield_behavior::reset_condition_cache();

        [$this->course, $this->dlx] = $this->create_mobile_instance();
        foreach (['Title', 'Notes', 'Secret', 'Elsewhere'] as $name) {
            $this->fieldids[$name] = $this->add_mobile_field($this->dlx, 'text', $name);
        }
        $this->add_mobile_behavior($this->dlx, 'Required', ['required' => 1]);
        $this->add_mobile_behavior($this->dlx, 'Read only', ['editable' => 0, 'editableby' => []]);
    }

    /**
     * A view whose entries can be added and edited, with the given entry template.
     *
     * @param string $entrytemplate
     * @return base
     */
    private function view(string $entrytemplate): base {
        global $DB;
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Edit', $entrytemplate . ' ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $this->dlx = new datalynx($this->dlx->id());

        return $this->dlx->get_view('grid', $DB->get_record('datalynx_views', ['id' => $viewid]));
    }

    /**
     * The element name of a field value.
     *
     * @param string $field field name
     * @param int|string $entryid
     * @return string
     */
    private function name(string $field, int|string $entryid): string {
        return "field_{$this->fieldids[$field]}_{$entryid}";
    }

    /**
     * Only fields the view offers and the behavior lets the user edit pass, and only for the
     * submitted entries.
     */
    public function test_filter_keeps_only_editable_fields_of_submitted_entries(): void {
        $service = new entry_submission_service($this->view('[[Title]] [[Notes]] [[Secret|Read only]]'));

        $data = (object) [
            $this->name('Title', -1) => 'Kept',
            $this->name('Notes', -1) . '_extra' => 'Kept too',
            $this->name('Secret', -1) => 'Not editable',
            $this->name('Elsewhere', -1) => 'Not in the view',
            $this->name('Title', 99) => 'Another entry',
            'field_garbage' => 'x',
            'submitbutton' => 'Save',
        ];

        $this->assertEquals((object) [
            $this->name('Title', -1) => 'Kept',
            $this->name('Notes', -1) . '_extra' => 'Kept too',
            'submitbutton' => 'Save',
        ], $service->filter_data([-1], $data));
    }

    /**
     * Several new entries are submitted as their negative count.
     */
    public function test_new_entries_are_expanded(): void {
        $this->assertSame([-1, -2, -3], entry_submission_service::expand_entry_ids(['-3']));
        $this->assertSame([5, 7], entry_submission_service::expand_entry_ids(['5', '7']));

        $service = new entry_submission_service($this->view('[[Title]]'));
        $filtered = $service->filter_data([-2], (object) [$this->name('Title', -1) => 'a', $this->name('Title', -2) => 'b']);
        $this->assertCount(2, (array) $filtered);
    }

    /**
     * A required field is an error whether it is submitted empty or left out of the request.
     */
    public function test_required_field_left_out_is_an_error(): void {
        $service = new entry_submission_service($this->view('[[Title|Required]] [[Notes]]'));
        $required = get_string('fieldrequired', 'datalynx');

        $this->assertSame([$this->name('Title', -1) => $required], $service->validate([-1], []));
        $this->assertSame([$this->name('Title', -1) => $required], $service->validate([-1], [$this->name('Title', -1) => '']));
        $this->assertSame([], $service->validate([-1], [$this->name('Title', -1) => 'Given']));
    }

    /**
     * A required field whose availability conditions are not met is not required.
     */
    public function test_required_field_hidden_by_conditions_is_not_required(): void {
        global $USER;
        $this->add_mobile_behavior($this->dlx, 'When on', [
            'required' => 1,
            'conditions' => ['match' => 'all', 'rules' => [
                ['sourcefieldid' => $this->fieldids['Notes'], 'not' => '', 'operator' => '=', 'value' => 'on'],
            ]],
        ]);
        $entryid = $this->add_mobile_entry($this->dlx, $USER->id, [$this->fieldids['Notes'] => 'off']);
        $service = new entry_submission_service($this->view('[[Title|When on]] [[Notes]]'));

        $this->assertSame([], $service->validate([$entryid], []));
    }

    /**
     * The entry form validates through the service, with the same result.
     */
    public function test_form_validation_matches_the_service(): void {
        $view = $this->view('[[Title|Required]] [[Notes]]');
        $data = ['new' => 1, $this->name('Notes', -1) => 'x'];

        $reflection = new ReflectionClass($view);
        $editentries = $reflection->getProperty('editentries');
        $editentries->setAccessible(true);
        $editentries->setValue($view, [-1]);
        $definition = $reflection->getMethod('set_display_definition');
        $definition->setAccessible(true);
        $definition->invoke($view);
        $form = new datalynxview_entries_form(new moodle_url('/mod/datalynx/view.php'), ['view' => $view, 'update' => '-1']);

        $service = new entry_submission_service($view);
        $this->assertSame($service->validate([-1], $data), $form->validation($data, []));
        $this->assertArrayHasKey($this->name('Title', -1), $form->validation($data, []));
    }

    /**
     * Saving stores what may be edited, ignores what was injected, and reports invalid input.
     */
    public function test_save(): void {
        global $DB;
        $service = new entry_submission_service($this->view('[[Title|Required]] [[Secret|Read only]]'));

        $result = $service->save([-1], (object) [$this->name('Secret', -1) => 'Injected']);
        $this->assertSame([], $result['entryids']);
        $this->assertArrayHasKey($this->name('Title', -1), $result['errors']);

        $result = $service->save([-1], (object) [
            $this->name('Title', -1) => 'Saved',
            $this->name('Secret', -1) => 'Injected',
            $this->name('Elsewhere', -1) => 'Injected',
        ]);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['entryids']);
        $entryid = $result['entryids'][0];
        $contents = $DB->get_records_menu('datalynx_contents', ['entryid' => $entryid], '', 'fieldid, content');
        $this->assertSame([$this->fieldids['Title'] => 'Saved'], $contents);
    }

    /**
     * A view without an edit action does not let the service save existing entries.
     */
    public function test_save_requires_the_view_to_allow_editing(): void {
        global $DB, $USER;
        $entryid = $this->add_mobile_entry($this->dlx, $USER->id, [$this->fieldids['Title'] => 'Old']);
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Read', '[[Title]]');
        $view = (new datalynx($this->dlx->id()))->get_view('grid', $DB->get_record('datalynx_views', ['id' => $viewid]));

        $this->expectException(moodle_exception::class);
        (new entry_submission_service($view))->save([$entryid], (object) ["field_{$this->fieldids['Title']}_{$entryid}" => 'New']);
    }

    /**
     * Fieldgroups: required lines need every subfield, filled lines are validated by their subfields'
     * behaviors, and the hidden empty lines the form posts are not validated.
     */
    public function test_fieldgroup_lines(): void {
        $amountid = $this->add_mobile_field($this->dlx, 'text', 'Amount');
        $noteid = $this->add_mobile_field($this->dlx, 'text', 'Note');
        $groupid = $this->add_mobile_field($this->dlx, 'fieldgroup', 'Rows', [
            'param1' => json_encode(["$amountid:|Required|", "$noteid:||"]),
            'param2' => 4,
            'param3' => 2,
            'param4' => 1,
        ]);
        $service = new entry_submission_service($this->view('[[Rows]]'));
        $line = fn(int $fieldid, int $i) => "field_{$fieldid}_-1_fieldgroup_{$groupid}_{$i}";
        $required = get_string('fieldrequired', 'datalynx');

        // Line 0 is required: both subfields. Lines 1-3 are posted empty and not checked.
        $errors = $service->validate([-1], [
            $line($amountid, 0) => '', $line($noteid, 0) => '',
            $line($amountid, 1) => '', $line($noteid, 1) => '',
            $line($amountid, 2) => '', $line($noteid, 2) => '',
        ]);
        $this->assertSame([$line($amountid, 0) => $required, $line($noteid, 0) => $required], $errors);

        // A filled optional line: its subfield with a required behavior needs a value.
        $errors = $service->validate([-1], [
            $line($amountid, 0) => '5', $line($noteid, 0) => 'first',
            $line($amountid, 1) => '', $line($noteid, 1) => 'second',
        ]);
        $this->assertSame([$line($amountid, 1) => $required], $errors);

        // Subfield values pass the guard only as lines of the fieldgroup.
        $filtered = $service->filter_data([-1], (object) [$line($amountid, 0) => '5', "field_{$amountid}_-1" => 'no']);
        $this->assertSame([$line($amountid, 0) => '5'], (array) $filtered);
    }

    /**
     * The placeholders the form posts for an empty selection count as empty, wherever they sit; a
     * single real choice does not (it used to, so a fieldgroup line holding it was dropped).
     */
    public function test_empty_selection_placeholders(): void {
        $this->assertTrue(\datalynxfield_multiselect\field::is_fieldvalue_empty([-1 => '-999']));
        $this->assertTrue(\datalynxfield_multiselect\field::is_fieldvalue_empty(''));
        $this->assertFalse(\datalynxfield_multiselect\field::is_fieldvalue_empty(['3']));
        $this->assertFalse(\datalynxfield_multiselect\field::is_fieldvalue_empty([-1 => '-999', 0 => '2']));

        $this->assertTrue(\datalynxfield_teammemberselect\field::is_fieldvalue_empty([-1 => '-999']));
        $this->assertTrue(\datalynxfield_teammemberselect\field::is_fieldvalue_empty([]));
        $this->assertFalse(\datalynxfield_teammemberselect\field::is_fieldvalue_empty([-1 => '-999', 0 => '7']));
    }

    /**
     * Required is decided by the service for every field type; each field says when its value is
     * empty and where the error shows.
     *
     * @dataProvider required_field_provider
     * @param string $type field type
     * @param array $params field settings
     * @param string $suffix what follows field_{fieldid}_{entryid} in the error element ('grp…' prefixes)
     * @param array $empty submission values that leave the field empty, by element suffix
     * @param array $filled submission values that fill it, by element suffix
     */
    public function test_required_per_field_type(string $type, array $params, string $suffix, array $empty, array $filled): void {
        $fieldid = $this->add_mobile_field($this->dlx, $type, 'Typed', $params);
        $service = new entry_submission_service($this->view('[[Typed|Required]]'));
        $name = "field_{$fieldid}_-1";
        $element = str_starts_with($suffix, 'grp') ? "grp{$name}" : $name . $suffix;
        $values = function (array $bysuffix) use ($name): array {
            $data = [];
            foreach ($bysuffix as $key => $value) {
                $data[$name . $key] = $value;
            }
            return $data;
        };

        $errors = $service->validate([-1], $values($empty));
        $this->assertArrayHasKey($element, $errors, json_encode($errors));
        $this->assertCount(1, $errors);
        $this->assertSame([], $service->validate([-1], $values($filled)));
        // Left out entirely is as empty as submitted empty.
        $this->assertArrayHasKey($element, $service->validate([-1], []));
    }

    /**
     * Field types with their empty and filled submissions.
     *
     * @return array
     */
    public static function required_field_provider(): array {
        $options = ['param1' => "One\nTwo\nThree"];
        return [
            'text' => ['text', [], '', ['' => '  '], ['' => 'x']],
            'number' => ['number', [], '', ['' => ''], ['' => '0']],
            'select' => ['select', $options, '', ['' => '0'], ['' => '2']],
            'radiobutton' => ['radiobutton', $options, '_group', [], ['' => '1']],
            'multiselect' => ['multiselect', $options, '', ['' => [-1 => '-999']], ['' => [-1 => '-999', 0 => '3']]],
            'checkbox' => ['checkbox', $options, '', ['' => [1 => '', 2 => '', 3 => '']], ['' => [1 => '', 2 => '2', 3 => '']]],
            'url' => ['url', [], '_grp', ['_url' => '', '_alt' => 'Link'], ['_url' => 'https://example.org']],
            'editor' => ['editor', [], '_editor', ['_editor' => ['text' => '<p></p>', 'format' => 1]],
                ['_editor' => ['text' => '<p>Hi</p>', 'format' => 1]]],
            'time' => ['time', [], '', ['' => 0], ['' => 1700000000]],
            'teammemberselect' => ['teammemberselect', ['param1' => 5, 'param3' => 1], '', ['' => [-1 => '-999']],
                ['' => [-1 => '-999', 0 => '2']]],
            'location' => ['location', [], '_address', ['_address' => '', '_lat' => '', '_lng' => ''],
                ['_address' => 'Graz', '_lat' => '47.07', '_lng' => '15.44']],
        ];
    }

    /**
     * A number that is given must be a number, required or not.
     */
    public function test_number_must_be_numeric_when_given(): void {
        $fieldid = $this->add_mobile_field($this->dlx, 'number', 'Amount');
        $service = new entry_submission_service($this->view('[[Amount]]'));

        $this->assertSame([], $service->validate([-1], ["field_{$fieldid}_-1" => '']));
        $this->assertSame(
            ["field_{$fieldid}_-1" => get_string('errnumeric', 'datalynx')],
            $service->validate([-1], ["field_{$fieldid}_-1" => 'ten'])
        );
    }

    /**
     * Saving runs the fields' after-save step only for a submission that is saved.
     */
    public function test_after_save_step_runs_only_for_saved_entries(): void {
        global $DB, $USER;
        $proffield = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'phone', 'name' => 'Phone',
        ]);
        \mod_datalynx\local\field_format\manager::save_format((object) [
            'dataid' => $this->dlx->id(),
            'name' => 'myphone',
            'fieldtype' => 'entryauthor',
            'settings' => json_encode(['option' => 'phone', 'editable' => 1, 'mandatory' => 0]),
        ]);
        $service = new entry_submission_service($this->view('[[Title|Required]] ##author:myphone##'));
        $phone = fn() => $DB->get_field('user_info_data', 'data', ['userid' => $USER->id, 'fieldid' => $proffield->id]);

        // The title is missing: nothing is saved, the profile stays as it was.
        $result = $service->save([-1], (object) ['field_myphone_-1' => '0316 1234']);
        $this->assertNotEmpty($result['errors']);
        $this->assertFalse($phone());

        $result = $service->save([-1], (object) ['field_myphone_-1' => '0316 1234', $this->name('Title', -1) => 'Saved']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('0316 1234', $phone());
    }

    /**
     * The course group field shows nothing where its behavior hides it, and no input where it is
     * not editable, like every other field.
     */
    public function test_coursegroup_follows_behaviors(): void {
        $this->add_mobile_behavior($this->dlx, 'Hidden', ['visibletopermission' => []]);
        $fieldid = $this->add_mobile_field($this->dlx, 'coursegroup', 'Group');
        $field = (new datalynx($this->dlx->id()))->get_field_from_id($fieldid);
        $entry = (object) ['id' => -1];

        $editable = $field->renderer()->replacements(['[[Group]]'], $entry, ['edit' => true]);
        $this->assertIsArray($editable['[[Group]]'][1]);

        $this->setUser($this->create_mobile_student($this->course));
        $hidden = $field->renderer()->replacements(['[[Group|Hidden]]'], $entry, ['edit' => true]);
        $this->assertSame(['html', ''], $hidden['[[Group|Hidden]]']);
        $readonly = $field->renderer()->replacements(['[[Group|Read only]]'], $entry, ['edit' => true]);
        $this->assertSame('html', $readonly['[[Group|Read only]]'][0]);
    }

    /**
     * The upgrade turns the legacy ##*status## tag into ##status##, also inside serialized settings.
     */
    public function test_upgrade_replaces_legacy_required_status_tag(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/datalynx/db/upgrade.php');
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Old', '<div>##*status##</div>', [
            'section' => '##*status## ##entries##',
            'param5' => serialize(['tpl' => 'x ##*status## y']),
            'patterns' => 'cached',
        ]);

        mod_datalynx_upgrade_replace_view_tag('##*status##', '##status##');

        $view = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertSame('<div>##status##</div>', $view->param2);
        $this->assertSame('##status## ##entries##', $view->section);
        $this->assertSame(['tpl' => 'x ##status## y'], unserialize($view->param5));
        $this->assertNull($view->patterns);
    }
}
