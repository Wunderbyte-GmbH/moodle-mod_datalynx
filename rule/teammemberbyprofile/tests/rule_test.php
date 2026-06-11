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
 * Tests for the teammemberbyprofile rule.
 *
 * @package    datalynxrule_teammemberbyprofile
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxrule_teammemberbyprofile;

use advanced_testcase;
use mod_datalynx\datalynx;

/**
 * Tests that the rule adds the privileged, profile-matching users to a teammemberselect field.
 *
 * @covers \datalynxrule_teammemberbyprofile\rule
 */
final class rule_test extends advanced_testcase {
    /** Option label that matches faculty 2 (option key 2). */
    private const FACULTY2 = 'Department manager faculty 2';

    /** Option label that matches faculty 1 (option key 1). */
    private const FACULTY1 = 'Department manager faculty 1';

    /** @var datalynx The datalynx instance under test. */
    private $dlx;

    /** @var int The select field id. */
    private $selectid;

    /** @var int The custom profile field id. */
    private $profilefieldid;

    /** @var \stdClass Non-editing teacher matching faculty 2. */
    private $teachera;

    /** @var \stdClass Non-editing teacher matching faculty 1. */
    private $teacherb;

    /** @var \stdClass Student matching faculty 2 but without the teacher privilege. */
    private $students;

    /**
     * Set up an instance with a select field, a custom profile field and three users.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $this->selectid = $this->create_field('select', 'Department manager',
            ['param1' => self::FACULTY1 . "\n" . self::FACULTY2 . "\nCoordinator"]);
        $this->dlx->get_fields(null, false, true);

        $profilefield = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'managerrole', 'name' => 'Manager role',
        ]);
        $this->profilefieldid = (int) $profilefield->id;

        $this->teachera = $this->create_member($course->id, 'teacher', self::FACULTY2);
        $this->teacherb = $this->create_member($course->id, 'teacher', self::FACULTY1);
        $this->students = $this->create_member($course->id, 'student', self::FACULTY2);
    }

    /**
     * Overwrite mode: only the privileged user whose profile matches the entry's option is added.
     */
    public function test_overwrite_adds_only_matching_privileged_users(): void {
        $teamid = $this->create_team_field(5);
        $entryid = $this->create_entry_with_option(self::FACULTY2);

        $rule = $this->make_rule($teamid, 'overwrite');
        $this->run_rule($rule, $entryid);

        $this->assertEquals([$this->teachera->id], $this->get_team($teamid, $entryid));
    }

    /**
     * Merge mode keeps members already present and adds the matching ones (deduplicated).
     */
    public function test_merge_keeps_existing_members(): void {
        $teamid = $this->create_team_field(5);
        $entryid = $this->create_entry_with_option(self::FACULTY2);
        // Pre-populate the team with an unrelated member.
        $this->set_team($teamid, $entryid, [$this->students->id]);

        $rule = $this->make_rule($teamid, 'merge');
        $this->run_rule($rule, $entryid);

        $this->assertEqualsCanonicalizing(
            [$this->students->id, $this->teachera->id],
            $this->get_team($teamid, $entryid)
        );
    }

    /**
     * The matched list is truncated to the team field's maximum size.
     */
    public function test_truncates_to_max_team_size(): void {
        $teacherc = $this->create_member($this->dlx->course->id, 'teacher', self::FACULTY2);
        $teamid = $this->create_team_field(1);
        $entryid = $this->create_entry_with_option(self::FACULTY2);

        $rule = $this->make_rule($teamid, 'overwrite');
        $this->run_rule($rule, $entryid);
        $this->assertDebuggingCalled();

        $team = $this->get_team($teamid, $entryid);
        $this->assertCount(1, $team);
        $this->assertContains($team[0], [(int) $this->teachera->id, (int) $teacherc->id]);
    }

    /**
     * When the entry has no option selected the team field is left untouched.
     */
    public function test_no_selection_leaves_field_untouched(): void {
        global $DB;
        $teamid = $this->create_team_field(5);
        // Entry without any content for the select field.
        $entryid = $this->create_entry();

        $rule = $this->make_rule($teamid, 'overwrite');
        $this->run_rule($rule, $entryid);

        $this->assertFalse($DB->record_exists('datalynx_contents',
            ['entryid' => $entryid, 'fieldid' => $teamid]));
    }

    /**
     * The rule never re-fires entry_updated (no recursion) and is idempotent on a second run.
     */
    public function test_no_recursion_and_idempotent(): void {
        $teamid = $this->create_team_field(5);
        $entryid = $this->create_entry_with_option(self::FACULTY2);
        $rule = $this->make_rule($teamid, 'overwrite');

        $sink = $this->redirectEvents();
        $this->run_rule($rule, $entryid);
        $events = $sink->get_events();
        $sink->close();

        $names = array_map(fn($e) => $e->eventname, $events);
        $this->assertNotContains('\mod_datalynx\event\entry_updated', $names);
        $this->assertContains('\mod_datalynx\event\team_updated', $names);
        $this->assertEquals([$this->teachera->id], $this->get_team($teamid, $entryid));

        // Second run with the same data must not write again (no further team_updated).
        $sink2 = $this->redirectEvents();
        $this->run_rule($rule, $entryid);
        $secondnames = array_map(fn($e) => $e->eventname, $sink2->get_events());
        $sink2->close();
        $this->assertNotContains('\mod_datalynx\event\team_updated', $secondnames);
        $this->assertEquals([$this->teachera->id], $this->get_team($teamid, $entryid));
    }

    // --- helpers ---------------------------------------------------------------------------------

    /**
     * Insert a datalynx field record and return its id.
     *
     * @param string $type Field type.
     * @param string $name Field name.
     * @param array $params param1..param10 overrides.
     * @return int New field id.
     */
    private function create_field(string $type, string $name, array $params = []): int {
        global $DB;
        $record = (object) [
            'dataid' => $this->dlx->id(),
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
     * Create a teammemberselect field with the given maximum team size.
     *
     * @param int $maxsize Maximum team size (param1).
     * @return int The new field id.
     */
    private function create_team_field(int $maxsize): int {
        $id = $this->create_field('teammemberselect', 'Team', ['param1' => (string) $maxsize, 'param2' => '']);
        $this->dlx->get_fields(null, false, true);
        return $id;
    }

    /**
     * Create an enrolled user with a managerrole profile value.
     *
     * @param int $courseid Course id.
     * @param string $rolearchetype Enrolment role shortname (e.g. teacher, student).
     * @param string $managerrole Value for the custom profile field.
     * @return \stdClass The user record.
     */
    private function create_member(int $courseid, string $rolearchetype, string $managerrole): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $rolearchetype);
        $DB->insert_record('user_info_data', (object) [
            'userid' => $user->id, 'fieldid' => $this->profilefieldid, 'data' => $managerrole, 'dataformat' => 0,
        ]);
        return $user;
    }

    /**
     * Create an approved entry with no field content and return its id.
     *
     * @return int New entry id.
     */
    private function create_entry(): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => $this->students->id,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Create an entry whose select field holds the given option label.
     *
     * @param string $optionlabel Option label.
     * @return int New entry id.
     */
    private function create_entry_with_option(string $optionlabel): int {
        global $DB;
        $entryid = $this->create_entry();
        $selectfield = $this->dlx->get_field_from_id($this->selectid);
        $key = array_search($optionlabel, $selectfield->get_options(), true);
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $this->selectid, 'entryid' => $entryid, 'lineid' => 0, 'content' => (string) $key,
        ]);
        return $entryid;
    }

    /**
     * Store a list of user ids as the team field content of an entry.
     *
     * @param int $teamid Team field id.
     * @param int $entryid Entry id.
     * @param int[] $userids User ids.
     */
    private function set_team(int $teamid, int $entryid, array $userids): void {
        global $DB;
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $teamid, 'entryid' => $entryid, 'lineid' => 0, 'content' => json_encode($userids),
        ]);
    }

    /**
     * Read the team field content of an entry as a sorted array of int user ids.
     *
     * @param int $teamid Team field id.
     * @param int $entryid Entry id.
     * @return int[] Sorted user ids.
     */
    private function get_team(int $teamid, int $entryid): array {
        global $DB;
        $content = $DB->get_field('datalynx_contents', 'content',
            ['entryid' => $entryid, 'fieldid' => $teamid, 'lineid' => 0]);
        $ids = $content ? array_map('intval', json_decode($content, true)) : [];
        sort($ids);
        return $ids;
    }

    /**
     * Insert a rule record and return the rule object.
     *
     * @param int $teamid Target team field id.
     * @param string $mode 'overwrite' or 'merge'.
     * @return rule
     */
    private function make_rule(int $teamid, string $mode): rule {
        global $DB;
        $record = (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'teammemberbyprofile',
            'name' => 'Team by profile ' . $mode . ' ' . $teamid,
            'description' => '',
            'enabled' => 1,
            'param1' => serialize(['entry_created', 'entry_updated']),
            'param2' => $mode,
            'param3' => null,
            'param4' => null,
            'param5' => null,
            'param6' => (string) $this->selectid,
            'param7' => 'managerrole',
            'param8' => (string) $teamid,
            'param9' => 'teacher',
            'param10' => null,
        ];
        $record->id = (int) $DB->insert_record('datalynx_rules', $record);
        return new rule($this->dlx, $record);
    }

    /**
     * Fire the rule for the given entry using a fabricated entry_updated event.
     *
     * @param rule $rule The rule object.
     * @param int $entryid Entry id.
     */
    private function run_rule(rule $rule, int $entryid): void {
        $event = \mod_datalynx\event\entry_updated::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid,
            'other' => ['dataid' => $this->dlx->id()],
        ]);
        $rule->trigger($event);
    }
}
