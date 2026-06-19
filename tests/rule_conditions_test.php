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
     * @param int $fieldid The field id to store content for.
     * @param string|null $content The content value.
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
}
