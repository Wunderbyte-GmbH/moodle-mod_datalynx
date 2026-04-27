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
 * Tests for the internal entry field action patterns.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_entry\field as entry_field;
use moodle_url;
use ReflectionMethod;

/**
 * Tests for the internal entry field renderer.
 */
final class entry_pattern_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * The ##edit## action should preserve the edited entry as both editentries and eids.
     *
     * @covers \datalynxfield_entry\renderer::display_edit
     */
    public function test_display_edit_sets_matching_editentries_and_eids(): void {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($instance->id);

        $fieldrecord = entry_field::get_field_objects($df->id())[entry_field::_ENTRY];
        $field = new entry_field($df, $fieldrecord);
        $renderer = $field->renderer();

        $entry = (object) [
            'id' => 42,
            'baseurl' => new moodle_url('/mod/datalynx/view.php', ['d' => $df->id()]),
        ];

        $method = new ReflectionMethod($renderer, 'display_edit');
        $method->setAccessible(true);

        $html = $method->invoke($renderer, $entry);

        $this->assertStringContainsString('editentries=42', $html);
        $this->assertStringContainsString('eids=42', $html);
        $this->assertStringContainsString('sesskey=', $html);
    }
}
