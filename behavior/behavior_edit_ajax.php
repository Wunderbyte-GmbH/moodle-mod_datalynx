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
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);

require_once('../../../config.php');

$behaviorid = required_param('behaviorid', PARAM_INT);
$permissionid = optional_param('permissionid', 0, PARAM_INT);
$forproperty = required_param('forproperty', PARAM_ALPHA);

$dataid = $DB->get_field('datalynx_behaviors', 'dataid', array('id' => $behaviorid));
$courseid = $DB->get_field('datalynx', 'course', array('id' => $dataid));

// This cannot work as the id needs to be courseid not dataid.
// $cm = get_coursemodule_from_id('datalynx', $dataid, 0, false, MUST_EXIST);
// require_login($courseid, false, $cm);

require_sesskey();

$toggle = "ERROR";
if ($forproperty == "required") {
    $required = $DB->get_field('datalynx_behaviors', $forproperty, array('id' => $behaviorid));
    if ($required) {
        $required = 0;
        $toggle = "OFF";
    } else {
        $required = 1;
        $toggle = "ON";
    }

    $DB->set_field('datalynx_behaviors', $forproperty, $required, array('id' => $behaviorid));
} else {
    $permissions = unserialize(
            $DB->get_field('datalynx_behaviors', $forproperty, array('id' => $behaviorid)));
    if (!in_array($permissionid, $permissions)) {
        $permissions[] = $permissionid;
        $toggle = "ON";
    } else {
        if (($key = array_search($permissionid, $permissions)) !== false) {
            unset($permissions[$key]);
        }
        $toggle = "OFF";
    }
    $DB->set_field('datalynx_behaviors', $forproperty, serialize($permissions),
            array('id' => $behaviorid));
}

echo json_encode($toggle);
die();
