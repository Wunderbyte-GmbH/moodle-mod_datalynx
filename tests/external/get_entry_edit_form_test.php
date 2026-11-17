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
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;

/**
 * The form to add or edit an entry (mod_datalynx#272).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\external\get_entry_edit_form
 * @covers     \mod_datalynx\local\entry\entry_edit_form
 */
final class get_entry_edit_form_test extends externallib_advanced_testcase {
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
        $this->ids['Tags'] = $this->add_mobile_field($this->dlx, 'multiselect', 'Tags', ['param1' => "Red\nBlue"]);
        $this->ids['When'] = $this->add_mobile_field($this->dlx, 'time', 'When');
        $this->ids['Secret'] = $this->add_mobile_field($this->dlx, 'text', 'Secret');
        $this->ids['Notes'] = $this->add_mobile_field($this->dlx, 'editor', 'Notes');
        $students = [datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR];
        $this->add_mobile_behavior($this->dlx, 'Required', ['required' => 1, 'editableby' => $students]);
        $this->add_mobile_behavior($this->dlx, 'Read only', ['editable' => 0, 'editableby' => []]);
        $this->viewid = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Edit',
            '[[Title|Required]] [[Tags]] [[When]] [[Secret|Read only]] [[Notes]] ##edit##',
            ['section' => '##addnewentry## ##entries##']
        );
        $this->student = $this->create_mobile_student($this->course);
        $this->dlx = new datalynx($this->dlx->id());
    }

    /**
     * Call the service and clean the result against its returns.
     *
     * @param int $entryid
     * @param int|null $viewid
     * @return array
     */
    private function call(int $entryid, ?int $viewid = null): array {
        $result = get_entry_edit_form::execute($this->dlx->id(), $viewid ?? $this->viewid, $entryid);

        return external_api::clean_returnvalue(get_entry_edit_form::execute_returns(), $result);
    }

    /**
     * The fields of a form by name.
     *
     * @param array $form
     * @return array
     */
    private static function fields(array $form): array {
        return array_column($form['fields'], null, 'name');
    }

    /**
     * The function is in the Moodle App's service.
     */
    public function test_function_is_in_the_mobile_service(): void {
        global $DB;

        $this->assertTrue($DB->record_exists_sql(
            "SELECT 1
               FROM {external_services_functions} sf
               JOIN {external_services} s ON s.id = sf.externalserviceid
              WHERE s.shortname = :service AND sf.functionname = :function",
            ['service' => MOODLE_OFFICIAL_MOBILE_SERVICE, 'function' => 'mod_datalynx_get_entry_edit_form']
        ));
    }

    /**
     * A new entry: the editable fields with their controls; the read-only one is not shown; an
     * editor, which has no control, is listed as unsupported, and the form stays usable because
     * it is not required.
     */
    public function test_new_entry_form(): void {
        $this->setUser($this->student);

        $form = $this->call(0);

        $this->assertTrue($form['allowed']);
        $this->assertSame([0, -1], [$form['entryid'], $form['formentryid']]);
        $fields = self::fields($form);
        $this->assertSame(['Title', 'Tags', 'When'], array_keys($fields));
        $this->assertSame('text', $fields['Title']['control']);
        $this->assertTrue($fields['Title']['required']);
        $this->assertSame([['name' => '', 'list' => true, 'value' => '[]']], $fields['Tags']['inputs']);
        $this->assertSame(['Notes'], $form['unsupportedfields']);
        $this->assertTrue($form['supported']);
        $this->assertStringContainsString('new=1', $form['browserurl']);
    }

    /**
     * A required field without a control makes the form unsupported: it is edited on the web.
     */
    public function test_required_field_without_control(): void {
        $editrequired = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Notes required',
            '[[Title]] [[Notes|Required]] ##edit##',
            ['section' => '##addnewentry## ##entries##']
        );

        $form = $this->call(0, $editrequired);

        $this->assertTrue($form['allowed']);
        $this->assertFalse($form['supported']);
    }

    /**
     * Whether the form opens agrees with whether the save is accepted.
     */
    public function test_allowed_agrees_with_the_save(): void {
        global $DB;

        $other = $this->create_mobile_student($this->course);
        $own = $this->add_mobile_entry($this->dlx, $this->student->id, [$this->ids['Title'] => 'Mine']);
        $theirs = $this->add_mobile_entry($this->dlx, $other->id, [$this->ids['Title'] => 'Theirs']);
        $readonly = $this->add_mobile_view($this->dlx, 'grid', 'Browse', '[[Title]]');

        $this->setUser($this->student);
        $this->assertTrue($this->call($own)['allowed']);
        $this->assertFalse($this->call($theirs)['allowed']);
        $this->assertNotSame('', $this->call($theirs)['denyreason']);
        $this->assertFalse($this->call($own, $readonly)['allowed']);
        $this->assertFalse($this->call(0, $readonly)['allowed']);

        // A final submission is locked for its author.
        $DB->set_field('datalynx_entries', 'status', \datalynxfield_status\field::STATUS_FINAL_SUBMISSION, ['id' => $own]);
        $this->assertFalse($this->call($own)['allowed']);

        // No entries left to add.
        $DB->set_field('datalynx', 'maxentries', 2, ['id' => $this->dlx->id()]);
        $this->add_mobile_entry($this->dlx, $this->student->id, [$this->ids['Title'] => 'Second']);
        $this->assertFalse($this->call(0)['allowed']);

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertTrue($this->call($theirs)['allowed']);
    }

    /**
     * An existing entry: its values, the read-only field shown, not editable.
     */
    public function test_existing_entry_values(): void {
        $entryid = $this->add_mobile_entry($this->dlx, $this->student->id, [
            $this->ids['Title'] => 'Mine', $this->ids['Tags'] => '#2#', $this->ids['When'] => '1768042800',
            $this->ids['Secret'] => 'Hidden away',
        ]);
        $this->setUser($this->student);

        $fields = self::fields($this->call($entryid));

        $this->assertSame('Mine', $fields['Title']['inputs'][0]['value']);
        $this->assertSame('["2"]', $fields['Tags']['inputs'][0]['value']);
        $this->assertSame('1768042800', $fields['When']['inputs'][0]['value']);
        $this->assertSame('static', $fields['Secret']['control']);
        $this->assertSame([], $fields['Secret']['inputs']);
        $this->assertStringContainsString('Hidden away', $fields['Secret']['html']);
    }

    /**
     * The values of the form, sent unchanged through submit_entry, change nothing.
     */
    public function test_round_trip(): void {
        global $DB;

        $entryid = $this->add_mobile_entry($this->dlx, $this->student->id, [
            $this->ids['Title'] => 'Mine', $this->ids['Tags'] => '#1#,#2#', $this->ids['When'] => '1768042800',
        ]);
        $this->setUser($this->student);
        $before = $DB->get_records_menu('datalynx_contents', ['entryid' => $entryid], 'fieldid', 'fieldid, content');

        $data = [];
        foreach ($this->call($entryid)['fields'] as $field) {
            foreach ($field['inputs'] as $input) {
                $data[] = ['fieldid' => $field['fieldid'], 'name' => $input['name'], 'value' => $input['value']];
            }
        }
        $result = submit_entry::execute($this->dlx->id(), $this->viewid, $entryid, $data);

        $this->assertTrue($result['status'], json_encode($result));
        $after = $DB->get_records_menu('datalynx_contents', ['entryid' => $entryid], 'fieldid', 'fieldid, content');
        $this->assertEquals($before, $after);
    }
}
