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
 * Tests for the select field "matches my profile field" (MY_PROFILE) search operator.
 *
 * @package    datalynxfield_select
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_select;

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;

/**
 * Tests that the MY_PROFILE operator filters entries by matching a select option against
 * the current user's profile field value.
 *
 * @covers \datalynxfield_select\field::get_search_sql
 * @covers \datalynxfield_select\field::resolve_my_profile_option_key
 * @covers \datalynxfield_select\field::resolve_user_profile_value
 */
final class search_my_profile_test extends advanced_testcase {
    /** @var datalynx The datalynx instance under test. */
    private $dlx;

    /** @var int The select field id. */
    private $selectid;

    /** @var int[] Map of option label => created entry id. */
    private $entries = [];

    /**
     * Set up a datalynx instance with a select field and one entry per option.
     */
    public function setUp(): void {
        global $DB, $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        // Options become keys 1=Sales, 2=Marketing, 3=IT.
        $this->selectid = $this->create_field($this->dlx->id(), 'select', 'Department', ['param1' => "Sales\nMarketing\nIT"]);
        $this->dlx->get_fields(null, false, true);

        foreach (['Sales' => 1, 'Marketing' => 2, 'IT' => 3] as $label => $key) {
            $entryid = $this->create_entry($this->dlx->id(), $USER->id);
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $this->selectid, 'entryid' => $entryid, 'content' => (string) $key,
            ]);
            $this->entries[$label] = $entryid;
        }
    }

    /**
     * A standard user field (department) is matched against the option label.
     */
    public function test_match_standard_user_field(): void {
        global $USER;

        $USER->department = 'Marketing';
        $result = $this->run_my_profile_search('department');
        $this->assertEquals([$this->entries['Marketing']], array_keys($result));
    }

    /**
     * Matching is case-insensitive and ignores surrounding whitespace.
     */
    public function test_match_is_case_insensitive(): void {
        global $USER;

        $USER->department = '  marKETing ';
        $result = $this->run_my_profile_search('department');
        $this->assertEquals([$this->entries['Marketing']], array_keys($result));
    }

    /**
     * A custom profile field (by shortname) is matched against the option label.
     */
    public function test_match_custom_profile_field(): void {
        global $DB, $USER;

        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'mydept', 'name' => 'My department',
        ]);
        $DB->insert_record('user_info_data', (object) [
            'userid' => $USER->id, 'fieldid' => $field->id, 'data' => 'IT', 'dataformat' => 0,
        ]);

        $result = $this->run_my_profile_search('mydept');
        $this->assertEquals([$this->entries['IT']], array_keys($result));
    }

    /**
     * When the profile value matches no option, no entries are returned.
     */
    public function test_no_matching_option_returns_nothing(): void {
        global $USER;

        $USER->department = 'Logistics';
        $result = $this->run_my_profile_search('department');
        $this->assertEmpty($result);
    }

    /**
     * When the profile field is empty, no entries are returned.
     */
    public function test_empty_profile_value_returns_nothing(): void {
        global $USER;

        $USER->department = '';
        $result = $this->run_my_profile_search('department');
        $this->assertEmpty($result);
    }

    /**
     * The NOT modifier returns every entry except the matching one.
     */
    public function test_not_match_returns_other_entries(): void {
        global $USER;

        $USER->department = 'Marketing';
        $result = $this->run_my_profile_search('department', 'NOT');
        $this->assertEqualsCanonicalizing(
            [$this->entries['Sales'], $this->entries['IT']],
            array_keys($result)
        );
    }

    /**
     * Build a filter using the MY_PROFILE operator on the select field and return its entries.
     *
     * @param string $shortname The profile field shortname operand.
     * @param string $not '' or 'NOT'.
     * @return array entryid => entry.
     */
    private function run_my_profile_search(string $shortname, string $not = ''): array {
        global $DB;

        $customsearch = [$this->selectid => ['AND' => [[$not, 'MY_PROFILE', $shortname]]]];
        $filterid = (int) $DB->insert_record('datalynx_filters', (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'MyProfileFilter' . $shortname . $not,
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
        $filter->contentfields = [$this->selectid];
        $entriesclass = new datalynx_entries($this->dlx, $filter);
        return $entriesclass->get_entries()->entries ?? [];
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
}
