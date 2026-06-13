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
 * Form class for the fieldgroup field type.
 *
 * @package    datalynxfield_fieldgroup
 * @subpackage fieldgroup
 * @copyright  2018 michael pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_fieldgroup;

use html_writer;
use mod_datalynx\form\datalynxfield_form;
use stdClass;

/**
 * Form class for the fieldgroup field type.
 */
class form extends datalynxfield_form {
    /**
     *
     * {@inheritdoc}
     * @see datalynxfield_form::field_definition()
     */
    public function field_definition() {
        global $PAGE;
        $mform = &$this->_form;

        // Hidden input: JavaScript keeps this in sync as a JSON array of
        // "fieldid:formatname|behaviorname|layoutname" strings.
        $mform->addElement('hidden', 'param1', '[]');
        $mform->setType('param1', PARAM_RAW);

        // Build a fieldid → name map for JS to resolve IDs to labels when rendering rows.
        // JS reads the current entries from the hidden param1 input at page-load time so
        // it correctly handles both initial load and re-render after a validation error.
        $fieldmap = [];
        foreach ($this->dlx->get_fields(null, false, true) as $fid => $fobj) {
            if ($fobj->for_use_in_fieldgroup()) {
                $fieldmap[(int)$fid] = $fobj->name();
            }
        }

        // Subfield list table (rows rendered by JS on load and on add/edit/remove).
        $tableheaders = html_writer::tag(
            'tr',
            html_writer::tag('th', get_string('subfieldfield', 'datalynx')) .
            html_writer::tag('th', get_string('subfieldformat', 'datalynx')) .
            html_writer::tag('th', get_string('subfieldbehavior', 'datalynx')) .
            html_writer::tag('th', get_string('subfieldlayout', 'datalynx')) .
            html_writer::tag('th', get_string('subfieldactions', 'datalynx'))
        );
        $table = html_writer::tag('thead', $tableheaders) .
                 html_writer::tag('tbody', '', ['id' => 'fieldgroup-subfields-body']);
        $mform->addElement('html', html_writer::tag(
            'table',
            $table,
            ['class' => 'table table-sm generaltable mb-2', 'id' => 'fieldgroup-subfields-table']
        ));

        // Add subfield button — type=button so it does not submit the enclosing form.
        $mform->addElement('html', html_writer::tag(
            'button',
            get_string('subfieldaddbutton', 'datalynx'),
            [
                'type'        => 'button',
                'class'       => 'btn btn-secondary mb-3',
                'data-action' => 'fieldgroup-addsubfield',
                'data-d'      => $this->dlx->id(),
                'data-cmid'   => $this->dlx->cm->id,
            ]
        ));

        // Initialise the AMD module. The JS reads the hidden param1 input to render rows,
        // so the table is always in sync with the submitted (or initial) JSON.
        $PAGE->requires->js_call_amd(
            'mod_datalynx/fieldgroup_subfield_settings',
            'init',
            [[
                'd'         => $this->dlx->id(),
                'cmid'      => $this->dlx->cm->id,
                'formClass' => 'datalynxfield_fieldgroup\\form\\subfield_dynamic_form',
                'fieldmap'  => $fieldmap,
            ]]
        );

        // Number of times the field group can be filled out.
        $mform->addElement('text', 'param2', get_string('nummax', 'datalynx'));
        $mform->setDefault('param2', 3);
        $mform->addRule('param2', null, 'numeric', null, 'client');
        $mform->setType('param2', PARAM_INT);

        // Number of fieldgroups to show by default.
        $mform->addElement('text', 'param3', get_string('numshowdefault', 'datalynx'));
        $mform->setDefault('param3', 3); // Defaults to three.
        $mform->addRule('param3', null, 'numeric', null, 'client');
        $mform->setType('param3', PARAM_INT);

        // Number of required fieldgroups to be filled out.
        $mform->addElement('text', 'param4', get_string('numrequired', 'datalynx'));
        $mform->setDefault('param4', 0);
        $mform->addRule('param4', null, 'numeric', null, 'client');
        $mform->setType('param4', PARAM_INT);

        // MDL-0000 TODO: Select displaymode.
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_form::validation()
     * @param array $data Submitted form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Decode JSON from the hidden param1 input to validate the list of entries.
        $entries = json_decode($data['param1'] ?? '[]', true) ?: [];
        if (empty($entries)) {
            $errors['param1'] = get_string('onefieldrequired', 'datalynx');
            return $errors;
        }

        $fields = $this->dlx->get_fields(null, false, true);
        foreach ($entries as $val) {
            $parts   = explode(':', $val, 2);
            $fieldid = (int)$parts[0];
            if (!(array_key_exists($fieldid, $fields) && $fields[$fieldid]->for_use_in_fieldgroup())) {
                $fieldtype = $fields[$fieldid]->type ?? '?';
                $errors['param1'] = get_string('unsupportedfield', 'datalynx', $fieldtype);
            }
        }
        return $errors;
    }

    /**
     * Ensure param1 is a JSON string before handing it to the hidden input element.
     *
     * @param array|stdClass $data new contents of the form
     */
    public function set_data($data) {
        if (is_array($data->param1 ?? null)) {
            // Normalize: old entries may be bare integers (legacy format); convert all to strings.
            $data->param1 = json_encode(array_map('strval', array_values($data->param1)));
        } else if (!empty($data->param1)) {
            // Normalize JSON from DB: old format stores bare integers [1604,1605];
            // new format expects strings ["1604","1604:fmt|beh|lay"]. Convert if needed.
            $decoded = json_decode($data->param1, true);
            if (is_array($decoded)) {
                $data->param1 = json_encode(array_map('strval', array_values($decoded)));
            }
        } else {
            $data->param1 = '[]';
        }
        parent::set_data($data);
    }
}
