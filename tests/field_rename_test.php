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
 * Tests for field renaming in views and custom filters.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use stdClass;

/**
 * Class field_rename_test
 *
 * @package    mod_datalynx
 * @covers     \mod_datalynx\local\view\base
 * @covers     \mod_datalynx\local\field\datalynxfield_base
 * @covers     \datalynx
 * @covers     \datalynxfield_behavior
 * @covers     \datalynxfield_layout
 * @covers     \mod_datalynx\local\field_format\manager
 */
final class field_rename_test extends advanced_testcase {
    /**
     * Test the regex replacement logic for view templates.
     */
    public function test_replace_field_tag_regex(): void {
        $oldname = 'title';
        $newname = 'headline';

        // 1. Test basic renames.
        $text = 'Some text [[title]] and [[title:format]] and [[title|behavior]] and [[title|behavior|renderer]]';
        $expected = 'Some text [[headline]] and [[headline:format]] and [[headline|behavior]] and [[headline|behavior|renderer]]';
        $this->assertEquals($expected, \mod_datalynx\local\view\base::replace_field_tag($text, $oldname, $newname));

        // 2. Test renaming with hash IDs and trailing @.
        $text2 = '[[title#id]] and [[title]]@';
        $expected2 = '[[headline#id]] and [[headline]]@';
        $this->assertEquals($expected2, \mod_datalynx\local\view\base::replace_field_tag($text2, $oldname, $newname));

        // 3. Test partial matches (should NOT be renamed).
        $text3 = '[[title_extended]] and [[title_extended:format]]';
        $this->assertEquals($text3, \mod_datalynx\local\view\base::replace_field_tag($text3, $oldname, $newname));

        // 4. Test deletions.
        $text4 = 'Some text [[title]] and [[title:format]] and [[title|behavior]] and [[title_extended]]';
        $expected4 = 'Some text  and  and  and [[title_extended]]';
        $this->assertEquals($expected4, \mod_datalynx\local\view\base::replace_field_tag($text4, $oldname, ''));
    }

    /**
     * Test that updating a field's name triggers renaming in views.
     */
    public function test_rename_field_updates_views(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup datalynx and a field.
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldrecordid = $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'oldfield',
            'description' => '',
        ]);
        $field = $dlx->get_field_from_id($fieldrecordid);

        // Setup a view containing the field tag in multiple columns.
        $viewid = $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'My View',
            'description' => 'Desc [[oldfield]]',
            'section' => 'Section [[oldfield|behavior]]',
            'param1' => 'Param1 [[oldfield:format]]',
            'param2' => 'Param2 [[oldfield_extended]]', // Partial, should not change.
        ]);

        // Rename the field.
        $formdata = new stdClass();
        $formdata->id = $field->id();
        $formdata->name = 'newfield';
        $field->update_field($formdata);

        // Verify the view was updated.
        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[newfield]]', $updatedview->description);
        $this->assertEquals('Section [[newfield|behavior]]', $updatedview->section);
        $this->assertEquals('Param1 [[newfield:format]]', $updatedview->param1);
        $this->assertEquals('Param2 [[oldfield_extended]]', $updatedview->param2);
    }

    /**
     * Test that updating a field's name triggers renaming in custom filters.
     */
    public function test_rename_field_updates_customfilters(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup datalynx and a field.
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldrecordid = $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'oldfield',
            'description' => '',
        ]);
        $field = $dlx->get_field_from_id($fieldrecordid);

        // Setup a custom filter referencing the field name.
        $fieldlist = [
            $field->id() => [
                'name' => 'oldfield',
                'sortable' => 1,
            ],
            999 => [
                'name' => 'otherfield',
                'sortable' => 0,
            ],
        ];

        $filterid = $DB->insert_record('datalynx_customfilters', (object) [
            'dataid' => $dlx->id(),
            'name' => 'My Custom Filter',
            'fieldlist' => json_encode($fieldlist),
        ]);

        // Rename the field.
        $formdata = new stdClass();
        $formdata->id = $field->id();
        $formdata->name = 'newfield';
        $field->update_field($formdata);

        // Verify the custom filter fieldlist was updated.
        $updatedfilter = $DB->get_record('datalynx_customfilters', ['id' => $filterid]);
        $updatedfieldlist = json_decode($updatedfilter->fieldlist, true);

        $this->assertEquals('newfield', $updatedfieldlist[$field->id()]['name']);
        $this->assertEquals('otherfield', $updatedfieldlist[999]['name']);
    }

    /**
     * Test that deleting a field triggers clean up in views and custom filters.
     */
    public function test_delete_field_cleans_views_and_customfilters(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup datalynx and a field.
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldrecordid = $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'oldfield',
            'description' => '',
        ]);
        $field = $dlx->get_field_from_id($fieldrecordid);

        // Setup a view containing the field tag.
        $viewid = $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'My View',
            'description' => '',
            'section' => 'Section [[oldfield|behavior]]',
        ]);

        // Setup a custom filter referencing the field name.
        $fieldlist = [
            $field->id() => [
                'name' => 'oldfield',
                'sortable' => 1,
            ],
        ];

        $filterid = $DB->insert_record('datalynx_customfilters', (object) [
            'dataid' => $dlx->id(),
            'name' => 'My Custom Filter',
            'fieldlist' => json_encode($fieldlist),
        ]);

        // Delete the field using bulk action case.
        $dlx->replace_field_in_views('oldfield', '');
        $dlx->replace_field_in_filters('oldfield', '');

        // Verify the view tag was removed.
        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Section ', $updatedview->section);

        // Verify the custom filter entry was removed.
        $updatedfilter = $DB->get_record('datalynx_customfilters', ['id' => $filterid]);
        $this->assertNull($updatedfilter->fieldlist);
    }

    /**
     * Test the regex replacement logic for format, behavior, and layout tags.
     */
    public function test_replace_additional_tags_regex(): void {
        // 1. Format tags renaming.
        $text = '[[field:oldformat]] and [[field:oldformat|behavior]] and ##field:oldformat##';
        $expected = '[[field:newformat]] and [[field:newformat|behavior]] and ##field:newformat##';
        $this->assertEquals($expected, \mod_datalynx\local\view\base::replace_format_tag($text, 'oldformat', 'newformat'));

        // 2. Format tags deletion.
        $text = '[[field:oldformat]] and [[field:oldformat|behavior]] and ##field:oldformat##';
        $expected = '[[field]] and [[field|behavior]] and ##field##';
        $this->assertEquals($expected, \mod_datalynx\local\view\base::replace_format_tag($text, 'oldformat', ''));

        // 3. Behavior tags renaming.
        $text = '[[field|oldbehavior]] and [[field:format|oldbehavior]] and [[field|oldbehavior|layout]]';
        $expected = '[[field|newbehavior]] and [[field:format|newbehavior]] and [[field|newbehavior|layout]]';
        $this->assertEquals($expected, \mod_datalynx\local\view\base::replace_behavior_tag($text, 'oldbehavior', 'newbehavior'));

        // 4. Behavior tags deletion.
        $text = '[[field|oldbehavior]] and [[field|oldbehavior|layout]]';
        $expected = '[[field]] and [[field||layout]]';
        $this->assertEquals($expected, \mod_datalynx\local\view\base::replace_behavior_tag($text, 'oldbehavior', ''));

        // 5. Layout tags renaming.
        $text = '[[field|behavior|oldlayout]] and [[field||oldlayout]]';
        $expected = '[[field|behavior|newlayout]] and [[field||newlayout]]';
        $this->assertEquals($expected, \mod_datalynx\local\view\base::replace_layout_tag($text, 'oldlayout', 'newlayout'));

        // 6. Layout tags deletion.
        $text = '[[field|behavior|oldlayout]] and [[field||oldlayout]]';
        $expected = '[[field|behavior]] and [[field]]';
        $this->assertEquals($expected, \mod_datalynx\local\view\base::replace_layout_tag($text, 'oldlayout', ''));
    }

    /**
     * Test that updating/deleting behaviors propagates to views.
     */
    public function test_behavior_renaming_updates_views(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Insert a behavior.
        $behaviorid = $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $dlx->id(),
            'name' => 'oldbehavior',
            'visibleto' => serialize(['permissions' => [], 'users' => [], 'teammember' => []]),
            'editableby' => serialize([]),
            'required' => serialize([]),
            'description' => '',
        ]);

        // Setup a view with behavior tags.
        $viewid = $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'My View',
            'description' => 'Desc [[field|oldbehavior]]',
            'section' => 'Section [[field|oldbehavior|layout]]',
        ]);

        // Rename behavior.
        $formdata = \mod_datalynx\local\field\datalynxfield_behavior::get_behavior($behaviorid);
        $formdata->name = 'newbehavior';
        \mod_datalynx\local\field\datalynxfield_behavior::update_behavior($formdata);

        // Verify renaming.
        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[field|newbehavior]]', $updatedview->description);
        $this->assertEquals('Section [[field|newbehavior|layout]]', $updatedview->section);

        // Delete behavior.
        \mod_datalynx\local\field\datalynxfield_behavior::delete_behavior($behaviorid);

        // Verify deletion.
        $deletedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[field]]', $deletedview->description);
        $this->assertEquals('Section [[field||layout]]', $deletedview->section);
    }

    /**
     * Test that updating/deleting layouts propagates to views.
     */
    public function test_layout_renaming_updates_views(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Insert a renderer (layout).
        $rendererid = $DB->insert_record('datalynx_renderers', (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'oldlayout',
        ]);

        // Setup a view with layout tags.
        $viewid = $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'My View',
            'description' => 'Desc [[field|behavior|oldlayout]]',
            'section' => 'Section [[field||oldlayout]]',
        ]);

        // Rename layout.
        $formdata = \mod_datalynx\local\field\datalynxfield_layout::get_renderer($rendererid);
        $formdata->name = 'newlayout';
        $formdata->d = $dlx->id();
        \mod_datalynx\local\field\datalynxfield_layout::update_renderer($formdata);

        // Verify renaming.
        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[field|behavior|newlayout]]', $updatedview->description);
        $this->assertEquals('Section [[field||newlayout]]', $updatedview->section);

        // Delete layout.
        \mod_datalynx\local\field\datalynxfield_layout::delete_renderer($rendererid);

        // Verify deletion.
        $deletedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[field|behavior]]', $deletedview->description);
        $this->assertEquals('Section [[field]]', $deletedview->section);
    }

    /**
     * Test that updating a format propagates to views.
     */
    public function test_format_renaming_updates_views(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Insert a format.
        $formatid = $DB->insert_record('datalynx_field_formats', (object) [
            'dataid' => $dlx->id(),
            'name' => 'oldformat',
            'fieldtype' => 'text',
        ]);

        // Setup a view with format tags.
        $viewid = $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'My View',
            'description' => 'Desc [[field:oldformat]]',
            'section' => 'Section [[field:oldformat|behavior]]',
        ]);

        // Rename format.
        $record = (object) [
            'id' => $formatid,
            'dataid' => $dlx->id(),
            'name' => 'newformat',
            'fieldtype' => 'text',
        ];
        \mod_datalynx\local\field_format\manager::save_format($record);

        // Verify renaming.
        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[field:newformat]]', $updatedview->description);
        $this->assertEquals('Section [[field:newformat|behavior]]', $updatedview->section);
    }

    /**
     * Test that behavior renaming and deletion propagate to views without active admin session.
     */
    public function test_behavior_renaming_without_admin_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $behaviorid = $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $dlx->id(),
            'name' => 'oldbehavior',
            'visibleto' => serialize(['permissions' => [], 'users' => [], 'teammember' => []]),
            'editableby' => serialize([]),
            'required' => serialize([]),
            'description' => '',
        ]);

        $viewid = $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'My View',
            'description' => 'Desc [[field|oldbehavior]]',
            'section' => 'Section [[field|oldbehavior|layout]]',
        ]);

        // Rename behavior.
        $formdata = \mod_datalynx\local\field\datalynxfield_behavior::get_behavior($behaviorid);
        $formdata->name = 'newbehavior';
        \mod_datalynx\local\field\datalynxfield_behavior::update_behavior($formdata);

        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[field|newbehavior]]', $updatedview->description);
        $this->assertEquals('Section [[field|newbehavior|layout]]', $updatedview->section);

        // Delete behavior.
        \mod_datalynx\local\field\datalynxfield_behavior::delete_behavior($behaviorid);

        $deletedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Desc [[field]]', $deletedview->description);
        $this->assertEquals('Section [[field||layout]]', $deletedview->section);
    }

    /**
     * Test that field (including fieldgroups) renaming propagates to views without active admin session.
     */
    public function test_field_renaming_without_admin_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldrecordid = $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(),
            'type' => 'fieldgroup', // Test fieldgroup as well since it inherits from base.
            'name' => 'oldfield',
            'description' => '',
        ]);
        $field = $dlx->get_field_from_id($fieldrecordid);

        $viewid = $DB->insert_record('datalynx_views', (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'My View',
            'description' => '',
            'section' => 'Section [[oldfield|behavior]]',
        ]);

        // Rename field.
        $formdata = new stdClass();
        $formdata->id = $field->id();
        $formdata->name = 'newfield';
        $field->update_field($formdata);

        $updatedview = $DB->get_record('datalynx_views', ['id' => $viewid]);
        $this->assertEquals('Section [[newfield|behavior]]', $updatedview->section);
    }
}
