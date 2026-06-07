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
}
