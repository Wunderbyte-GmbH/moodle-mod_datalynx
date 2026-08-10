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
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;

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
        $entryid = $this->create_entry($dataid, $userid);
        $contentid = $this->create_content($entryid, $fieldid, 'attachment.txt');

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
     * Insert a datalynx entry and return its id.
     *
     * @param int $dataid
     * @param int $userid
     * @return int entry id
     */
    private function create_entry(int $dataid, int $userid): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('datalynx_entries', (object) [
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
    }

    /**
     * Insert a content row and return its id.
     *
     * @param int $entryid
     * @param int $fieldid
     * @param string $content
     * @return int content id
     */
    private function create_content(int $entryid, int $fieldid, string $content = 'x'): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid,
            'entryid' => $entryid,
            'content' => $content,
        ]);
    }

    /**
     * Post a comment on an entry.
     *
     * @param int $contextid
     * @param int $entryid
     * @param int $userid
     * @param string $content
     * @return int comment id
     */
    private function add_comment(int $contextid, int $entryid, int $userid, string $content = 'A comment'): int {
        global $DB;
        return (int) $DB->insert_record('comments', (object) [
            'contextid' => $contextid,
            'component' => 'mod_datalynx',
            'commentarea' => 'entry',
            'itemid' => $entryid,
            'content' => $content,
            'format' => FORMAT_PLAIN,
            'userid' => $userid,
            'timecreated' => time(),
        ]);
    }

    /**
     * Count comments made by a user in the datalynx entry area.
     *
     * @param int $userid
     * @return int
     */
    private function count_comments_by_user(int $userid): int {
        global $DB;
        return $DB->count_records('comments', ['component' => 'mod_datalynx', 'commentarea' => 'entry', 'userid' => $userid]);
    }

    /**
     * Add a rating on an entry.
     *
     * @param int $contextid
     * @param int $entryid
     * @param int $userid
     * @param string $ratingarea
     * @param int $rating
     * @return int rating id
     */
    private function add_rating(int $contextid, int $entryid, int $userid, string $ratingarea = 'entry', int $rating = 3): int {
        global $DB;
        $now = time();
        return (int) $DB->insert_record('rating', (object) [
            'contextid' => $contextid,
            'component' => 'mod_datalynx',
            'ratingarea' => $ratingarea,
            'itemid' => $entryid,
            'scaleid' => 5,
            'rating' => $rating,
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Count ratings on an entry.
     *
     * @param int $contextid
     * @param int $entryid
     * @return int
     */
    private function count_ratings_on_entry(int $contextid, int $entryid): int {
        global $DB;
        return $DB->count_records('rating', ['contextid' => $contextid, 'component' => 'mod_datalynx', 'itemid' => $entryid]);
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

    /**
     * get_users_in_context() must return every entry author in the context.
     *
     * @covers ::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        $course = $this->getDataGenerator()->create_course();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $stranger = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_file_field($instance->id);
        $this->create_entry_with_files($instance->id, $context->id, $userone->id, $fieldid);
        $this->create_entry_with_files($instance->id, $context->id, $usertwo->id, $fieldid);

        $userlist = new userlist($context, 'mod_datalynx');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertCount(2, $userids);
        $this->assertContains((int) $userone->id, $userids);
        $this->assertContains((int) $usertwo->id, $userids);
        $this->assertNotContains((int) $stranger->id, $userids);
    }

    /**
     * delete_data_for_users() must delete only the approved users' data and files.
     *
     * @covers ::delete_data_for_users
     */
    public function test_delete_data_for_users_only_deletes_selected(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_file_field($instance->id);
        $contentone = $this->create_entry_with_files($instance->id, $context->id, $userone->id, $fieldid);
        $contenttwo = $this->create_entry_with_files($instance->id, $context->id, $usertwo->id, $fieldid);

        // Approve deletion of user one only.
        $approved = new approved_userlist($context, 'mod_datalynx', [$userone->id]);
        provider::delete_data_for_users($approved);

        // User one is erased (entry, content and both file areas).
        $this->assertSame(0, $this->count_entry_files($context->id, $contentone));
        $this->assertFalse($DB->record_exists('datalynx_contents', ['id' => $contentone]));

        // User two is untouched.
        $this->assertSame(2, $this->count_entry_files($context->id, $contenttwo));
        $this->assertTrue($DB->record_exists('datalynx_contents', ['id' => $contenttwo]));
        $this->assertSame(1, $DB->count_records('datalynx_entries', ['dataid' => $instance->id]));
    }

    /**
     * A user who only commented (never authored an entry) is discovered in the context and by userid.
     *
     * @covers ::get_users_in_context
     * @covers ::get_contexts_for_userid
     */
    public function test_commenter_is_discovered(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $commenter = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $entryid = $this->create_entry($instance->id, $author->id);
        $this->add_comment($context->id, $entryid, $commenter->id);

        $userlist = new userlist($context, 'mod_datalynx');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertContains((int) $author->id, $userids);
        $this->assertContains((int) $commenter->id, $userids);

        // The commenter, who authored nothing, still has this context in their list.
        $contextlist = provider::get_contexts_for_userid($commenter->id);
        $this->assertEqualsCanonicalizing([$context->id], $contextlist->get_contextids());
    }

    /**
     * delete_data_for_all_users_in_context() removes all comments in the context.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_for_all_users_removes_comments(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $commenter = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $entryid = $this->create_entry($instance->id, $author->id);
        $this->add_comment($context->id, $entryid, $author->id);
        $this->add_comment($context->id, $entryid, $commenter->id);
        $this->assertSame(2, $DB->count_records('comments', ['contextid' => $context->id, 'commentarea' => 'entry']));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $DB->count_records('comments', ['contextid' => $context->id, 'commentarea' => 'entry']));
    }

    /**
     * delete_data_for_user() removes that user's comments, leaving others' comments.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_for_user_removes_own_comments(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $commenter = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $entryid = $this->create_entry($instance->id, $author->id);
        $this->add_comment($context->id, $entryid, $author->id);
        $this->add_comment($context->id, $entryid, $commenter->id);

        $contextlist = new approved_contextlist($commenter, 'mod_datalynx', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $this->count_comments_by_user($commenter->id));
        $this->assertSame(1, $this->count_comments_by_user($author->id));
    }

    /**
     * delete_data_for_users() removes only the approved users' comments.
     *
     * @covers ::delete_data_for_users
     */
    public function test_delete_for_users_removes_selected_comments(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $commenterone = $this->getDataGenerator()->create_user();
        $commentertwo = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $entryid = $this->create_entry($instance->id, $author->id);
        $this->add_comment($context->id, $entryid, $commenterone->id);
        $this->add_comment($context->id, $entryid, $commentertwo->id);

        $approved = new approved_userlist($context, 'mod_datalynx', [$commenterone->id]);
        provider::delete_data_for_users($approved);

        $this->assertSame(0, $this->count_comments_by_user($commenterone->id));
        $this->assertSame(1, $this->count_comments_by_user($commentertwo->id));
    }

    /**
     * A user's comment is included in their exported data.
     *
     * @covers ::export_user_data
     */
    public function test_export_includes_user_comments(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $commenter = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // The entry needs a content row to appear in the export query.
        $fieldid = $this->create_file_field($instance->id);
        $entryid = $this->create_entry($instance->id, $author->id);
        $this->create_content($entryid, $fieldid, 'entry body');
        $this->add_comment($context->id, $entryid, $commenter->id, 'Hello from the commenter');

        // The real export runs as the requesting user (the task sets its userid), and
        // core_comment::export_comments() filters "only this user's" comments by $USER.
        $this->setUser($commenter);
        $contextlist = new approved_contextlist($commenter, 'mod_datalynx', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
        $data = $writer->get_data([$entryid, get_string('commentsubcontext', 'core_comment')]);
        $this->assertNotEmpty($data->comments);
        $this->assertSame('Hello from the commenter', reset($data->comments)->content);
    }

    /**
     * A user who only rated an entry (never authored one) is discovered in the context and by userid.
     *
     * @covers ::get_users_in_context
     * @covers ::get_contexts_for_userid
     */
    public function test_rater_is_discovered(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $rater = $this->getDataGenerator()->create_user();
        $activityrater = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $entryid = $this->create_entry($instance->id, $author->id);
        $this->add_rating($context->id, $entryid, $rater->id, 'entry');
        $this->add_rating($context->id, $entryid, $activityrater->id, 'activity');

        $userlist = new userlist($context, 'mod_datalynx');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();
        $this->assertContains((int) $author->id, $userids);
        $this->assertContains((int) $rater->id, $userids);
        $this->assertContains((int) $activityrater->id, $userids);

        // Each rater, who authored nothing, still has this context in their list.
        $this->assertEqualsCanonicalizing(
            [$context->id],
            provider::get_contexts_for_userid($rater->id)->get_contextids()
        );
        $this->assertEqualsCanonicalizing(
            [$context->id],
            provider::get_contexts_for_userid($activityrater->id)->get_contextids()
        );
    }

    /**
     * delete_data_for_all_users_in_context() removes all ratings in the context.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_for_all_users_removes_ratings(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $rater = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_file_field($instance->id);
        $entryid = $this->create_entry($instance->id, $author->id);
        $this->create_content($entryid, $fieldid);
        $this->add_rating($context->id, $entryid, $rater->id, 'entry');
        $this->add_rating($context->id, $entryid, $rater->id, 'activity');
        $this->assertSame(2, $this->count_ratings_on_entry($context->id, $entryid));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $this->count_ratings_on_entry($context->id, $entryid));
    }

    /**
     * Deleting a user removes ratings on that user's (now deleted) entries, but not the ratings the
     * user gave on surviving entries — core_rating never removes ratings per user, only per item.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_for_user_removes_ratings_on_deleted_entries_only(): void {
        $course = $this->getDataGenerator()->create_course();
        $authorone = $this->getDataGenerator()->create_user();
        $authortwo = $this->getDataGenerator()->create_user();
        $rater = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // Author one owns entry one (rated by a third user). Author one also rated author two's entry.
        $fieldid = $this->create_file_field($instance->id);
        $entryone = $this->create_entry($instance->id, $authorone->id);
        $this->create_content($entryone, $fieldid);
        $entrytwo = $this->create_entry($instance->id, $authortwo->id);
        $this->add_rating($context->id, $entryone, $rater->id, 'entry');
        $this->add_rating($context->id, $entrytwo, $authorone->id, 'entry');

        $contextlist = new approved_contextlist($authorone, 'mod_datalynx', [$context->id]);
        provider::delete_data_for_user($contextlist);

        // Entry one is deleted, so the rating on it is gone.
        $this->assertSame(0, $this->count_ratings_on_entry($context->id, $entryone));
        // Author one's rating on author two's surviving entry stays (it counts toward a grade).
        $this->assertSame(1, $this->count_ratings_on_entry($context->id, $entrytwo));
    }

    /**
     * A user's rating is included in their exported data.
     *
     * @covers ::export_user_data
     */
    public function test_export_includes_user_ratings(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $rater = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_file_field($instance->id);
        $entryid = $this->create_entry($instance->id, $author->id);
        $this->create_content($entryid, $fieldid, 'entry body');
        $this->add_rating($context->id, $entryid, $rater->id, 'entry', 4);

        $this->setUser($rater);
        $contextlist = new approved_contextlist($rater, 'mod_datalynx', [$context->id]);
        provider::export_user_data($contextlist);

        $writer = \core_privacy\local\request\writer::with_context($context);
        $ratings = $writer->get_related_data([$entryid], 'rating');
        $this->assertNotEmpty($ratings);
        $this->assertSame(4, (int) reset($ratings)->rating);
    }

    /**
     * An entry with no content row is still fully deleted, along with its comments and ratings.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_content_less_entry_is_deleted_for_all_users(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        // An entry with NO datalynx_contents row, but with a comment and a rating.
        $entryid = $this->create_entry($instance->id, $author->id);
        $this->add_comment($context->id, $entryid, $author->id);
        $this->add_rating($context->id, $entryid, $author->id, 'entry');

        provider::delete_data_for_all_users_in_context($context);

        $this->assertFalse($DB->record_exists('datalynx_entries', ['id' => $entryid]));
        $this->assertSame(0, $DB->count_records('comments', ['itemid' => $entryid, 'commentarea' => 'entry']));
        $this->assertSame(0, $this->count_ratings_on_entry($context->id, $entryid));
    }

    /**
     * A user's content-less entry (and its ratings/comments) is deleted on a per-user request.
     *
     * @covers ::delete_data_for_user
     */
    public function test_content_less_entry_is_deleted_for_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $entryid = $this->create_entry($instance->id, $author->id);
        $this->add_comment($context->id, $entryid, $author->id);
        $this->add_rating($context->id, $entryid, $author->id, 'entry');

        $contextlist = new approved_contextlist($author, 'mod_datalynx', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('datalynx_entries', ['id' => $entryid]));
        $this->assertSame(0, $this->count_comments_by_user((int) $author->id));
        $this->assertSame(0, $this->count_ratings_on_entry($context->id, $entryid));
    }
}
