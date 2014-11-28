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
 * @subpackage gradeitem
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 *
 */
class datalynxfield_gradeitem_renderer extends datalynxfield_renderer {

    /**
     *
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->name();

        $replacements = array();

        // there is only one possible tag here, no edit
        $tag = "[[$fieldname]]";
        $replacements[$tag] = array('html', $this->display_grade($entry));
/*
        switch ($field->infotype) {
            case 'checkbox':
                $replacements[$tag] = array('html', $this->display_checkbox($entry));
                break;
            case 'datetime':
                $replacements[$tag] = array('html', $this->display_datetime($entry));
                break;
            case 'menu':
            case 'text':
                $replacements[$tag] = array('html', $this->display_text($entry));
                break;
            case 'textarea':
                $replacements[$tag] = array('html', $this->display_richtext($entry));
                break;
            default:
                $replacements[$tag] = '';
        }
*/
        return $replacements;
    }

    /**
     *
     */
    protected function display_grade($entry) {
        $field = $this->_field;
        $fieldid = $field->id();

        if (!isset($entry->{"c{$fieldid}_content"})) {
            return '';
        }
        
        $number = (float) $entry->{"c{$fieldid}_content"};       
        $decimals = 2;
        // only apply number formatting if param1 contains an integer number >= 0:
        if ($decimals) {
            // removes leading zeros (eg. '007' -> '7'; '00' -> '0')
            $str = sprintf("%4.{$decimals}f", $number);
        } else {
            $str = (int) $number;
        }
        return $str;
    }

}
