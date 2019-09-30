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
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_teammemberselect_form extends datalynxfield_form {

    /**
     * Defines the necessary form elements for field creation
     */
    public function field_definition() {
        parent::field_definition();

        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributesheader', get_string('fieldattributes', 'datalynx'));

        // Hidden element for positive number comparison.
        $mform->addElement('hidden', 'zero', 0);
        $mform->setType('zero', PARAM_INT);

        // Maximum team size.
        $mform->addElement('text', 'param1', get_string('teamsize', 'datalynx'), array('size' => 3));
        $mform->addHelpButton('param1', 'teamsize', 'datalynx');
        $mform->setType('param1', PARAM_INT);
        $mform->addRule('param1', get_string('teamsize_error_required', 'datalynx'), 'required',
                null, 'client');
        $mform->addRule(array('param1', 'zero'), get_string('teamsize_error_value', 'datalynx'), 'compare', 'gt');

        // Minimum required team size.
        $mform->addElement('text', 'param3', get_string('minteamsize', 'datalynx'), array('size' => 3));
        $mform->addHelpButton('param3', 'minteamsize', 'datalynx');
        $mform->setType('param3', PARAM_INT);
        $mform->addRule(array('param3', 'param1'), get_string('minteamsize_error_value', 'datalynx'), 'compare', 'lte');
        $mform->setDefault('param3', 0);

        // Admissible roles.
        $group = array();
        $permissions = $this->_df->get_datalynx_permission_names(true);

        foreach ($permissions as $key => $label) {
            $checkbox = &$mform->createElement('checkbox', $key, null, $label, array('group' => 1, 'size' => 1));
            $group[] = $checkbox;
        }
        $mform->addGroup($group, 'param2', get_string('admissibleroles', 'datalynx'), '<br />');
        $mform->addHelpButton('param2', 'admissibleroles', 'datalynx');
        $mform->addGroupRule('param2', get_string('admissibleroles_error', 'datalynx'), 'required', null, 1, 'client');

        $mform->addElement('select', 'param4', get_string('listformat', 'datalynx'), $this->_field->separators);
        $mform->setType('param4', PARAM_INT);
        $mform->setDefault('param4', datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_UL);

        $mform->addElement('checkbox', 'param7', get_string('user_can_add_self', 'datalynx'), null, 1);
        $mform->addHelpButton('param7', 'user_can_add_self', 'datalynx');
        $mform->setType('param7', PARAM_BOOL);

        $mform->addElement('checkbox', 'param6', get_string('notifyteammembers', 'datalynx'), null, 1);
        $mform->setType('param6', PARAM_BOOL);

        $mform->addElement('checkbox', 'param8', get_string('allowunsubscription', 'datalynx'), null, 1);
        $mform->addHelpButton('param8', 'allowunsubscription', 'datalynx');
        $mform->setType('param8', PARAM_INT);

        $attributes = array();
        $message = '';
        if ($teamfield = $this->_field->get_teamfield()) {
            if ($this->_field->field->id != $teamfield->id) {
                $message = $teamfield->name . ' is already designated as a team field!';
                $attributes = array('disabled' => 'disabled');
            }
        }

        $mform->addElement('checkbox', 'teamfieldenable', get_string('teamfield', 'datalynx'),
                $message, $attributes);
        $mform->addHelpButton('teamfieldenable', 'teamfield', 'datalynx');

        $fieldmenu = $this->_df->get_fields(array_keys($this->_df->get_internal_fields()), true);
        $fieldmenu = array('-1' => 'No field') + $fieldmenu;
        $mform->addElement('select', 'param5', get_string('referencefield', 'datalynx'), $fieldmenu);
        $mform->addHelpButton('param5', 'referencefield', 'datalynx');
        $mform->setType('param5', PARAM_INT);
        $mform->disabledIf('param5', 'teamfieldenable', 'notchecked');
    }

    /**
     * This function is overriden to decode param2 field from JSON notation into an array
     *
     * @param array $data new contents of the form
     */
    public function set_data($data) {
        $elements = json_decode($data->param2, true);
        $elements = $elements == null ? array() : $elements;
        $data->param2 = array();
        foreach ($elements as $element) {
            $data->param2[$element] = 1;
        }
        $data->param5 = isset($data->param5) ? $data->param5 : 0;
        $data->param6 = isset($data->param6) ? $data->param6 : 0;
        $data->param7 = isset($data->param7) ? $data->param7 : 0;
        $data->param8 = isset($data->param8) ? $data->param8 : 0;
        $data->teamfieldenable = $data->param5 != 0;
        parent::set_data($data);
    }

    /**
     * This function is overriden to encode param2 field into JSON notation as the param2 is of type
     * string
     *
     * @param boolean $slashed TODO: add description!
     * @return array submitted, validated and processed form contents
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            $data->param2 = json_encode(array_keys($data->param2));
            $data->param5 = isset($data->param5) ? $data->param5 : 0;
            $data->param6 = isset($data->param6) ? $data->param6 : 0;
            $data->param7 = isset($data->param7) ? $data->param7 : 0;
            $data->param8 = isset($data->param8) ? $data->param8 : 0;
        }
        return $data;
    }
}
