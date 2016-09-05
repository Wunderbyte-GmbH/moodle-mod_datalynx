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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package datalynxfield
 * @subpackage radiobutton
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once (dirname(__FILE__) . "/../renderer.php");


/**
 * Class datalynxfield_radiobutton_renderer Renderer for radiobutton field type
 */
class datalynxfield_radiobutton_renderer extends datalynxfield_renderer {

    /**
     *
     * @var datalynxfield_radiobutton
     */
    protected $_field = null;

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $menuoptions = $field->options_menu();
        $fieldname = "field_{$fieldid}_$entryid";
        $required = !empty($options['required']);
        $selected = !empty($entry->{"c{$fieldid}_content"}) ? (int) $entry->{"c{$fieldid}_content"} : 0;
        
        // check for default value
        if (!$selected and $defaultval = $field->get('param2')) {
            $selected = (int) array_search($defaultval, $menuoptions);
        }
        
        $separator = $field->separators[(int) $field->get('param2')]['chr'];
        
        $elemgrp = array();
        foreach ($menuoptions as $id => $option) {
            $radio = &$mform->createElement('radio', $fieldname, $separator, $option, $id);
            if ($id == $selected) {
                $radio->setChecked(true);
            }
            $elemgrp[] = $radio;
        }
        
        $mform->addGroup($elemgrp, "{$fieldname}_group", null, $separator, false);
        
        $mform->setDefaults(array($fieldname => (int) $selected));
        
        if ($required) {
            $mform->addRule("{$fieldname}_group", null, 'required', null, 'client');
        }
    }

    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();
        
        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = (int) $entry->{"c{$fieldid}_content"};
            $options = $field->options_menu();
            
            if (!empty($params['options'])) {
                $str = array();
                foreach ($options as $key => $option) {
                    $isselected = (int) ($key == $selected);
                    $str[] = "$isselected $option";
                }
                $str = implode(',', $str);
                return $str;
            }
            
            if (!empty($params['key'])) {
                if ($selected) {
                    return $selected;
                } else {
                    return '';
                }
            }
            
            if ($selected and $selected <= count($options)) {
                return $options[$selected];
            }
        }
        
        return '';
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        global $CFG;
        HTML_QuickForm::registerElementType('checkboxgroup', 
                "$CFG->dirroot/mod/datalynx/checkboxgroup/checkboxgroup.php", 
                'HTML_QuickForm_checkboxgroup');
        
        $field = $this->_field;
        $fieldid = $field->id();
        
        $selected = $value;
        
        $options = $field->options_menu();
        
        $fieldname = "f_{$i}_$fieldid";
        $select = &$mform->createElement('checkboxgroup', $fieldname, null, $options, '');
        $select->setValue($selected);
        
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');
        
        return array(array($select), null);
    }
}
