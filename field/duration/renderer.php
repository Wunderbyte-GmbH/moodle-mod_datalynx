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
 * @subpackage duration
 * @copyright 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 *
 */
class datalynxfield_duration_renderer extends datalynxfield_renderer {

    /**
     *
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $number = '';
        if ($entryid > 0 and !empty($entry->{"c{$fieldid}_content"})){
            $number = $entry->{"c{$fieldid}_content"};
        }
        
        // Field width
        $fieldattr = array();
        if ($field->get('param2')) {
            $fieldattr['style'] = 'width:'. s($field->get('param2')). s($field->get('param3')). ';';
        }

        $mform->addElement('duration', $fieldname, '', array('optional' => null), $fieldattr);
        $mform->setDefault($fieldname, $number);
        $required = !empty($options['required']);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }

    /**
     *
     */
    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();
        if (isset($entry->{"c{$fieldid}_content"})) {
            $duration = (int) $entry->{"c{$fieldid}_content"};
        } else {
            $duration = '';
        }
        
        $format = !empty($params['format']) ? $params['format'] : '';
        if ($duration) {
            list($value, $unit) = $field->seconds_to_unit($duration);
            $units = $field->get_units();
            switch ($format) {
                case 'unit':
                    return $units[$unit]; break;
                    
                case 'value':
                    return $value; break;
                    
                case 'seconds':
                    return $duration; break;
                    
                case 'interval':
                    return format_time($duration); break;
                    
                default:
                    return $value. ' '. $units[$unit]; break;
            }
        }
        return '';
    }

    /**
     *
     */
    public function display_search(&$mform, $i = 0, $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = array();
        $arr[] = &$mform->createElement('duration', $fieldname);
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');

        return array($arr, null);
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:unit]]"] = array(false);
        $patterns["[[$fieldname:value]]"] = array(false);
        $patterns["[[$fieldname:seconds]]"] = array(false);
        $patterns["[[$fieldname:interval]]"] = array(false);

        return $patterns;
    }

    /**
     * Array of patterns this field supports
     */
    protected function supports_rules() {
        return array(
            self::RULE_REQUIRED,
            self::RULE_NOEDIT,
        );
    }
}

