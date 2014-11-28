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
 * @subpackage select
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 * 
 */
class datalynxfield_select_renderer extends datalynxfield_renderer {

    protected $_cats = array();

    /**
     *
     */
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

        list($elem, $separators) = $this->render($mform, "{$fieldname}_selected", $menuoptions, $selected, $required);
        // Add group or element
        if (is_array($elem)) {
            $mform->addGroup($elem, "{$fieldname}_grp",null, $separators, false);
        } else {
            $mform->addElement($elem);
        }
        
        if ($required) {
            $this->set_required($mform, "{$fieldname}_selected", $selected);
        }

        // Input field for adding a new option
        if (!empty($options['addnew'])) {
            if ($field->get('param4') or has_capability('mod/datalynx:managetemplates', $field->df()->context)) {
                $mform->addElement('text', "{$fieldname}_newvalue", get_string('newvalue', 'datalynx'));
                $mform->setType("{$fieldname}_newvalue", PARAM_TEXT);
                $mform->disabledIf("{$fieldname}_newvalue", "{$fieldname}_selected", 'neq', 0);
            }
            return;
        }
    }

    /**
     *
     */
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

    /**
     * $value is the selected index 
     */
    public function display_search(&$mform, $i = 0, $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();

        $options = $field->options_menu();
        $selected = $value ? (int) $value : '';
        $fieldname = "f_{$i}_$fieldid";
        list($elem, $separator) = $this->render($mform, $fieldname, $options, $selected, false, true);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');
        
        // Return group or element
        if (!is_array($elem)) {
            $elem = array($elem);
        }
        return array($elem, $separator);
    }

    /**
     *
     */
    protected function display_category($entry, $params = null) {
        $field = $this->_field;
        $fieldid = $field->id();
        if (!isset($this->_cats[$fieldid])) {
            $this->_cats[$fieldid] = null;
        }

        $str = '';
        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = (int) $entry->{"c{$fieldid}_content"};
            
            $options = $field->options_menu();
            if ($selected and $selected <= count($options) and $selected != $this->_cats[$fieldid]) {
                $this->_cats[$fieldid] = $selected;
                $str = $options[$selected];
            }
        }
        
        return $str;
    }

    /**
     * 
     */
    protected function render(&$mform, $fieldname, $options, $selected, $required = false, $overridedisabled = false) {
        $select = &$mform->createElement('select', $fieldname, null);

        if (isset($this->_field->field->param5) && !$overridedisabled) {
            $disabled = $this->_field->get_disabled_values_for_user();
        } else {
            $disabled = array();
        }

        $options = array('' => get_string('choosedots')) + $options;
        foreach ($options as $id => $name) {
            if (array_search($id, $disabled) === false || $id == $selected) {
                $select->addOption($name, $id);
            } else {
                $select->addOption($name, $id, array('disabled' => 'disabled'));
            }
        }

        $select->setSelected($selected);
        return array($select, null);
    }

    /**
     *
     */
    protected function set_required(&$mform, $fieldname, $selected) {
        $mform->addRule($fieldname, null, 'required', null, 'client');
    }

    /**
     * Array of patterns this field supports 
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:addnew]]"] = array(false);
        $patterns["[[$fieldname:options]]"] = array(false);
        $patterns["[[$fieldname:cat]]"] = array(false);
        $patterns["[[$fieldname:key]]"] = array(false);

        return $patterns; 
    }
    
    /**
     * Array of patterns this field supports 
     */
    protected function supports_rules() {
        return array(
            self::RULE_REQUIRED
        );
    }
    
}
