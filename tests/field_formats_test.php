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

        // Create a view with legacy patterns.
        $view = new stdClass();
        $view->dataid = $dlx->id();
        $view->name = 'Legacy View';
        $view->type = 'grid';
        $view->description = '';
        $view->section = 'Date field display: ##mytime:legacyformat##';
        $view->param1 = 'Details: [[mytime:anotherlegacy]]';
        $DB->insert_record('datalynx_views', $view);

        // Verify formats do not exist yet.
        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'legacyformat'));
        $this->assertNull(\mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'anotherlegacy'));

        // Run the auto-creation scanner.
        \mod_datalynx\local\field_format\manager::auto_create_formats_from_templates($dlx->id());

        // Verify both formats were created automatically with the correct fieldtype.
        $format1 = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'legacyformat');
        $this->assertNotNull($format1);
        $this->assertEquals('time', $format1->get_fieldtype());

        $format2 = \mod_datalynx\local\field_format\manager::get_format_by_name($dlx->id(), 'anotherlegacy');
        $this->assertNotNull($format2);
        $this->assertEquals('time', $format2->get_fieldtype());
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
    }
}
