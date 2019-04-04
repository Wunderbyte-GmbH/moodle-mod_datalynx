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
 * @package datalynx_rule
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../classes/datalynx.php');

$urlparams = new stdClass();

$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.
$urlparams->rid = optional_param('rid', -1, PARAM_INT); // Update rule id.

// Rules list actions.
$urlparams->new = optional_param('new', 0, PARAM_INT); // New rule.

$urlparams->enabled = optional_param('enabled', 0, PARAM_INT); // Rule enabled/disabled flag.
$urlparams->redit = optional_param('redit', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Rules to delete.
$urlparams->delete = optional_param('delete', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Rules to delete.
$urlparams->duplicate = optional_param('duplicate', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Rules to duplicate.

$urlparams->confirmed = optional_param('confirmed', 0, PARAM_INT);

// Rule actions.
$urlparams->update = optional_param('update', 0, PARAM_INT); // Update rule.
$urlparams->cancel = optional_param('cancel', 0, PARAM_BOOL);

// Set a datalynx object.
$df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);

require_login($df->data->course, false, $df->cm);

require_capability('mod/datalynx:managetemplates', $df->context);

$df->set_page('rule/index', array('modjs' => true, 'urlparams' => $urlparams));

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/rule/index.php', array('id' => $df->cm->id)));

$rm = $df->get_rule_manager();

// DATA PROCESSING.
if ($urlparams->duplicate and confirm_sesskey()) { // Duplicate any requested rules.
    $rm->process_rules('duplicate', $urlparams->duplicate, $urlparams->confirmed);
} else {
    if ($urlparams->delete and confirm_sesskey()) { // Delete any requested rules.
        $rm->process_rules('delete', $urlparams->delete, $urlparams->confirmed);
    } else {
        if ($urlparams->enabled and confirm_sesskey()) { // Set rule to enabled/disabled.
            $rm->process_rules('enabled', $urlparams->enabled, true); // Confirmed by default.
        } else {
            if ($urlparams->update and confirm_sesskey()) { // Add/update a new rule.
                $rm->process_rules('update', $urlparams->rid, true);
            }
        }
    }
}

// Any notifications?
if (!$rules = $rm->get_rules()) {
    $df->notifications['bad'][] = get_string('rulesnoneindatalynx', 'datalynx'); // Nothing in.
    // Datalynx.
}

// Print header.
$df->print_header(array('tab' => 'rules', 'urlparams' => $urlparams));

// Print the rule add link.
$rm->print_add_rule();

// If there are rules print admin style list of them.
if ($rules) {
    $rm->print_rule_list();
}

$df->print_footer();