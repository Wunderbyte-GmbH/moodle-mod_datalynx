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
 *
 * @package mod
 * @subpackage mod_datalynx
 * @copyright 2013 onwards Ivan Sakic, David Bogner, Michael Pollak and others
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
defined('MOODLE_INTERNAL') or die();

/**
 * MOD FUNCTIONS WHICH ARE CALLED FROM OUTSIDE THE MODULE
 */

/**
 * Indicates API features that the datalynx supports.
 *
 * @param string $feature
 * @return mixed true if yes (some features may use other values)
 */
function datalynx_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_RATE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * Adds an instance of a datalynx
 *
 * @global object
 * @param object $data
 * @return $int
 */
function datalynx_add_instance($data) {
    global $CFG, $DB;

    $data->timemodified = time();

    if (empty($data->grade)) {
        $data->grade = 0;
        $data->grademethod = 0;
    }

    if (!empty($data->assessed)) {
        $data->grademethod = $data->assessed;
    } else {
        $data->rating = 0;
    }

    if (empty($data->ratingtime) or empty($data->assessed)) {
        $data->assesstimestart  = 0;
        $data->assesstimefinish = 0;
    }

    if (!empty($data->scale)) {
        $data->rating = $data->scale;
    } else {
        $data->rating = 0;
    }

    if ($CFG->datalynx_maxentries) {
        $data->maxentries = $CFG->datalynx_maxentries;
    }

    if (!$data->id = $DB->insert_record('datalynx', $data)) {
        return false;
    }

    datalynx_grade_item_update($data);
    return $data->id;
}

/**
 * updates an instance of a data
 *
 * @global object
 * @param object $data
 * @return bool
 */
function datalynx_update_instance($data) {
    global $DB;

    $data->id = $data->instance;

    $data->timemodified = time();

    if (empty($data->grade)) {
        $data->grade = 0;
        $data->grademethod = 0;
    }

    if (!empty($data->assessed)) {
        $data->grademethod = $data->assessed;
    } else {
        $data->rating = 0;
    }

    if (empty($data->ratingtime) or empty($data->assessed)) {
        $data->assesstimestart  = 0;
        $data->assesstimefinish = 0;
    }

    if (!empty($data->scale)) {
        $data->rating = $data->scale;
    } else {
        $data->rating = 0;
    }

    if (!$DB->update_record('datalynx', $data)) {
        return false;
    }

    datalynx_update_grades($data);

    return true;
}

/**
 * deletes an instance of a data
 *
 * @global object
 * @param int $id
 * @return bool
 */
function datalynx_delete_instance($id) {
    global $DB;

    if (!$data = $DB->get_record('datalynx', array('id' => $id))) {
        return false;
    }

    $cm = get_coursemodule_from_instance('datalynx', $data->id);
    $context = context_module::instance($cm->id);

    // Files.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_datalynx');

    // Get all the content in this datalynx.
    $sql = "SELECT e.id FROM {datalynx_entries} e WHERE e.dataid = ?";
    $DB->delete_records_select('datalynx_contents', "entryid IN ($sql)", array($id));

    // Delete fields views filters entries.
    $DB->delete_records('datalynx_fields', array('dataid' => $id));
    $DB->delete_records('datalynx_views', array('dataid' => $id));
    $DB->delete_records('datalynx_filters', array('dataid' => $id));
    $DB->delete_records('datalynx_entries', array('dataid' => $id));

    // Delete the instance itself.
    $result = $DB->delete_records('datalynx', array('id' => $id));

    // Cleanup gradebook.
    datalynx_grade_item_delete($data);

    return $result;
}

/**
 * Return a list of page types
 *
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 * @return array
 */
function datalynx_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array('mod-datalynx-*' => get_string('page-mod-datalynx-x', 'datalynx'));
    return $modulepagetype;
}

// RESET.

/**
 * prints the form elements that control
 * whether the course reset functionality affects the data.
 *
 * @param $mform form passed by reference
 */
function datalynx_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'datalynxheader', get_string('modulenameplural', 'datalynx'));
    $mform->addElement('checkbox', 'reset_datalynx_data', get_string('entriesdeleteall',
            'datalynx'));

    $mform->addElement('checkbox', 'reset_datalynx_notenrolled',
            get_string('deletenotenrolled', 'datalynx'));
    $mform->disabledIf('reset_datalynx_notenrolled', 'reset_datalynx_data', 'checked');

    $mform->addElement('checkbox', 'reset_datalynx_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_datalynx_ratings', 'reset_datalynx_data', 'checked');

    $mform->addElement('checkbox', 'reset_datalynx_comments', get_string('deleteallcomments'));
    $mform->disabledIf('reset_datalynx_comments', 'reset_datalynx_data', 'checked');
}

/**
 * Course reset form defaults.
 *
 * @return array
 */
function datalynx_reset_course_form_defaults($course) {
    return array('reset_datalynx_data' => 0, 'reset_datalynx_ratings' => 1,
            'reset_datalynx_comments' => 1, 'reset_datalynx_notenrolled' => 0
    );
}

/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional type
 */
function datalynx_reset_gradebook($courseid, $type = '') {
    global $DB;

    $sql = "SELECT d.*, cm.idnumber as cmidnumber, d.course as courseid
              FROM {datalynx} d, {course_modules} cm, {modules} m
             WHERE m.name='datalynx' AND m.id=cm.module AND cm.instance=d.id AND d.course=?";

    if ($datalynxs = $DB->get_records_sql($sql, array($courseid))) {
        foreach ($datalynxs as $datalynx) {
            datalynx_grade_item_update($datalynx, 'reset');
        }
    }
}

/**
 * Actual implementation of the rest coures functionality, delete all the
 * data responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function datalynx_reset_userdata($data) {
    global $CFG, $DB;

    require_once($CFG->libdir . '/filelib.php');
    require_once($CFG->dirroot . '/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'datalynx');
    $status = array();

    $allrecordssql = "SELECT e.id
                        FROM {datalynx_entries} e
                             INNER JOIN {datalynx} d ON e.dataid = d.id
                       WHERE d.course = ?";

    $alldatassql = "SELECT d.id
                      FROM {datalynx} d
                     WHERE d.course=?";

    $rm = new rating_manager();
    $ratingdeloptions = new stdClass();
    $ratingdeloptions->component = 'mod_datalynx';
    $ratingdeloptions->ratingarea = 'entry';

    // Delete entries if requested.
    if (!empty($data->reset_datalynx_data)) {
        $DB->delete_records_select('comments', "itemid IN ($allrecordssql) AND commentarea='entry'",
                array($data->courseid));
        $DB->delete_records_select('datalynx_contents', "entryid IN ($allrecordssql)",
                array($data->courseid));
        $DB->delete_records_select('datalynx_entries', "dataid IN ($alldatassql)",
                array($data->courseid));

        if ($datas = $DB->get_records_sql($alldatassql, array($data->courseid))) {
            foreach ($datas as $dataid => $unused) {
                fulldelete("$CFG->dataroot/$data->courseid/moddata/datalynx/$dataid");

                if (!$cm = get_coursemodule_from_instance('datalynx', $dataid)) {
                    continue;
                }
                $datacontext = context_module::instance($cm->id);

                $ratingdeloptions->contextid = $datacontext->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        if (empty($data->reset_gradebook_grades)) {
            // Remove all grades from gradebook.
            datalynx_reset_gradebook($data->courseid);
        }
        $status[] = array('component' => $componentstr,
                'item' => get_string('entriesdeleteall', 'datalynx'), 'error' => false);
    }

    // Remove entries by users not enrolled into course.
    if (!empty($data->reset_datalynx_notenrolled)) {
        $recordssql = "SELECT e.id, e.userid, e.dataid, u.id AS userexists, u.deleted AS userdeleted
                         FROM {datalynx_entries} e
                              INNER JOIN {datalynx} d ON e.dataid = d.id
                              LEFT OUTER JOIN {user} u ON e.userid = u.id
                        WHERE d.course = ? AND e.userid > 0";

        $coursecontext = context_course::instance($data->courseid);
        $notenrolled = array();
        $fields = array();
        $rs = $DB->get_recordset_sql($recordssql, array($data->courseid));
        foreach ($rs as $record) {
            if (array_key_exists($record->userid, $notenrolled) or !$record->userexists or
                    $record->userdeleted or !is_enrolled($coursecontext, $record->userid)
            ) {
                // Delete ratings.
                if (!$cm = get_coursemodule_from_instance('datalynx', $record->dataid)) {
                    continue;
                }
                $datacontext = context_module::instance($cm->id);
                $ratingdeloptions->contextid = $datacontext->id;
                $ratingdeloptions->itemid = $record->id;
                $rm->delete_ratings($ratingdeloptions);

                $DB->delete_records('comments', array('itemid' => $record->id, 'commentarea' => 'entry'));
                $DB->delete_records('datalynx_contents', array('entryid' => $record->id));
                $DB->delete_records('datalynx_entries', array('id' => $record->id));
                // HACK: this is ugly - the entryid should be before the fieldid!
                if (!array_key_exists($record->dataid, $fields)) {
                    if ($fs = $DB->get_records('datalynx_fields', array('dataid' => $record->dataid))) {
                        $fields[$record->dataid] = array_keys($fs);
                    } else {
                        $fields[$record->dataid] = array();
                    }
                }
                foreach ($fields[$record->dataid] as $fieldid) {
                    fulldelete(
                            "$CFG->dataroot/$data->courseid/moddata/datalynx/$record->dataid/$fieldid/$record->id");
                }
                $notenrolled[$record->userid] = true;
            }
            rs_close($rs);
            $status[] = array('component' => $componentstr,
                    'item' => get_string('deletenotenrolled', 'datalynx'), 'error' => false);
        }
    }

    // Remove all ratings.
    if (!empty($data->reset_datalynx_ratings)) {
        if ($datas = $DB->get_records_sql($alldatassql, array($data->courseid))) {
            foreach ($datas as $dataid => $unused) {
                if (!$cm = get_coursemodule_from_instance('datalynx', $dataid)) {
                    continue;
                }
                $datacontext = context_module::instance($cm->id);

                $ratingdeloptions->contextid = $datacontext->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        if (empty($data->reset_gradebook_grades)) {
            // Remove all grades from gradebook.
            datalynx_reset_gradebook($data->courseid);
        }

        $status[] = array('component' => $componentstr, 'item' => get_string('deleteallratings'),
                'error' => false);
    }

    // Remove all comments.
    if (!empty($data->reset_datalynx_comments)) {
        $DB->delete_records_select('comments', "itemid IN ($allrecordssql) AND commentarea='entry'",
                array($data->courseid));
        $status[] = array('component' => $componentstr, 'item' => get_string('deleteallcomments'),
                'error' => false);
    }

    // Updating dates - shift may be negative too.
    if ($data->timeshift) {
        shift_course_mod_dates('datalynx', array('timeavailable', 'timedue'), $data->timeshift, $data->courseid);
        $status[] = array('component' => $componentstr, 'item' => get_string('datechanged'),
                'error' => false);
    }

    return $status;
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function datalynx_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/rating:view',
            'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate',
            'moodle/comment:view', 'moodle/comment:post', 'moodle/comment:delete'
    );
}

/**
 * Lists all browsable file areas
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @return array
 */
function datalynx_get_file_areas($course, $cm, $context) {
    $areas = array('viewsection' => 'View template files', 'viewparam2' => 'Entry template files',
            'content' => 'Entry content files'
    );

    return $areas;
}

/**
 * File browsing support for datalynx module.
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param cm_info $cm
 * @param context $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info_stored file_info_stored instance or null if not found
 */
function datalynx_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid,
        $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    if (!isset($areas[$filearea])) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot . '/mod/datalynx/locallib.php');
        return new datalynx_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    if (!$view = $DB->get_record('datalynx_views', array('id' => $itemid))) {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_datalynx', $filearea, $itemid, $filepath,
            $filename))
    ) {
        return null;
    }

    $urlbase = $CFG->wwwroot . '/pluginfile.php';

    return new file_info_stored($browser, $context, $storedfile, $urlbase, s($view->name), true,
            true, false, false);
}

/**
 * Serves the datalynx attachments.
 * Implements needed access control ;-)
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function mod_datalynx_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB, $USER;

    // FIELD CONTENT files.
    if (($filearea === 'content' or $filearea === 'thumb') and
            $context->contextlevel == CONTEXT_MODULE
    ) {

        $contentid = (int) array_shift($args);

        if (!$content = $DB->get_record('datalynx_contents', array('id' => $contentid))) {
            return false;
        }

        if (!$field = $DB->get_record('datalynx_fields', array('id' => $content->fieldid))) {
            return false;
        }

        if (!$entry = $DB->get_record('datalynx_entries', array('id' => $content->entryid))) {
            return false;
        }

        if (!$datalynx = $DB->get_record('datalynx', array('id' => $field->dataid))) {
            return false;
        }

        if ($datalynx->id != $cm->instance) {
            // Hacker attempt - context does not match the contentid.
            return false;
        }

        // Check if approved.
        if ($datalynx->approval and !has_capability('mod/datalynx:approve', $context) and
                !$entry->approved and $USER->id != $entry->userid
        ) {
            return false;
        }

        // Group access.
        if ($entry->groupid) {
            $groupmode = groups_get_activity_groupmode($cm, $course);
            if ($groupmode == SEPARATEGROUPS and
                    !has_capability('moodle/site:accessallgroups', $context)
            ) {
                if (!groups_is_member($entry->groupid)) {
                    return false;
                }
            }
        }

        // Separate participants.
        $groupmode = isset($groupmode) ? $groupmode : groups_get_activity_groupmode($cm, $course);
        if ($groupmode == -1) {
            if (empty($USER->id)) {
                return false;
            }
            if ($USER->id != $entry->userid and
                    !has_capability('mod/datalynx:manageentries', $context)
            ) {
                return false;
            }
        }

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_datalynx/$filearea/$contentid/$relativepath";
        $oldpath = "/$context->id/mod_dataform/$filearea/$contentid/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            if (!$file = $fs->get_file_by_hash(sha1($oldpath)) or $file->is_directory()) {
                return false;
            }
        }

        // Finally send the file.
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    // VIEW TEMPLATE files.
    if (strpos($filearea, 'view') !== false and $context->contextlevel == CONTEXT_MODULE) {
        require_course_login($course, true, $cm);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_datalynx/$filearea/$relativepath";
        $oldpath = "/$context->id/mod_dataform/$filearea/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            if (!$file = $fs->get_file_by_hash(sha1($oldpath)) or $file->is_directory()) {
                return false;
            }
        }

        // Finally send the file.
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    // PDF VIEW files.
    $viewpdfareas = array('view_pdfframe', 'view_pdfwmark', 'view_pdfcert');
    if (in_array($filearea, $viewpdfareas) and $context->contextlevel == CONTEXT_MODULE) {
        require_course_login($course, true, $cm);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_datalynx/$filearea/$relativepath";
        $oldpath = "/$context->id/mod_dataform/$filearea/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            if (!$file = $fs->get_file_by_hash(sha1($oldpath)) or $file->is_directory()) {
                return false;
            }
        }

        // Finally send the file.
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    // PRESET files.
    if (($filearea === 'course_presets' or $filearea === 'site_presets')) {
        require_course_login($course, true, $cm);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_datalynx/$filearea/$relativepath";
        $oldpath = "/$context->id/mod_dataform/$filearea/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            if (!$file = $fs->get_file_by_hash(sha1($oldpath)) or $file->is_directory()) {
                return false;
            }
        }

        // Finally send the file.
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    if (($filearea === 'js' or $filearea === 'css')) {
        require_course_login($course, true, $cm);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_datalynx/$filearea/$relativepath";
        $oldpath = "/$context->id/mod_dataform/$filearea/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            if (!$file = $fs->get_file_by_hash(sha1($oldpath)) or $file->is_directory()) {
                return false;
            }
        }

        // Finally send the file.
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    if (strpos($filearea, 'actor-') === 0 and $context->contextlevel == CONTEXT_MODULE) {
        require_course_login($course, true, $cm);

        $itemid = (int) array_shift($args);

        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_datalynx/$filearea/$itemid/$relativepath";
        $oldpath = "/$context->id/mod_dataform/$filearea/$itemid/$relativepath";

        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            if (!$file = $fs->get_file_by_hash(sha1($oldpath)) or $file->is_directory()) {
                return false;
            }
        }

        // Finally send the file.
        send_stored_file($file, 0, 0, true); // Download MUST be forced - security!
    }

    return false;
}

/**
 */
function datalynx_extend_navigation($navigation, $course, $module, $cm) {
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $datanode The node to add module settings to
 */
function datalynx_extend_settings_navigation(settings_navigation $settings, navigation_node $dfnode) {
    global $PAGE, $USER;

    $templatesmanager = has_capability('mod/datalynx:managetemplates', $PAGE->cm->context);
    $entriesmanager = has_capability('mod/datalynx:manageentries', $PAGE->cm->context);

    // Delete.
    if ($templatesmanager) {
        $dfnode->add(get_string('delete'),
                new moodle_url('/course/mod.php',
                        array('delete' => $PAGE->cm->id, 'sesskey' => sesskey()
                        )));
    }

    // Index.
    if (has_capability('mod/datalynx:viewindex', $PAGE->cm->context)) {
        $dfnode->add(get_string('index', 'datalynx'),
                new moodle_url('/mod/datalynx/index.php', array('id' => $PAGE->course->id)));
    }

    // Notifications.
    if (isloggedin() and !isguestuser()) {
        $dfnode->add(get_string('messages', 'message'),
                new moodle_url('/message/edit.php',
                        array('id' => $USER->id, 'course' => $PAGE->course->id,
                                'context' => $PAGE->context->id)));
    }

    // Manage.
    if ($templatesmanager or $entriesmanager) {
        $manage = $dfnode->add(get_string('manage', 'datalynx'));
        if ($templatesmanager) {
            $manage->add(get_string('views', 'datalynx'),
                    new moodle_url('/mod/datalynx/view/index.php', array('id' => $PAGE->cm->id)));
            $fields = $manage->add(get_string('fields', 'datalynx'),
                    new moodle_url('/mod/datalynx/field/index.php', array('id' => $PAGE->cm->id)));
            $manage->add(get_string('filters', 'datalynx'),
                    new moodle_url('/mod/datalynx/filter/index.php', array('id' => $PAGE->cm->id)));
            $manage->add(get_string('customfilters', 'datalynx'),
                    new moodle_url('/mod/datalynx/customfilter/index.php', array('id' => $PAGE->cm->id)));
            $manage->add(get_string('rules', 'datalynx'),
                    new moodle_url('/mod/datalynx/rule/index.php', array('id' => $PAGE->cm->id)));
            $manage->add(get_string('tools', 'datalynx'),
                    new moodle_url('/mod/datalynx/tool/index.php', array('id' => $PAGE->cm->id)));
            $manage->add(get_string('jsinclude', 'datalynx'),
                    new moodle_url('/mod/datalynx/js.php',
                            array('id' => $PAGE->cm->id, 'jsedit' => 1)));
            $manage->add(get_string('cssinclude', 'datalynx'),
                    new moodle_url('/mod/datalynx/css.php',
                            array('id' => $PAGE->cm->id, 'cssedit' => 1)));
            $manage->add(get_string('presets', 'datalynx'),
                    new moodle_url('/mod/datalynx/preset/index.php', array('id' => $PAGE->cm->id)));
            $manage->add(get_string('statistics', 'datalynx'),
                    new moodle_url('/mod/datalynx/statistics/index.php',
                            array('id' => $PAGE->cm->id)));
            $fields->add(get_string('behaviors', 'datalynx'),
                    new moodle_url('/mod/datalynx/behavior/index.php', array('id' => $PAGE->cm->id)));
            $fields->add(get_string('renderers', 'datalynx'),
                    new moodle_url('/mod/datalynx/renderer/index.php', array('id' => $PAGE->cm->id)));
        }
        $manage->add(get_string('import', 'datalynx'),
                new moodle_url('/mod/datalynx/import.php', array('id' => $PAGE->cm->id)));
    }
}

// Info.

/**
 * returns a list of participants of this datalynx
 */
function datalynx_get_participants($dataid) {
    global $DB;

    $params = array('dataid' => $dataid);

    $sql = "SELECT DISTINCT u.id
              FROM {user} u,
                   {datalynx_entries} e
             WHERE e.dataid = :dataid AND
                   u.id = e.userid";
    $entries = $DB->get_records_sql($sql, $params);

    $sql = "SELECT DISTINCT u.id
              FROM {user} u,
                   {datalynx_entries} e,
                   {comments} c
             WHERE e.dataid = ? AND
                   u.id = e.userid AND
                   e.id = c.itemid AND
                   c.commentarea = 'entry'";
    $comments = $DB->get_records_sql($sql, $params);

    $sql = "SELECT DISTINCT u.id
              FROM {user} u,
                   {datalynx_entries} e,
                   {ratings} r
             WHERE e.dataid = ? AND
                   u.id = e.userid AND
                   e.id = r.itemid AND
                   r.component = 'mod_datalynx' AND
                   (r.ratingarea = 'entry' OR
                   r.ratingarea = 'activity')";
    $ratings = $DB->get_records_sql($sql, $params);

    $participants = array();

    if ($entries) {
        foreach ($entries as $entry) {
            $participants[$entry->id] = $entry;
        }
    }
    if ($comments) {
        foreach ($comments as $comment) {
            $participants[$comment->id] = $comment;
        }
    }
    if ($ratings) {
        foreach ($ratings as $rating) {
            $participants[$rating->id] = $rating;
        }
    }
    return $participants;
}

/**
 * returns a summary of datalynx activity of this user
 */
function datalynx_user_outline($course, $user, $mod, $data) {
    global $DB, $CFG;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'datalynx', $data->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $sqlparams = array('dataid' => $data->id, 'userid' => $user->id
    );
    if ($countrecords = $DB->count_records('datalynx_entries', $sqlparams)) {
        $result = new stdClass();
        $result->info = get_string('entriescount', 'datalynx', $countrecords);
        $lastrecordset = $DB->get_records('datalynx_entries', $sqlparams, 'timemodified DESC',
                'id,timemodified', 0, 1);
        $lastrecord = reset($lastrecordset);
        $result->time = $lastrecord->timemodified;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else {
        if ($grade) {
            $result = new stdClass();
            $result->info = get_string('grade') . ': ' . $grade->str_long_grade;
            $result->time = $grade->dategraded;
            return $result;
        }
    }
    return null;
}

/**
 * TODO Prints all the records uploaded by this user
 */
function datalynx_user_complete($course, $user, $mod, $data) {
    global $DB, $CFG;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'datalynx', $data->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo '<p>' . get_string('grade') . ': ' . $grade->str_long_grade . '</p>';
        if ($grade->str_feedback) {
            echo '<p>' . get_string('feedback') . ': ' . $grade->str_feedback . '</p>';
        }
    }
    $sqlparams = array('dataid' => $data->id, 'userid' => $user->id);
    if ($countrecords = $DB->count_records('datalynx_entries', $sqlparams)) {
        // TODO get the default view add a filter for user only and display.
        $x = 1;
    }
}

// Participantion Reports.

/**
 */
function datalynx_get_view_actions() {
    return array('view');
}

/**
 */
function datalynx_get_post_actions() {
    return array('add', 'update', 'record delete');
}

// COMMENTS.

/**
 * Running addtional permission check on plugin, for example, plugins
 * may have switch to turn on/off comments option, this callback will
 * affect UI display, not like pluginname_comment_validate only throw
 * exceptions.
 * Capability check has been done in comment->check_permissions(), we
 * don't need to do it again here.
 *
 * @param stdClass $commentparam {
 *        context => context the context object
 *        courseid => int course id
 *        cm => stdClass course module object
 *        commentarea => string comment area
 *        itemid => int itemid
 *        }
 * @return array
 */
function datalynx_comment_permissions($commentparam) {
    global $CFG;

    return array('post' => true, 'view' => true);
}

/**
 * Validate comment parameter before perform other comments actions
 *
 * @param stdClass $commentparam {
 *        context => context the context object
 *        courseid => int course id
 *        cm => stdClass course module object
 *        commentarea => string comment area
 *        itemid => int itemid
 *        }
 * @return boolean
 */
function datalynx_comment_validate($commentparam) {
    global $CFG;

    require_once("field/_comment/field_class.php");
    $comment = new datalynxfield__comment($commentparam->cm->instance);
    return $comment->validation($commentparam);
}

/**
 */
function datalynx_comment_add($newcomment, $commentparam) {
    $df = new mod_datalynx\datalynx($commentparam->cm->instance);
    $eventdata = (object) array('items' => $newcomment);
}

// Grading.

/**
 * Return rating related permissions
 *
 * @param string $contextid the context id
 * @param string $component the component to get rating permissions for
 * @param string $ratingarea the rating area to get permissions for
 * @return array * @param bool $type Type of comparison (or/and; can be used as return value if no
 *         conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *         value depends on comparison tyay an associative array of the user's rating permissions
 */
function datalynx_rating_permissions($contextid, $component, $ratingarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component == 'mod_datalynx' and ($ratingarea == 'entry' or $ratingarea == 'activity')) {
        return array('view' => has_capability('mod/datalynx:ratingsview', $context),
                'viewany' => has_capability('mod/datalynx:ratingsviewany', $context),
                'viewall' => has_capability('mod/datalynx:ratingsviewall', $context),
                'rate' => has_capability('mod/datalynx:rate', $context));
    }
    return null;
}

/**
 *
 * @param $params
 * @return bool
 * @throws coding_exception
 * @throws rating_exception
 */
function datalynx_rating_validate($params) {
    global $DB, $USER;

    require_once(dirname(__FILE__) . "/classes/datalynx.php");

    $df = new mod_datalynx\datalynx(null, $params['context']->instanceid);

    // Check the component is mod_datalynx.
    if ($params['component'] != 'mod_datalynx') {
        throw new rating_exception('invalidcomponent');
    }

    // You can't rate your own entries unless you can manage ratings.
    if (!has_capability('mod/datalynx:manageratings', $params['context']) and
            $params['rateduserid'] == $USER->id
    ) {
        throw new rating_exception('nopermissiontorate');
    }

    // If the supplied context doesnt match the item's context.
    if ($params['context']->id != $df->context->id) {
        throw new rating_exception('invalidcontext');
    }

    // Check the ratingarea is entry or activity.
    if ($params['ratingarea'] != 'entry' and $params['ratingarea'] != 'activity') {
        throw new rating_exception('invalidratingarea');
    }

    $data = $df->data;

    // Vaildate activity scale and rating range.
    if ($params['ratingarea'] == 'activity') {
        if ($params['scaleid'] != $data->grade) {
            throw new rating_exception('invalidscaleid');
        }

        // Upper limit.
        if ($data->grade < 0) {
            // Its a custom scale.
            $scalerecord = $DB->get_record('scale', array('id' => -$data->grade
            ));
            if ($scalerecord) {
                $scalearray = explode(',', $scalerecord->scale);
                if ($params['rating'] > count($scalearray)) {
                    throw new rating_exception('invalidnum');
                }
            } else {
                throw new rating_exception('invalidscaleid');
            }
        } else {
            if ($params['rating'] > $data->grade) {
                // If its numeric and submitted rating is above maximum.
                throw new rating_exception('invalidnum');
            }
        }
    }

    // Vaildate entry scale and rating range.
    if ($params['ratingarea'] == 'entry') {
        if ($params['scaleid'] != $data->rating) {
            throw new rating_exception('invalidscaleid');
        }

        // Upper limit.
        if ($data->rating < 0) {
            // Its a custom scale.
            $scalerecord = $DB->get_record('scale', array('id' => -$data->rating));
            if ($scalerecord) {
                $scalearray = explode(',', $scalerecord->scale);
                if ($params['rating'] > count($scalearray)) {
                    throw new rating_exception('invalidnum');
                }
            } else {
                throw new rating_exception('invalidscaleid');
            }
        } else {
            if ($params['rating'] > $data->rating) {
                // If its numeric and submitted rating is above maximum.
                throw new rating_exception('invalidnum');
            }
        }
    }

    // Lower limit.
    if ($params['rating'] < 0 and $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    $entry = $DB->get_record('datalynx_entries', array('id' => $params['itemid']), '*', MUST_EXIST);

    // Check the item we are rating was created in the assessable time window.
    if (!empty($data->assesstimestart) && !empty($data->assesstimefinish)) {
        if ($entry->timecreated < $data->assesstimestart ||
                 $entry->timecreated > $data->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    // Make sure groups allow this user to see the item they're rating.
    $groupid = $df->currentgroup;
    if ($groupid > 0 and $groupmode = groups_get_activity_groupmode($df->cm, $df->course)) {
        // Groups are being used.
        if (!groups_group_exists($groupid)) {
            // Can't find group.
            throw new rating_exception('cannotfindgroup'); // Something is wrong.
        }

        if (!groups_is_member($groupid) and
                !has_capability('moodle/site:accessallgroups', $df->context)
        ) {
            // Do not allow rating of posts from other groups when in SEPARATEGROUPS or.
            // VISIBLEGROUPS.
            throw new rating_exception('notmemberofgroup');
        }
    }

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param $data
 * @param int $userid
 * @return array array of grades, false if none
 * @throws coding_exception
 */
function datalynx_get_user_grades($data, $userid = 0) {
    global $CFG;

    require_once("$CFG->dirroot/rating/lib.php");

    $options = new stdClass();
    $options->component = 'mod_datalynx';
    $options->ratingarea = 'entry';

    // This is ripped off directly from the datalynx activity.
    $options->modulename = 'datalynx';
    $options->moduleid = $data->id;
    $options->userid = $userid;
    $options->aggregationmethod = $data->assessed;
    $options->scaleid = $data->rating;
    $options->itemtable = 'datalynx_entries';
    $options->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($options);
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $data null means all databases
 * @param int $userid specific user only, 0 mean all
 * @param bool $nullifnone
 */
function datalynx_update_grades($data = null, $userid = 0, $nullifnone = true) {
    global $CFG, $DB;
    require_once("$CFG->libdir/gradelib.php");

    if (!$data->assessed) {
        datalynx_grade_item_update($data);
    } else {
        if ($grades = datalynx_get_user_grades($data, $userid)) {
            datalynx_grade_item_update($data, $grades);
        } else {
            if ($userid and $nullifnone) {
                $grade = new stdClass();
                $grade->userid = $userid;
                $grade->rawgrade = null;
                datalynx_grade_item_update($data, $grade);
            } else {
                datalynx_grade_item_update($data);
            }
        }
    }
}

/**
 * Update all grades in gradebook.
 *
 * @global object
 */
function datalynx_upgrade_grades() {
    global $DB;

    $sql = "SELECT COUNT('x')
              FROM {datalynx} d, {course_modules} cm, {modules} m
             WHERE m.name='datalynx' AND m.id=cm.module AND cm.instance=d.id";
    $count = $DB->count_records_sql($sql);

    $sql = "SELECT d.*, cm.idnumber AS cmidnumber, d.course AS courseid
              FROM {datalynx} d, {course_modules} cm, {modules} m
             WHERE m.name='datalynx' AND m.id=cm.module AND cm.instance=d.id";
    $rs = $DB->get_recordset_sql($sql);
    if ($rs->valid()) {
        $pbar = new progress_bar('dataupgradegrades', 500, true);
        $i = 0;
        foreach ($rs as $data) {
            $i++;
            upgrade_set_timeout(60 * 5); // Set up timeout, may also abort execution.
            datalynx_update_grades($data, 0, false);
            $pbar->update($i, $count, "Updating Datalynx grades ($i/$count).");
        }
    }
    $rs->close();
}

/**
 * Update/create grade item for given datalynx
 *
 * @param object $data object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return object grade_item
 */
function datalynx_grade_item_update($data, $grades = null) {
    global $CFG;
    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir . '/gradelib.php');
    }

    $params = array('itemname' => $data->name, 'idnumber' => $data->cmidnumber);

    if (!$data->rating) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else {
        if ($data->rating > 0) {
            $params['gradetype'] = GRADE_TYPE_VALUE;
            $params['grademax'] = $data->rating;
            $params['grademin'] = 0;
        } else {
            if ($data->rating < 0) {
                $params['gradetype'] = GRADE_TYPE_SCALE;
                $params['scaleid'] = -$data->grade;
            }
        }
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/datalynx', $data->course, 'mod', 'datalynx', $data->id, 0, $grades,
            $params);
}

/**
 * Delete grade item for given data
 *
 * @param object $data object
 * @return object grade_item
 */
function datalynx_grade_item_delete($data) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");

    return grade_update('mod/datalynx', $data->course, 'mod', 'datalynx', $data->id, 0, null,
            array('deleted' => 1));
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in datalynx settings.
 *
 * @param $course
 * @param $cm
 * @param $userid
 * @param $type
 * @return bool
 * @throws Exception
 */
function datalynx_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    if (!($datalynx = $DB->get_record('datalynx', array('id' => $cm->instance)))) {
        throw new Exception("Can't find datalynx {$cm->instance}");
    }

    if (!isset($datalynx->completionentries)) {
        throw new Exception(
                "'completionentries' field does not exist in 'datalynx' table! Upgrade your database!");
    }

    $params = array('userid' => $userid, 'dataid' => $datalynx->id);
    if ($datalynx->approval) {
        $sql = "SELECT COUNT(1)
              FROM {datalynx_entries} de
             WHERE de.userid = :userid
               AND de.dataid = :dataid
               AND de.approved = 1";
    } else {
        $sql = "SELECT COUNT(1)
              FROM {datalynx_entries} de
             WHERE de.userid = :userid
               AND de.dataid = :dataid";
    }
    $count = $DB->get_field_sql($sql, $params);

    return $count >= $datalynx->completionentries;
}

/**
 * This function returns if a scale is being used by one datalyxx
 *
 * @global object
 * @param int $dataid
 * @param int $scaleid negative number
 * @return bool
 */
function datalynx_scale_used($dataid, $scaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record('datalynx', array('id' => "$dataid", 'rating' => "-$scaleid"));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of datalyxx
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any datalynx
 */
function datalynx_scale_used_anywhere($scaleid) {
    global $DB;
    return ($scaleid and $DB->record_exists('datalynx', array('rating' => "-$scaleid")));
}

/**
 * Returns datalynx pages tagged with a specified tag.
 *
 * This is a callback used by the tag area mod_datalynx/datalynx_contents to search for datalynx entries
 * tagged with a specific tag.
 *
 * @param core_tag_tag $tag
 * @param bool $exclusivemode if set to true it means that no other entities tagged with this tag
 *             are displayed on the page and the per-page limit may be bigger
 * @param int $fromctx context id where the link was displayed, may be used by callbacks
 *            to display items in the same context first
 * @param int $ctx context id where to search for records
 * @param bool $rec search in subcontexts as well
 * @param int $page 0-based number of page being displayed
 * @return \core_tag\output\tagindex
 */
function mod_datalynx_get_tagged_entries($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = 1, $page = 0) {
    global $OUTPUT, $DB, $USER;
    $perpage = $exclusivemode ? 20 : 5;

    // Build the SQL query.
    $ctxselect = context_helper::get_preload_record_columns_sql('ctx');
    $query = "SELECT dc.entryid  AS eid, de.dataid, de.userid, de.groupid, de.approved,
                    cm.id AS cmid, c.id AS courseid, c.shortname, c.fullname, $ctxselect
                FROM {datalynx_contents} dc
                JOIN {datalynx_entries} de ON de.id = dc.entryid
                JOIN {datalynx} d ON d.id = de.dataid
                JOIN {modules} m ON m.name='datalynx'
                JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = d.id
                JOIN {tag_instance} tt ON dc.id = tt.itemid
                JOIN {course} c ON cm.course = c.id
                JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :coursemodulecontextlevel
               WHERE tt.itemtype = :itemtype AND tt.tagid = :tagid AND tt.component = :component
                 AND de.id %ITEMFILTER% AND c.id %COURSEFILTER%";

    $params = array('itemtype' => 'datalynx_contents', 'tagid' => $tag->id, 'component' => 'mod_datalynx',
            'coursemodulecontextlevel' => CONTEXT_MODULE);

    if ($ctx) {
        $context = $ctx ? context::instance_by_id($ctx) : context_system::instance();
        $query .= $rec ? ' AND (ctx.id = :contextid OR ctx.path LIKE :path)' : ' AND ctx.id = :contextid';
        $params['contextid'] = $context->id;
        $params['path'] = $context->path . '/%';
    }

    $query .= " ORDER BY ";
    if ($fromctx) {
        // In order-clause specify that modules from inside "fromctx" context should be returned first.
        $fromcontext = context::instance_by_id($fromctx);
        $query .= ' (CASE WHEN ctx.id = :fromcontextid OR ctx.path LIKE :frompath THEN 0 ELSE 1 END),';
        $params['fromcontextid'] = $fromcontext->id;
        $params['frompath'] = $fromcontext->path . '/%';
    }
    $query .= ' c.sortorder, cm.id, eid';

    $totalpages = $page + 1;

    // Use core_tag_index_builder to build and filter the list of items.
    $builder = new core_tag_index_builder('mod_datalynx', 'datalynx_contents', $query, $params, $page * $perpage, $perpage + 1);
    while ($item = $builder->has_item_that_needs_access_check()) {
        context_helper::preload_from_record($item);
        $courseid = $item->courseid;
        if (!$builder->can_access_course($courseid)) {
            $builder->set_accessible($item, false);
            continue;
        }
        $modinfo = get_fast_modinfo($builder->get_course($courseid));
        // Set accessibility of this item and all other items in the same course.
        // FIXME: Check not testet and probably not working for all of the options.
        // Solution is to use datalynx instance to check accessibility.
        // But not a problem if no information is displayed. Only link to datalynx entry is shown.
        $builder->walk(
                function($taggeditem) use ($courseid, $modinfo, $builder) {
                    if ($taggeditem->courseid == $courseid) {
                        $accessible = false;
                        if (($cm = $modinfo->get_cm($taggeditem->cmid)) && $cm->uservisible) {
                            $datalynx = (object) array('id' => $taggeditem->datalynxid,
                                    'course' => $cm->course
                            );
                            $datalynx = new mod_datalynx\datalynx($taggeditem->datalynxid);

                            if (!$datalynx->user_can_view_all_entries()) {
                                if ($taggeditem->userid == $USER->id) {
                                    $accessible = true;
                                }
                                if ($datalynx->data->approval and
                                        !has_capability('mod/datalynx:manageentries', $datalynx->context)
                                ) {
                                    if (isloggedin() AND $taggeditem->approved == 1) {
                                        $accessible = true;
                                    }
                                }
                            } else {
                                $accessible = true;
                            }
                        }
                        $builder->set_accessible($taggeditem, $accessible);
                    }
                });
    }

    $items = $builder->get_items();
    if (count($items) > $perpage) {
        $totalpages = $page + 2; // We don't need exact page count, just indicate that the next page exists.
        array_pop($items);
    }

    // Build the display contents.
    if ($items) {
        $tagfeed = new core_tag\output\tagfeed();
        foreach ($items as $item) {
            context_helper::preload_from_record($item);
            $modinfo = get_fast_modinfo($item->courseid);
            $cm = $modinfo->get_cm($item->cmid);
            $pageurl = new moodle_url('/mod/datalynx/view.php', array('id' => $item->cmid, 'eids' => $item->eid));
            $pagename = format_string(get_string('linktoentry', 'mod_datalynx'), true,
                    array('context' => context_module::instance($item->cmid)));
            $pagename = html_writer::link($pageurl, $pagename);
            $courseurl = course_get_url($item->courseid, $cm->sectionnum);
            $cmname = html_writer::link($cm->url, $cm->get_formatted_name());
            $coursename = format_string($item->fullname, true, array('context' => context_course::instance($item->courseid)));
            $coursename = html_writer::link($courseurl, $coursename);
            $icon = html_writer::link($pageurl, html_writer::empty_tag('img', array('src' => $cm->get_icon_url())));
            $tagfeed->add($icon, $pagename, $cmname . '<br>' . $coursename);
        }

        $content = $OUTPUT->render_from_template('core_tag/tagfeed',
                $tagfeed->export_for_template($OUTPUT));

        return new core_tag\output\tagindex($tag, 'mod_datalynx', 'datalynx_contents', $content,
                $exclusivemode, $fromctx, $ctx, $rec, $page, $totalpages);
    }
}
