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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * @package datalynxfield
 * @copyright 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

require_once("$CFG->libdir/formslib.php");

class datalynxfield_form extends moodleform {
    protected $_field = null;
    protected $_df = null;

    public function __construct($field, $action = null, $customdata = null, $method = 'post', $target = '', $attributes = null, $editable = true) {
        $this->_field = $field;
        $this->_df = $field->df();
        
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable);       
    }
    
    function definition() {
        global $CFG;
        $mform = &$this->_form;

        // buttons
        //-------------------------------------------------------------------------------
        $this->add_action_buttons();

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // name
        $mform->addElement('text', 'name', get_string('name'), array('size'=>'32'));
        $mform->addRule('name', null, 'required', null, 'client');
        
        // description
        $mform->addElement('text', 'description', get_string('description'), array('size'=>'64'));

        // visible
        $options = array(
            datalynxfield_base::VISIBLE_NONE => get_string('fieldvisiblenone', 'datalynx'),
            datalynxfield_base::VISIBLE_OWNER => get_string('fieldvisibleowner', 'datalynx'),
            datalynxfield_base::VISIBLE_ALL => get_string('fieldvisibleall', 'datalynx'),
        );
        $mform->addElement('select', 'visible', get_string('fieldvisibility', 'datalynx'), $options);

        // Editable
        //$options = array(-1 => get_string('unlimited'), 0 => get_string('none'));
        $options = array(-1 => get_string('yes'), 0 => get_string('no'));
        //$options = $options + array_combine(range(1,50), range(1,50));
        $mform->addElement('select', 'edits', get_string('fieldeditable', 'datalynx'), $options);
        $mform->setDefault('edits', -1);

        // Label
        $mform->addElement('textarea', 'label', get_string('fieldlabel', 'datalynx'), array('cols' => 60, 'rows' => 5));
        $mform->addHelpButton('label', 'fieldlabel', 'datalynx');

        // Strings strip tags
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
            $mform->setType('description', PARAM_TEXT);
            $mform->setType('label', PARAM_RAW);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
            $mform->setType('description', PARAM_CLEANHTML);
            $mform->setType('label', PARAM_RAW);
        }

        //-------------------------------------------------------------------------------
        $this->field_definition();

        // buttons
        //-------------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    /**
     *
     */
    function field_definition() {
    }    
    
    /**
     *
     */
    function add_action_buttons($cancel = true, $submit = null){
        $mform = &$this->_form;

        $buttonarray=array();
        // save and display
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
        // save and continue
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savecontinue', 'datalynx'));
        // cancel
        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     *
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($this->_df->name_exists('fields', $data['name'], $this->_field->id())) {
            $errors['name'] = get_string('invalidname','datalynx', get_string('field', 'datalynx'));
        }

        return $errors;
    }

}

class datalynxfield_option_form extends datalynxfield_form {

    function definition_after_data() {
        $this->add_option_dialog();
    }

    protected function add_option_dialog() {
        $mform = &$this->_form;
        $options = $this->_field->get_options();
        if (!empty($options)) {
            $header = &$mform->createElement('static', '', get_string('existingoptions', 'datalynx'),
                '<table><thead><th>' . get_string('option', 'datalynx') . '</th><th>' .
                get_string('deleteoption', 'datalynx') . '</th><th>' . get_string('renameoption', 'datalynx') .
                '</th></thead><tbody>');
            $mform->insertElementBefore($header, 'param2');
            foreach ($options as $id => $option) {
                $group = array();
                $group[] = &$mform->createElement('static', '', '', "<tr><td>{$option}</td><td>");
                $group[] = &$mform->createElement('checkbox', "deleteoption[{$id}]", '');
                $group[] = &$mform->createElement('static', '', '', '</td><td>');
                $group[] = &$mform->createElement('text', "renameoption[{$id}]", '');
                $group[] = &$mform->createElement('static', '', '', '</td></tr>');
                $tablerow = &$mform->createElement('group', 'existingoptions', '', $group, null, false);
                $mform->insertElementBefore($tablerow, 'param2');
                $mform->disabledIf("renameoption[{$id}]", "deleteoption[{$id}]", 'checked');
            }
            $footer = &$mform->createElement('static', '', '', '</tbody></table>');
            $mform->insertElementBefore($footer, 'param2');
        }
        $addnew = &$mform->createElement('textarea', 'addoptions', get_string('addoptions', 'datalynx'), 'wrap="virtual" rows="5" cols="30"');
        $mform->insertElementBefore($addnew, 'param2');
        if (empty($options)) {
            $mform->addRule('addoptions', null, 'required', null, 'client');
        }
    }

    /**
     *
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $oldoptions = $this->_field->get_options();
        if (count($oldoptions) == 0 && empty($data['addoptions'])) {
            $errors['addoptions'] = get_string('nooptions','datalynx');
        } else if (isset($data['deleteoption']) && count($data['deleteoption']) == count($oldoptions) && empty($data['addoptions'])) {
            $errors['addoptions'] = get_string('nooptions','datalynx');
        }

        return $errors;
    }
}
