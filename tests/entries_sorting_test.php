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
 * Tests for entry sorting logic on PostgreSQL and other databases.
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
 * Tests for entry sorting logic on PostgreSQL and other databases.
 *
 * @coversDefaultClass \mod_datalynx\local\datalynx_entries
 */
final class entries_sorting_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test sorting entries by numeric (duration) fields using DISTINCT and ORDER BY.
     *
     * @covers ::get_entries
     */
    public function test_get_entries_sorting_by_duration(): void {
        global $DB, $USER;

        // 1. Setup course and datalynx activity.
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // 2. Setup field (duration type).
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'duration',
            'name' => 'DurationField',
            'description' => '',
            'required' => 0,
            'visibleto' => 0,
            'editableby' => 0,
            'param1' => 'seconds',
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
            'param6' => '',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $fieldid = (int) $DB->insert_record('datalynx_fields', $fieldrecord);
        $field = new \datalynxfield_duration\field($dlx, $DB->get_record('datalynx_fields', ['id' => $fieldid]));

        // 3. Create three entries with different duration contents (e.g. 100, 300, 200).
        $entry1id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time() - 10,
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry1id,
            'content' => '100',
        ]);

        $entry2id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time() - 5,
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry2id,
            'content' => '300',
        ]);

        $entry3id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry3id,
            'content' => '200',
        ]);

        // 4. Setup filter with sorting on our duration field.
        $filterrecord = (object) [
            'dataid' => $dlx->id(),
            'name' => 'SortFilter',
            'description' => '',
            'customsort' => serialize([$fieldid => 0]), // ASC sorting by our field.
            'customsearch' => '',
            'search' => '',
            'groupby' => '',
            'perpage' => 10,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ];
        $filterid = (int) $DB->insert_record('datalynx_filters', $filterrecord);
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];

        // 5. Query entries via datalynx_entries.
        $entriesclass = new datalynx_entries($dlx, $filter);
        $result = $entriesclass->get_entries();

        $this->assertNotEmpty($result->entries);
        $this->assertEquals(3, count($result->entries));

        // 6. Assert correct sorting order (entry1 = 100, entry3 = 200, entry2 = 300).
        $ids = array_keys($result->entries);
        $this->assertEquals($entry1id, $ids[0]);
        $this->assertEquals($entry3id, $ids[1]);
        $this->assertEquals($entry2id, $ids[2]);

        // 7. Verify descending sorting (DESC).
        $filterrecord->id = $filterid;
        $filterrecord->customsort = serialize([$fieldid => 1]); // DESC sorting.
        $DB->update_record('datalynx_filters', $filterrecord);
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];

        $entriesclass = new datalynx_entries($dlx, $filter);
        $result = $entriesclass->get_entries();

        $this->assertNotEmpty($result->entries);
        $ids = array_keys($result->entries);
        $this->assertEquals($entry2id, $ids[0]); // 300
        $this->assertEquals($entry3id, $ids[1]); // 200
        $this->assertEquals($entry1id, $ids[2]); // 100
    }

    /**
     * Test filtering and sorting entries by numeric (duration) fields using DISTINCT and ORDER BY.
     *
     * @covers ::get_entries
     */
    public function test_get_entries_filtering_and_sorting_by_duration(): void {
        global $DB, $USER;

        // 1. Setup course and datalynx activity.
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        // 2. Setup field (duration type).
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'duration',
            'name' => 'DurationField',
            'description' => '',
            'required' => 0,
            'visibleto' => 0,
            'editableby' => 0,
            'param1' => 'seconds',
            'param2' => '',
            'param3' => '',
            'param4' => '',
            'param5' => '',
            'param6' => '',
            'param7' => '',
            'param8' => '',
            'param9' => '',
            'param10' => '',
        ];
        $fieldid = (int) $DB->insert_record('datalynx_fields', $fieldrecord);
        $field = new \datalynxfield_duration\field($dlx, $DB->get_record('datalynx_fields', ['id' => $fieldid]));

        // 3. Create three entries with different duration contents (e.g. 100, 300, 200).
        $entry1id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time() - 10,
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry1id,
            'content' => '100',
        ]);

        $entry2id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time() - 5,
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry2id,
            'content' => '300',
        ]);

        $entry3id = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entry3id,
            'content' => '200',
        ]);

        // 4. Setup filter with filtering (duration >= 200) and sorting DESC by our duration field.
        $customsearch = [
            $fieldid => [
                'AND' => [
                    ['', '>=', ['200']],
                ],
            ],
        ];

        $filterrecord = (object) [
            'dataid' => $dlx->id(),
            'name' => 'SortFilter',
            'description' => '',
            'customsort' => serialize([$fieldid => 1]), // DESC sorting.
            'customsearch' => serialize($customsearch),
            'search' => '',
            'groupby' => '',
            'perpage' => 10,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ];
        $filterid = (int) $DB->insert_record('datalynx_filters', $filterrecord);
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->contentfields = [$fieldid];

        // 5. Query entries via datalynx_entries.
        $entriesclass = new datalynx_entries($dlx, $filter);
        $result = $entriesclass->get_entries();

        $this->assertNotEmpty($result->entries);
        // Only 2 entries should match (300 and 200, 100 is filtered out).
        $this->assertEquals(2, count($result->entries));

        // Assert correct sorting order (entry2 = 300 first, entry3 = 200 second).
        $ids = array_keys($result->entries);
        $this->assertEquals($entry2id, $ids[0]);
        $this->assertEquals($entry3id, $ids[1]);
    }
}
