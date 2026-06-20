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
}
