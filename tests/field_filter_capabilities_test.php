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
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the field searchability/sortability capabilities used by the filter forms.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;

/**
 * Tests that pure action fields are excluded from filters while data fields stay searchable/sortable.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_base::supports_search
 * @covers \mod_datalynx\local\field\datalynxfield_base::supports_sort
 */
final class field_filter_capabilities_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
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
     * Data fields are searchable/sortable; the submit and cancel action fields are not.
     */
    public function test_action_fields_excluded_from_search_and_sort(): void {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // Data-bearing fields: searchable and sortable.
        $this->create_field($dlx->id(), 'text', 'TextField');
        $this->create_field($dlx->id(), 'file', 'FileField', ['param2' => '2']);
        $this->create_field($dlx->id(), 'multiselect', 'MultiField', ['param1' => "A\nB\nC"]);
        $dlx->get_fields(null, false, true); // Force re-read so the new fields are picked up.

        foreach (['TextField', 'FileField', 'MultiField'] as $name) {
            $field = $this->get_field_by_name($dlx, $name);
            $this->assertTrue($field->supports_search(), "$name should be searchable");
            $this->assertTrue($field->supports_sort(), "$name should be sortable");
        }

        // Internal approval field is searchable (custom select-based search).
        $approve = $dlx->get_field_from_id('approve');
        $this->assertNotEmpty($approve);
        $this->assertTrue($approve->supports_search(), 'approve should be searchable');

        // Pure action fields: excluded from both search and sort.
        foreach (['submit', 'cancel'] as $internalid) {
            $field = $dlx->get_field_from_id($internalid);
            $this->assertNotEmpty($field, "internal field $internalid should exist");
            $this->assertFalse($field->supports_search(), "$internalid must not be searchable");
            $this->assertFalse($field->supports_sort(), "$internalid must not be sortable");
        }
    }

    /**
     * The file field's "has a file / has not a file" selector returns the correct entries.
     */
    public function test_file_has_file_search(): void {
        global $DB, $USER;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fileid = $this->create_field($dlx->id(), 'file', 'FileField', ['param2' => '2']);
        $dlx->get_fields(null, false, true);

        // Entry with a file: content column holds '1'. Entry without a file: holds '0'.
        $withfile = $this->create_entry($dlx->id(), $USER->id);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fileid, 'entryid' => $withfile, 'content' => '1',
        ]);
        $withoutfile = $this->create_entry($dlx->id(), $USER->id);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fileid, 'entryid' => $withoutfile, 'content' => '0',
        ]);

        // Has a file => selector value 0.
        $hasfile = $this->run_file_search($dlx, $fileid, '0');
        $this->assertEquals([$withfile], array_keys($hasfile));

        // Has not a file => selector value 1.
        $nofile = $this->run_file_search($dlx, $fileid, '1');
        $this->assertEquals([$withoutfile], array_keys($nofile));
    }

    /**
     * Build a filter that searches the file field for the given selector value and return its entries.
     *
     * @param datalynx $dlx The datalynx instance.
     * @param int $fileid The file field id.
     * @param string $selectorvalue '0' = has a file, '1' = has not a file.
     * @return array entryid => entry.
     */
    private function run_file_search(datalynx $dlx, int $fileid, string $selectorvalue): array {
        global $DB;
        // The file field's operator select is empty/disabled, so the stored operator is ''.
        $customsearch = [$fileid => ['AND' => [['', '', $selectorvalue]]]];
        $filterid = (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $dlx->id(),
            'name' => 'FileFilter' . $selectorvalue,
            'description' => '',
            'customsort' => '',
            'customsearch' => serialize($customsearch),
            'search' => '',
            'groupby' => '',
            'perpage' => 10,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ]);
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fileid];
        $entriesclass = new datalynx_entries($dlx, $filter);
        return $entriesclass->get_entries()->entries;
    }

    /**
     * Create an approved entry and return its id.
     *
     * @param int $dataid The datalynx instance id.
     * @param int $userid The author id.
     * @return int The new entry id.
     */
    private function create_entry(int $dataid, int $userid): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dataid,
            'userid' => $userid,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Fetch a field object by its name.
     *
     * @param datalynx $dlx The datalynx instance.
     * @param string $name The field name.
     * @return \mod_datalynx\local\field\datalynxfield_base
     */
    private function get_field_by_name(datalynx $dlx, string $name) {
        foreach ($dlx->get_fields() as $field) {
            if ($field->name() === $name) {
                return $field;
            }
        }
        $this->fail("Field $name not found");
    }
}
