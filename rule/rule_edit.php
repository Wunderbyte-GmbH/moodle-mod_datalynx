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
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");

$urlparams = new stdClass();
$urlparams->d = required_param('d', PARAM_INT); // Datalynx ID.

$urlparams->type = optional_param('type', '', PARAM_ALPHA); // Type of a rule to edit.
$urlparams->rid = optional_param('rid', 0, PARAM_INT); // Rule id to edit.

// Set a datalynx object.
$df = new mod_datalynx\datalynx($urlparams->d);

require_login($df->data->course, false, $df->cm);

require_capability('mod/datalynx:managetemplates', $df->context);

$df->set_page('rule/rule_edit', array('urlparams' => $urlparams));

$rm = $df->get_rule_manager();

if ($urlparams->rid) {
    $rule = $rm->get_rule_from_id($urlparams->rid, true); // Force get.
} else {
    if ($urlparams->type) {
        $rule = $rm->get_rule($urlparams->type);
    }
}

$mform = $rule->get_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/datalynx/rule/index.php', array('d' => $df->id())));

    // No submit buttons.
} else {
    if (!$mform->no_submit_button_pressed()) {
        if ($data = $mform->get_data()) {

            // Add new rule.
            if (!$rule->get_id()) {
                $ruleid = $rule->insert_rule($data);

                $other = array('dataid' => $df->id());
                $event = \mod_datalynx\event\rule_created::create(
                        array('context' => $df->context, 'objectid' => $ruleid, 'other' => $other));
                $event->trigger();

                // Update rule.
            } else {
                $data->id = $rule->get_id();
                $rule->update_rule($data);

                $other = array('dataid' => $df->id());
                $event = \mod_datalynx\event\rule_updated::create(
                        array('context' => $df->context, 'objectid' => $rule->get_id(), 'other' => $other));
                $event->trigger();
            }

            if ($data->submitbutton != get_string('savecontinue', 'datalynx')) {
                redirect(new moodle_url('/mod/datalynx/rule/index.php', array('d' => $df->id())));
            }

            // Continue to edit so refresh the form.
            $mform = $rule->get_form();
        }
    }
}

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/rule/index.php', array('id' => $df->cm->id)));

// Print header.
$df->print_header(array('tab' => 'rules', 'nonotifications' => true, 'urlparams' => $urlparams));

$formheading = $rule->get_id() ? get_string('ruleedit', 'datalynx', $rule->get_name()) : get_string(
        'rulenew', 'datalynx', $rule->typename());
echo html_writer::tag('h2', format_string($formheading), array('class' => 'mdl-align'));

// Display form.
$mform->set_data($rule->to_form());
$mform->display();

$df->print_footer();
