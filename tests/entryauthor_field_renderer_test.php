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
 * Tests for the entryauthor field renderer.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_entryauthor\field as entryauthor_field;
use ReflectionMethod;

/**
 * Tests for the entryauthor field renderer.
 *
 * @covers \datalynxfield_entryauthor\renderer::patterns
 */
final class entryauthor_field_renderer_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test pattern generation when a field format is present.
     */
    public function test_patterns_with_field_format_does_not_throw_error(): void {
        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);

        // Field entryauthor has several field objects. Let's get one.
        $fieldrecord = entryauthor_field::get_field_objects($dlx->id())[entryauthor_field::_USERNAME];
        $field = new entryauthor_field($dlx, $fieldrecord);
        $renderer = $field->renderer();

        // Create a format to trigger the loops and conditions in renderer patterns method.
        $record = new \stdClass();
        $record->dataid = $dlx->id();
        $record->name = 'name';
        $record->fieldtype = 'entryauthor';
        $record->settings = json_encode([]);
        \mod_datalynx\local\field_format\manager::save_format($record);

        // Use reflection to call the protected patterns() method.
        $method = new ReflectionMethod($renderer, 'patterns');
        $method->setAccessible(true);
        $patterns = $method->invoke($renderer);

        $this->assertIsArray($patterns);
        $this->assertArrayHasKey('##author:name##', $patterns);
        $this->assertArrayHasKey('##author:edit##', $patterns);
    }

    /**
     * Test that view->field_tags() correctly merges fields without converting array to string.
     */
    public function test_field_tags_with_multiple_fields_does_not_convert_array_to_string(): void {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);

        // Add a view.
        $viewrecord = new \stdClass();
        $viewrecord->dataid = $dlx->id();
        $viewrecord->name = 'testgrid';
        $viewrecord->type = 'grid';
        $viewrecord->description = '';
        $viewrecord->param10 = 0;
        $viewid = $DB->insert_record('datalynx_views', $viewrecord);

        // Call get_view on $dlx.
        $view = $dlx->get_view('grid', $viewid);

        // Call field_tags on the view.
        $tags = $view->field_tags();

        $this->assertIsArray($tags);
        // Ensure no option values are arrays (which would cause the HTML writer error).
        foreach ($tags as $cat => $sub) {
            $this->assertIsArray($sub);
            foreach ($sub as $subcat => $items) {
                $this->assertIsArray($items);
                foreach ($items as $tag => $label) {
                    $this->assertIsString($label);
                }
            }
        }
    }

    /**
     * Test pattern replacement for custom user profile fields.
     */
    public function test_replacements_with_custom_user_profile_field(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $dlx = new datalynx($this->getDataGenerator()->create_module('datalynx', ['course' => $course->id])->id);

        // Add a custom user profile field.
        $fieldid = $DB->insert_record('user_info_field', [
            'shortname' => 'zweitname',
            'name' => 'Zweitname',
            'datatype' => 'text',
            'categoryid' => 1, // Default category.
        ]);

        // Add custom profile field data for a user.
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('user_info_data', [
            'userid' => $user->id,
            'fieldid' => $fieldid,
            'data' => 'Hubert',
        ]);

        // Create an entry where this user is the author.
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $user->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);

        // Get entryauthor field.
        $fieldrecord = entryauthor_field::get_field_objects($dlx->id())[entryauthor_field::_USERNAME];
        $field = new entryauthor_field($dlx, $fieldrecord);
        $renderer = $field->renderer();

        // Call replacements.
        $replacements = $renderer->replacements(['##author:zweitname##'], $entry);

        $this->assertIsArray($replacements);
        $this->assertArrayHasKey('##author:zweitname##', $replacements);
        $this->assertEquals('Hubert', $replacements['##author:zweitname##'][1]);
    }
}
