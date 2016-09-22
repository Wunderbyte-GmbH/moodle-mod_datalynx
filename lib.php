<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package mod
 * @subpackage datalynx
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *         
 *          The Datalynx has been developed as an enhanced counterpart
 *          of Moodle's Database activity module (1.9.11+ (20110323)).
 *          To the extent that Datalynx code corresponds to Database code,
 *          certain copyrights on the Database module may obtain.
 */

/**
 * MOD FUNCTIONS WHICH ARE CALLED FROM OUTSIDE THE MODULE
 */
defined('MOODLE_INTERNAL') or die();

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
    
    // files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_datalynx');
    
    // get all the content in this datalynx
    $sql = "SELECT e.id FROM {datalynx_entries} e WHERE e.dataid = ?";
    $DB->delete_records_select('datalynx_contents', "entryid IN ($sql)", array($id));
    
    // delete fields views filters entries
    $DB->delete_records('datalynx_fields', array('dataid' => $id));
    $DB->delete_records('datalynx_views', array('dataid' => $id));
    $DB->delete_records('datalynx_filters', array('dataid' => $id));
    $DB->delete_records('datalynx_entries', array('dataid' => $id));
    
    // Delete the instance itself
    $result = $DB->delete_records('datalynx', array('id' => $id));
    
    // cleanup gradebook
    datalynx_grade_item_delete($data);
    
    return $result;
}

/**
 * Return a list of page types
 *
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function datalynx_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-datalynx-*' => get_string('page-mod-datalynx-x', 'datalynx'));
    return $module_pagetype;
}

// ------------------------------------------------------------
// RESET
// ------------------------------------------------------------

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
    
    require_once ($CFG->libdir . '/filelib.php');
    require_once ($CFG->dirroot . '/rating/lib.php');
    
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
    
    // delete entries if requested
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
            // remove all grades from gradebook
            datalynx_reset_gradebook($data->courseid);
        }
        $status[] = array('component' => $componentstr, 
            'item' => get_string('entriesdeleteall', 'datalynx'), 'error' => false);
    }
    
    // remove entries by users not enrolled into course
    if (!empty($data->reset_datalynx_notenrolled)) {
        $recordssql = "SELECT e.id, e.userid, e.dataid, u.id AS userexists, u.deleted AS userdeleted
                         FROM {datalynx_entries} e
                              INNER JOIN {datalynx} d ON e.dataid = d.id
                              LEFT OUTER JOIN {user} u ON e.userid = u.id
                        WHERE d.course = ? AND e.userid > 0";
        
        $course_context = context_course::instance($data->courseid);
        $notenrolled = array();
        $fields = array();
        $rs = $DB->get_recordset_sql($recordssql, array($data->courseid));
        foreach ($rs as $record) {
            if (array_key_exists($record->userid, $notenrolled) or !$record->userexists or
                     $record->userdeleted or !is_enrolled($course_context, $record->userid)) {
                // delete ratings
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
    
    // remove all ratings
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
            // remove all grades from gradebook
            datalynx_reset_gradebook($data->courseid);
        }
        
        $status[] = array('component' => $componentstr, 'item' => get_string('deleteallratings'), 
            'error' => false);
    }
    
    // remove all comments
    if (!empty($data->reset_datalynx_comments)) {
        $DB->delete_records_select('comments', "itemid IN ($allrecordssql) AND commentarea='entry'", 
                array($data->courseid));
        $status[] = array('component' => $componentstr, 'item' => get_string('deleteallcomments'), 
            'error' => false);
    }
    
    // updating dates - shift may be negative too
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
        require_once ($CFG->dirroot . '/mod/datalynx/locallib.php');
        return new datalynx_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }
    
    if (!$view = $DB->get_record('datalynx_views', array('id' => $itemid))) {
        return null;
    }
    
    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_datalynx', $filearea, $itemid, $filepath, 
            $filename))) {
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
    
    // FIELD CONTENT files
    if (($filearea === 'content' or $filearea === 'thumb') and
             $context->contextlevel == CONTEXT_MODULE) {
        
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
            // hacker attempt - context does not match the contentid
            return false;
        }
        
        // check if approved
        if ($datalynx->approval and !has_capability('mod/datalynx:approve', $context) and
                 !$entry->approved and $USER->id != $entry->userid) {
            return false;
        }
        
        // group access
        if ($entry->groupid) {
            $groupmode = groups_get_activity_groupmode($cm, $course);
            if ($groupmode == SEPARATEGROUPS and
                     !has_capability('moodle/site:accessallgroups', $context)) {
                if (!groups_is_member($entry->groupid)) {
                    return false;
                }
            }
        }
        
        // Separate participants
        $groupmode = isset($groupmode) ? $groupmode : groups_get_activity_groupmode($cm, $course);
        if ($groupmode == -1) {
            if (empty($USER->id)) {
                return false;
            }
            if ($USER->id != $entry->userid and
                     !has_capability('mod/datalynx:manageentries', $context)) {
                return false;
            }
        }
        
        // TODO
        // require_once("field/$field->type/field_class.php");
        // $fieldclass = "datalynxfield_$field->type";
        // if (!$fieldclass::file_ok($relativepath)) {
        // return false;
        // }
        
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_datalynx/$filearea/$contentid/$relativepath";
        $oldpath = "/$context->id/mod_dataform/$filearea/$contentid/$relativepath";
        
        $fs = get_file_storage();
        if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
            if (!$file = $fs->get_file_by_hash(sha1($oldpath)) or $file->is_directory()) {
                return false;
            }
        }
        
        // finally send the file
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    }
    
    // VIEW TEMPLATE files
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
        
        // finally send the file
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    }
    
    // PDF VIEW files
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
        
        // finally send the file
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
    }
    
    // PRESET files
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
        
        // finally send the file
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
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
        
        // finally send the file
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
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
        
        // finally send the file
        send_stored_file($file, 0, 0, true); // download MUST be forced - security!
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
    
    // delete
    if ($templatesmanager) {
        $dfnode->add(get_string('delete'), 
                new moodle_url('/course/mod.php', 
                        array('delete' => $PAGE->cm->id, 'sesskey' => sesskey()
                        )));
    }
    
    // index
    $dfnode->add(get_string('index', 'datalynx'), 
            new moodle_url('/mod/datalynx/index.php', array('id' => $PAGE->course->id)));
    
    // notifications
    if (isloggedin() and !isguestuser()) {
        $dfnode->add(get_string('messaging', 'message'), 
                new moodle_url('/message/edit.php', 
                        array('id' => $USER->id, 'course' => $PAGE->course->id, 
                            'context' => $PAGE->context->id)));
    }
    
    // manage
    if ($templatesmanager or $entriesmanager) {
        $manage = $dfnode->add(get_string('manage', 'datalynx'));
        if ($templatesmanager) {
            $manage->add(get_string('views', 'datalynx'), 
                    new moodle_url('/mod/datalynx/view/index.php', array('id' => $PAGE->cm->id)));
            $fields = $manage->add(get_string('fields', 'datalynx'), 
                    new moodle_url('/mod/datalynx/field/index.php', array('id' => $PAGE->cm->id)));
            $manage->add(get_string('filters', 'datalynx'), 
                    new moodle_url('/mod/datalynx/filter/index.php', array('id' => $PAGE->cm->id)));
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

// ------------------------------------------------------------
// Info
// ------------------------------------------------------------

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
    require_once ("$CFG->libdir/gradelib.php");
    
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
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;
        $result->time = $grade->dategraded;
        return $result;
    }
    return NULL;
}

/**
 * TODO Prints all the records uploaded by this user
 */
function datalynx_user_complete($course, $user, $mod, $data) {
    global $DB, $CFG;
    require_once ("$CFG->libdir/gradelib.php");
    
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
        // TODO get the default view add a filter for user only and display
    }
}

// ------------------------------------------------------------
// Participantion Reports
// ------------------------------------------------------------

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

// ------------------------------------------------------------
// COMMENTS
// ------------------------------------------------------------

/**
 * Running addtional permission check on plugin, for example, plugins
 * may have switch to turn on/off comments option, this callback will
 * affect UI display, not like pluginname_comment_validate only throw
 * exceptions.
 * Capability check has been done in comment->check_permissions(), we
 * don't need to do it again here.
 *
 * @param stdClass $comment_param {
 *        context => context the context object
 *        courseid => int course id
 *        cm => stdClass course module object
 *        commentarea => string comment area
 *        itemid => int itemid
 *        }
 * @return array
 */
function datalynx_comment_permissions($comment_param) {
    global $CFG;
    
    // require_once("$CFG->field/_comment/field_class.php");
    // $comment = new datalynxfield__comment($comment_param->cm->instance);
    // return $comment->permissions($comment_param);
    return array('post' => true, 'view' => true);
}

/**
 * Validate comment parameter before perform other comments actions
 *
 * @param stdClass $comment_param {
 *        context => context the context object
 *        courseid => int course id
 *        cm => stdClass course module object
 *        commentarea => string comment area
 *        itemid => int itemid
 *        }
 * @return boolean
 */
function datalynx_comment_validate($comment_param) {
    global $CFG;
    
    require_once ("field/_comment/field_class.php");
    $comment = new datalynxfield__comment($comment_param->cm->instance);
    return $comment->validation($comment_param);
}

/**
 */
function datalynx_comment_add($newcomment, $comment_param) {
    $df = new datalynx($comment_param->cm->instance);
    $eventdata = (object) array('items' => $newcomment);
    // $df->events_trigger("commentadded", $eventdata); FIXME: this should behave differently!
}

// ------------------------------------------------------------
// Grading
// ------------------------------------------------------------

/**
 * Return rating related permissions
 *
 * @param string $contextid the context id
 * @param string $component the component to get rating permissions for
 * @param string $ratingarea the rating area to get permissions for
 * @return arr * @param bool $type Type of comparison (or/and; can be used as return value if no
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
    
    require_once (dirname(__FILE__) . "/mod_class.php");
    
    $df = new datalynx(null, $params['context']->instanceid);
    
    // Check the component is mod_datalynx
    if ($params['component'] != 'mod_datalynx') {
        throw new rating_exception('invalidcomponent');
    }
    
    // you can't rate your own entries unless you can manage ratings
    if (!has_capability('mod/datalynx:manageratings', $params['context']) and
             $params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }
    
    // if the supplied context doesnt match the item's context
    if ($params['context']->id != $df->context->id) {
        throw new rating_exception('invalidcontext');
    }
    
    // Check the ratingarea is entry or activity
    if ($params['ratingarea'] != 'entry' and $params['ratingarea'] != 'activity') {
        throw new rating_exception('invalidratingarea');
    }
    
    $data = $df->data;
    
    // vaildate activity scale and rating range
    if ($params['ratingarea'] == 'activity') {
        if ($params['scaleid'] != $data->grade) {
            throw new rating_exception('invalidscaleid');
        }
        
        // upper limit
        if ($data->grade < 0) {
            // its a custom scale
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
        } else if ($params['rating'] > $data->grade) {
            // if its numeric and submitted rating is above maximum
            throw new rating_exception('invalidnum');
        }
    }
    
    // vaildate entry scale and rating range
    if ($params['ratingarea'] == 'entry') {
        if ($params['scaleid'] != $data->rating) {
            throw new rating_exception('invalidscaleid');
        }
        
        // upper limit
        if ($data->rating < 0) {
            // its a custom scale
            $scalerecord = $DB->get_record('scale', array('id' => -$data->rating));
            if ($scalerecord) {
                $scalearray = explode(',', $scalerecord->scale);
                if ($params['rating'] > count($scalearray)) {
                    throw new rating_exception('invalidnum');
                }
            } else {
                throw new rating_exception('invalidscaleid');
            }
        } else if ($params['rating'] > $data->rating) {
            // if its numeric and submitted rating is above maximum
            throw new rating_exception('invalidnum');
        }
    }
    
    // lower limit
    if ($params['rating'] < 0 and $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }
    
    // Make sure groups allow this user to see the item they're rating
    $groupid = $df->currentgroup;
    if ($groupid > 0 and $groupmode = groups_get_activity_groupmode($df->cm, $df->course)) {
        // Groups are being used
        if (!groups_group_exists($groupid)) {
            // Can't find group
            throw new rating_exception('cannotfindgroup'); // something is wrong
        }
        
        if (!groups_is_member($groupid) and
                 !has_capability('moodle/site:accessallgroups', $df->context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or
            // VISIBLEGROUPS
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
    
    require_once ("$CFG->dirroot/rating/lib.php");
    
    $options = new stdClass();
    $options->component = 'mod_datalynx';
    $options->ratingarea = 'entry';
    
    // this is ripped off directly from the datalynx activity
    $options->modulename = 'datalynx';
    $options->moduleid = $data->id;
    $options->userid = $userid;
    $options->aggregationmethod = $data->grademethod;
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
    require_once ("$CFG->libdir/gradelib.php");
    
    if (!$data->rating) {
        datalynx_grade_item_update($data);
    } else if ($grades = datalynx_get_user_grades($data, $userid)) {
        datalynx_grade_item_update($data, $grades);
    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid = $userid;
        $grade->rawgrade = NULL;
        datalynx_grade_item_update($data, $grade);
    } else {
        datalynx_grade_item_update($data);
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
            upgrade_set_timeout(60 * 5); // set up timeout, may also abort execution
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
    if (!function_exists('grade_update')) { // workaround for buggy PHP versions
        require_once ($CFG->libdir . '/gradelib.php');
    }
    
    $params = array('itemname' => $data->name, 'idnumber' => $data->cmidnumber);
    
    if (!$data->rating) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else if ($data->rating > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $data->rating;
        $params['grademin'] = 0;
    } else if ($data->rating < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid'] = -$data->grade;
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
    require_once ("$CFG->libdir/gradelib.php");
    
    return grade_update('mod/datalynx', $data->course, 'mod', 'datalynx', $data->id, 0, NULL, 
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
    $sql = "SELECT COUNT(1)
              FROM {datalynx_entries} de
             WHERE de.userid = :userid
               AND de.dataid = :dataid
               AND de.approved = 1";
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
