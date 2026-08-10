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
 * Tests for NOT criteria across field types.
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
 * A NOT criterion must return the entries that do not meet the positive criterion, including
 * those that hold no value for the field at all - an entry with no content record cannot satisfy
 * a condition on the joined content table, but it does belong in the result of a negated one.
 *
 * @group mod_datalynx
 * @covers \mod_datalynx\local\filter\datalynx_filter::get_search_sql
 * @covers \datalynxfield_time\field::get_search_sql
 * @covers \datalynxfield_duration\field::get_search_sql
 * @covers \mod_datalynx\local\field\datalynxfield_option_multiple::get_search_sql
 */
final class filter_not_criteria_test extends advanced_testcase {
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
     * Insert a field of the given type and return its id.
     *
     * @param string $type The datalynx field type.
     * @param string $name The field name.
     * @param string $param1 The param1 value (option list for option fields).
     * @return int The new field id.
     */
    private function make_field(string $type, string $name, string $param1 = ''): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => $type, 'name' => $name, 'description' => '',
            'required' => 0, 'visibleto' => 0, 'editableby' => 0,
            'param1' => $param1, 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);
    }

    /**
     * Insert an entry, optionally with one content value.
     *
     * @param int $fieldid The field id to store content for.
     * @param string|null $content The content value, or null to write no content record at all.
     * @return int The new entry id.
     */
    private function make_entry(int $fieldid, ?string $content): int {
        global $DB, $USER;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($content !== null) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
            ]);
        }
        return $entryid;
    }

    /**
     * Run the given customsearch over the instance and return the matching entry ids.
     *
     * @param array $customsearch customsearch aggregated by field id
     * @param int[] $contentfields field ids the filter should load content for
     * @return int[]
     */
    private function filtered_entry_ids(array $customsearch, array $contentfields): array {
        global $DB;

        $filterid = (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'NOT criteria',
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
        $filter->contentfields = $contentfields;
        $result = (new datalynx_entries($this->dlx, $filter))->get_entries();

        return array_map('intval', array_keys($result->entries));
    }

    /**
     * A negated time range must keep the entries outside the range and the entry that has no time
     * at all. The condition also has to be negated as a whole: "NOT (t >= from AND t < to)", not
     * "(NOT t >= from) AND t < to".
     */
    public function test_not_between_on_time_keeps_entries_outside_the_range_and_without_a_value(): void {
        $fieldid = $this->make_field('time', 'Departure');

        $before = $this->make_entry($fieldid, (string) 1000);
        $inside = $this->make_entry($fieldid, (string) 5000);
        $after = $this->make_entry($fieldid, (string) 9000);
        $novalue = $this->make_entry($fieldid, null);

        $ids = $this->filtered_entry_ids(
            [$fieldid => ['AND' => [['NOT', 'BETWEEN', [4000, 6000]]]]],
            [$fieldid]
        );

        $this->assertNotContains($inside, $ids);
        $this->assertEqualsCanonicalizing([$before, $after, $novalue], $ids);
    }

    /**
     * The same for a negated exact time.
     */
    public function test_not_equal_on_time_keeps_entries_without_a_value(): void {
        $fieldid = $this->make_field('time', 'Departure');

        $match = $this->make_entry($fieldid, (string) 5000);
        $other = $this->make_entry($fieldid, (string) 9000);
        $novalue = $this->make_entry($fieldid, null);

        $ids = $this->filtered_entry_ids(
            [$fieldid => ['AND' => [['NOT', '=', [5000, 0]]]]],
            [$fieldid]
        );

        $this->assertNotContains($match, $ids);
        $this->assertEqualsCanonicalizing([$other, $novalue], $ids);
    }

    /**
     * The positive time range still behaves: only the entry inside it is returned.
     */
    public function test_between_on_time_returns_only_the_entry_inside_the_range(): void {
        $fieldid = $this->make_field('time', 'Departure');

        $this->make_entry($fieldid, (string) 1000);
        $inside = $this->make_entry($fieldid, (string) 5000);
        $this->make_entry($fieldid, null);

        $ids = $this->filtered_entry_ids(
            [$fieldid => ['AND' => [['', 'BETWEEN', [4000, 6000]]]]],
            [$fieldid]
        );

        $this->assertEquals([$inside], $ids);
    }

    /**
     * A negated duration must not return the entries it is meant to exclude. The fragment built
     * for the lookup states the criterion positively; negating it there as well would cancel out
     * the exclusion and return exactly the matching entry.
     */
    public function test_not_equal_on_duration_excludes_the_matching_entry(): void {
        $fieldid = $this->make_field('duration', 'Travel time');

        $match = $this->make_entry($fieldid, (string) 3600);
        $other = $this->make_entry($fieldid, (string) 7200);
        $novalue = $this->make_entry($fieldid, null);

        $ids = $this->filtered_entry_ids(
            [$fieldid => ['AND' => [['NOT', '=', [3600]]]]],
            [$fieldid]
        );

        $this->assertNotContains($match, $ids);
        $this->assertEqualsCanonicalizing([$other, $novalue], $ids);
    }

    /**
     * The positive duration criterion still returns the matching entry only.
     */
    public function test_equal_on_duration_returns_only_the_matching_entry(): void {
        $fieldid = $this->make_field('duration', 'Travel time');

        $match = $this->make_entry($fieldid, (string) 3600);
        $this->make_entry($fieldid, (string) 7200);
        $this->make_entry($fieldid, null);

        $ids = $this->filtered_entry_ids(
            [$fieldid => ['AND' => [['', '=', [3600]]]]],
            [$fieldid]
        );

        $this->assertEquals([$match], $ids);
    }

    /**
     * "NOT any of Red" on a checkbox field must return the entries without Red, including the one
     * with no content record. The rescue arm only works when the fragment reports that it does not
     * read the joined content table, so the filter does not qualify it with cX.fieldid = X.
     */
    public function test_not_anyof_on_checkbox_keeps_entries_without_a_content_record(): void {
        $fieldid = $this->make_field('checkbox', 'Colors', "Red\nGreen\nBlue");

        $red = $this->make_entry($fieldid, '#1#,#3#');
        $green = $this->make_entry($fieldid, '#2#');
        $novalue = $this->make_entry($fieldid, null);

        $ids = $this->filtered_entry_ids(
            [$fieldid => ['AND' => [['NOT', 'ANY_OF', [1]]]]],
            [$fieldid]
        );

        $this->assertNotContains($red, $ids);
        $this->assertEqualsCanonicalizing([$green, $novalue], $ids);
    }

    /**
     * When no entry holds the excluded option the criterion matches every entry rather than none.
     */
    public function test_not_anyof_on_checkbox_returns_all_when_no_entry_has_the_option(): void {
        $fieldid = $this->make_field('checkbox', 'Colors', "Red\nGreen\nBlue");

        $green = $this->make_entry($fieldid, '#2#');
        $novalue = $this->make_entry($fieldid, null);

        $ids = $this->filtered_entry_ids(
            [$fieldid => ['AND' => [['NOT', 'ANY_OF', [1]]]]],
            [$fieldid]
        );

        $this->assertEqualsCanonicalizing([$green, $novalue], $ids);
    }

    /**
     * A criterion in the OR bucket that resolves to nothing must be skipped rather than added as an
     * empty fragment, which would compile to "( OR ... )" and merge a non-array parameter set.
     */
    public function test_or_bucket_skips_a_criterion_that_resolves_to_nothing(): void {
        $selectid = $this->make_field('select', 'Validation', "validated\nrejected\nin review");

        $rejected = $this->make_entry($selectid, '2');
        $inreview = $this->make_entry($selectid, '3');

        // The first row excludes an option no entry holds, so it contributes no SQL at all; the
        // second row is an ordinary criterion. Two rows are needed for the empty one to break the
        // OR chain.
        $ids = $this->filtered_entry_ids(
            [$selectid => ['OR' => [['NOT', 'ANY_OF', [1]], ['', 'ANY_OF', [3]]]]],
            [$selectid]
        );

        $this->assertContains($inreview, $ids);
        $this->assertNotContains($rejected, $ids);
    }
}
