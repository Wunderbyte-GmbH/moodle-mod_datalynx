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
 * Tests for notifying the people behind entries that match each other.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxrule_eventnotification\rule;
use mod_datalynx\local\rule\match_compiler;
use mod_datalynx\local\rule\match_ledger;

/**
 * The generic half of counterpart matching: finding the entries and telling the right people once.
 *
 * @group mod_datalynx
 * @covers \datalynxrule_eventnotification\rule
 * @covers \mod_datalynx\local\rule\base::find_matching_entries
 * @covers \mod_datalynx\local\rule\match_ledger
 */
final class rule_matching_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    /** @var \stdClass */
    private $author;

    /** @var \stdClass */
    private $other;

    /** @var int The text field the tests match on. */
    private int $fieldid;

    /**
     * Set up an instance with one text field and two enrolled users.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->preventResetByRollback();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);

        $this->author = $this->getDataGenerator()->create_and_enrol($course);
        $this->other = $this->getDataGenerator()->create_and_enrol($course);

        $this->fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => 'text', 'name' => 'Topic', 'description' => '',
            'param1' => '', 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);
    }

    /**
     * Insert an entry with a topic.
     *
     * @param \stdClass $user
     * @param string|null $topic
     * @param int $approved
     * @return int
     */
    private function make_entry(\stdClass $user, ?string $topic, int $approved = 1): int {
        global $DB;

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $user->id, 'groupid' => 0,
            'approved' => $approved, 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($topic !== null) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $this->fieldid, 'entryid' => $entryid, 'content' => $topic,
            ]);
        }

        return $entryid;
    }

    /**
     * Insert a rule matching on the topic field.
     *
     * @param array $overrides values merged into the matching configuration
     * @param array $recipients the param3 recipient configuration
     * @return rule
     */
    private function make_rule(array $overrides = [], array $recipients = []): rule {
        global $DB;

        $config = $overrides + [
            'criteria' => [['fieldid' => $this->fieldid, 'op' => match_compiler::OP_SAME]],
            'requireapproved' => 1,
            'dedupe' => rule::SCOPE_RULE,
            'forgetstale' => 1,
            'maxmatches' => rule::DEFAULT_MAX_MATCHES,
        ];

        $ruleid = (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'eventnotification',
            'name' => 'Match topics ' . $DB->count_records('datalynx_rules'),
            'description' => '',
            'enabled' => 1,
            'param1' => json_encode(['entry_created', 'entry_updated', 'entry_deleted']),
            'param2' => rule::FROM_AUTHOR,
            'param3' => json_encode($recipients ?: ['matchauthors' => 1, 'subjectauthorpermatch' => 1]),
            'param9' => json_encode([rule::MATCH_KEY => $config]),
        ]);

        return new rule($this->dlx, $DB->get_record('datalynx_rules', ['id' => $ruleid]));
    }

    /**
     * Run a rule's sweep and return the messages it sent.
     *
     * @param rule $rule
     * @param int $entryid
     * @return \stdClass[]
     */
    private function sweep(rule $rule, int $entryid): array {
        $sink = $this->redirectMessages();
        $rule->run_matching([
            'eventname' => 'entry_created',
            'entryid' => $entryid,
            'objectid' => $entryid,
            'teamfieldid' => 0,
        ]);
        // The sender traces every message, which would make each test risky.
        ob_start();
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();

        return $messages;
    }

    /**
     * Both sides of a match hear about each other, each pointing at the other's entry.
     */
    public function test_both_authors_are_notified_about_the_other_entry(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $match = $this->make_entry($this->other, 'Rust');

        $messages = $this->sweep($this->make_rule(), $subject);

        $this->assertCount(2, $messages);
        $byuser = [];
        foreach ($messages as $message) {
            $byuser[(int) $message->useridto] = $message;
        }
        $this->assertArrayHasKey((int) $this->author->id, $byuser);
        $this->assertArrayHasKey((int) $this->other->id, $byuser);
        // The counterpart's author is pointed at the entry that triggered the rule, and the
        // triggering author at the counterpart.
        $this->assertStringContainsString("eids=$subject", $byuser[(int) $this->other->id]->fullmessagehtml);
        $this->assertStringContainsString("eids=$match", $byuser[(int) $this->author->id]->fullmessagehtml);
    }

    /**
     * An entry that does not match produces nothing.
     */
    public function test_a_different_value_is_not_a_match(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $this->make_entry($this->other, 'Go');

        $this->assertCount(0, $this->sweep($this->make_rule(), $subject));
    }

    /**
     * An entry never matches itself or the other entries of its own author.
     */
    public function test_own_entries_are_never_matched(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $this->make_entry($this->author, 'Rust');

        $this->assertCount(0, $this->sweep($this->make_rule(), $subject));
    }

    /**
     * Unapproved entries are left out unless the rule says otherwise.
     */
    public function test_unapproved_entries_are_excluded_by_default(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $this->make_entry($this->other, 'Rust', 0);

        $this->assertCount(0, $this->sweep($this->make_rule(), $subject));
        $this->assertCount(2, $this->sweep($this->make_rule(['requireapproved' => 0]), $subject));
    }

    /**
     * A pair is announced once, however often the entry is saved.
     */
    public function test_a_pair_is_announced_once(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $this->make_entry($this->other, 'Rust');
        $rule = $this->make_rule();

        $this->assertCount(2, $this->sweep($rule, $subject));
        $this->assertCount(0, $this->sweep($rule, $subject));
    }

    /**
     * Two rules sharing a scope announce a pair once between them; without it, once each.
     */
    public function test_rules_sharing_a_scope_do_not_announce_a_pair_twice(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $this->make_entry($this->other, 'Rust');

        $shared = $this->make_rule(['dedupe' => 'topics']);
        $alsoshared = $this->make_rule(['dedupe' => 'topics']);
        $this->assertCount(2, $this->sweep($shared, $subject));
        $this->assertCount(0, $this->sweep($alsoshared, $subject));

        $ownscope = $this->make_rule(['dedupe' => rule::SCOPE_RULE]);
        $this->assertCount(2, $this->sweep($ownscope, $subject));
    }

    /**
     * Switching the record of announced pairs off notifies on every save.
     */
    public function test_without_dedupe_every_save_notifies(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $this->make_entry($this->other, 'Rust');
        $rule = $this->make_rule(['dedupe' => '']);

        $this->assertCount(2, $this->sweep($rule, $subject));
        $this->assertCount(2, $this->sweep($rule, $subject));
    }

    /**
     * A pair that stops matching is forgotten, so it is news again when it comes back.
     */
    public function test_a_pair_that_stops_matching_is_forgotten(): void {
        global $DB;

        $subject = $this->make_entry($this->author, 'Rust');
        $match = $this->make_entry($this->other, 'Rust');
        $rule = $this->make_rule();

        $this->assertCount(2, $this->sweep($rule, $subject));
        $this->assertEquals(1, $DB->count_records('datalynx_rule_matches'));

        $DB->set_field('datalynx_contents', 'content', 'Go', ['entryid' => $match, 'fieldid' => $this->fieldid]);
        $this->assertCount(0, $this->sweep($rule, $subject));
        $this->assertEquals(0, $DB->count_records('datalynx_rule_matches'));

        $DB->set_field('datalynx_contents', 'content', 'Rust', ['entryid' => $match, 'fieldid' => $this->fieldid]);
        $this->assertCount(2, $this->sweep($rule, $subject));
    }

    /**
     * The plain recipients only hear from a matching rule when it actually found something.
     */
    public function test_plain_recipients_hear_only_about_an_actual_match(): void {
        $subject = $this->make_entry($this->author, 'Rust');
        $rule = $this->make_rule([], ['author' => 1, 'matchauthors' => 1]);

        $this->assertCount(0, $this->sweep($rule, $subject));

        $this->make_entry($this->other, 'Rust');
        $recipients = array_map(
            static fn($message) => (int) $message->useridto,
            $this->sweep($rule, $subject)
        );
        sort($recipients);
        $expected = [(int) $this->author->id, (int) $this->other->id];
        sort($expected);
        $this->assertEquals($expected, $recipients);
    }

    /**
     * An entry that holds no value for a criterion's field matches nothing rather than everything.
     */
    public function test_an_entry_without_a_value_matches_nothing(): void {
        $subject = $this->make_entry($this->author, null);
        $this->make_entry($this->other, 'Rust');

        $this->assertCount(0, $this->sweep($this->make_rule(), $subject));
    }

    /**
     * Deleting an entry clears its pairs, because entry ids are reused.
     */
    public function test_deleting_an_entry_clears_its_pairs(): void {
        global $DB;

        $subject = $this->make_entry($this->author, 'Rust');
        $this->make_entry($this->other, 'Rust');
        $rule = $this->make_rule();

        $this->sweep($rule, $subject);
        $this->assertEquals(1, $DB->count_records('datalynx_rule_matches'));

        $rule->trigger(\mod_datalynx\event\entry_deleted::create([
            'objectid' => $subject,
            'context' => $this->dlx->context,
            'other' => ['dataid' => $this->dlx->id(), 'view' => 0],
        ]));

        $this->assertEquals(0, $DB->count_records('datalynx_rule_matches'));
    }

    /**
     * A pair is the same pair whichever entry it is named from.
     */
    public function test_the_ledger_treats_a_pair_as_unordered(): void {
        $ledger = new match_ledger($this->dlx->id(), 'topics');

        $this->assertTrue($ledger->remember(7, 3));
        $this->assertFalse($ledger->remember(3, 7));

        $ledger->forget(7, 3);
        $this->assertTrue($ledger->remember(3, 7));
    }

    /**
     * forget_missing() drops the partners that are not in the list it is given, and only those.
     */
    public function test_forget_missing_keeps_only_the_partners_it_is_given(): void {
        global $DB;

        $ledger = new match_ledger($this->dlx->id(), 'topics');
        $ledger->remember(1, 2);
        $ledger->remember(1, 3);
        $ledger->remember(4, 5);

        $ledger->forget_missing(1, [3]);

        $this->assertEquals(2, $DB->count_records('datalynx_rule_matches'));
        $this->assertTrue($ledger->remember(1, 2), 'the dropped pair is news again');
        $this->assertFalse($ledger->remember(1, 3), 'the kept pair is still known');
        $this->assertFalse($ledger->remember(4, 5), 'an unrelated pair is untouched');
    }

    /**
     * A rule without matching criteria is an ordinary notification rule, unchanged.
     */
    public function test_a_rule_without_criteria_behaves_as_before(): void {
        global $DB;

        $entryid = $this->make_entry($this->author, 'Rust');
        $ruleid = (int) $DB->insert_record('datalynx_rules', (object) [
            'dataid' => $this->dlx->id(),
            'type' => 'eventnotification',
            'name' => 'Plain',
            'description' => '',
            'enabled' => 1,
            'param1' => json_encode(['entry_created']),
            'param2' => rule::FROM_AUTHOR,
            'param3' => json_encode(['author' => 1]),
        ]);
        $rule = new rule($this->dlx, $DB->get_record('datalynx_rules', ['id' => $ruleid]));

        $sink = $this->redirectMessages();
        $rule->trigger(\mod_datalynx\event\entry_created::create([
            'context' => $this->dlx->context,
            'objectid' => $entryid,
            'other' => ['dataid' => $this->dlx->id()],
        ]));
        ob_start();
        while ($task = \core\task\manager::get_next_adhoc_task(time())) {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        }
        ob_end_clean();
        $messages = $sink->get_messages();
        $sink->close();

        $this->assertCount(1, $messages);
        $this->assertEquals((int) $this->author->id, (int) $messages[0]->useridto);
    }
}
