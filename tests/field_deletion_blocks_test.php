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
 * Tests for field deletion blocks and confirmation.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * Tests for field deletion blocking when used in fieldgroups, filters, or custom filters.
 *
 * @covers \mod_datalynx\datalynx::process_fields
 * @covers \mod_datalynx\datalynx::find_fieldgroups_using_fields
 * @covers \mod_datalynx\datalynx::find_customfilters_using_fields
 */
final class field_deletion_blocks_test extends advanced_testcase {
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
     * Insert a datalynx field record and return its id.
     *
     * @param int $dataid The datalynx instance id.
     * @param string $type The field type.
     * @param string $name The field name.
     * @param array $params Extra param1..param10 overrides.
     * @return int The new field id.
     */
    private function create_field(int $dataid, string $type, string $name, array $params = []): int {
        global $DB;
        $record = (object) [
            'dataid' => $dataid,
            'type' => $type,
            'name' => $name,
            'description' => '',
            'required' => 0,
            'visibleto' => 0,
            'editableby' => 0,
        ];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = $params["param$i"] ?? '';
        }
        return (int) $DB->insert_record('datalynx_fields', $record);
    }

    /**
     * Field deletion should prompt for confirmation if the field is not blocked.
     */
    public function test_delete_field_prompts_confirm(): void {
        $dlx = $this->create_test_datalynx();
        $fieldid = $this->create_field($dlx->id(), 'text', 'UnusedField');
        $dlx->get_fields(null, false, true);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('deleteconfirm');
        $dlx->process_fields('delete', (string)$fieldid, false);
    }

    /**
     * Field deletion should proceed successfully when confirmed and not blocked.
     */
    public function test_delete_field_confirmed_success(): void {
        global $DB;
        $dlx = $this->create_test_datalynx();
        $fieldid = $this->create_field($dlx->id(), 'text', 'UnusedField');
        $dlx->get_fields(null, false, true);

        // Verify it exists.
        $this->assertTrue($DB->record_exists('datalynx_fields', ['id' => $fieldid]));

        // Process deletion.
        $dlx->process_fields('delete', (string)$fieldid, true);

        // Verify it no longer exists.
        $this->assertFalse($DB->record_exists('datalynx_fields', ['id' => $fieldid]));
    }

    /**
     * Field deletion should be blocked if the field is used in a fieldgroup.
     */
    public function test_delete_field_blocked_by_fieldgroup(): void {
        $dlx = $this->create_test_datalynx();
        $fieldid = $this->create_field($dlx->id(), 'text', 'SubField');

        // Create fieldgroup using SubField.
        $fieldids = [$fieldid];
        $this->create_field($dlx->id(), 'fieldgroup', 'MyFieldgroup', ['param1' => json_encode($fieldids)]);
        $dlx->get_fields(null, false, true);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('deleteblocked');
        $dlx->process_fields('delete', (string)$fieldid, false);
    }

    /**
     * Field deletion should be blocked if the field is used in a view filter.
     */
    public function test_delete_field_blocked_by_view_filter(): void {
        global $DB;
        $dlx = $this->create_test_datalynx();
        $fieldid = $this->create_field($dlx->id(), 'text', 'SubField');
        $dlx->get_fields(null, false, true);

        // Create a view filter searching this field.
        $customsearch = [$fieldid => ['AND' => [['', '', 'value']]]];
        $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $dlx->id(),
            'name' => 'ViewFilter',
            'description' => '',
            'customsort' => '',
            'customsearch' => serialize($customsearch),
            'search' => '',
            'groupby' => '',
            'perpage' => 10,
            'selection' => 0,
        ]);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('deleteblocked');
        $dlx->process_fields('delete', (string)$fieldid, false);
    }

    /**
     * Field deletion should be blocked if the field is used in a custom filter.
     */
    public function test_delete_field_blocked_by_custom_filter(): void {
        global $DB;
        $dlx = $this->create_test_datalynx();
        $fieldid = $this->create_field($dlx->id(), 'text', 'SubField');
        $dlx->get_fields(null, false, true);

        // Create a custom filter using this field.
        // Custom filter fieldlist format: {"fieldid": {"name": "fieldname", "sortable": 1}}.
        $fieldlist = [$fieldid => ['name' => 'SubField', 'sortable' => 1]];
        $DB->insert_record('datalynx_customfilters', (object) [
            'dataid' => $dlx->id(),
            'name' => 'CustomFilter',
            'description' => '',
            'visible' => 1,
            'fulltextsearch' => 0,
            'fieldlist' => json_encode($fieldlist),
        ]);

        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('deleteblocked');
        $dlx->process_fields('delete', (string)$fieldid, false);
    }
}
