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

    /**
     * Insert a tag field.
     *
     * @param int $dataid
     * @return int field id
     */
    private function create_tag_field(int $dataid): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dataid,
            'type' => 'tag',
            'name' => 'Tags',
            'description' => '',
        ]);
    }

    /**
     * Create an entry with a tagged content row, the way the tag field stores it.
     *
     * @param int $dataid
     * @param context_module $context
     * @param int $userid
     * @param int $fieldid
     * @param string[] $tags
     * @return int[] entry id and content id
     */
    private function create_tagged_entry(int $dataid, context_module $context, int $userid, int $fieldid, array $tags): array {
        $entryid = $this->create_entry($dataid, $userid);
        $contentid = $this->create_content($entryid, $fieldid, implode(',', $tags));
        \core_tag_tag::set_item_tags('mod_datalynx', 'datalynx_contents', $contentid, $context, $tags);
        return [$entryid, $contentid];
    }

    /**
     * Count the tag instances attached to a content row.
     *
     * @param int $contentid
     * @return int
     */
    private function count_tag_instances(int $contentid): int {
        global $DB;
        return $DB->count_records('tag_instance', [
            'component' => 'mod_datalynx',
            'itemtype' => 'datalynx_contents',
            'itemid' => $contentid,
        ]);
    }

    /**
     * The tag subsystem is declared in the metadata.
     *
     * @covers ::get_metadata
     */
    public function test_metadata_links_tag_subsystem(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('mod_datalynx'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('core_tag', $names);
    }

    /**
     * Tags of a tag field are exported below the content row they belong to.
     *
     * @covers ::export_user_data
     */
    public function test_export_includes_tags(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_tag_field($instance->id);
        [$entryid, $contentid] = $this->create_tagged_entry($instance->id, $context, $author->id, $fieldid, ['alpha', 'beta']);

        $this->setUser($author);
        provider::export_user_data(new approved_contextlist($author, 'mod_datalynx', [$context->id]));

        $writer = \core_privacy\local\request\writer::with_context($context);
        $tags = $writer->get_related_data([$entryid, $contentid], 'tags');
        $this->assertEqualsCanonicalizing(['alpha', 'beta'], array_values((array) $tags));
    }

    /**
     * Deleting a whole context leaves no tag instances behind.
     *
     * @covers ::delete_data_for_all_users_in_context
     */
    public function test_delete_for_all_users_removes_tag_instances(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_tag_field($instance->id);
        [, $contentid] = $this->create_tagged_entry($instance->id, $context, $author->id, $fieldid, ['alpha', 'beta']);
        $this->assertSame(2, $this->count_tag_instances($contentid));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertSame(0, $this->count_tag_instances($contentid));
    }

    /**
     * A per-user deletion removes the tag instances of that user's entries only.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_for_user_removes_own_tag_instances_only(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_tag_field($instance->id);
        [, $contentid] = $this->create_tagged_entry($instance->id, $context, $author->id, $fieldid, ['alpha', 'beta']);
        [, $othercontentid] = $this->create_tagged_entry($instance->id, $context, $other->id, $fieldid, ['alpha']);

        provider::delete_data_for_user(new approved_contextlist($author, 'mod_datalynx', [$context->id]));

        $this->assertSame(0, $this->count_tag_instances($contentid));
        $this->assertSame(1, $this->count_tag_instances($othercontentid));
    }

    /**
     * A multi-user deletion removes the tag instances of the selected users' entries only.
     *
     * @covers ::delete_data_for_users
     */
    public function test_delete_for_users_removes_selected_tag_instances_only(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_tag_field($instance->id);
        [, $contentid] = $this->create_tagged_entry($instance->id, $context, $author->id, $fieldid, ['alpha', 'beta']);
        [, $othercontentid] = $this->create_tagged_entry($instance->id, $context, $other->id, $fieldid, ['alpha']);

        provider::delete_data_for_users(new approved_userlist($context, 'mod_datalynx', [$author->id]));

        $this->assertSame(0, $this->count_tag_instances($contentid));
        $this->assertSame(1, $this->count_tag_instances($othercontentid));
    }

    /**
     * Deleting the content of a tag field through the field API removes its tag instances.
     *
     * @covers \datalynxfield_tag\field::delete_content
     */
    public function test_tag_field_delete_content_removes_tag_instances(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_tag_field($instance->id);
        [$entryid, $contentid] = $this->create_tagged_entry($instance->id, $context, $author->id, $fieldid, ['alpha', 'beta']);
        [, $keptcontentid] = $this->create_tagged_entry($instance->id, $context, $author->id, $fieldid, ['alpha']);

        $dlx = new \mod_datalynx\datalynx($instance->id);
        $dlx->get_field_from_id($fieldid)->delete_content($entryid);

        $this->assertSame(0, $this->count_tag_instances($contentid));
        $this->assertSame(1, $this->count_tag_instances($keptcontentid));
    }

    /**
     * A course reset that deletes all entries removes their tag instances.
     *
     * @covers ::datalynx_reset_userdata
     */
    public function test_course_reset_removes_tag_instances(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/datalynx/lib.php');

        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_tag_field($instance->id);
        [, $contentid] = $this->create_tagged_entry($instance->id, $context, $author->id, $fieldid, ['alpha', 'beta']);

        datalynx_reset_userdata((object) ['courseid' => $course->id, 'reset_datalynx_data' => 1, 'timeshift' => 0]);

        $this->assertSame(0, $this->count_tag_instances($contentid));
    }

    /**
     * Insert a team member select field.
     *
     * @param int $dataid
     * @return int field id
     */
    private function create_team_field(int $dataid): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $dataid,
            'type' => 'teammemberselect',
            'name' => 'Team',
            'description' => '',
        ]);
    }

    /**
     * Read the stored member list of a team member select content row.
     *
     * @param int $contentid
     * @return array
     */
    private function get_team(int $contentid): array {
        global $DB;
        return json_decode($DB->get_field('datalynx_contents', 'content', ['id' => $contentid]), true);
    }

    /**
     * A user who is only named as a team member of somebody else's entry is discovered, in both
     * the integer and the legacy string storage format, and a user whose id is merely a substring
     * of a member's id is not.
     *
     * @covers ::get_users_in_context
     * @covers ::get_contexts_for_userid
     */
    public function test_team_member_is_discovered(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $member = $this->getDataGenerator()->create_user();
        $legacymember = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_team_field($instance->id);
        $entryid = $this->create_entry($instance->id, $author->id);
        $this->create_content($entryid, $fieldid, json_encode([(int) $member->id]));
        $legacyentryid = $this->create_entry($instance->id, $author->id);
        $this->create_content($legacyentryid, $fieldid, json_encode([(string) $legacymember->id]));
        // An id that contains the member's id as a substring must not match the member.
        $substringentryid = $this->create_entry($instance->id, $author->id);
        $this->create_content($substringentryid, $fieldid, json_encode([(int) ($member->id . '0' . $member->id)]));

        $userlist = new userlist($context, 'mod_datalynx');
        provider::get_users_in_context($userlist);
        $this->assertContains((int) $member->id, $userlist->get_userids());
        $this->assertContains((int) $legacymember->id, $userlist->get_userids());

        foreach ([$member, $legacymember] as $user) {
            $contextids = provider::get_contexts_for_userid($user->id)->get_contextids();
            $this->assertEquals([$context->id], array_values($contextids));
        }

        $stranger = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($stranger->id)->get_contextids());
    }

    /**
     * An entry naming the user as a team member is part of the user's export.
     *
     * @covers ::export_user_data
     */
    public function test_export_includes_entries_with_user_as_team_member(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $member = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_team_field($instance->id);
        $entryid = $this->create_entry($instance->id, $author->id);
        $contentid = $this->create_content($entryid, $fieldid, json_encode([(int) $member->id]));
        $otherentryid = $this->create_entry($instance->id, $author->id);
        $othercontentid = $this->create_content($otherentryid, $fieldid, json_encode([(int) $author->id]));

        $this->setUser($member);
        provider::export_user_data(new approved_contextlist($member, 'mod_datalynx', [$context->id]));

        $writer = \core_privacy\local\request\writer::with_context($context);
        $data = $writer->get_data([$entryid, $contentid]);
        $this->assertSame(json_encode([(int) $member->id]), $data->content);
        $this->assertEmpty($writer->get_data([$otherentryid, $othercontentid]));
    }

    /**
     * A per-user deletion removes the user from the team of somebody else's entry, keeping the
     * entry and its other team members.
     *
     * @covers ::delete_data_for_user
     */
    public function test_delete_for_user_removes_team_membership(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $member = $this->getDataGenerator()->create_user();
        $othermember = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        $fieldid = $this->create_team_field($instance->id);
        $entryid = $this->create_entry($instance->id, $author->id);
        $contentid = $this->create_content($entryid, $fieldid, json_encode([(int) $member->id, (int) $othermember->id]));

        provider::delete_data_for_user(new approved_contextlist($member, 'mod_datalynx', [$context->id]));

        $this->assertTrue($DB->record_exists('datalynx_entries', ['id' => $entryid]));
        $this->assertSame([(int) $othermember->id], $this->get_team($contentid));
    }

    /**
     * A multi-user deletion removes the selected users from the teams of remaining entries, also
     * from legacy string storage, and leaves other contexts alone.
     *
     * @covers ::delete_data_for_users
     */
    public function test_delete_for_users_removes_team_memberships(): void {
        $course = $this->getDataGenerator()->create_course();
        $author = $this->getDataGenerator()->create_user();
        $member = $this->getDataGenerator()->create_user();
        $othermember = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('datalynx', $instance->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $otherinstance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);

        $fieldid = $this->create_team_field($instance->id);
        $entryid = $this->create_entry($instance->id, $author->id);
        $contentid = $this->create_content($entryid, $fieldid, json_encode([(string) $member->id, (string) $othermember->id]));
        $otherfieldid = $this->create_team_field($otherinstance->id);
        $otherentryid = $this->create_entry($otherinstance->id, $author->id);
        $othercontentid = $this->create_content($otherentryid, $otherfieldid, json_encode([(int) $member->id]));

        provider::delete_data_for_users(new approved_userlist($context, 'mod_datalynx', [$member->id]));

        $this->assertSame([(int) $othermember->id], $this->get_team($contentid));
        $this->assertSame([(int) $member->id], $this->get_team($othercontentid));
    }

    /**
     * Every datalynx subplugin ships a privacy provider, so the registry shows them as compliant.
     *
     * @coversNothing
     */
    public function test_all_subplugins_are_privacy_compliant(): void {
        $manager = new \core_privacy\manager();
        foreach (['datalynxfield', 'datalynxview', 'datalynxrule'] as $plugintype) {
            foreach (array_keys(\core_component::get_plugin_list($plugintype)) as $name) {
                $component = "{$plugintype}_{$name}";
                $this->assertTrue($manager->component_is_compliant($component), "$component has no privacy provider");
                if (!is_subclass_of("\\{$component}\\privacy\\provider", \core_privacy\local\metadata\null_provider::class)) {
                    continue;
                }
                $reason = $manager->get_null_provider_reason($component);
                $this->assertTrue(get_string_manager()->string_exists($reason, $component), "$component lacks its reason string");
            }
        }
    }
}
