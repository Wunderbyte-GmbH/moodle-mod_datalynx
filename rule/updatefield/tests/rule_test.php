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
 * Tests for the update-field rule.
 *
 * @package    datalynxrule_updatefield
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxrule_updatefield;

use advanced_testcase;
use mod_datalynx\datalynx;

/**
 * Tests for the update-field rule.
 *
 * @group mod_datalynx
 * @covers \datalynxrule_updatefield\rule
 */
final class rule_test extends advanced_testcase {
    /** @var datalynx The datalynx instance used by the current test. */
    private datalynx $dlx;

    /** @var int Counter used to give each test rule a unique name. */
    private int $rulecounter = 0;

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
     * @param string $param1 The param1 value (newline-separated options for option fields).
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
     * Insert an entry and return its id.
     *
     * @param int $status The entry submission status (datalynx_entries.status).
     * @param int $approved The entry approval flag (datalynx_entries.approved).
     * @return int The new entry id.
     */
    private function make_entry(int $status = 0, int $approved = 1): int {
        global $DB, $USER;
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => $approved, 'status' => $status, 'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * Store a content value for one field of an entry.
     *
     * @param int $entryid
     * @param int $fieldid
     * @param string $content
     */
    private function set_content(int $entryid, int $fieldid, string $content): void {
        global $DB;
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
        ]);
    }

    /**
     * Read the stored content of one field of an entry.
     *
     * @param int $entryid
     * @param int $fieldid
     * @return string|null null when there is no content row
     */
    private function content_of(int $entryid, int $fieldid): ?string {
        global $DB;
        $rec = $DB->get_record('datalynx_contents', ['entryid' => $entryid, 'fieldid' => $fieldid]);
        return $rec ? $rec->content : null;
    }

    /**
     * Insert an update-field rule and return the loaded rule object.
     *
     * @param int $fieldid target field id (param2)
     * @param string|null $value stored new value (param3)
     * @param array $conditions customsearch aggregated by field id (param9), or []
     * @param bool $cascade whether to fire a follow-up event (param4)
     * @param array $events triggering event names (param1)
     * @return \mod_datalynx\local\rule\base
     */
    private function make_rule(
        int $fieldid,
        ?string $value,
        array $conditions = [],
        bool $cascade = false,
        array $events = ['entry_updated']
    ): \mod_datalynx\local\rule\base {
        global $DB;
        $ruleid = (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'Update rule ' . (++$this->rulecounter),
            'description' => '',
            'type' => 'updatefield',
            'enabled' => 1,
            'param1' => json_encode($events),
            'param2' => $fieldid,
            'param3' => $value,
            'param4' => $cascade ? '1' : null,
            'param9' => $conditions ? json_encode($conditions) : null,
        ]);
        return $this->dlx->get_rule_manager()->get_rule_from_id($ruleid);
    }

    /**
     * Fire an entry_updated event for the given entry through the rule and return the result.
     *
     * @param \mod_datalynx\local\rule\base $rule
     * @param int $entryid
     * @param int[] $changedfields field IDs reported as changed
     * @return bool
     */
    private function fire_update(\mod_datalynx\local\rule\base $rule, int $entryid, array $changedfields = []): bool {
        $event = \mod_datalynx\event\entry_updated::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid,
            'other' => ['dataid' => $this->dlx->id(), 'changed_field_ids' => $changedfields],
        ]);
        return $rule->trigger($event);
    }

    /**
     * A text field is set to the configured literal value.
     */
    public function test_updates_text_field(): void {
        $fieldid = $this->make_field('text', 'Decision');
        $entryid = $this->make_entry();
        $rule = $this->make_rule($fieldid, 'Approved');

        $this->assertTrue($this->fire_update($rule, $entryid));
        $this->assertSame('Approved', $this->content_of($entryid, $fieldid));
    }

    /**
     * A single-select field is set to the configured option key.
     */
    public function test_updates_select_field(): void {
        $fieldid = $this->make_field('select', 'State', "Open\nClosed");
        $entryid = $this->make_entry();
        // Closed is option key 2.
        $rule = $this->make_rule($fieldid, '2');

        $this->assertTrue($this->fire_update($rule, $entryid));
        $this->assertSame('2', $this->content_of($entryid, $fieldid));
    }

    /**
     * A checkbox field is set to the configured set of option keys (stored as #k#,#k#).
     */
    public function test_updates_checkbox_field(): void {
        $fieldid = $this->make_field('checkbox', 'Tags', "A\nB\nC");
        $entryid = $this->make_entry();
        $rule = $this->make_rule($fieldid, json_encode([1, 3]));

        $this->assertTrue($this->fire_update($rule, $entryid));
        $this->assertSame('#1#,#3#', $this->content_of($entryid, $fieldid));
    }

    /**
     * Updating a field that already has content overwrites it in place (no duplicate row).
     */
    public function test_overwrites_existing_content_without_duplicating(): void {
        global $DB;
        $fieldid = $this->make_field('text', 'Decision');
        $entryid = $this->make_entry();
        $this->set_content($entryid, $fieldid, 'Pending');
        $rule = $this->make_rule($fieldid, 'Approved');

        $this->fire_update($rule, $entryid);

        $this->assertSame('Approved', $this->content_of($entryid, $fieldid));
        $this->assertCount(
            1,
            $DB->get_records('datalynx_contents', ['entryid' => $entryid, 'fieldid' => $fieldid]),
            'There must be exactly one content row for the field.'
        );
    }

    /**
     * The rule does not write when the trigger conditions are not satisfied.
     */
    public function test_condition_gating(): void {
        $titleid = $this->make_field('text', 'Title');
        $targetid = $this->make_field('text', 'Decision');
        // Only act when Title equals "go".
        $rule = $this->make_rule($targetid, 'Approved', [
            $titleid => ['AND' => [['', '=', 'go']]],
        ]);

        $match = $this->make_entry();
        $this->set_content($match, $titleid, 'go');
        $nomatch = $this->make_entry();
        $this->set_content($nomatch, $titleid, 'stop');

        $this->assertTrue($this->fire_update($rule, $match));
        $this->assertSame('Approved', $this->content_of($match, $targetid));

        $this->assertFalse($this->fire_update($rule, $nomatch));
        $this->assertNull($this->content_of($nomatch, $targetid));
    }

    /**
     * With an on-change list, the rule writes only when a listed field actually changed.
     */
    public function test_only_on_change_gating(): void {
        $triggerid = $this->make_field('radiobutton', 'Priority', "low\nhigh");
        $targetid = $this->make_field('text', 'Decision');
        $rule = $this->make_rule($targetid, 'Approved', [
            rule::ONCHANGE_KEY => [$triggerid],
        ]);

        $changed = $this->make_entry();
        $this->assertTrue($this->fire_update($rule, $changed, [$triggerid]));
        $this->assertSame('Approved', $this->content_of($changed, $targetid));

        $unchanged = $this->make_entry();
        $this->assertFalse($this->fire_update($rule, $unchanged, []));
        $this->assertNull($this->content_of($unchanged, $targetid));
    }

    /**
     * Setting the field to the value it already holds is a no-op and (with cascade on) fires no event.
     */
    public function test_no_change_when_value_already_set(): void {
        $fieldid = $this->make_field('text', 'Decision');
        $entryid = $this->make_entry();
        $this->set_content($entryid, $fieldid, 'Approved');
        $rule = $this->make_rule($fieldid, 'Approved', [], true);

        $sink = $this->redirectEvents();
        $this->fire_update($rule, $entryid);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('Approved', $this->content_of($entryid, $fieldid));
        $this->assertEmpty(
            array_filter($events, fn($e) => $e instanceof \mod_datalynx\event\entry_updated),
            'No follow-up entry_updated event should fire when the value did not change.'
        );
    }

    /**
     * Loop safety: a cascade-enabled rule that listens to entry_updated and updates a field must
     * terminate. Firing a real entry_updated event runs the production observer
     * ({@see \mod_datalynx\local\rule\manager::trigger_rules()}), which re-enters the rule; the
     * re-entrancy guard breaks the cycle, the value is written exactly once, and the test simply
     * returning proves there is no infinite loop.
     */
    public function test_loop_safety_with_cascade(): void {
        global $DB;
        $fieldid = $this->make_field('text', 'Decision');
        $entryid = $this->make_entry();
        // Cascade on, no conditions: the follow-up event would re-trigger this very rule.
        $this->make_rule($fieldid, 'Approved', [], true);

        // Fire a real event so the live observer dispatches to the rule (no event sink).
        \mod_datalynx\event\entry_updated::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid,
            'other' => ['dataid' => $this->dlx->id(), 'changed_field_ids' => []],
        ])->trigger();

        $this->assertSame('Approved', $this->content_of($entryid, $fieldid));
        $this->assertCount(
            1,
            $DB->get_records('datalynx_contents', ['entryid' => $entryid, 'fieldid' => $fieldid]),
            'The value must be written exactly once despite the cascade.'
        );
    }

    /**
     * Backup + restore must remap the field ids the rule stores: the target field (param2) and the
     * field ids inside the trigger conditions and the on-change list (param9).
     *
     * duplicate_module() runs a real activity backup followed by a restore into the same course,
     * which assigns brand-new field and rule ids — exactly the remapping path the restore step must
     * handle ({@see \restore_datalynx_activity_structure_step::process_datalynx_rule()}).
     */
    public function test_backup_restore_remaps_field_ids(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $titleid = $this->make_field('text', 'Title');
        $targetid = $this->make_field('select', 'State', "Open\nClosed");
        $this->make_rule($targetid, '2', [
            $titleid => ['AND' => [['', '=', 'go']]],
            rule::ONCHANGE_KEY => [$titleid],
        ]);

        // Duplicate the activity: a full backup + restore that re-creates fields and the rule.
        $newcm = duplicate_module($this->dlx->course, $this->dlx->cm);
        $newdataid = (int) $newcm->instance;

        $newfields = $DB->get_records_menu('datalynx_fields', ['dataid' => $newdataid], '', 'name, id');
        $newtitleid = (int) $newfields['Title'];
        $newtargetid = (int) $newfields['State'];
        $newrule = $DB->get_record('datalynx_rules', ['dataid' => $newdataid, 'type' => 'updatefield']);
        $this->assertNotEmpty($newrule, 'The updatefield rule must be restored.');

        // The duplicate genuinely has different ids (otherwise the test would prove nothing).
        $this->assertNotEquals($targetid, $newtargetid);

        // Param2 points at the restored target field.
        $this->assertSame($newtargetid, (int) $newrule->param2);

        // Param9 condition key and the on-change list point at the restored condition field.
        $conditions = json_decode($newrule->param9, true);
        $this->assertArrayHasKey((string) $newtitleid, $conditions);
        $this->assertSame([$newtitleid], array_map('intval', $conditions[rule::ONCHANGE_KEY]));

        // The stored value (param3) carries no ids and is preserved verbatim.
        $this->assertSame('2', $newrule->param3);
    }

    /**
     * The form round-trips the target field and value through param2/param3.
     */
    public function test_form_saves_field_and_value(): void {
        $fieldid = $this->make_field('text', 'Decision');
        $formclass = \datalynxrule_updatefield\form\rule_form::class;
        $submitted = [
            'd' => $this->dlx->id(),
            'cmid' => $this->dlx->cm->id,
            'rid' => 0,
            'type' => 'updatefield',
            'name' => 'SaveRule',
            'description' => '',
            'enabled' => 1,
            'entry_updated' => 1,
            'param2' => $fieldid,
            "fieldvalue_$fieldid" => 'Approved',
            'param4' => 1,
        ];
        $ajaxdata = $formclass::mock_ajax_submit($submitted);
        $form = new $formclass(null, null, 'post', '', null, true, $ajaxdata);
        $data = $form->get_data();

        $this->assertNotNull($data, 'get_data() returned null (form not submitted/validated)');
        $this->assertEquals($fieldid, (int) $data->param2);
        $this->assertSame('Approved', $data->param3);
        $this->assertSame('1', $data->param4);
        $this->assertObjectNotHasProperty("fieldvalue_$fieldid", $data);
    }
}
