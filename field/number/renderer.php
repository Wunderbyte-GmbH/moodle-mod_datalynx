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
 * @subpackage number
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/text/renderer.php");

/**
 */
class datalynxfield_number_renderer extends datalynxfield_text_renderer {

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_text_renderer::render_edit_mode()
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);
        $content = '';
        if (isset($entry->{"c{$fieldid}_content"}) and $entry->{"c{$fieldid}_content"} === "0" or !empty(
                $entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        }
        $fieldattr = array();
        $mform->addElement('text', $fieldname, null, $fieldattr);
        $mform->setType($fieldname, PARAM_RAW);
        $mform->addRule($fieldname, get_string('err_numeric', 'datalynx'), 'numeric', null, 'client');
        $mform->setDefault($fieldname, $content);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }

    /**
     *
     * {@inheritdoc}
     * @see datalynxfield_text_renderer::render_display_mode()
     */
    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $outputemptystring = !empty($field->get('param4')) ? $field->get('param4') : 0;
        if (!isset($entry->{"c{$fieldid}_content"}) and !$outputemptystring) {
            return 0;
        } else if (!isset($entry->{"c{$fieldid}_content"})) {
            return '';
        }
        $number = (float) $entry->{"c{$fieldid}_content"};
        $decimals = (float) trim($field->get('param1'));
        // Only apply number formatting if param1 contains an integer number >= 0:.
        if ($decimals) {
            // Removes leading zeros (eg. '007' -> '7'; '00' -> '0').
            $str = sprintf("%4.{$decimals}f", $number);
        } else {
            $str = (float) $number;
        }
        return $str;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_text_renderer::render_search_mode()
     */
    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = array();

        $arr[] = &$mform->createElement('text', "{$fieldname}[0]", null, array('size' => '6'));
        $mform->setType("{$fieldname}[0]", PARAM_FLOAT);
        $mform->setDefault("{$fieldname}[0]", isset($value[0]) ? $value[0] : '');
        $mform->disabledIf("{$fieldname}[0]", "searchoperator$i", 'eq', '');

        $arr[] = &$mform->createElement('text', "{$fieldname}[1]", null, array('size' => '6'));
        $mform->setType("{$fieldname}[1]", PARAM_FLOAT);
        $mform->setDefault("{$fieldname}[1]", isset($value[1]) ? $value[1] : '');
        $mform->disabledIf("{$fieldname}[1]", "searchoperator$i", 'neq', 'BETWEEN');

        return array($arr, null);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_text_renderer::validate()
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() and isset($formdata->$formfieldname)) {
                $value = optional_param($formfieldname, '', PARAM_RAW);
                if (!is_numeric($value)) {
                    $errors[$formfieldname] = get_string('err_numeric', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
