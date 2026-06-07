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

/**
 * Tests for mod_datalynx Field Formats.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use stdClass;

/**
 * Tests for Field Formats manager, subplugins, and migration scanning.
 *
 * @covers \mod_datalynx\local\field_format\manager
 * @covers \mod_datalynx\local\field_format\base
 */
final class field_formats_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a datalynx instance for testing.
     *
     * @return datalynx
     */
    private function create_test_datalynx(): datalynx {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);

        return new datalynx($instance->id);
    }

    /**
     * Test saving, retrieving, and updating field formats.
     */
    public function test_format_crud(): void {
        $dlx = $this->create_test_datalynx();

        // Create a new format.
        $record = new stdClass();
        $record->dataid = $dlx->id();
        $record->name = 'buttonformat';
        $record->fieldtype = 'submit';
        $record->settings = json_encode(['buttontext' => 'Custom Submit', 'showarrow' => 1]);

        $formatid = \mod_datalynx\local\field_format\manager::save_format($record);
        $this->assertGreaterThan(0, $formatid);

        // Retrieve by ID.
        $format = \mod_datalynx\local\field_format\manager::get_format_by_id($formatid);
        $this->assertNotNull($format);
        $this->assertEquals('buttonformat', $format->get_name());
        $this->assertEquals('submit', $format->get_fieldtype());
        $this->assertEquals('Custom Submit', $format->get_setting('buttontext'));

        // Retrieve by name.
        $formatbyname = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'buttonformat');
        $this->assertNotNull($formatbyname);
        $this->assertEquals($formatid, $formatbyname->get_record()->id);

        // Retrieve all for instance.
        $formats = \mod_datalynx\local\field_format\manager::get_formats_for_instance($dlx->id());
        $this->assertCount(1, $formats);
        $this->assertArrayHasKey($formatid, $formats);

        // Update the format.
        $record->id = $formatid;
        $record->settings = json_encode(['buttontext' => 'Updated Text', 'showarrow' => 0]);
        \mod_datalynx\local\field_format\manager::save_format($record);

        $format = \mod_datalynx\local\field_format\manager::get_format_by_id($formatid);
        $this->assertEquals('Updated Text', $format->get_setting('buttontext'));
        $this->assertEquals(0, $format->get_setting('showarrow'));

        // Delete the format.
        $deleted = \mod_datalynx\local\field_format\manager::delete_format($formatid);
        $this->assertTrue($deleted);

        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_id($formatid));
    }

    /**
     * Test deletion checks when a format is used in view templates.
     */
    public function test_delete_blocked_when_used(): void {
        global $DB;
        $dlx = $this->create_test_datalynx();

        // Create a format.
        $record = new stdClass();
        $record->dataid = $dlx->id();
        $record->name = 'customdate';
        $record->fieldtype = 'time';
        $record->settings = json_encode(['dateformat' => 'Y-m-d']);
        $formatid = \mod_datalynx\local\field_format\manager::save_format($record);

        // Create a view referencing the format.
        $view = new stdClass();
        $view->dataid = $dlx->id();
        $view->name = 'Test View';
        $view->type = 'grid';
        $view->description = '';
        $view->section = '##mytime:customdate##';
        $viewid = $DB->insert_record('datalynx_views', $view);

        // Attempt deletion, expecting exception.
        $this->expectException(\moodle_exception::class);
        \mod_datalynx\local\field_format\manager::delete_format($formatid);
    }

    /**
     * Test migration scanning of legacy formats (e.g. ##myfield:formatname##) inside view templates.
     */
    public function test_auto_create_formats_from_templates(): void {
        global $DB;
        $dlx = $this->create_test_datalynx();

        // Add a field of type 'time' named 'mytime'.
        $field = new stdClass();
        $field->dataid = $dlx->id();
        $field->name = 'mytime';
        $field->type = 'time';
        $field->description = '';
        $DB->insert_record('datalynx_fields', $field);

        // Add a field of type 'userinfo' named 'myuserinfo'.
        $field2 = new stdClass();
        $field2->dataid = $dlx->id();
        $field2->name = 'myuserinfo';
        $field2->type = 'userinfo';
        $field2->description = '';
        $DB->insert_record('datalynx_fields', $field2);

        // Create a view with legacy patterns.
        $view = new stdClass();
        $view->dataid = $dlx->id();
        $view->name = 'Legacy View';
        $view->type = 'grid';
        $view->description = '';
        $view->section = 'Date field display: ##mytime:legacyformat##';
        $view->param1 = 'Details: [[mytime:anotherlegacy]]';
        $view->param2 = 'Author info: ##author:idnumber##';
        $view->param3 = 'User info format: [[myuserinfo:checkbox]] and ##myuserinfo:customfieldformat##';
        $DB->insert_record('datalynx_views', $view);

        // Verify formats do not exist yet.
        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'legacyformat'));
        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'anotherlegacy'));
        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'idnumber'));
        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'checkbox'));
        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'customfieldformat'));

        // Run the auto-creation scanner.
        \mod_datalynx\local\field_format\manager::auto_create_formats_from_templates($dlx->id());

        // Verify formats were created automatically with the correct fieldtype.
        $format1 = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'legacyformat');
        $this->assertNotNull($format1);
        $this->assertEquals('time', $format1->get_fieldtype());

        $format2 = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'anotherlegacy');
        $this->assertNotNull($format2);
        $this->assertEquals('time', $format2->get_fieldtype());

        $format3 = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'idnumber');
        $this->assertNotNull($format3);
        $this->assertEquals('entryauthor', $format3->get_fieldtype());
        $this->assertEquals('idnumber', $format3->get_setting('option'));

        $format4 = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'checkbox');
        $this->assertNotNull($format4);
        $this->assertEquals('userinfo', $format4->get_fieldtype());
        $this->assertEquals('checkbox', $format4->get_setting('option'));

        $format5 = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'customfieldformat');
        $this->assertNotNull($format5);
        $this->assertEquals('userinfo', $format5->get_fieldtype());
        $this->assertEquals('customfieldformat', $format5->get_setting('option'));
    }

    /**
     * Test list of supported field types.
     */
    public function test_get_supported_field_types(): void {
        $types = \mod_datalynx\local\field_format\manager::get_supported_field_types();
        $this->assertArrayHasKey('time', $types);
        $this->assertArrayHasKey('submit', $types);
        $this->assertArrayHasKey('cancel', $types);
        $this->assertArrayHasKey('teammemberselect', $types);
        $this->assertArrayHasKey('file', $types);
        $this->assertArrayHasKey('picture', $types);
        $this->assertArrayHasKey('duration', $types);
        $this->assertArrayHasKey('editor', $types);
        $this->assertArrayHasKey('coursegroup', $types);

        // Assert new option-supporting fields are present.
        $this->assertArrayHasKey('number', $types);
        $this->assertArrayHasKey('url', $types);
        $this->assertArrayHasKey('multiselect', $types);
        $this->assertArrayHasKey('checkbox', $types);
        $this->assertArrayHasKey('select', $types);
        $this->assertArrayHasKey('radiobutton', $types);
        $this->assertArrayHasKey('text', $types);
        $this->assertArrayHasKey('textarea', $types);
        $this->assertArrayHasKey('youtube', $types);
        $this->assertArrayHasKey('gradeitem', $types);

        // Assert empty fields are not present.
        $this->assertArrayNotHasKey('approve', $types);
        $this->assertArrayNotHasKey('comment', $types);
        $this->assertArrayNotHasKey('datalynxview', $types);
        $this->assertArrayNotHasKey('entry', $types);
        $this->assertArrayNotHasKey('entrygroup', $types);
        $this->assertArrayNotHasKey('entryteammemberprofilefield', $types);
        $this->assertArrayNotHasKey('fieldgroup', $types);
        $this->assertArrayNotHasKey('identifier', $types);
        $this->assertArrayNotHasKey('status', $types);
        $this->assertArrayNotHasKey('tag', $types);
    }

    /**
     * Test userinfo field pattern replacement with custom field format.
     */
    public function test_userinfo_replacements_with_field_format(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);

        // Add user info field.
        $fieldrecord = new stdClass();
        $fieldrecord->dataid = $dlx->id();
        $fieldrecord->name = 'custominfo';
        $fieldrecord->type = 'userinfo';
        $fieldrecord->description = '';
        $fieldrecord->param1 = $DB->insert_record('user_info_field', [
            'shortname' => 'zweitname',
            'name' => 'Zweitname',
            'datatype' => 'text',
            'categoryid' => 1,
        ]);
        $fieldrecord->param2 = 'zweitname';
        $fieldrecord->param3 = 'text';
        $fieldid = $DB->insert_record('datalynx_fields', $fieldrecord);

        // Get userinfo field object.
        $field = new \datalynxfield_userinfo\field($dlx, $DB->get_record('datalynx_fields', ['id' => $fieldid]));
        $renderer = $field->renderer();

        // Add custom profile field data for a user.
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_info_data', [
            'userid' => $user->id,
            'fieldid' => $fieldrecord->param1,
            'data' => 'Hubert',
        ]);

        // Create format option for userinfo field.
        $record = new \stdClass();
        $record->dataid = $dlx->id();
        $record->name = 'myuserformat';
        $record->fieldtype = 'userinfo';
        $record->settings = json_encode(['option' => 'text']);
        \mod_datalynx\local\field_format\manager::save_format($record);

        // Create an entry where this user is the author.
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
        $entry->{"c{$fieldid}_content"} = 'Hubert';

        // Call replacements.
        $replacements = $renderer->replacements(['##custominfo:myuserformat##'], $entry);

        $this->assertIsArray($replacements);
        $this->assertArrayHasKey('##custominfo:myuserformat##', $replacements);
        $this->assertEquals('Hubert', $replacements['##custominfo:myuserformat##'][1]);
    }

    /**
     * Test userinfo field format option set to a custom user profile field.
     */
    public function test_userinfo_custom_profile_field_option(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);

        // Add user info field (main field, shortname 'zweitname').
        $fieldrecord = new stdClass();
        $fieldrecord->dataid = $dlx->id();
        $fieldrecord->name = 'custominfo';
        $fieldrecord->type = 'userinfo';
        $fieldrecord->description = '';
        $fieldrecord->param1 = $DB->insert_record('user_info_field', [
            'shortname' => 'zweitname',
            'name' => 'Zweitname',
            'datatype' => 'text',
            'categoryid' => 1,
        ]);
        $fieldrecord->param2 = 'zweitname';
        $fieldrecord->param3 = 'text';
        $fieldid = $DB->insert_record('datalynx_fields', $fieldrecord);

        // Add another custom profile field ('birthday').
        $birthdayfieldid = $DB->insert_record('user_info_field', [
            'shortname' => 'birthday',
            'name' => 'Birthday',
            'datatype' => 'text',
            'categoryid' => 1,
        ]);

        // Create format option for userinfo field pointing to 'birthday'.
        $record = new \stdClass();
        $record->dataid = $dlx->id();
        $record->name = 'mybirthdayformat';
        $record->fieldtype = 'userinfo';
        $record->settings = json_encode(['option' => 'birthday']);
        \mod_datalynx\local\field_format\manager::save_format($record);

        // Create an entry where a user is the author.
        $user = $this->getDataGenerator()->create_user();
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);

        // Insert initial data for birthday.
        $DB->insert_record('user_info_data', [
            'userid' => $user->id,
            'fieldid' => $birthdayfieldid,
            'data' => '1990-01-01',
        ]);

        // Get userinfo field object.
        $field = new \datalynxfield_userinfo\field($dlx, $DB->get_record('datalynx_fields', ['id' => $fieldid]));
        $renderer = $field->renderer();

        // 1. Test view mode replacement.
        $replacements = $renderer->replacements(['##custominfo:mybirthdayformat##'], $entry);
        $this->assertIsArray($replacements);
        $this->assertArrayHasKey('##custominfo:mybirthdayformat##', $replacements);
        $this->assertEquals('1990-01-01', $replacements['##custominfo:mybirthdayformat##'][1]);

        // 2. Test edit mode and validation/saving.
        // We mock submitted form data.
        $formfieldname = "field_{$fieldid}_{$entryid}_birthday";
        $formdata = new stdClass();
        $formdata->{$formfieldname} = '1995-05-05';

        // Run validate which should update the profile data.
        $errors = $renderer->validate($entryid, ['##custominfo:mybirthdayformat##'], $formdata);
        $this->assertEmpty($errors);

        // Verify that the birthday was updated on the user profile in the database.
        $updatedval = $DB->get_field('user_info_data', 'data', ['userid' => $user->id, 'fieldid' => $birthdayfieldid]);
        $this->assertEquals('1995-05-05', $updatedval);
    }

    /**
     * Test migration of rating double-colon tags (avg:bar, avg:star) to single-colon tags (avgbar, avgstar).
     */
    public function test_rating_format_upgrade_and_conversion(): void {
        global $DB;

        $dlx = $this->create_test_datalynx();

        // 1. Create a field of type rating.
        $field = new stdClass();
        $field->dataid = $dlx->id();
        $field->name = 'ratings';
        $field->type = 'rating';
        $field->description = '';
        $DB->insert_record('datalynx_fields', $field);

        // 2. Create a view template containing legacy double-colon tags.
        $view = new stdClass();
        $view->dataid = $dlx->id();
        $view->name = 'Legacy View';
        $view->type = 'grid';
        $view->description = '';
        $view->section = 'Rating displays: ##ratings:avg:bar## and [[ratings:avg:star]]';
        $viewid = $DB->insert_record('datalynx_views', $view);

        // 3. Create a legacy format record with a double-colon name.
        $formatrec = new stdClass();
        $formatrec->dataid = $dlx->id();
        $formatrec->name = 'avg:bar';
        $formatrec->fieldtype = 'rating';
        $formatrec->settings = json_encode([]);
        $formatid = $DB->insert_record('datalynx_field_formats', $formatrec);

        // 4. Run the replacement and upgrade migration logic (simulating what upgrade.php does).
        // A. View templates replacement
        $textfields = [
            'patterns', 'section', 'param1', 'param2', 'param3',
            'param4', 'param5', 'param6', 'param7', 'param8',
            'param9', 'param10',
        ];
        $views = $DB->get_records('datalynx_views', ['id' => $viewid]);
        foreach ($views as $view) {
            foreach ($textfields as $textfield) {
                if (!empty($view->$textfield)) {
                    $view->$textfield = str_replace(
                        ['##ratings:avg:bar##', '##ratings:avg:star##', '[[ratings:avg:bar]]', '[[ratings:avg:star]]'],
                        ['##ratings:avgbar##', '##ratings:avgstar##', '[[ratings:avgbar]]', '[[ratings:avgstar]]'],
                        $view->$textfield
                    );
                }
            }
            $DB->update_record('datalynx_views', $view);
        }

        // B. Formats table migration.
        $formats = $DB->get_records('datalynx_field_formats', ['fieldtype' => 'rating']);
        foreach ($formats as $format) {
            $name = $format->name;
            if ($name === 'avg:bar' || $name === 'avg:star') {
                $newname = str_replace(':', '', $name);
                if (
                    !$DB->record_exists(
                        'datalynx_field_formats',
                        ['dataid' => $format->dataid, 'fieldtype' => 'rating', 'name' => $newname]
                    )
                ) {
                    $format->name = $newname;
                    $format->settings = json_encode(['option' => $newname]);
                    $DB->update_record('datalynx_field_formats', $format);
                } else {
                    $DB->delete_records('datalynx_field_formats', ['id' => $format->id]);
                }
            }
        }

        // C. Run the scanner.
        \mod_datalynx\local\field_format\manager::auto_create_formats_from_all_instances();

        // 5. Verify view templates were successfully modified.
        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Rating displays: ##ratings:avgbar## and [[ratings:avgstar]]', $updatedview->section);

        // 6. Verify formats are correctly migrated and created.
        $formatavgbar = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'avgbar');
        $this->assertNotNull($formatavgbar);
        $this->assertEquals('rating', $formatavgbar->get_fieldtype());
        $this->assertEquals('avgbar', $formatavgbar->get_setting('option'));

        $formatavgstar = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'avgstar');
        $this->assertNotNull($formatavgstar);
        $this->assertEquals('rating', $formatavgstar->get_fieldtype());
        $this->assertEquals('avgstar', $formatavgstar->get_setting('option'));
    }

    /**
     * Test formatting options and legacy upgrades/defaults for the 10 fields.
     */
    public function test_new_field_format_options(): void {
        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);

        // Test multiselect/checkbox default settings and formatting.
        $multiselectformat = \mod_datalynx\local\field_format\manager::get_format_instance((object)[
            'fieldtype' => 'multiselect',
            'name' => 'list',
            'settings' => json_encode([]),
        ]);
        $defaults = $multiselectformat->get_default_settings_for_name('list');
        $this->assertEquals('list', $defaults['option']);

        // Test number format default settings.
        $numberformat = \mod_datalynx\local\field_format\manager::get_format_instance((object)[
            'fieldtype' => 'number',
            'name' => '2',
            'settings' => json_encode([]),
        ]);
        $defaults = $numberformat->get_default_settings_for_name('2');
        $this->assertEquals(2, $defaults['decimals']);

        // Test text format default settings.
        $textformat = \mod_datalynx\local\field_format\manager::get_format_instance((object)[
            'fieldtype' => 'text',
            'name' => '10',
            'settings' => json_encode([]),
        ]);
        $defaults = $textformat->get_default_settings_for_name('10');
        $this->assertEquals(10, $defaults['maxlength']);

        // Test url format default settings.
        $urlformat = \mod_datalynx\local\field_format\manager::get_format_instance((object)[
            'fieldtype' => 'url',
            'name' => 'imageflex',
            'settings' => json_encode([]),
        ]);
        $defaults = $urlformat->get_default_settings_for_name('imageflex');
        $this->assertEquals('imageflex', $defaults['option']);

        // Test gradeitem format default settings.
        $gradeformat = \mod_datalynx\local\field_format\manager::get_format_instance((object)[
            'fieldtype' => 'gradeitem',
            'name' => '3',
            'settings' => json_encode([]),
        ]);
        $defaults = $gradeformat->get_default_settings_for_name('3');
        $this->assertEquals(3, $defaults['decimals']);
    }
}
