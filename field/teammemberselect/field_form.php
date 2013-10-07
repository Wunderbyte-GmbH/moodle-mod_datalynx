<?php
// This file is part of Moodle - http://moodle.org/.
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
 * @package dataformfield
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("$CFG->dirroot/mod/dataform/field/field_form.php");

class dataformfield_teammemberselect_form extends dataformfield_form {

    /**
     * Defines the necessary form elements for field creation
     */
    public function field_definition() {
        parent::field_definition();

        $mform =& $this->_form;

        $mform->addElement('header', 'fieldattributesheader', get_string('fieldattributes', 'dataform'));

        // Hidden element for positive number comparison
        $mform->addElement('hidden', 'zero', 0);
        $mform->setType('zero', PARAM_INT);

        // Maximum team size
        $mform->addElement('text', 'param1', get_string('teamsize', 'dataform'), array('size' => 3));
        $mform->addHelpButton('param1', 'teamsize', 'dataform');
        $mform->setType('param1', PARAM_INT);
        $mform->addRule('param1', get_string('teamsize_error_required', 'dataform'), 'required', null, 'client');
        $mform->addRule(array('param1', 'zero'), get_string('teamsize_error_value', 'dataform'), 'compare', 'gt');

        // Minimum required team size
        $mform->addElement('text', 'param3', get_string('minteamsize', 'dataform'), array('size' => 3));
        $mform->addHelpButton('param3', 'minteamsize', 'dataform');
        $mform->setType('param3', PARAM_INT);
        $mform->addRule(array('param3', 'param1'), get_string('minteamsize_error_value', 'dataform'), 'compare', 'lte');
        $mform->setDefault('param3', 0);

        // Admissible roles
        $group = array();
        $context = context_system::instance();
        $roles = get_default_enrol_roles($context);

        foreach ($roles as $key => $label) {
            $checkbox = &$mform->createElement('checkbox', $key, null, $label, array('group' => 1));
            $group[] = $checkbox;
        }
        $mform->addGroup($group, 'param2', get_string('admissibleroles', 'dataform'), '<br />');
        $mform->addHelpButton('param2', 'admissibleroles', 'dataform');
        $mform->addGroupRule('param2', get_string('admissibleroles_error', 'dataform'), 'required', null, 1, 'client');

        $mform->addElement('select', 'param4', get_string('listformat', 'dataform'), $this->_field->separators);
        $mform->setType('param4', PARAM_INT);
        $mform->setDefault('param4', dataformfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_UL);
    }

    /**
     * This function is overriden to decode param2 field from JSON notation into an array
     * @param array $data new contents of the form
     */
    public function set_data($data) {
        $elements = json_decode($data->param2, true);
        $elements = $elements == null ? array() : $elements;
        $data->param2 = array();
        foreach ($elements as $element) {
            $data->param2[$element] = 1;
        }
        parent::set_data($data);
    }

    /**
     * This function is overriden to encode param2 field into JSON notation as the param2 is of type string
     * @param  boolean $slashed TODO: add description!
     * @return array submitted, validated and processed form contents
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            $data->param2 = json_encode(array_keys($data->param2));
        }
        return $data;
    }
}
