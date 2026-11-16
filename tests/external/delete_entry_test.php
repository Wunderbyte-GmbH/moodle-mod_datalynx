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

namespace mod_datalynx\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');
require_once($CFG->libdir . '/completionlib.php');

use core_external\external_api;
use externallib_advanced_testcase;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;
use moodle_exception;

/**
 * Deleting one entry through a view from the Moodle App (mod_datalynx#269).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\external\delete_entry
 */
final class delete_entry_test extends externallib_advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $titleid;

    /** @var int a view with ##delete## */
    private int $viewid;

    /** @var \stdClass */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $instance = $this->getDataGenerator()->create_module('datalynx', [
            'course' => $this->course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionentries' => 1,
        ]);
        $this->dlx = new datalynx($instance->id);
        $this->titleid = $this->add_mobile_field($this->dlx, 'text', 'Title');
        $this->viewid = $this->add_mobile_view($this->dlx, 'grid', 'Manage', '[[Title]] ##edit## ##delete##');
        $this->student = $this->create_mobile_student($this->course);
    }

    /**
     * Call the service and clean the result against its returns.
     *
     * @param int $entryid
     * @param int|null $viewid
     * @param int|null $dataid
     * @return array
     */
    private function call(int $entryid, ?int $viewid = null, ?int $dataid = null): array {
        $result = delete_entry::execute($dataid ?? $this->dlx->id(), $viewid ?? $this->viewid, $entryid);

        return external_api::clean_returnvalue(delete_entry::execute_returns(), $result);
    }

    /**
     * Whether an entry is still there.
     *
     * @param int $entryid
     * @return bool
     */
    private function exists(int $entryid): bool {
        global $DB;
        return $DB->record_exists('datalynx_entries', ['id' => $entryid]);
    }

    /**
     * The function is in the Moodle App's service.
     */
    public function test_function_is_in_the_mobile_service(): void {
        global $DB;

        $this->assertTrue($DB->record_exists_sql(
            "SELECT 1
               FROM {external_services_functions} sf
               JOIN {external_services} s ON s.id = sf.externalserviceid
              WHERE s.shortname = :service AND sf.functionname = :function",
            ['service' => MOODLE_OFFICIAL_MOBILE_SERVICE, 'function' => 'mod_datalynx_delete_entry']
        ));
    }

    /**
     * The author deletes their entry: the entry, its contents and its files are gone, entry_deleted
     * is fired, completion goes back to incomplete, and nothing is printed.
     */
    public function test_owner_deletes_own_entry(): void {
        global $DB;

        $entryid = $this->add_mobile_entry($this->dlx, $this->student->id, [$this->titleid => 'Mine']);
        $contentid = (int) $DB->get_field('datalynx_contents', 'id', ['entryid' => $entryid]);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $this->dlx->context->id, 'component' => 'mod_datalynx', 'filearea' => 'content',
            'itemid' => $contentid, 'filepath' => '/', 'filename' => 'note.txt',
        ], 'attached');
        $completion = new \completion_info($this->course);
        $cm = get_coursemodule_from_instance('datalynx', $this->dlx->id());
        $completion->update_state($cm, COMPLETION_COMPLETE, $this->student->id);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $this->student->id)->completionstate);
        $this->setUser($this->student);

        $this->expectOutputString('');
        $sink = $this->redirectEvents();
        $result = $this->call($entryid);
        $events = array_filter($sink->get_events(), fn($event) => $event instanceof \mod_datalynx\event\entry_deleted);
        $sink->close();

        $this->assertTrue($result['status']);
        $this->assertSame($entryid, $result['entryid']);
        $this->assertSame([], $result['warnings']);
        $this->assertFalse($this->exists($entryid));
        $this->assertFalse($DB->record_exists('datalynx_contents', ['entryid' => $entryid]));
        $this->assertTrue($fs->is_area_empty($this->dlx->context->id, 'mod_datalynx', 'content', $contentid));
        $this->assertCount(1, $events);
        $this->assertSame($entryid, (int) reset($events)->objectid);
        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_data($cm, false, $this->student->id)->completionstate);
    }

    /**
     * A student cannot delete another user's entry; a teacher can.
     */
    public function test_student_cannot_delete_another_users_entry_teacher_can(): void {
        $other = $this->create_mobile_student($this->course);
        $entryid = $this->add_mobile_entry($this->dlx, $other->id, [$this->titleid => 'Theirs']);

        $this->setUser($this->student);
        try {
            $this->call($entryid);
            $this->fail('A student deleted another user\'s entry.');
        } catch (moodle_exception $e) {
            $this->assertSame('entrydeletenotallowed', $e->errorcode);
        }
        $this->assertTrue($this->exists($entryid));

        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $this->assertTrue($this->call($entryid)['status']);
        $this->assertFalse($this->exists($entryid));
    }

    /**
     * An entry in final submission cannot be deleted without editfinalsubmission.
     */
    public function test_final_submission_needs_editfinalsubmission(): void {
        global $DB;

        $entryid = $this->add_mobile_entry($this->dlx, $this->student->id, [$this->titleid => 'Submitted']);
        $DB->set_field('datalynx_entries', 'status', \datalynxfield_status\field::STATUS_FINAL_SUBMISSION, ['id' => $entryid]);
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('mod/datalynx:editfinalsubmission', CAP_PROHIBIT, $roleid, $this->dlx->context->id, true);

        foreach ([$this->student, $teacher] as $user) {
            $this->setUser($user);
            try {
                $this->call($entryid);
                $this->fail('An entry in final submission was deleted.');
            } catch (moodle_exception $e) {
                $this->assertSame('entrydeletenotallowed', $e->errorcode);
            }
        }
        $this->assertTrue($this->exists($entryid));

        assign_capability('mod/datalynx:editfinalsubmission', CAP_ALLOW, $roleid, $this->dlx->context->id, true);
        $this->assertTrue($this->call($entryid)['status']);
    }

    /**
     * An entry of another instance is not found through this one.
     */
    public function test_entry_of_another_instance_is_not_found(): void {
        [, $otherdlx] = $this->create_mobile_instance();
        $this->setAdminUser();
        $entryid = $this->add_mobile_entry($otherdlx, $this->student->id, [$this->titleid => 'Elsewhere']);

        $this->expectException(invalid_parameter_exception::class);
        try {
            $this->call($entryid);
        } finally {
            $this->assertTrue($this->exists($entryid));
        }
    }

    /**
     * A view without ##delete## does not delete, even for a user who may manage the entry.
     */
    public function test_view_without_delete_action(): void {
        $readonly = $this->add_mobile_view($this->dlx, 'grid', 'Browse', '[[Title]] ##edit##');
        $entryid = $this->add_mobile_entry($this->dlx, $this->student->id, [$this->titleid => 'Mine']);
        $this->setAdminUser();

        try {
            $this->call($entryid, $readonly);
            $this->fail('A view without ##delete## deleted an entry.');
        } catch (moodle_exception $e) {
            $this->assertSame('viewdeletenotallowed', $e->errorcode);
        }
        $this->assertTrue($this->exists($entryid));
    }

    /**
     * The view must be one the user can see.
     */
    public function test_hidden_view_is_refused(): void {
        $hidden = $this->add_mobile_view($this->dlx, 'grid', 'Managers only', '[[Title]] ##delete##', ['visible' => 1]);
        $entryid = $this->add_mobile_entry($this->dlx, $this->student->id, [$this->titleid => 'Mine']);
        $this->setUser($this->student);

        $this->expectException(invalid_parameter_exception::class);
        $this->call($entryid, $hidden);
    }

    /**
     * The delete button of the app is offered exactly where the service deletes (candelete).
     */
    public function test_candelete_matches_the_service(): void {
        $other = $this->create_mobile_student($this->course);
        $own = $this->add_mobile_entry($this->dlx, $this->student->id, [$this->titleid => 'Own']);
        $theirs = $this->add_mobile_entry($this->dlx, $other->id, [$this->titleid => 'Theirs']);
        $this->setUser($this->student);

        $payload = (new \mod_datalynx\local\mobile\entries_payload_builder($this->dlx))->build(['viewid' => $this->viewid]);
        $candelete = array_column($payload['entries'], 'candelete', 'id');

        $this->assertTrue($candelete[$own]);
        $this->assertFalse($candelete[$theirs]);
        $this->assertTrue($this->call($own)['status']);
        $this->expectException(moodle_exception::class);
        $this->call($theirs);
    }
}
