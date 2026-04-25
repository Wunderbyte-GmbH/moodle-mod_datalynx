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
 * Tests for removing legacy field visibility/editability storage.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * Tests for legacy field column cleanup.
 *
 * @covers \mod_datalynx\datalynx::get_field
 * @covers \mod_datalynx\local\field\datalynxfield_base::set_field
 * @covers \datalynxfield_approve\field::get_field_objects
 */
final class field_legacy_cleanup_test extends advanced_testcase {
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
     * New field instances should no longer carry legacy visibility/editability properties.
     */
    public function test_new_field_definition_omits_legacy_columns(): void {
        $df = $this->create_test_datalynx();

        $field = $df->get_field('text');

        $this->assertFalse(property_exists($field->field, 'visible'));
        $this->assertFalse(property_exists($field->field, 'edits'));
    }

    /**
     * Internal field definitions should no longer expose legacy visibility data.
     */
    public function test_internal_field_objects_omit_legacy_visibility(): void {
        $df = $this->create_test_datalynx();

        $fields = \datalynxfield_approve\field::get_field_objects($df->id());
        $approvedfield = reset($fields);

        $this->assertFalse(property_exists($approvedfield, 'visible'));
    }
}
