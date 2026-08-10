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

/**
 * Privacy provider implementation for mod_datalynx.
 *
 * @package mod_datalynx
 * @copyright 2018 onwards The datalynx contributors
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\privacy;

// TODO: MDL-0000 Which are needed?
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\manager;

/**
 * Privacy provider implementation for mod_datalynx.
 *
 * @package mod_datalynx
 */
class provider implements

    // This plugin stores personal data.
    \core_privacy\local\metadata\provider,

    // This plugin can determine and delete the users who have data in a given context.
    \core_privacy\local\request\core_userlist_provider,

    // This plugin is a core_user_data_provider.
    \core_privacy\local\request\plugin\provider {
    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {

        // Table datalynx_entries.
        $collection->add_database_table(
            'datalynx_entries',
            [
                'userid' => 'privacy:metadata:datalynx_entries:userid',
                'groupid' => 'privacy:metadata:datalynx_entries:groupid',
                'timecreated' => 'privacy:metadata:datalynx_entries:timecreated',
                'timemodified' => 'privacy:metadata:datalynx_entries:timemodified',
                'approved' => 'privacy:metadata:datalynx_entries:approved',
                'status' => 'privacy:metadata:datalynx_entries:status',
                'timesubmitted' => 'privacy:metadata:datalynx_entries:timesubmitted',
                'assessed' => 'privacy:metadata:datalynx_entries:assessed',
            ],
            'privacy:metadata:datalynx_entries'
        );
        // Table datalynx_contents.
        $collection->add_database_table(
            'datalynx_contents',
            [
                'fieldid' => 'privacy:metadata:datalynx_contents:fieldid',
                'content' => 'privacy:metadata:datalynx_contents:content',
                'content1' => 'privacy:metadata:datalynx_contents:content1',
                'content2' => 'privacy:metadata:datalynx_contents:content2',
                'content3' => 'privacy:metadata:datalynx_contents:content3',
                'content4' => 'privacy:metadata:datalynx_contents:content4',
            ],
            'privacy:metadata:datalynx_contents'
        );
        // Table datalynx_rule_matches.
        $collection->add_database_table(
            'datalynx_rule_matches',
            [
                'entrylow' => 'privacy:metadata:datalynx_rule_matches:entrylow',
                'entryhigh' => 'privacy:metadata:datalynx_rule_matches:entryhigh',
                'timenotified' => 'privacy:metadata:datalynx_rule_matches:timenotified',
            ],
            'privacy:metadata:datalynx_rule_matches'
        );

        // Subsystems used.
        $collection->link_subsystem('core_files', 'privacy:metadata:filepurpose');
        $collection->link_subsystem('core_comment', 'privacy:metadata:commentpurpose');
        $collection->link_subsystem('core_rating', 'privacy:metadata:ratingpurpose');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {

        // Fetch all entries in datalynx created by the user.
        $sql = "SELECT c.id
            FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {datalynx} dl ON dl.id = cm.instance
            INNER JOIN {datalynx_entries} de ON de.dataid = dl.id
            WHERE de.userid = :userid";

        $params = [
            'modname'       => 'datalynx',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        // Fetch contexts where the user commented on an entry (their own or someone else's).
        $commentsql = "SELECT c.id
            FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {datalynx} dl ON dl.id = cm.instance
            INNER JOIN {datalynx_entries} de ON de.dataid = dl.id
            INNER JOIN {comments} com ON com.itemid = de.id
                AND com.component = :component AND com.commentarea = :commentarea
            WHERE com.userid = :userid";
        $contextlist->add_from_sql($commentsql, [
            'modname'      => 'datalynx',
            'contextlevel' => CONTEXT_MODULE,
            'component'    => 'mod_datalynx',
            'commentarea'  => 'entry',
            'userid'       => $userid,
        ]);

        // Fetch contexts where the user rated an entry, in either rating area.
        foreach (['entry', 'activity'] as $ratingarea) {
            $ratingquery = \core_rating\privacy\provider::get_sql_join('r', 'mod_datalynx', $ratingarea, 'de.id', $userid, true);
            $ratingsql = "SELECT c.id
                FROM {context} c
                INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                INNER JOIN {datalynx} dl ON dl.id = cm.instance
                INNER JOIN {datalynx_entries} de ON de.dataid = dl.id
                {$ratingquery->join}
                WHERE {$ratingquery->userwhere}";
            $contextlist->add_from_sql($ratingsql, [
                'modname'      => 'datalynx',
                'contextlevel' => CONTEXT_MODULE,
            ] + $ratingquery->params);
        }

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist the userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        // Users who authored an entry in this datalynx instance.
        $sql = "SELECT de.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {datalynx} dl ON dl.id = cm.instance
                  JOIN {datalynx_entries} de ON de.dataid = dl.id
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid, 'modname' => 'datalynx']);

        // Users who commented on an entry in this instance.
        \core_comment\privacy\provider::get_users_in_context_from_sql(
            $userlist,
            'com',
            'mod_datalynx',
            'entry',
            $context->id
        );

        // Users who rated an entry in this instance, in either rating area.
        $itemsql = "SELECT de.id
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                      JOIN {datalynx} dl ON dl.id = cm.instance
                      JOIN {datalynx_entries} de ON de.dataid = dl.id
                     WHERE cm.id = :cmid";
        $itemparams = ['cmid' => $context->instanceid, 'modname' => 'datalynx'];
        \core_rating\privacy\provider::get_users_in_context_from_sql(
            $userlist,
            'raten',
            'mod_datalynx',
            'entry',
            $itemsql,
            $itemparams
        );
        \core_rating\privacy\provider::get_users_in_context_from_sql(
            $userlist,
            'ratac',
            'mod_datalynx',
            'activity',
            $itemsql,
            $itemparams
        );
    }

    /**
     * Export personal data for the given approved_contextlist. User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }

        $user = $contextlist->get_user();

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $sql = "SELECT cm.id AS cmid, dl.name AS dataname, cm.course AS courseid, " . self::sql_fields() . "
                FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                JOIN {datalynx} dl ON dl.id = cm.instance
                JOIN {datalynx_entries} de ON de.dataid = dl.id
                JOIN {datalynx_contents} dc ON dc.entryid = de.id
                JOIN {datalynx_fields} df ON df.id = dc.fieldid
                WHERE ctx.id {$contextsql} AND ctx.contextlevel = :contextlevel
                AND (de.userid = :userid
                     OR EXISTS (SELECT 1 FROM {comments} com
                                 WHERE com.itemid = de.id AND com.component = :ccomponent
                                   AND com.commentarea = :ccommentarea AND com.userid = :cuserid)
                     OR EXISTS (SELECT 1 FROM {rating} rat
                                 WHERE rat.itemid = de.id AND rat.contextid = ctx.id
                                   AND rat.component = :rcomponent
                                   AND rat.ratingarea IN (:rareaentry, :rareaactivity)
                                   AND rat.userid = :ruserid))
                ORDER BY cm.id, de.id, dc.fieldid";
        $rs = $DB->get_recordset_sql($sql, $contextparams + ['contextlevel' => CONTEXT_MODULE,
                'modname' => 'datalynx', 'userid' => $user->id, 'moddata' => 'mod_datalynx',
                'ccomponent' => 'mod_datalynx', 'ccommentarea' => 'entry', 'cuserid' => $user->id,
                'rcomponent' => 'mod_datalynx', 'rareaentry' => 'entry', 'rareaactivity' => 'activity',
                'ruserid' => $user->id]);

        $context = null;
        $recordobj = null;
        foreach ($rs as $row) {
            if (!$context || $context->instanceid != $row->cmid) {
                // This row belongs to the different data module than the previous row.
                // Export the data for the previous module.
                self::export_datalynx($context, $user);
                // Start new data module.
                $context = \context_module::instance($row->cmid);
            }

            if (!$recordobj || $row->entryid != $recordobj->id) {
                // Export previous datalynx entry.
                self::export_datalynx_entry($context, $user, $recordobj);
                // Prepare for exporting new datalynx entry.
                $recordobj = self::extract_object_from_entry($row, 'entry', ['dataid' => $row->dataid]);
            }

            $fieldobj = self::extract_object_from_entry($row, 'field', ['dataid' => $row->dataid]);
            $contentobj = self::extract_object_from_entry(
                $row,
                'content',
                ['fieldid' => $fieldobj->id, 'entryid' => $recordobj->id]
            );
            self::export_datalynx_content($context, $recordobj, $fieldobj, $contentobj);
        }
        $rs->close();
        self::export_datalynx_entry($context, $user, $recordobj);
        self::export_datalynx($context, $user);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $recordstobedeleted = [];

        $sql = "SELECT " . self::sql_fields() . "
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                JOIN {datalynx} dl ON dl.id = cm.instance
                JOIN {datalynx_entries} de ON de.dataid = dl.id
                LEFT JOIN {datalynx_contents} dc ON dc.entryid = de.id
                LEFT JOIN {datalynx_fields} df ON df.id = dc.fieldid
                WHERE cm.id = :cmid
                ORDER BY de.id";
        $rs = $DB->get_recordset_sql($sql, ['cmid' => $context->instanceid, 'modname' => 'datalynx']);
        foreach ($rs as $row) {
            self::mark_datalynx_contents_for_deletion($context, $row);
            $recordstobedeleted[$row->entryid] = $row->entryid;
        }
        $rs->close();

        self::delete_datalynx_entries($context, $recordstobedeleted);

        // Delete every comment posted on entries in this context.
        \core_comment\privacy\provider::delete_comments_for_all_users($context, 'mod_datalynx', 'entry');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        $recordstobedeleted = [];

        foreach ($contextlist->get_contexts() as $context) {
            $sql = "SELECT " . self::sql_fields() . "
                FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                JOIN {datalynx} dl ON dl.id = cm.instance
                JOIN {datalynx_entries} de ON de.dataid = dl.id AND de.userid = :userid
                LEFT JOIN {datalynx_contents} dc ON dc.entryid = de.id
                LEFT JOIN {datalynx_fields} df ON df.id = dc.fieldid
                WHERE ctx.id = :ctxid AND ctx.contextlevel = :contextlevel
                ORDER BY de.id";
            $rs = $DB->get_recordset_sql($sql, ['ctxid' => $context->id, 'contextlevel' => CONTEXT_MODULE,
                'modname' => 'datalynx', 'userid' => $user->id]);
            foreach ($rs as $row) {
                self::mark_datalynx_contents_for_deletion($context, $row);
                $recordstobedeleted[$row->entryid] = $row->entryid;
            }
            $rs->close();
            self::delete_datalynx_entries($context, $recordstobedeleted);
        }

        // Delete this user's comments across the approved contexts.
        \core_comment\privacy\provider::delete_comments_for_user($contextlist, 'mod_datalynx', 'entry');
    }

    /**
     * Delete multiple users' data within a single context.
     *
     * @param approved_userlist $userlist the approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $recordstobedeleted = [];

        $sql = "SELECT " . self::sql_fields() . "
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                JOIN {datalynx} dl ON dl.id = cm.instance
                JOIN {datalynx_entries} de ON de.dataid = dl.id AND de.userid $usersql
                LEFT JOIN {datalynx_contents} dc ON dc.entryid = de.id
                LEFT JOIN {datalynx_fields} df ON df.id = dc.fieldid
                WHERE cm.id = :cmid
                ORDER BY de.id";
        $params = ['cmid' => $context->instanceid, 'modname' => 'datalynx'] + $userparams;
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            self::mark_datalynx_contents_for_deletion($context, $row);
            $recordstobedeleted[$row->entryid] = $row->entryid;
        }
        $rs->close();

        self::delete_datalynx_entries($context, $recordstobedeleted);

        // Delete the approved users' comments in this context.
        \core_comment\privacy\provider::delete_comments_for_users($userlist, 'mod_datalynx', 'entry');
    }

    /**
     * Export one entry in the datalynx_entries table)
     *
     * @param \context $context
     * @param \stdClass $user
     * @param \stdClass $recordobj
     */
    protected static function export_datalynx_entry($context, $user, $recordobj) {
        if (!$recordobj) {
            return;
        }
        $data = [
            'userid' => transform::user($user->id),
            'groupid' => $recordobj->groupid,
            'timecreated' => transform::datetime($recordobj->timecreated),
            'timemodified' => transform::datetime($recordobj->timemodified),
            'approved' => transform::yesno($recordobj->approved),
            'status' => transform::yesno($recordobj->status),
            'timesubmitted' => $recordobj->timesubmitted ? transform::datetime($recordobj->timesubmitted) : '',
            'assessed' => transform::yesno($recordobj->assessed),
        ];
        // Data about the record.
        writer::with_context($context)->export_data([$recordobj->id], (object)$data);
        // Related tags.
        \core_tag\privacy\provider::export_item_tags(
            $user->id,
            $context,
            [$recordobj->id],
            'mod_datalynx',
            'datalynx_entries',
            $recordobj->id
        );
        // Export comments. For entries not authored by this user export only this user's comments;
        // for the user's own entries export the comments made by everybody.
        \core_comment\privacy\provider::export_comments(
            $context,
            'mod_datalynx',
            'entry',
            $recordobj->id,
            [$recordobj->id],
            $recordobj->userid != $user->id
        );
        // Export ratings for both rating areas. For the user's own entries export everybody's
        // ratings; for others' entries export only this user's ratings. The activity-area ratings
        // are nested under their own subcontext so they cannot collide with the entry-area ratings.
        \core_rating\privacy\provider::export_area_ratings(
            $user->id,
            $context,
            [$recordobj->id],
            'mod_datalynx',
            'entry',
            $recordobj->id,
            $recordobj->userid != $user->id
        );
        \core_rating\privacy\provider::export_area_ratings(
            $user->id,
            $context,
            [$recordobj->id, get_string('activity')],
            'mod_datalynx',
            'activity',
            $recordobj->id,
            $recordobj->userid != $user->id
        );
    }

    /**
     * Creates an object from all fields in the $entry where key starts with $prefix
     *
     * @param \stdClass $entry
     * @param string $prefix
     * @param array $additionalfields
     * @return \stdClass
     */
    protected static function extract_object_from_entry($entry, $prefix, $additionalfields = []) {
        $object = new \stdClass();
        foreach ($entry as $key => $value) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(.*)/', $key, $matches)) {
                $object->{$matches[1]} = $value;
            }
        }
        if ($additionalfields) {
            foreach ($additionalfields as $key => $value) {
                $object->$key = $value;
            }
        }
        return $object;
    }

    /**
     * Export basic info about datalynx activity module
     *
     * @param \context $context
     * @param \stdClass $user
     */
    protected static function export_datalynx($context, $user) {
        if (!$context) {
            return;
        }
        $contextdata = helper::get_context_data($context, $user);
        helper::export_context_files($context, $user);
        writer::with_context($context)->export_data([], $contextdata); // TODO: MDL-0000 Check if this is export_data or not.
    }

    /**
     * Marks datalynx_entry and datalynx_contents for deletion
     *
     * @param \context $context
     * @param \stdClass $row result of SQL query - tables data_content, data_record, data_fields join together
     */
    protected static function mark_datalynx_contents_for_deletion($context, $row) {
        $recordobj = self::extract_object_from_entry($row, 'entry', ['dataid' => $row->dataid]);
        if ($row->contentid && $row->fieldid) {
            $fieldobj = self::extract_object_from_entry($row, 'field', ['dataid' => $row->dataid]);
            self::extract_object_from_entry(
                $row,
                'content',
                ['fieldid' => $fieldobj->id, 'entryid' => $recordobj->id]
            );

            // Allow datafield plugin to implement their own deletion (not yet implemented).
        }
    }

    /**
     * Deletes records marked for deletion and all associated data
     *
     * Should be executed after all records were marked by {@see mark_data_content_for_deletion()}
     *
     * Deletes records from datalynx_contents and datalynx_entries tables, associated files.
     *
     * @param \context $context
     * @param array $recordstobedeleted list of ids of the data records that need to be deleted
     */
    protected static function delete_datalynx_entries($context, $recordstobedeleted) {
        global $DB;
        if (empty($recordstobedeleted)) {
            return;
        }

        [$sql, $params] = $DB->get_in_or_equal($recordstobedeleted, SQL_PARAMS_NAMED);

        // Delete files. Uploaded file/picture content is stored under the 'content' and 'thumb'
        // fileareas keyed by content id (there is no 'datalynx_entries' filearea), so both must be
        // cleared for every content row belonging to the entries being removed.
        $fs = get_file_storage();
        $itemidtest = "IN (SELECT dc.id FROM {datalynx_contents} dc WHERE dc.entryid $sql)";
        foreach (['content', 'thumb'] as $filearea) {
            $fs->delete_area_files_select(
                $context->id,
                'mod_datalynx',
                $filearea,
                $itemidtest,
                $params
            );
        }

        // Delete ratings on these entries, in both rating areas. Ratings are keyed by entry id, and
        // per core_rating they are removed only when the rated item itself is deleted (never
        // per-user, as that would affect grades) — which is exactly what is happening here.
        foreach (['entry', 'activity'] as $ratingarea) {
            \core_rating\privacy\provider::delete_ratings_select($context, 'mod_datalynx', $ratingarea, $sql, $params);
        }

        // Delete from datalynx_contents.
        $DB->delete_records_select('datalynx_contents', 'entryid ' . $sql, $params);
        // Delete the derived waypoint index and the record of which entry pairs were announced,
        // both of which name entries that are about to disappear. The ledger names an entry in
        // either of two columns, and a named placeholder may occur only once per statement, so
        // the id list is bound a second time under its own prefix.
        $DB->delete_records_select('datalynx_waypoints', 'entryid ' . $sql, $params);
        [$highsql, $highparams] = $DB->get_in_or_equal($recordstobedeleted, SQL_PARAMS_NAMED, 'dlxhigh');
        $DB->delete_records_select(
            'datalynx_rule_matches',
            "entrylow $sql OR entryhigh $highsql",
            $params + $highparams
        );
        // Delete from datalynx_entries.
        $DB->delete_records_select('datalynx_entries', 'id ' . $sql, $params);
        // Note: Keep the space after entryid and id.
    }

    /**
     * Export one field answer in a record in database activity module
     *
     * @param \context $context
     * @param \stdClass $recordobj record from DB table {data_records}
     * @param \stdClass $fieldobj record from DB table {data_fields}
     * @param \stdClass $contentobj record from DB table {data_content}
     */
    protected static function export_datalynx_content($context, $recordobj, $fieldobj, $contentobj) {
        $value = (object)[
            'field' => [
                // Name and description are displayed in mod_data without applying format_string().
                'name' => $fieldobj->name,
                'description' => $fieldobj->description,
                'type' => $fieldobj->type,
            ],
            'content' => $contentobj->content,
        ];
        foreach (['content1', 'content2', 'content3', 'content4'] as $key) {
            if ($contentobj->$key !== null) {
                $value->$key = $contentobj->$key;
            }
        }
        manager::get_provider_classname_for_component('datafield_' . $fieldobj->type);

        // Data field plugin does not implement datafield_provider, just export default value.
        writer::with_context($context)->export_data([$recordobj->id, $contentobj->id], $value);
        writer::with_context($context)->export_area_files(
            [$recordobj->id, $contentobj->id],
            'mod_datalynx',
            'content',
            $contentobj->id
        );
    }

    /**
     * SQL query that returns all fields from {datalynx_contents}, {datalynx_fields} and {datalynx_entries} tables
     *
     * @return string
     */
    protected static function sql_fields() {
        // Field df.required AS fieldrequired was removed.
        return 'dl.id AS dataid, dc.id AS contentid, dc.fieldid, df.type AS fieldtype, df.name AS fieldname,
                  df.description AS fielddescription,
                  df.param1 AS fieldparam1, df.param2 AS fieldparam2, df.param3 AS fieldparam3, df.param4 AS fieldparam4,
                  df.param5 AS fieldparam5, df.param6 AS fieldparam6, df.param7 AS fieldparam7, df.param8 AS fieldparam8,
                  df.param9 AS fieldparam9, df.param10 AS fieldparam10,
                  dc.content AS contentcontent, dc.content1 AS contentcontent1, dc.content2 AS contentcontent2,
                  dc.content3 AS contentcontent3, dc.content4 AS contentcontent4,
                  dc.entryid, de.timecreated AS entrytimecreated,
                  de.timemodified AS entrytimemodified,
                  de.timesubmitted AS entrytimesubmitted,
                  de.status AS entrystatus,
                  de.assessed AS entryassessed,
                  de.approved AS entryapproved, de.groupid AS entrygroupid, de.userid AS entryuserid';
    }
}
