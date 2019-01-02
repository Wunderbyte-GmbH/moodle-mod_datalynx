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
 * @package datalynxfield
 * @subpackage _approve
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");
require_once("$CFG->dirroot/mod/datalynx/entries_class.php");

ob_start();

$d = required_param('d', PARAM_INT);
$viewid = required_param('view', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$entryid = required_param('entryid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_TEXT);

$cm = get_coursemodule_from_instance('datalynx', $d, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$data = $DB->get_record('datalynx', array('id' => $d), '*', MUST_EXIST);

require_sesskey();

$context = context_module::instance($cm->id);
require_login($course, true, $cm);
require_capability('mod/datalynx:approve', $context);

global $DB;
$completiontype = COMPLETION_UNKNOWN;
$df = new mod_datalynx\datalynx($d);
if ($action == 'approve') {
    $DB->set_field('datalynx_entries', 'approved', 1, array('id' => $entryid));
    $entriesclass = new datalynx_entries($df);
    $processed = $entriesclass->create_approved_entries_for_team(array($entryid));
    if ($processed) {
        $eventdata = (object) array('view' => $df->get_view_from_id($viewid), 'items' => $processed);
        $df->events_trigger("entryapproved", $eventdata);
    }
    $return = $DB->get_field('datalynx_entries', 'approved', array('id' => $entryid)) == 1;
    $completiontype = COMPLETION_COMPLETE;
} else {
    if ($action == 'disapprove') {
        $DB->set_field('datalynx_entries', 'approved', 0, array('id' => $entryid));
        $return = $DB->get_field('datalynx_entries', 'approved', array('id' => $entryid)) == 0;
        $processed = array($entryid => $DB->get_record('datalynx_entries', array('id' => $entryid)));
        if ($processed) {
            $eventdata = (object) array('view' => $df->get_view_from_id($viewid), 'items' => $processed);
            $df->events_trigger("entrydisapproved", $eventdata);
        }
        $completiontype = COMPLETION_INCOMPLETE;
    } else {
        $return = false;
    }
}
// Update completion state.
$completion = new completion_info($course);
if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC &&
        $data->completionentries
) {
    $userid = $DB->get_field('datalynx_entries', 'userid', array('id' => $entryid));
    $completion->update_state($cm, $completiontype, $userid);
}

if (ob_get_contents()) {
    ob_clean();
}
echo json_encode($return);

die();
