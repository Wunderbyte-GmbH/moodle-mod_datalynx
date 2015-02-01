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
 * @subpackage number
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/text/renderer.php");

/**
 *
 */
class datalynxfield_number_renderer extends datalynxfield_text_renderer {

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        parent::render_edit_mode($mform, $entry, $options);
        
        $fieldid = $this->_field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $mform->addRule($fieldname, null, 'numeric', null, 'client');
    }

    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();
        if (isset($entry->{"c{$fieldid}_content"})) {
            $number = (float) $entry->{"c{$fieldid}_content"};
        } else {
            return '';
        }
        
        $decimals = (int) trim($field->get('param1'));
        // only apply number formatting if param1 contains an integer number >= 0:
        if ($decimals) {
            // removes leading zeros (eg. '007' -> '7'; '00' -> '0')
            $str = sprintf("%4.{$decimals}f", $number);
        } else {
            $str = (int) $number;
        }
        
        return $str;
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = array();

        $arr[] = &$mform->createElement('text', "{$fieldname}[0]", null, array('size'=>'6'));
        $mform->setType("{$fieldname}[0]", PARAM_FLOAT);
        $mform->setDefault("{$fieldname}[0]", $value[0]);
        $mform->disabledIf("{$fieldname}[0]", "searchoperator$i", 'eq', '');

        $arr[] = &$mform->createElement('text', "{$fieldname}[1]", null, array('size'=>'6'));
        $mform->setType("{$fieldname}[1]", PARAM_FLOAT);
        $mform->setDefault("{$fieldname}[1]", $value[1]);
        $mform->disabledIf("{$fieldname}[1]", "searchoperator$i", 'neq', 'BETWEEN');

        return array($arr, null);
    }

}
