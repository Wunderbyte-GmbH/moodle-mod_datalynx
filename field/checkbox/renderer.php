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
 * @subpackage checkbox
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

require_once(dirname(__FILE__) . "/../renderer.php");

/**
 * Class datalynxfield_checkbox_renderer Renderer for checkbox field type
 */
class datalynxfield_checkbox_renderer extends datalynxfield_renderer {

    /* @var datalynxfield_checkbox */
    protected $_field = null;

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $menuoptions = $field->options_menu();
        $required = $options['required'];

        $content = !empty($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : null;

        $separator = $field->separators[(int) $field->get('param2')]['chr'];

        $elemgrp = array();
        foreach ($menuoptions as $i => $option) {
            $elemgrp[] = &$mform->createElement('advcheckbox', $i, null, $option, null, array(null, $i));
        }

        $mform->addGroup($elemgrp, $fieldname, null, $separator, true);

        $selected = array();
        if ($entryid > 0 and $content) {
            $selectedraw = array_diff(array_unique(explode('#', $content)), ['']);

            foreach ($selectedraw as $item) {
                $selected[$item] = $item;
            }
        }

        // check for default values
        if (!$selected and $field->get('param2')) {
            $selected = $field->default_values();
        }

        $mform->getElement($fieldname)->setValue($selected);

        if ($required) {
            $mform->addGroupRule($fieldname, get_string('err_required', 'form'), 'required', null, 1);
        }
    }

    /**
     *
     */
    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};

            $options = $field->options_menu();

            $contents = explode('#', $content);

            $str = array();
            foreach ($options as $key => $option) {
                $selected = (int) in_array($key, $contents);
                if ($selected) {
                    $str[] = $option;
                }
            }
            $separator = $field->separators[(int) $field->get('param3')]['chr'];
            if ($separator == '</li><li>' && count($str) > 0) {
                $str = '<ul><li>' . implode($separator, $str) . '</li></ul>';
            } else {
                $str = implode($separator, $str);
            }
        } else {
            $str = '';
        }

        return $str;
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        global $CFG;
        HTML_QuickForm::registerElementType('checkboxgroup', "$CFG->dirroot/mod/datalynx/checkboxgroup/checkboxgroup.php", 'HTML_QuickForm_checkboxgroup');

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

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();
        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = array();
        foreach ($tags as $tag) {
            list(,$behavior,) = $this->process_tag($tag);
            /* @var $behavior datalynx_field_behavior */

            if ($behavior->is_required()) {
                if (empty($formdata->$formfieldname)) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                } else {
                    $empty = true;
                    foreach ($formdata->$formfieldname as $value) {
                        $empty = $empty && empty($value);
                    }
                    if ($empty) {
                        $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                    }
                }
            }
        }

        return $errors;
    }

}
