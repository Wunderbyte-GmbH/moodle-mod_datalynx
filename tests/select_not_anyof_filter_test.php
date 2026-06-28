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
 * Tests the "NOT Any of" view filter on a single-choice (select) field.
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
 * Tests the "NOT Any of" view filter on a single-choice (select) field.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_option_single::get_search_sql
 */
final class select_not_anyof_filter_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a datalynx instance with a select field offering three options.
     *
     * Options are 1-based: 1 = validated, 2 = rejected, 3 = in review.
     *
     * @return array [datalynx $dlx, int $fieldid]
     */
    private function create_instance_with_select_field(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dlx->id(),
            'type' => 'select',
            'name' => 'Validation',
            'description' => '',
            'param1' => "validated\nrejected\nin review",
            'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);

        return [$dlx, $fieldid];
    }

    /**
     * Insert an entry. When $option is null no content row is written for the field,
     * mirroring an entry whose select value was never set.
     *
     * @param datalynx $dlx
     * @param int $fieldid
     * @param int|null $option 1-based option index, or null for no content row
     * @return int entry id
     */
    private function add_entry(datalynx $dlx, int $fieldid, ?int $option): int {
        global $DB, $USER;

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(), 'userid' => $USER->id, 'groupid' => 0, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($option !== null) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => (string) $option,
            ]);
        }
        return $entryid;
    }

    /**
     * Run the given customsearch over the instance and return the matching entry ids.
     *
     * @param datalynx $dlx
     * @param int $fieldid
     * @param array $customsearch
     * @return int[]
     */
    private function get_filtered_entry_ids(datalynx $dlx, int $fieldid, array $customsearch): array {
        global $DB;

        $filterid = (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $dlx->id(),
            'name' => 'Not validated',
            'description' => '',
            'customsort' => '',
            'customsearch' => serialize($customsearch),
            'search' => '',
            'groupby' => '',
            'perpage' => 50,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ]);

        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];
        $result = (new datalynx_entries($dlx, $filter))->get_entries();

        return array_map('intval', array_keys($result->entries));
    }

    /**
     * "NOT Any of validated" must return every entry that is not validated, including the entry
     * that has no content row for the field. Before the fix the no-content entry was dropped
     * because the content LEFT JOIN qualifier (cX.fieldid = X) evaluated to NULL for it.
     */
    public function test_not_anyof_includes_entries_with_no_content_row(): void {
        [$dlx, $fieldid] = $this->create_instance_with_select_field();

        $validated = $this->add_entry($dlx, $fieldid, 1);
        $rejected = $this->add_entry($dlx, $fieldid, 2);
        $inreview = $this->add_entry($dlx, $fieldid, 3);
        $novalue = $this->add_entry($dlx, $fieldid, null);

        // AND <field> NOT "Any of" [validated].
        $customsearch = [$fieldid => ['AND' => [['NOT', 'ANY_OF', [1]]]]];
        $ids = $this->get_filtered_entry_ids($dlx, $fieldid, $customsearch);

        $this->assertNotContains($validated, $ids);
        $this->assertContains($rejected, $ids);
        $this->assertContains($inreview, $ids);
        $this->assertContains($novalue, $ids);
        $this->assertCount(3, $ids);
    }

    /**
     * When no entry holds the excluded value, "NOT Any of validated" matches every entry rather
     * than over-restricting to none. This exercises the empty-eids branch of the fix.
     */
    public function test_not_anyof_returns_all_when_no_entry_has_the_value(): void {
        [$dlx, $fieldid] = $this->create_instance_with_select_field();

        $rejected = $this->add_entry($dlx, $fieldid, 2);
        $inreview = $this->add_entry($dlx, $fieldid, 3);
        $novalue = $this->add_entry($dlx, $fieldid, null);

        $customsearch = [$fieldid => ['AND' => [['NOT', 'ANY_OF', [1]]]]];
        $ids = $this->get_filtered_entry_ids($dlx, $fieldid, $customsearch);

        $this->assertEqualsCanonicalizing([$rejected, $inreview, $novalue], $ids);
    }

    /**
     * Sanity check the positive case still works: "Any of validated" returns only the validated entry.
     */
    public function test_anyof_returns_only_matching_entry(): void {
        [$dlx, $fieldid] = $this->create_instance_with_select_field();

        $validated = $this->add_entry($dlx, $fieldid, 1);
        $this->add_entry($dlx, $fieldid, 2);
        $this->add_entry($dlx, $fieldid, null);

        $customsearch = [$fieldid => ['AND' => [['', 'ANY_OF', [1]]]]];
        $ids = $this->get_filtered_entry_ids($dlx, $fieldid, $customsearch);

        $this->assertEquals([$validated], $ids);
    }
}
