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
 * Tests for the mod_datalynx custom completion rule.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\completion\custom_completion;

/**
 * Tests that the completionentries rule is evaluated on Moodle 4.5+ (see issue #245).
 *
 * @coversDefaultClass \mod_datalynx\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Insert a datalynx entry.
     *
     * @param int $dataid
     * @param int $userid
     * @param int $approved
     * @return int
     */
    private function create_entry(int $dataid, int $userid, int $approved = 1): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dataid,
            'userid' => $userid,
            'groupid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'approved' => $approved,
            'status' => 0,
            'timesubmitted' => 0,
            'assessed' => 0,
        ]);
    }

    /**
     * Build a custom_completion instance for a datalynx module and user.
     *
     * @param \stdClass $course
     * @param int $cmid
     * @param int $userid
     * @return custom_completion
     */
    private function completion_for(\stdClass $course, int $cmid, int $userid): custom_completion {
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        return new custom_completion($cm, $userid);
    }

    /**
     * The rule stays incomplete until the required number of entries is reached.
     *
     * @covers ::get_state
     */
    public function test_completionentries_counts_entries(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('datalynx', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $DB->set_field('datalynx', 'completionentries', 2, ['id' => $module->id]);
        $DB->set_field('datalynx', 'approval', 0, ['id' => $module->id]);

        $this->assertEquals(
            COMPLETION_INCOMPLETE,
            $this->completion_for($course, $module->cmid, (int) $user->id)->get_state('completionentries')
        );

        $this->create_entry($module->id, $user->id);
        $this->assertEquals(
            COMPLETION_INCOMPLETE,
            $this->completion_for($course, $module->cmid, (int) $user->id)->get_state('completionentries')
        );

        $this->create_entry($module->id, $user->id);
        $this->assertEquals(
            COMPLETION_COMPLETE,
            $this->completion_for($course, $module->cmid, (int) $user->id)->get_state('completionentries')
        );
    }

    /**
     * When approval is enabled only approved entries count towards completion.
     *
     * @covers ::get_state
     */
    public function test_completionentries_respects_approval(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('datalynx', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $DB->set_field('datalynx', 'completionentries', 1, ['id' => $module->id]);
        $DB->set_field('datalynx', 'approval', 1, ['id' => $module->id]);

        // An unapproved entry does not count.
        $this->create_entry($module->id, $user->id, 0);
        $this->assertEquals(
            COMPLETION_INCOMPLETE,
            $this->completion_for($course, $module->cmid, (int) $user->id)->get_state('completionentries')
        );

        // An approved entry completes the rule.
        $this->create_entry($module->id, $user->id, 1);
        $this->assertEquals(
            COMPLETION_COMPLETE,
            $this->completion_for($course, $module->cmid, (int) $user->id)->get_state('completionentries')
        );
    }

    /**
     * The module exposes the completion rule and a human-readable description.
     *
     * @covers ::get_defined_custom_rules
     * @covers ::get_custom_rule_descriptions
     */
    public function test_rule_is_defined_and_described(): void {
        global $DB;

        $this->assertSame(['completionentries'], custom_completion::get_defined_custom_rules());

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $module = $this->getDataGenerator()->create_module('datalynx', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);
        $DB->set_field('datalynx', 'completionentries', 3, ['id' => $module->id]);
        rebuild_course_cache($course->id, true);

        $descriptions = $this->completion_for($course, $module->cmid, (int) $user->id)->get_custom_rule_descriptions();
        $this->assertArrayHasKey('completionentries', $descriptions);
        $this->assertStringContainsString('3', $descriptions['completionentries']);
    }
}
