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
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");

$urlparams = new stdClass();
$urlparams->d = required_param('d', PARAM_INT); // Datalynx ID.

$urlparams->type = optional_param('type', '', PARAM_ALPHA); // Type of a field to edit.
$urlparams->fid = optional_param('fid', 0, PARAM_INT); // Field id to edit.

// Set a datalynx object.
$dlx = new mod_datalynx\datalynx($urlparams->d);

require_login($dlx->data->course, false, $dlx->cm);

$dlx->set_page('field/field_edit', ['urlparams' => $urlparams]);

require_sesskey();
require_capability('mod/datalynx:managetemplates', $dlx->context);

if ($urlparams->fid) {
    $field = $dlx->get_field_from_id($urlparams->fid, true); // Force get.
} else {
    if ($urlparams->type) {
        $field = $dlx->get_field($urlparams->type);
    }
}

$mform = $field->get_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/datalynx/field/index.php', ['d' => $dlx->id()]));

    // No submit buttons.
} else if (!$mform->no_submit_button_pressed()) {
    if ($data = $mform->get_data()) {
        // Add new field.
        if (!$field->id()) {
            $fieldid = $field->insert_field($data);
            if (!isset($data->param10)) {
                $param10 = 0;
            } else {
                $param10 = $data->param10;
            }
            // For text fields: Store the field itself as autocompletion reference field if no other field is chosen.
            if ($field->type == 'text' && $param10 <= 0 && $data->param9 == $dlx->id()) {
                $DB->set_field('datalynx_fields', 'param10', $fieldid, ['id' => $fieldid]);
            }
            $other = ['dataid' => $dlx->id()];
            $event = \mod_datalynx\event\field_created::create(
                ['context' => $dlx->context, 'objectid' => $fieldid, 'other' => $other]
            );
            $event->trigger();

            // Update field.
        } else {
            $data->id = $field->id();
            $field->update_field($data);

            $other = ['dataid' => $dlx->id()];
            $event = \mod_datalynx\event\field_updated::create(
                ['context' => $dlx->context, 'objectid' => $field->id(), 'other' => $other,
                ]
            );
            $event->trigger();
        }

        if ($data->submitbutton != get_string('savecontinue', 'datalynx')) {
            redirect(new moodle_url('/mod/datalynx/field/index.php', ['d' => $dlx->id()]));
        }

        // Continue to edit so refresh the form.
        $mform = $field->get_form();
    }
}

// Activate navigation node.
navigation_node::override_active_url(
    new moodle_url('/mod/datalynx/field/index.php', ['id' => $dlx->cm->id])
);

// Print header.
$dlx->print_header(['tab' => 'fields', 'nonotifications' => true, 'urlparams' => $urlparams]);

$formheading = $field->id() ? get_string('fieldedit', 'datalynx', $field->name()) : get_string(
    'fieldnew',
    'datalynx',
    $field->typename()
);
echo html_writer::tag('h2', format_string($formheading), ['class' => 'mdl-align']);

// Display form.
$mform->set_data($field->to_form());
$mform->display();

$dlx->print_footer();
