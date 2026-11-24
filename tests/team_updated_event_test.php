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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');
require_once($CFG->dirroot . '/mod/datalynx/db/upgrade.php');

use advanced_testcase;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use mod_datalynx\local\datalynx_entries;
use stdClass;

/**
 * The team_updated event carries user ids, not user records (mod_datalynx#321).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\event\team_updated
 * @covers     \datalynxfield_teammemberselect\field::notify_team_members
 */
final class team_updated_event_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var stdClass */
    private stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $teamid;

    /** @var stdClass[] students a, b, c */
    private array $students = [];

    /** @var stdClass */
    private stdClass $teacher;

    /** @var int */
    private int $entryid;

    /**
     * An instance with a team of a and b, edited by a teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        [$this->course, $dlx] = $this->create_mobile_instance();
        foreach (['a', 'b', 'c'] as $key) {
            $this->students[$key] = $this->create_mobile_student($this->course);
        }
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->teamid = $this->add_mobile_field($dlx, 'teammemberselect', 'Team', [
            'param1' => '5', 'param2' => json_encode([datalynx::PERMISSION_STUDENT]),
        ]);
        $team = json_encode([(int) $this->students['a']->id, (int) $this->students['b']->id]);
        $this->entryid = $this->add_mobile_entry($dlx, $this->teacher->id, [$this->teamid => $team]);
        $this->dlx = new datalynx($dlx->id());
        $this->setUser($this->teacher);
    }

    /**
     * Adding c and removing a in one save triggers one event that holds the ids only.
     */
    public function test_one_event_with_ids_only(): void {
        $sink = $this->redirectEvents();
        $this->save_team(['b', 'c']);
        $events = array_values(array_filter($sink->get_events(), fn($event) => $event instanceof event\team_updated));
        $sink->close();

        $this->assertCount(1, $events);
        $other = $events[0]->other;
        $this->assertSame([(int) $this->students['c']->id], $other['addedmembers']);
        $this->assertSame([(int) $this->students['a']->id], $other['removedmembers']);
        $this->assertSame($this->teamid, $other['fieldid']);
        $encoded = json_encode($other);
        foreach (['password', 'email', 'username', 'lastip', $this->students['c']->email] as $leak) {
            $this->assertStringNotContainsString($leak, $encoded);
        }

        // Saving the same team again changes nothing and triggers nothing.
        $sink = $this->redirectEvents();
        $this->save_team(['b', 'c']);
        $this->assertSame([], array_filter($sink->get_events(), fn($event) => $event instanceof event\team_updated));
        $sink->close();
    }

    /**
     * The event refuses member lists that are not lists of user ids.
     */
    public function test_event_refuses_user_records(): void {
        $this->expectException(\coding_exception::class);
        event\team_updated::create([
            'context' => $this->dlx->context, 'objectid' => $this->entryid,
            'other' => ['dataid' => $this->dlx->id(), 'fieldid' => $this->teamid, 'name' => 'Team',
                'addedmembers' => json_encode([$this->students['a']]), 'removedmembers' => []],
        ]);
    }

    /**
     * A notification rule on the team runs once for a save that adds and removes members, and
     * reaches the new team.
     */
    public function test_notification_rule_runs_once(): void {
        global $DB;

        $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(), 'name' => 'Team changed', 'description' => '',
            'type' => 'eventnotification', 'enabled' => 1,
            'param1' => json_encode(["team_updated:{$this->teamid}"]),
            'param2' => \datalynxrule_eventnotification\rule::FROM_CURRENT_USER,
            'param3' => json_encode(['teams' => [$this->teamid]]),
        ]);
        \mod_datalynx\local\definition_cache::purge();
        $this->dlx = new datalynx($this->dlx->id());

        $this->save_team(['b', 'c']);

        $tasks = \core\task\manager::get_adhoc_tasks(task\sendmessage_task::class);
        $this->assertCount(1, $tasks);
        $messages = \mod_datalynx\local\serialized::decode(
            base64_decode(reset($tasks)->get_custom_data_as_string()),
            [\core\message\message::class]
        );
        $recipients = array_map(fn($message) => (int) $message->userto->id, $messages);
        sort($recipients);
        $this->assertSame([(int) $this->students['b']->id, (int) $this->students['c']->id], $recipients);
    }

    /**
     * The log row of the event and the privacy export of the acting user hold no data of the
     * team members beyond their ids.
     */
    public function test_log_and_privacy_export_hold_ids_only(): void {
        global $DB;

        $this->preventResetByRollback(); // The log is written when the transaction is committed.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        $this->save_team(['b', 'c']);

        $rows = $DB->get_records('logstore_standard_log', ['eventname' => '\\' . event\team_updated::class]);
        $this->assertCount(1, $rows);
        $other = \tool_log\local\privacy\helper::decode_other(reset($rows)->other);
        $this->assertSame([(int) $this->students['c']->id], $other['addedmembers']);

        $context = $this->dlx->context;
        \logstore_standard\privacy\provider::export_user_data(
            new approved_contextlist($this->teacher, 'tool_log', [$context->id])
        );
        $path = [get_string('privacy:path:logs', 'tool_log'), get_string('pluginname', 'logstore_standard')];
        $export = json_encode(writer::with_context($context)->get_data($path));
        $this->assertStringContainsString('addedmembers', $export);
        foreach (['password', 'email', 'username', 'lastip', $this->students['c']->email] as $leak) {
            $this->assertStringNotContainsString($leak, $export);
        }
    }

    /**
     * The upgrade reduces logged user records to their ids, in serialized and JSON rows, and
     * leaves rows that hold ids only untouched.
     */
    public function test_upgrade_cleans_logged_user_records(): void {
        global $DB;

        $a = $DB->get_record('user', ['id' => $this->students['a']->id]);
        $c = $DB->get_record('user', ['id' => $this->students['c']->id]);
        $legacy = [
            'dataid' => (string) $this->dlx->id(), 'fieldid' => (string) $this->teamid, 'name' => 'Team',
            'addedmembers' => json_encode([$c->id => $c]),
            'removedmembers' => json_encode([$a->id => $a]),
        ];
        $clean = ['dataid' => $this->dlx->id(), 'fieldid' => $this->teamid, 'name' => 'Team',
            'addedmembers' => [(int) $c->id], 'removedmembers' => []];
        $row = fn(string $other) => $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => '\\' . event\team_updated::class, 'component' => 'mod_datalynx', 'action' => 'updated',
            'target' => 'team', 'crud' => 'u', 'edulevel' => 2, 'contextid' => $this->dlx->context->id,
            'contextlevel' => CONTEXT_MODULE, 'contextinstanceid' => $this->dlx->cm->id, 'userid' => $this->teacher->id,
            'courseid' => $this->course->id, 'anonymous' => 0, 'other' => $other, 'timecreated' => time(),
            'objecttable' => 'datalynx_entries', 'objectid' => $this->entryid,
        ]);
        $serialized = $row(serialize($legacy));
        $json = $row(json_encode($legacy));
        $untouched = $row(serialize($clean));
        $before = $DB->get_field('logstore_standard_log', 'other', ['id' => $untouched]);

        $this->assertSame(2, mod_datalynx_upgrade_clean_team_updated_logs(1));

        $expected = ['addedmembers' => [(int) $c->id], 'removedmembers' => [(int) $a->id]];
        $fromserialized = \mod_datalynx\local\serialized::to_array(
            $DB->get_field('logstore_standard_log', 'other', ['id' => $serialized])
        );
        $fromjson = json_decode($DB->get_field('logstore_standard_log', 'other', ['id' => $json]), true);
        foreach ([$fromserialized, $fromjson] as $other) {
            $this->assertSame($expected, array_intersect_key($other, $expected));
            $this->assertSame('Team', $other['name']);
            $this->assertStringNotContainsString('password', json_encode($other));
        }
        $this->assertSame($before, $DB->get_field('logstore_standard_log', 'other', ['id' => $untouched]));

        // A second run finds nothing to do.
        $this->assertSame(0, mod_datalynx_upgrade_clean_team_updated_logs());
    }

    /**
     * Save the team of the entry as the current user.
     *
     * @param string[] $keys students who form the team
     */
    private function save_team(array $keys): void {
        $ids = array_map(fn($key) => (int) $this->students[$key]->id, $keys);
        $entries = new datalynx_entries(new datalynx($this->dlx->id()));
        $data = (object) ["field_{$this->teamid}_{$this->entryid}" => $ids];
        $entries->process_entries('update', (string) $this->entryid, $data, true);
    }
}
