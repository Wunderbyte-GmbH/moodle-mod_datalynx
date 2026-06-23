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
 * Tests for rule conditions.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use stdClass;

/**
 * Tests for rule conditions.
 *
 * @group mod_datalynx
 * @covers \datalynxrule_eventnotification\rule
 */
final class rule_conditions_test extends advanced_testcase {
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
     * @param string $param1 The param1 value.
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
     * @param int $fieldid The field id to store content for (0 for none).
     * @param string|null $content The content value.
     * @param int $status The entry submission status (datalynx_entries.status).
     * @param int $approved The entry approval flag (datalynx_entries.approved).
     * @return int The new entry id.
     */
    private function make_entry(int $fieldid, ?string $content, int $status = 0, int $approved = 1): int {
        global $DB, $USER;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => $approved, 'status' => $status, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($fieldid && $content !== null) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
            ]);
        }
        return $entryid;
    }

    /** @var int Counter used to give each test rule a unique name. */
    private int $rulecounter = 0;

    /**
     * Insert an eventnotification rule whose trigger conditions are stored as JSON in param9.
     *
     * @param array $conditions customsearch aggregated by field id, or [] for "always fire".
     * @return \mod_datalynx\local\rule\base
     */
    private function make_condition_rule(array $conditions): \mod_datalynx\local\rule\base {
        global $DB;
        $ruleid = (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Condition rule ' . (++$this->rulecounter),
            'description' => '',
            'type' => 'eventnotification',
            'enabled' => 1,
            'param1' => json_encode(['entry_created']),
            'param9' => $conditions ? json_encode($conditions) : null,
        ]);
        return $this->dlx->get_rule_manager()->get_rule_from_id($ruleid);
    }

    /**
     * Fire an entry_created event for the given entry through the rule and return the trigger result.
     *
     * trigger() returns true when the conditions are met (the rule proceeds to notify) and false
     * when they are not, so it is a faithful proxy for condition evaluation.
     *
     * @param \mod_datalynx\local\rule\base $rule
     * @param int $entryid
     * @return bool
     */
    private function fire(\mod_datalynx\local\rule\base $rule, int $entryid): bool {
        $event = \mod_datalynx\event\entry_created::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid,
            'other' => ['dataid' => $this->dlx->id()],
        ]);
        return $rule->trigger($event);
    }

    /**
     * Test rule condition evaluation on checkbox fields (multiple options).
     */
    public function test_checkbox_rule_conditions(): void {
        global $DB;

        $checkboxfieldid = $this->make_field('checkbox', 'Colors', "Red\nGreen\nBlue");

        // Rule condition matches Red and Blue (keys '1' and '3').
        $rule = (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Checkbox Rule',
            'description' => 'Test Rule',
            'type' => 'eventnotification',
            'enabled' => 1,
            'param1' => json_encode(['entry_created']),
            'param5' => $checkboxfieldid,
            'param10' => json_encode(['1', '3']), // Red and Blue.
        ];
        $ruleid = $DB->insert_record('datalynx_rules', $rule);
        $ruleobj = $this->dlx->get_rule_manager()->get_rule_from_id($ruleid);

        // Matching entry (stored checkbox format is #1#,#3#).
        $entryid1 = $this->make_entry($checkboxfieldid, '#1#,#3#');
        $event1 = \mod_datalynx\event\entry_created::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid1,
            'other' => ['dataid' => $this->dlx->id()],
        ]);
        // The trigger() method should return true (it tries to execute/notify because conditions are met).
        $this->assertTrue($ruleobj->trigger($event1));

        // Non-matching entry.
        $entryid2 = $this->make_entry($checkboxfieldid, '#1#,#2#');
        $event2 = \mod_datalynx\event\entry_created::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid2,
            'other' => ['dataid' => $this->dlx->id()],
        ]);
        // The trigger() method should return false (conditions not met).
        $this->assertFalse($ruleobj->trigger($event2));
    }

    /**
     * Test rule condition evaluation on radiobutton/select fields (single value).
     */
    public function test_single_value_rule_conditions(): void {
        global $DB;

        $radiofieldid = $this->make_field('radiobutton', 'Size', "Small\nMedium\nLarge");

        $rule = (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Radio Rule',
            'description' => 'Test Rule',
            'type' => 'eventnotification',
            'enabled' => 1,
            'param1' => json_encode(['entry_created']),
            'param5' => $radiofieldid,
            'param10' => '2', // Medium option (key 2).
        ];
        $ruleid = $DB->insert_record('datalynx_rules', $rule);
        $ruleobj = $this->dlx->get_rule_manager()->get_rule_from_id($ruleid);

        // Matching entry.
        $entryid1 = $this->make_entry($radiofieldid, '2');
        $event1 = \mod_datalynx\event\entry_created::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid1,
            'other' => ['dataid' => $this->dlx->id()],
        ]);
        $this->assertTrue($ruleobj->trigger($event1));

        // Non-matching entry.
        $entryid2 = $this->make_entry($radiofieldid, '1');
        $event2 = \mod_datalynx\event\entry_created::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid2,
            'other' => ['dataid' => $this->dlx->id()],
        ]);
        $this->assertFalse($ruleobj->trigger($event2));
    }

    /**
     * Internal status field (##status##) condition: stored on datalynx_entries.status,
     * evaluated on the entry row (not datalynx_contents).
     */
    public function test_status_condition_param9(): void {
        $rule = $this->make_condition_rule([
            'status' => ['AND' => [['', '=', \datalynxfield_status\field::STATUS_FINAL_SUBMISSION]]],
        ]);

        $match = $this->make_entry(0, null, \datalynxfield_status\field::STATUS_FINAL_SUBMISSION);
        $nomatch = $this->make_entry(0, null, \datalynxfield_status\field::STATUS_DRAFT);

        $this->assertTrue($this->fire($rule, $match));
        $this->assertFalse($this->fire($rule, $nomatch));
    }

    /**
     * Internal approve field (##approved##) condition: stored on datalynx_entries.approved.
     */
    public function test_approve_condition_param9(): void {
        $rule = $this->make_condition_rule([
            'approve' => ['AND' => [['', '=', 1]]],
        ]);

        $match = $this->make_entry(0, null, 0, 1);
        $nomatch = $this->make_entry(0, null, 0, 0);

        $this->assertTrue($this->fire($rule, $match));
        $this->assertFalse($this->fire($rule, $nomatch));
    }

    /**
     * Text field exact-match (=) condition.
     */
    public function test_text_equals_condition_param9(): void {
        $fieldid = $this->make_field('text', 'Title');
        $rule = $this->make_condition_rule([
            $fieldid => ['AND' => [['', '=', 'hello']]],
        ]);

        $this->assertTrue($this->fire($rule, $this->make_entry($fieldid, 'hello')));
        $this->assertFalse($this->fire($rule, $this->make_entry($fieldid, 'world')));
    }

    /**
     * Text field contains (LIKE) condition. The value is a bare substring; the search adds the wildcards.
     */
    public function test_text_like_condition_param9(): void {
        $fieldid = $this->make_field('text', 'Body');
        $rule = $this->make_condition_rule([
            $fieldid => ['AND' => [['', 'LIKE', 'ell']]],
        ]);

        $this->assertTrue($this->fire($rule, $this->make_entry($fieldid, 'hello')));
        $this->assertFalse($this->fire($rule, $this->make_entry($fieldid, 'world')));
    }

    /**
     * Multiple conditions combined with AND across different fields (status AND text).
     */
    public function test_and_combination_param9(): void {
        $fieldid = $this->make_field('text', 'Title');
        $rule = $this->make_condition_rule([
            'status' => ['AND' => [['', '=', \datalynxfield_status\field::STATUS_FINAL_SUBMISSION]]],
            $fieldid => ['AND' => [['', 'LIKE', 'foo']]],
        ]);

        $final = \datalynxfield_status\field::STATUS_FINAL_SUBMISSION;
        $draft = \datalynxfield_status\field::STATUS_DRAFT;

        // Both conditions met.
        $this->assertTrue($this->fire($rule, $this->make_entry($fieldid, 'foobar', $final)));
        // Wrong status.
        $this->assertFalse($this->fire($rule, $this->make_entry($fieldid, 'foobar', $draft)));
        // Wrong text.
        $this->assertFalse($this->fire($rule, $this->make_entry($fieldid, 'bar', $final)));
    }

    /**
     * Multiple conditions combined with OR across different fields (status OR text).
     */
    public function test_or_combination_param9(): void {
        $fieldid = $this->make_field('text', 'Title');
        $rule = $this->make_condition_rule([
            'status' => ['OR' => [['', '=', \datalynxfield_status\field::STATUS_FINAL_SUBMISSION]]],
            $fieldid => ['OR' => [['', 'LIKE', 'foo']]],
        ]);

        $final = \datalynxfield_status\field::STATUS_FINAL_SUBMISSION;
        $draft = \datalynxfield_status\field::STATUS_DRAFT;

        // Only status matches.
        $this->assertTrue($this->fire($rule, $this->make_entry($fieldid, 'bar', $final)));
        // Only text matches.
        $this->assertTrue($this->fire($rule, $this->make_entry($fieldid, 'foobar', $draft)));
        // Neither matches.
        $this->assertFalse($this->fire($rule, $this->make_entry($fieldid, 'bar', $draft)));
    }

    /**
     * NOT negation: fire when the field value does NOT match.
     */
    public function test_not_negation_param9(): void {
        $rule = $this->make_condition_rule([
            'status' => ['AND' => [['NOT', '=', \datalynxfield_status\field::STATUS_FINAL_SUBMISSION]]],
        ]);

        // Draft != final -> NOT matches -> fires.
        $this->assertTrue($this->fire($rule, $this->make_entry(0, null, \datalynxfield_status\field::STATUS_DRAFT)));
        // Final == final -> NOT excludes -> does not fire.
        $this->assertFalse(
            $this->fire($rule, $this->make_entry(0, null, \datalynxfield_status\field::STATUS_FINAL_SUBMISSION))
        );
    }

    /**
     * No conditions configured: the rule always fires.
     */
    public function test_empty_conditions_always_fire(): void {
        $rule = $this->make_condition_rule([]);
        $this->assertTrue($this->fire($rule, $this->make_entry(0, null)));
    }
}
