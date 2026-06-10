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
 * Tests for value-based availability conditions on field behaviors.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\field\datalynxfield_behavior;
use stdClass;

/**
 * Tests for value-based availability conditions on field behaviors.
 *
 * @covers \mod_datalynx\local\field\datalynxfield_behavior::passes_conditions
 * @covers \mod_datalynx\local\field\datalynxfield_behavior::matching_entryids
 */
final class behavior_conditions_test extends advanced_testcase {
    /** @var datalynx The datalynx instance used by the current test. */
    private datalynx $dlx;

    /**
     * Set up a fresh datalynx instance per test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        datalynxfield_behavior::reset_condition_cache();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Insert a field of the given type and return its id.
     *
     * @param string $type The datalynx field type.
     * @param string $name The field name.
     * @param string $param1 The param1 value (e.g. newline-separated options for select).
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
     * Insert an entry with a single content value for one field and return its id.
     *
     * @param int $fieldid The field id to store content for (0 to store no content).
     * @param string|null $content The content value (null to store no content row).
     * @return int The new entry id.
     */
    private function make_entry(int $fieldid, ?string $content): int {
        global $DB, $USER;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($fieldid && $content !== null) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
            ]);
        }
        return $entryid;
    }

    /**
     * Build and persist a behavior carrying the given conditions, then load it back.
     *
     * @param array $conditions The conditions structure (['match' => ..., 'rules' => [...]]).
     * @return datalynxfield_behavior
     */
    private function make_behavior(array $conditions): datalynxfield_behavior {
        global $DB;
        $id = (int) $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'cond' . random_string(6),
            'description' => '',
            'visibleto' => serialize(['permissions' => []]),
            'editableby' => serialize([]),
            'required' => 0,
            'conditions' => json_encode($conditions),
        ]);
        return datalynxfield_behavior::from_id($id);
    }

    /**
     * A behavior with no rules always passes.
     */
    public function test_no_rules_passes(): void {
        $entryid = $this->make_entry(0, null);
        $behavior = $this->make_behavior(['match' => 'all', 'rules' => []]);
        $this->assertTrue($behavior->passes_conditions((object) ['id' => $entryid]));
    }

    /**
     * Unsaved entries (id <= 0) always pass (v1 limitation: conditions apply to saved entries only).
     */
    public function test_unsaved_entry_passes(): void {
        $fieldid = $this->make_field('text', 'Text');
        $behavior = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => '=', 'value' => 'x']]]);
        $this->assertTrue($behavior->passes_conditions((object) ['id' => -1]));
        $this->assertTrue($behavior->passes_conditions((object) ['id' => 0]));
    }

    /**
     * Single-select equality and the NOT flag (not-equal).
     */
    public function test_select_equal_and_notequal(): void {
        $fieldid = $this->make_field('select', 'Gender', "Male\nFemale");
        $male = $this->make_entry($fieldid, '1');
        $female = $this->make_entry($fieldid, '2');

        // Equal to option 1 (Male).
        $equal = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => 'ANY_OF', 'value' => [1]]]]);
        $this->assertTrue($equal->passes_conditions((object) ['id' => $male]));
        $this->assertFalse($equal->passes_conditions((object) ['id' => $female]));

        // NOT equal to option 1 (Male) via the NOT flag.
        $notequal = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => 'NOT', 'operator' => 'ANY_OF', 'value' => [1]]]]);
        $this->assertFalse($notequal->passes_conditions((object) ['id' => $male]));
        $this->assertTrue($notequal->passes_conditions((object) ['id' => $female]));
    }

    /**
     * Text equality, contains (LIKE) and empty.
     */
    public function test_text_equal_contains_empty(): void {
        $fieldid = $this->make_field('text', 'Notes');
        $hello = $this->make_entry($fieldid, 'hello world');
        $blank = $this->make_entry($fieldid, '');

        $equal = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => '=', 'value' => 'hello world']]]);
        $this->assertTrue($equal->passes_conditions((object) ['id' => $hello]));
        $this->assertFalse($equal->passes_conditions((object) ['id' => $blank]));

        $contains = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => 'LIKE', 'value' => 'world']]]);
        $this->assertTrue($contains->passes_conditions((object) ['id' => $hello]));
        $this->assertFalse($contains->passes_conditions((object) ['id' => $blank]));

        $empty = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => '', 'value' => '']]]);
        $this->assertTrue($empty->passes_conditions((object) ['id' => $blank]));
        $this->assertFalse($empty->passes_conditions((object) ['id' => $hello]));
    }

    /**
     * Numeric (duration) comparison operators.
     */
    public function test_duration_greater_and_equal(): void {
        $fieldid = $this->make_field('duration', 'Length', 'seconds');
        $short = $this->make_entry($fieldid, '100');
        $long = $this->make_entry($fieldid, '300');

        $greater = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => '>', 'value' => [200]]]]);
        $this->assertFalse($greater->passes_conditions((object) ['id' => $short]));
        $this->assertTrue($greater->passes_conditions((object) ['id' => $long]));

        $equal = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => '=', 'value' => [100]]]]);
        $this->assertTrue($equal->passes_conditions((object) ['id' => $short]));
        $this->assertFalse($equal->passes_conditions((object) ['id' => $long]));
    }

    /**
     * Time before/after comparison (stored as unix timestamps).
     */
    public function test_time_after(): void {
        $fieldid = $this->make_field('time', 'When');
        $threshold = mktime(0, 0, 0, 6, 1, 2026);
        $before = $this->make_entry($fieldid, (string) ($threshold - 86400));
        $after = $this->make_entry($fieldid, (string) ($threshold + 86400));

        // The time field's search value is a [from, to] pair (as produced by its parse_search()).
        $isafter = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => $fieldid, 'not' => '', 'operator' => '>', 'value' => [$threshold, 0]]]]);
        $this->assertFalse($isafter->passes_conditions((object) ['id' => $before]));
        $this->assertTrue($isafter->passes_conditions((object) ['id' => $after]));
    }

    /**
     * match=all requires every rule; match=any requires at least one.
     */
    public function test_match_all_vs_any(): void {
        $genderid = $this->make_field('select', 'Gender', "Male\nFemale");
        $textid = $this->make_field('text', 'Notes');

        // Entry is Male but notes do not contain "vip".
        $entryid = $this->make_entry($genderid, '1');
        global $DB;
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $textid, 'entryid' => $entryid, 'content' => 'ordinary',
        ]);

        $rules = [
            ['sourcefieldid' => $genderid, 'not' => '', 'operator' => 'ANY_OF', 'value' => [1]],
            ['sourcefieldid' => $textid, 'not' => '', 'operator' => 'LIKE', 'value' => 'vip'],
        ];

        $all = $this->make_behavior(['match' => 'all', 'rules' => $rules]);
        $this->assertFalse($all->passes_conditions((object) ['id' => $entryid]));

        $any = $this->make_behavior(['match' => 'any', 'rules' => $rules]);
        $this->assertTrue($any->passes_conditions((object) ['id' => $entryid]));
    }

    /**
     * A rule referencing a removed/unknown source field never matches.
     */
    public function test_unknown_source_field(): void {
        $entryid = $this->make_entry(0, null);
        $behavior = $this->make_behavior(['match' => 'all',
                'rules' => [['sourcefieldid' => 999999, 'not' => '', 'operator' => '=', 'value' => 'x']]]);
        $this->assertFalse($behavior->passes_conditions((object) ['id' => $entryid]));
    }

    /**
     * Conditions survive a form round-trip (form_to_db then db_to_form).
     */
    public function test_form_roundtrip(): void {
        $conditions = ['match' => 'any', 'rules' => [
            ['sourcefieldid' => 7, 'not' => 'NOT', 'operator' => 'ANY_OF', 'value' => [3, 5]],
        ]];

        $formdata = (object) [
            'id' => 0, 'd' => $this->dlx->id(), 'name' => 'b', 'description' => '',
            'visibletopermission' => [], 'visibletouser' => [], 'visibletoteammember' => [],
            'editableby' => [], 'required' => 0, 'conditions' => $conditions,
        ];
        $record = datalynxfield_behavior::form_to_db($formdata);
        $this->assertSame($conditions, json_decode($record->conditions, true));

        $record->id = 1;
        $back = datalynxfield_behavior::db_to_form($record);
        $this->assertSame($conditions, $back->conditions);
    }

    /**
     * An empty conditions set serializes to null (no conditions stored).
     */
    public function test_empty_conditions_serialize_null(): void {
        $formdata = (object) [
            'id' => 0, 'd' => $this->dlx->id(), 'name' => 'b', 'description' => '',
            'visibletopermission' => [], 'visibletouser' => [], 'visibletoteammember' => [],
            'editableby' => [], 'required' => 0, 'conditions' => ['match' => 'all', 'rules' => []],
        ];
        $record = datalynxfield_behavior::form_to_db($formdata);
        $this->assertNull($record->conditions);
    }
}
