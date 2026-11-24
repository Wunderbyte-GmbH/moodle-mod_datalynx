<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use advanced_testcase;
use core_external\external_api;
use mod_datalynx\datalynx;
use stdClass;

/**
 * External function tests for toggle_entry_approval.
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \mod_datalynx\external\toggle_entry_approval
 * @runTestsInSeparateProcesses
 */
final class toggle_entry_approval_test extends advanced_testcase {
    /**
     * Create a minimal datalynx view for event payloads.
     *
     * @param datalynx $dlx
     * @param string $type
     * @param string $param2
     * @return stdClass
     */
    private function create_view_record(datalynx $dlx, string $type = 'tabular', string $param2 = ''): stdClass {
        global $DB;

        $view = (object) [
            'dataid' => $dlx->id(),
            'type' => $type,
            'name' => 'Approval view',
            'description' => '',
            'visible' => 7,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '',
            'param2' => $param2,
        ];
        $view->id = $DB->insert_record('datalynx_views', $view);
        \mod_datalynx\local\definition_cache::purge();

        return $view;
    }

    /**
     * Create a minimal datalynx entry.
     *
     * @param datalynx $dlx
     * @param int $userid
     * @param int $approved
     * @return int
     */
    private function create_entry(datalynx $dlx, int $userid, int $approved = 0): int {
        global $DB;

        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $userid,
            'groupid' => 0,
            'approved' => $approved,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Test execute toggles approval on and off.
     *
     * @covers ::execute
     */
    public function test_execute_toggles_entry_approval(): void {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        $this->setUser($teacher);

        $instance = $generator->create_module('datalynx', [
            'course' => $course->id,
            'approval' => 1,
        ]);
        $dlx = new datalynx($instance->id);
        $view = $this->create_view_record($dlx, 'grid', '[[Title]] ##approve##');
        $entryid = $this->create_entry($dlx, $teacher->id, 0);

        $approvedresult = toggle_entry_approval::execute($instance->id, $view->id, $entryid, 'toggle-approval');
        $approvedresult = external_api::clean_returnvalue(toggle_entry_approval::execute_returns(), $approvedresult);

        $this->assertTrue($approvedresult['approved']);
        $this->assertSame($entryid, $approvedresult['entryid']);
        $this->assertStringContainsString('data-entryid="' . $entryid . '"', $approvedresult['controlhtml']);
        $this->assertStringContainsString('data-approved="1"', $approvedresult['controlhtml']);
        $this->assertStringContainsString('aria-checked="true"', $approvedresult['controlhtml']);
        $this->assertSame(1, (int) $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));

        $disapprovedresult = toggle_entry_approval::execute($instance->id, $view->id, $entryid, 'toggle-approval');
        $disapprovedresult = external_api::clean_returnvalue(toggle_entry_approval::execute_returns(), $disapprovedresult);

        $this->assertFalse($disapprovedresult['approved']);
        $this->assertSame($entryid, $disapprovedresult['entryid']);
        $this->assertStringContainsString('data-entryid="' . $entryid . '"', $disapprovedresult['controlhtml']);
        $this->assertStringContainsString('data-approved="0"', $disapprovedresult['controlhtml']);
        $this->assertStringContainsString('aria-checked="false"', $disapprovedresult['controlhtml']);
        $this->assertSame(0, (int) $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));
    }

    /**
     * Approval goes through the view: one without ##approve## refuses, and without a view the
     * activity's default view counts, as on the web.
     *
     * @covers ::execute
     */
    public function test_execute_needs_a_view_with_approve(): void {
        global $DB;

        $this->resetAfterTest();
        [$dlx, $teacher] = $this->approval_instance();
        $this->setUser($teacher);
        $plain = $this->create_view_record($dlx, 'grid', '[[Title]]');
        $review = $this->create_view_record($dlx, 'grid', '[[Title]] ##approve##');
        $entryid = $this->create_entry($dlx, $teacher->id, 0);

        try {
            toggle_entry_approval::execute($dlx->id(), $plain->id, $entryid, 'approve');
            $this->fail('Approved through a view without ##approve##.');
        } catch (\moodle_exception $e) {
            $this->assertSame('viewapprovenotallowed', $e->errorcode);
        }
        $this->assertSame(0, (int) $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));

        // Without a view: the default view, here the one without the tag, then the one with it.
        $DB->set_field('datalynx', 'defaultview', $plain->id, ['id' => $dlx->id()]);
        \mod_datalynx\local\definition_cache::purge();
        try {
            toggle_entry_approval::execute($dlx->id(), 0, $entryid, 'approve');
            $this->fail('Approved through a default view without ##approve##.');
        } catch (\moodle_exception $e) {
            $this->assertSame('viewapprovenotallowed', $e->errorcode);
        }
        $DB->set_field('datalynx', 'defaultview', $review->id, ['id' => $dlx->id()]);
        \mod_datalynx\local\definition_cache::purge();
        $result = toggle_entry_approval::execute($dlx->id(), 0, $entryid, 'approve');
        $this->assertTrue($result['approved']);
    }

    /**
     * The service approves as the web's approve action does: the same events, and completion
     * follows the approved entries both ways.
     *
     * @covers ::execute
     */
    public function test_execute_is_the_web_approval(): void {
        global $DB;

        $this->resetAfterTest();
        [$dlx, $teacher, $student, $course] = $this->approval_instance(['completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionentries' => 1]);
        $view = $this->create_view_record($dlx, 'grid', '[[Title]] ##approve##');
        $entryid = $this->create_entry($dlx, $student->id, 0);
        $cm = get_coursemodule_from_instance('datalynx', $dlx->id());
        $completion = new \completion_info($course);
        $this->setUser($teacher);

        $sink = $this->redirectEvents();
        toggle_entry_approval::execute($dlx->id(), $view->id, $entryid, 'toggle-approval');
        $events = array_map(fn($event) => get_class($event), $sink->get_events());
        $sink->clear();
        $this->assertContains(\mod_datalynx\event\entry_approved::class, $events);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);

        toggle_entry_approval::execute($dlx->id(), $view->id, $entryid, 'toggle-approval');
        $events = array_map(fn($event) => get_class($event), $sink->get_events());
        $sink->close();
        $this->assertContains(\mod_datalynx\event\entry_disapproved::class, $events);
        $this->assertSame(0, (int) $DB->get_field('datalynx_entries', 'approved', ['id' => $entryid]));
        // The disapproved entry no longer counts.
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $student->id)->completionstate);
    }

    /**
     * An instance with approval on, a teacher and a student.
     *
     * @param array $settings further settings of the instance
     * @return array [datalynx, teacher, student, course]
     */
    private function approval_instance(array $settings = []): array {
        set_config('enablecompletion', 1);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($student->id, $course->id, 'student');
        $instance = $generator->create_module('datalynx', ['course' => $course->id, 'approval' => 1] + $settings);

        return [new datalynx($instance->id), $teacher, $student, $course];
    }
}
