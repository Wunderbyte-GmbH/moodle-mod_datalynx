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
 * @subpackage teammemberselect
 * @copyright 2015 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');

ob_start();

$d = required_param('d', PARAM_INT);
$entryid = required_param('entryid', PARAM_INT);
$fieldid = required_param('fieldid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
$view = optional_param('view', null, PARAM_INT);
$ajax = optional_param('ajax', false, PARAM_BOOL);

if (!defined('AJAX_SCRIPT') && $ajax) {
    define('AJAX_SCRIPT', true);
}

$cm = get_coursemodule_from_instance('datalynx', $d, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_sesskey();
require_login($course, true, $cm);
require_capability('mod/datalynx:teamsubscribe', $context);

global $DB;
if ($action == 'subscribe') {
    $users = json_decode(
            $DB->get_field('datalynx_contents', 'content',
                    array('fieldid' => $fieldid, 'entryid' => $entryid)), true);
    if ($users !== null) {
        $users[] = "$userid";
        $users = array_unique($users);
        $users = array_diff($users, ["0"]);
        $users = array_values($users);
        $DB->set_field('datalynx_contents', 'content', json_encode($users),
                array('fieldid' => $fieldid, 'entryid' => $entryid));
    } else {
        $users = ["$userid"];
        $content = ['fieldid' => $fieldid, 'entryid' => $entryid,
                'content' => json_encode($users)];
        if ($content !== "null") {
            $DB->insert_record('datalynx_contents', (object) $content);
        } else {
            $return = "Team subscribe error: Failed encoding subscription!";
        }
    }

    $other = ['dataid' => $d, 'fieldid' => $fieldid,
            'name' => $DB->get_field('datalynx_fields', 'name', array('id' => $fieldid)),
            'addedmembers' => json_encode([$userid]), 'removedmembers' => json_encode([])
    ];

    $event = \mod_datalynx\event\team_updated::create(
            array('context' => $context, 'objectid' => $entryid, 'other' => $other));
    $event->trigger();

    $return = true;
} else {
    if ($action == 'unsubscribe') {
        $users = json_decode(
                $DB->get_field('datalynx_contents', 'content',
                        array('fieldid' => $fieldid, 'entryid' => $entryid)), true);
        if ($users !== null) {
            $users = array_unique($users);
            $users = array_values(array_diff($users, [$userid]));
            $users = array_diff($users, ["0"]);
            $users = array_values($users);
            if (empty($users)) {
                $DB->delete_records('datalynx_contents',
                        array('fieldid' => $fieldid, 'entryid' => $entryid));
            } else {
                $DB->set_field('datalynx_contents', 'content', json_encode($users),
                        array('fieldid' => $fieldid, 'entryid' => $entryid));
            }
            $return = true;

            $other = ['dataid' => $d, 'fieldid' => $fieldid,
                    'name' => $DB->get_field('datalynx_fields', 'name', array('id' => $fieldid)),
                    'addedmembers' => json_encode([]), 'removedmembers' => json_encode([$userid])
            ];

            $event = \mod_datalynx\event\team_updated::create(
                    array('context' => $context, 'objectid' => $entryid, 'other' => $other));
            $event->trigger();
        } else {
            $return = "Team subscribe error: The team list is empty!"; // Should not occur, as at least.
            // This user's id must be in the field.
        }
    } else {
        $return = "Team subscribe error: Wrong action!";
    }
}

if ($ajax) {
    if (ob_get_contents()) {
        ob_clean();
    }
    echo json_encode($return);

    die();
} else {
    $url = new moodle_url('../../view.php', array('d' => $d, 'view' => $view));
    redirect($url);
}

