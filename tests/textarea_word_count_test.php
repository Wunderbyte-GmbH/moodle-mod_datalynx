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
 * Tests for the word limits of the textarea field.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * Minimum (param8) and maximum (param7) word counts of a textarea are enforced on save.
 *
 * @covers \datalynxfield_textarea\renderer::validate
 * @covers \datalynxfield_textarea\renderer::validate_word_count
 */
final class textarea_word_count_test extends advanced_testcase {
    /**
     * Create a textarea field with the given word limits.
     *
     * @param int $max Maximum words (param7), 0 for none.
     * @param int $min Minimum words (param8), 0 for none.
     * @return \mod_datalynx\local\field\datalynxfield_base
     */
    private function make_field(int $max, int $min = 0) {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);
        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(), 'type' => 'textarea', 'name' => 'outline', 'description' => '',
            'param1' => '', 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '', 'param6' => '',
            'param7' => (string) $max, 'param8' => (string) $min, 'param9' => '', 'param10' => '',
        ]);
        return $dlx->get_field_from_id($fieldid, true);
    }

    /**
     * Texts above the maximum are rejected with the current count, texts at the limit pass.
     */
    public function test_maximum_word_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $renderer = $this->make_field(5)->renderer();

        $this->assertNull($renderer->validate_word_count('one two three four five'));
        $error = $renderer->validate_word_count('one two three four five six');
        $this->assertNotNull($error);
        $this->assertStringContainsString('5', $error);
        $this->assertStringContainsString('6', $error);
    }

    /**
     * Texts below the minimum are rejected, an empty text is left to the required check.
     */
    public function test_minimum_word_count(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $renderer = $this->make_field(0, 3)->renderer();

        $this->assertNotNull($renderer->validate_word_count('one two'));
        $this->assertNull($renderer->validate_word_count('one two three'));
        $this->assertNull($renderer->validate_word_count('   '));
    }

    /**
     * Without limits nothing is checked.
     */
    public function test_no_limits(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $renderer = $this->make_field(0)->renderer();
        $this->assertNull($renderer->validate_word_count(str_repeat('word ', 1000)));
    }

    /**
     * The limit is applied through validate(), keyed by the form element name.
     */
    public function test_validate_reports_word_limit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $field = $this->make_field(2);
        $name = "field_{$field->id()}_-1";

        $errors = $field->renderer()->validate(-1, ['[[outline]]'], (object) [$name => 'one two three']);
        $this->assertArrayHasKey($name, $errors);

        $errors = $field->renderer()->validate(-1, ['[[outline]]'], (object) [$name => 'one two']);
        $this->assertArrayNotHasKey($name, $errors);
    }
}
