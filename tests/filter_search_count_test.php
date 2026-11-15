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

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;

/**
 * The number of filtered entries counts each entry once, however many content rows match (#293).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\local\datalynx_entries::get_entries
 */
final class filter_search_count_test extends advanced_testcase {
    /** @var datalynx The datalynx instance used by the current test. */
    private datalynx $dlx;

    /**
     * Set up a fresh datalynx instance per test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Insert a text field.
     *
     * @param string $name The field name.
     * @return int The field id.
     */
    private function make_field(string $name): int {
        global $DB;
        $record = (object) ['dataid' => $this->dlx->id(), 'type' => 'text', 'name' => $name, 'description' => ''];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = '';
        }
        return (int) $DB->insert_record('datalynx_fields', $record);
    }

    /**
     * Insert an entry with the given content rows.
     *
     * @param array $contents List of [field id, content]; a field may occur several times (fieldgroup lines).
     * @return int The entry id.
     */
    private function make_entry(array $contents): int {
        global $DB, $USER;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        foreach ($contents as [$fieldid, $content]) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
            ]);
        }
        return $entryid;
    }

    /**
     * Run a filter over the instance.
     *
     * @param string $search The simple (full text) search term.
     * @param array $customsearch customsearch aggregated by field id
     * @param int $perpage Entries per page.
     * @return \stdClass The result of get_entries(): entries, max, found.
     */
    private function run_filter(string $search, array $customsearch = [], int $perpage = 50): \stdClass {
        global $DB;
        $filterid = (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Count',
            'description' => '',
            'customsort' => '',
            'customsearch' => $customsearch ? serialize($customsearch) : '',
            'search' => $search,
            'groupby' => '',
            'perpage' => $perpage,
            'selection' => 0,
            'page' => 0,
            'eids' => '',
        ]);
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        return (new datalynx_entries($this->dlx, $filter))->get_entries();
    }

    /**
     * A full text search term found in two fields of one entry counts that entry once.
     */
    public function test_simple_search_counts_each_entry_once(): void {
        $first = $this->make_field('First');
        $second = $this->make_field('Second');
        $both = $this->make_entry([[$first, 'Muster one'], [$second, 'Muster two']]);
        $one = $this->make_entry([[$first, 'Muster'], [$second, 'other']]);
        $this->make_entry([[$first, 'nothing'], [$second, 'here']]);

        $result = $this->run_filter('Muster');

        $this->assertEqualsCanonicalizing([$both, $one], array_map('intval', array_keys($result->entries)));
        $this->assertSame(3, (int) $result->max);
        $this->assertSame(2, (int) $result->found);
    }

    /**
     * A field search matching several lines of a fieldgroup subfield counts the entry once.
     */
    public function test_field_search_over_fieldgroup_lines_counts_each_entry_once(): void {
        $line = $this->make_field('Line');
        $lines = $this->make_entry([[$line, 'Muster a'], [$line, 'Muster b'], [$line, 'Muster c']]);
        $this->make_entry([[$line, 'other']]);

        $result = $this->run_filter('', [$line => ['AND' => [['', 'LIKE', 'Muster']]]]);

        $this->assertSame([$lines], array_map('intval', array_keys($result->entries)));
        $this->assertSame(1, (int) $result->found);
    }

    /**
     * The count drives paging, so it must match the entries actually listed.
     */
    public function test_paging_uses_distinct_count(): void {
        $first = $this->make_field('First');
        $second = $this->make_field('Second');
        for ($i = 0; $i < 3; $i++) {
            $this->make_entry([[$first, "Muster $i"], [$second, "Muster $i"]]);
        }

        $result = $this->run_filter('Muster', [], 2);

        $this->assertSame(3, (int) $result->found);
        $this->assertCount(2, $result->entries);
    }
}
