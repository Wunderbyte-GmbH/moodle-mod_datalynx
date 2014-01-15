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
 * @package dataformfield
 * @subpackage radiobutton
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/dataform/field/select/renderer.php");

/**
 * 
 */
class dataformfield_radiobutton_renderer extends dataformfield_select_renderer {

    /**
     * 
     */
    protected function render(&$mform, $fieldname, $options, $selected, $required = false, $overridedisabled = false) {
        $field = $this->_field;
        $separator = $field->separators[(int) $field->get('param3')]['chr'];
        $elemgrp = array();
        $separators = array();
        foreach ($options as $key => $option) {
            $elemgrp[] = &$mform->createElement('radio', $fieldname, $separator, $option, $key);
        }
        if (!empty($selected)) {
            $mform->setDefault($fieldname, (int) $selected);
        }
        return array($elemgrp, array($separator));
    }

    /**
     *
     */
    public function display_edit(&$mform, $entry, array $options = null) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $menuoptions = $field->options_menu();
        $required = !empty($options['required']);

        $content = !empty($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : null;

        if ($entryid > 0 and $content){
            $selected = explode('#', $content);
        } else {
            $selected = array();
        }

        // check for default values
        if (!$selected and $field->get('param2')) {
            //$selected = $field->default_values();
        }

        list($elem, $separators) = $this->render($mform, "{$fieldname}", $menuoptions, $selected, $required);
        // Add group or element
        if (is_array($elem)) {
            $mform->addGroup($elem, "{$fieldname}_grp",null, $separators, false);
        } else {
            $mform->addElement($elem);
        }

        if ($required) {
            $this->set_required($mform, $fieldname, $selected);
        }

        // Input field for adding a new option
        if (!empty($options['addnew'])) {
            if ($field->get('param4') or has_capability('mod/dataform:managetemplates', $field->df()->context)) {
                $mform->addElement('text', "{$fieldname}_newvalue", get_string('newvalue', 'dataform'));
                $mform->setType("{$fieldname}_newvalue", PARAM_TEXT);
                $mform->disabledIf("{$fieldname}_newvalue", "{$fieldname}_selected", 'neq', 0);
            }
            return;
        }
    }

    /**
     *
     */
    protected function set_required(&$mform, $fieldname, $selected) {
        global $PAGE;
        
        $mform->addRule("{$fieldname}_grp", null, 'required', null, 'client');
        // JS Error message
        $options = array(
            'fieldname' => $fieldname,
            'selected' => !empty($selected),
            'message' => get_string('err_required', 'form'),
        );

        $module = array(
            'name' => 'M.dataformfield_radiobutton_required',
            'fullpath' => '/mod/dataform/field/radiobutton/radiobutton.js',
            'requires' => array('base','node')
        );

        $PAGE->requires->js_init_call('M.dataformfield_radiobutton_required.init', array($options), false, $module);            
    }

}
