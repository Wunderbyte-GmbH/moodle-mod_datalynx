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
 * Field format edit/create/delete page.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');

use mod_datalynx\datalynx;
use mod_datalynx\local\field_format\manager as format_manager;

$urlparams = new stdClass();
$urlparams->d         = required_param('d', PARAM_INT);
$urlparams->id        = required_param('id', PARAM_INT); // Record ID; 0 = new.
$urlparams->fieldtype = optional_param('fieldtype', '', PARAM_ALPHANUMEXT);
$urlparams->action    = optional_param('action', 'edit', PARAM_ALPHA);

$datalynx = new datalynx($urlparams->d);

require_login($datalynx->data->course, false, $datalynx->cm);
$datalynx->set_page('fieldformat/edit', ['urlparams' => $urlparams]);

require_sesskey();
require_capability('mod/datalynx:managetemplates', $datalynx->context);

$returnurl = new moodle_url('/mod/datalynx/fieldformat/index.php', ['d' => $datalynx->id()]);

switch ($urlparams->action) {

    case 'delete':
        if ($urlparams->id) {
            format_manager::delete_format($urlparams->id);
        }
        redirect($returnurl);
        break;

    case 'edit':
    default:
        // Determine the field type: either from an existing record or from the URL param.
        $fieldtype = $urlparams->fieldtype;
        $existing  = null;

        if ($urlparams->id) {
            $existing = format_manager::get_format_by_id($urlparams->id);
            if ($existing) {
                $fieldtype = $existing->get_fieldtype();
            }
        }

        if (empty($fieldtype)) {
            // No field type specified; redirect back to index.
            redirect($returnurl);
        }

        // Load the field-type-specific form class.
        $formclass = "\\datalynxfield_{$fieldtype}\\form\\field_format_form";
        if (!class_exists($formclass)) {
            // Fall back to a generic form using field_format_form_base if no subclass exists.
            $formclass = null;
        }

        // Build form. Field-type-specific form classes extend field_format_form_base.
        if ($formclass !== null) {
            $mform = new $formclass();
        } else {
            // Use a minimal inline form for field types without a dedicated form class.
            $mform = new \mod_datalynx\form\field_format_form_generic();
        }

        if ($mform->is_cancelled()) {
            redirect($returnurl);
        }

        if ($data = $mform->get_data()) {
            format_manager::save_format($data);
            redirect($returnurl);
        }

        // Populate form defaults.
        $formdata = new stdClass();
        $formdata->id        = $urlparams->id;
        $formdata->dataid    = $datalynx->id();
        $formdata->fieldtype = $fieldtype;
        $formdata->name      = '';

        if ($existing) {
            $formdata->name = $existing->get_name();
            // Spread settings keys onto formdata for the form.
            foreach ($existing->get_settings() as $key => $value) {
                $formdata->$key = $value;
            }
        }

        $mform->set_data($formdata);

        // Print page.
        $datalynx->print_header(
            ['tab' => 'formats', 'nonotifications' => true, 'urlparams' => $urlparams]
        );

        if ($urlparams->id && $existing) {
            echo html_writer::tag(
                'h2',
                get_string('fieldformat_edit', 'datalynx') . ': ' . s($existing->get_name()),
                ['class' => 'mdl-align']
            );
        } else {
            echo html_writer::tag(
                'h2',
                get_string('newfieldformat', 'datalynx') . ' (' . s($fieldtype) . ')',
                ['class' => 'mdl-align']
            );
        }

        // Show description for this format type if a lang string exists.
        $desckey = 'fieldformat_description';
        if (get_string_manager()->string_exists($desckey, 'datalynxfield_' . $fieldtype)) {
            $description = get_string($desckey, 'datalynxfield_' . $fieldtype);
            echo html_writer::div(
                html_writer::tag('p', $description),
                'alert alert-info'
            );
        }

        $mform->display();

        $datalynx->print_footer();
        break;
}
