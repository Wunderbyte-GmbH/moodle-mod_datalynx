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
 * @package datalynxfield
 * @subpackage teammemberselect
 * @copyright 2015 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');

ob_start();

$d = required_param('d', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$fieldid = required_param('fieldid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$viewid = optional_param('viewid', 0, PARAM_INT);
$isajax = optional_param('ajax', false, PARAM_BOOL);

$cm = get_coursemodule_from_instance('datalynx', $d, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_sesskey();
require_login($course, true, $cm);
require_capability('mod/datalynx:teamsubscribe', $context);

global $DB;
if ($action == 'subscribe') {
    $users = json_decode($DB->get_field('datalynx_contents', 'content', array('fieldid' => $fieldid, 'entryid' => $entryid)));
    if ($users !== null) {
        $users[] = "$userid";
        $users = array_unique($users);
        $DB->set_field('datalynx_contents', 'content', json_encode($users), array('fieldid' => $fieldid, 'entryid' => $entryid));
    } else {
        $users = ["$userid"];
        $content = [
            'fieldid' => $fieldid,
            'entryid' => $entryid,
            'content' => json_encode($users)
        ];
        $DB->insert_record('datalynx_contents', (object) $content);
    }

    $return = true;
} else if ($action == 'unsubscribe') {
    $field = $DB->get_field('datalynx_contents', 'content', array('fieldid' => $fieldid, 'entryid' => $entryid));
    $users = json_decode($DB->get_field('datalynx_contents', 'content', array('fieldid' => $fieldid, 'entryid' => $entryid)));
    if ($users !== null) {
        $users = array_values(array_diff($users, array($userid)));
        $DB->set_field('datalynx_contents', 'content', json_encode($users), array('fieldid' => $fieldid, 'entryid' => $entryid));
        $return = true;
    } else {
        $return = false; //should not occur, as at least this user's id must be in the field
    }
} else {
    $return = false;
}

if (true) {
    if (ob_get_contents()) {
        ob_clean();
    }
    echo json_encode($return);

    die;
} else {
    $sourceview = optional_param('sourceview', $this->id(), PARAM_INT);
    $url = new moodle_url('view.php', array('d' => $this->_df->id(), 'view' => $sourceview));
    redirect($url);
}




