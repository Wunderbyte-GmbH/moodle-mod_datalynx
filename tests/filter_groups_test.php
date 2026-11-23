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
 * Requested groups filter the entries by their group, not by their author.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_datalynx\local\datalynx_entries::get_entries
 */
final class filter_groups_test extends advanced_testcase {
    /** @var datalynx The datalynx instance used by the current test. */
    private datalynx $dlx;

    /** @var \stdClass The course of the instance. */
    private \stdClass $course;

    /**
     * Set up a fresh datalynx instance without group mode per test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $this->course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Insert an entry.
     *
     * @param int $userid The author.
     * @param int $groupid The group of the entry.
     * @return int The entry id.
     */
    private function make_entry(int $userid, int $groupid): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $userid, 'groupid' => $groupid,
            'approved' => 1, 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Run the instance's entries through a filter limited to the given groups.
     *
     * @param array $groups Group ids, as view\base sets them from the groups option.
     * @return \stdClass The result of get_entries(): entries, max, found.
     */
    private function entries_of_groups(array $groups): \stdClass {
        global $DB;
        $filterid = (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $this->dlx->id(), 'name' => 'Groups', 'description' => '',
            'customsort' => '', 'customsearch' => '', 'search' => '', 'groupby' => '',
            'perpage' => 50, 'selection' => 0, 'page' => 0, 'eids' => '',
        ]);
        \mod_datalynx\local\definition_cache::purge();
        $filter = new datalynx_filter($DB->get_record('datalynx_filters', ['id' => $filterid]));
        $filter->groups = $groups;
        return (new datalynx_entries($this->dlx, $filter))->get_entries();
    }

    /**
     * Only the entries of the requested groups are returned, whoever wrote them.
     */
    public function test_requested_groups_filter_by_entry_group(): void {
        $generator = $this->getDataGenerator();
        $one = $generator->create_group(['courseid' => $this->course->id]);
        $two = $generator->create_group(['courseid' => $this->course->id]);
        $three = $generator->create_group(['courseid' => $this->course->id]);
        $author = $generator->create_user();
        $other = $generator->create_user();

        $inone = $this->make_entry($author->id, $one->id);
        $intwo = $this->make_entry($other->id, $two->id);
        $this->make_entry($author->id, $three->id);
        $this->make_entry($other->id, 0);

        $result = $this->entries_of_groups([$one->id, $two->id]);

        $this->assertEqualsCanonicalizing([$inone, $intwo], array_map('intval', array_keys($result->entries)));
        $this->assertSame(2, (int) $result->found);
        $this->assertSame(2, (int) $result->max);
    }

    /**
     * A user whose id equals a requested group id does not bring in their entries.
     */
    public function test_user_id_matching_group_id_is_not_a_match(): void {
        global $DB;
        $generator = $this->getDataGenerator();
        $group = $generator->create_group(['courseid' => $this->course->id]);
        $author = $generator->create_user();
        // A group whose id is the author's id: before the fix, it selected the author's entries.
        $DB->set_field('groups', 'id', $author->id, ['id' => $group->id]);

        $this->make_entry($author->id, 0);
        $ingroup = $this->make_entry($generator->create_user()->id, $author->id);

        $result = $this->entries_of_groups([$author->id]);

        $this->assertSame([$ingroup], array_map('intval', array_keys($result->entries)));
    }
}
