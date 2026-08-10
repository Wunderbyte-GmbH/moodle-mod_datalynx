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
 * Privacy provider tests for mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\privacy;

use advanced_testcase;
use context_module;
use core_privacy\local\request\approved_contextlist;

/**
 * Tests that GDPR deletion removes uploaded entry files (see issue #241).
 *
 * @coversDefaultClass \mod_datalynx\privacy\provider
 */
final class provider_test extends advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Insert a file field.
     *
     * @param int $dataid
     * @return int field id
     */
    private function create_file_field(int $dataid): int {
        global $DB;
        $record = (object) [
            'dataid' => $dataid,
            'type' => 'file',
            'name' => 'FileField',
            'description' => '',
            'required' => 0,
            'visibleto' => 0,
            'editableby' => 0,
        ];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = '';
        }
        return (int) $DB->insert_record('datalynx_fields', $record);
    }

    /**
     * Create an entry with a content row and a stored file in both the content and thumb fileareas.
     *
     * @param int $dataid
     * @param int $contextid
     * @param int $userid
     * @param int $fieldid
     * @return int the content id (also the file itemid)
     */
    private function create_entry_with_files(int $dataid, int $contextid, int $userid, int $fieldid): int {
        global $DB;

        $now = time();
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dataid,
            'userid' => $userid,
            'groupid' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
            'approved' => 1,
            'status' => 0,
            'timesubmitted' => 0,
            'assessed' => 0,
        ]);
        $contentid = (int) $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entryid,
            'content' => 'attachment.txt',
        ]);

        $fs = get_file_storage();
        foreach (['content', 'thumb'] as $filearea) {
            $fs->create_file_from_string((object) [
                'contextid' => $contextid,
                'component' => 'mod_datalynx',
                'filearea' => $filearea,
                'itemid' => $contentid,
                'filepath' => '/',
                'filename' => $filearea . '.txt',
            ], "payload-$filearea");
        }

        return $contentid;
    }

    /**
     * Count stored files (excluding directories) in the content and thumb areas for an item.
     *
     * @param int $contextid
     * @param int $itemid
     * @return int
     */
    private function count_entry_files(int $contextid, int $itemid): int {
        $fs = get_file_storage();
        $count = 0;
        foreach (['content', 'thumb'] as $filearea) {
            $count += count($fs->get_area_files($contextid, 'mod_datalynx', $filearea, $itemid, 'id', false));
        }
        return $count;
    }

    /**
     * delete_data_for_all_users_in_context() must remove the content and thumb files.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_for_all_users_removes_uploaded_files(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_file_field($instance->id);
        $contentid = $this->create_entry_with_files($instance->id, $context->id, $user->id, $fieldid);

        $this->assertSame(2, $this->count_entry_files($context->id, $contentid));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $this->count_entry_files($context->id, $contentid));
        $this->assertFalse($DB->record_exists('datalynx_contents', ['id' => $contentid]));
        $this->assertSame(0, $DB->count_records('datalynx_entries', ['dataid' => $instance->id]));
    }

    /**
     * delete_data_for_user() must remove the content and thumb files for that user's entries.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_for_user_removes_uploaded_files(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_file_field($instance->id);
        $contentid = $this->create_entry_with_files($instance->id, $context->id, $user->id, $fieldid);

        $this->assertSame(2, $this->count_entry_files($context->id, $contentid));

        $contextlist = new approved_contextlist($user, 'mod_datalynx', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $this->count_entry_files($context->id, $contentid));
        $this->assertFalse($DB->record_exists('datalynx_contents', ['id' => $contentid]));
    }
}
