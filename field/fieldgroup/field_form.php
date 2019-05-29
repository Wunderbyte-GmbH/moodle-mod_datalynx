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
 * @package datalynxfield
 * @subpackage fieldgroup
 * @copyright 2018 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_fieldgroup_form extends datalynxfield_form {

    /**
     *
     * {@inheritdoc}
     * @see datalynxfield_form::field_definition()
     */
    public function field_definition() {
        $mform = &$this->_form;

        // Fieldgroupfieldids are stored in param1.
        $fields = array();
        $fields = $this->_df->get_fields(null, false, true);
        $fieldnames = array();
        foreach ($fields as $fieldid => $field) {
            if ($field->for_use_in_fieldgroup()) {
                $fieldnames[$fieldid] = $field->name();
            }
        }
        asort($fieldnames);
        $options = array('multiple' => true);
        $mform->addElement('autocomplete', 'param1', get_string('fieldgroupfields', 'datalynx'),
                $fieldnames, $options);
        $mform->addHelpButton('param1', 'fieldgroupfields', 'datalynx');

        // Number of times the field group can be filled out.
        $mform->addElement('text', 'param2', 'beschreibung nummax'); // TODO: Multilang.
        $mform->setDefault('param2', 3);
        $mform->addRule('param2', null, 'numeric', null, 'client');
        $mform->setType('param2', PARAM_INT);

        // Number of fieldgroups to show by default.
        $mform->addElement('text', 'param3', 'beschreibung numshowdefault'); // TODO: Multilang.
        $mform->setDefault('param3', 3); // Defaults to three.
        $mform->addRule('param3', null, 'numeric', null, 'client');
        $mform->setType('param3', PARAM_INT);

        // Number of required fieldgroups to be filled out.
        $mform->addElement('text', 'param4', 'beschreibung numrequired'); // TODO: Multilang.
        $mform->setDefault('param4', 0);
        $mform->addRule('param4', null, 'numeric', null, 'client');
        $mform->setType('param4', PARAM_INT);

        // TODO: Select displaymode.
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_form::validation()
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check if any fieldnames are set.
        if (!isset($data['param1']) || isset($data['param1']) && empty($data['param1'])) {
            $errors['param1'] = "Sorry, you have to add at least one field to a fieldgroup."; // TODO: Multilang.
            return $errors;
        }

        // Check if all fieldnames are actually found and only fieldtypes are entered that have been tested.
        $fields = $this->_df->get_fields(null, false, true);
        foreach ($data['param1'] as $fieldid) {
            if (!(array_key_exists($fieldid, $fields) and $fields[$fieldid]->for_use_in_fieldgroup())) {
                $errors['param1'] = "Sorry, fields of type " . $fields[$fieldid]->type .
                        " are not yet supported in fieldgroups."; // TODO: Multilang.
            }
        }
        return $errors;
    }

    /**
     * This function is overriden to decode param1 field from JSON notation into an array
     *
     * @param array $data new contents of the form
     */
    public function set_data($data) {
        $elements = json_decode($data->param1, true);
        $elements = $elements == null ? array() : $elements;
        $data->param1 = array();
        foreach ($elements as $element) {
            $data->param1[] = $element;
        }
        parent::set_data($data);
    }

    /**
     * This function is overriden to encode param1 field into JSON notation
     *
     * @param boolean $slashed TODO: add description!
     * @return array submitted, validated and processed form contents
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            $data->param1 = json_encode($data->param1);
        }
        return $data;
    }
}
