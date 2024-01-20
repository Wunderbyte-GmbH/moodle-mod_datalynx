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
 * @subpackage text
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . "/../renderer.php");

/**
 * Class datalynxfield_text_renderer Renderer for text field type
 */
class datalynxfield_text_renderer extends datalynxfield_renderer {

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);
        $autocomplete = $field->get('param9');

        $content = '';
        if ($entryid > 0 && !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        }
        $fieldattr = array();
        $fieldattr['size'] = 30;

        if ($field->get('param4')) {
            $fieldattr['class'] = s($field->get('param4'));
        }
        if ($autocomplete) {
            $fieldattr['class'] = "datalynxfield_datalynxview datalynxview_{$fieldid}_{$entryid}";
            // If param10 is empty take the values of this field itself for autocomplete options.
            $reffieldid = $field->field->param10 ? $field->field->param10 : $field->field->id;
            $menu = array(
                    '_qf__force_multiselect_submission'
                    => get_string('choose')) + $field->df->get_distinct_textfieldvalues_by_id($reffieldid);
        }

        if ($autocomplete) {  // Render as autocomplete field if param9 is not empty.
            $fieldattr['tags'] = true;
            $mform->addElement('autocomplete', $fieldname, null, $menu, $fieldattr);
            $mform->setType($fieldname, PARAM_NOTAGS);
        } else {
            $mform->addElement('text', $fieldname, null, $fieldattr);
            $mform->setType($fieldname, PARAM_TEXT);
        }
        $mform->setDefault($fieldname, $content);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
        // Format rule.
        $format = $field->get('param4');
        if (!$format && $field->type == 'number') {
            $format = 'numeric';
        }
        if ($format) {
            $mform->addRule($fieldname, null, $format, null, 'client');
            // Adjust type.
            switch ($format) {
                case 'alphanumeric':
                    $mform->setType($fieldname, PARAM_ALPHANUM);
                    break;
                case 'lettersonly':
                    $mform->setType($fieldname, PARAM_ALPHA);
                    break;
                case 'numeric':
                    $mform->setType($fieldname, PARAM_INT);
                    break;
                case 'email':
                    $mform->setType($fieldname, PARAM_EMAIL);
                    break;
            }
        }
        // Length rule.
        if ($length = $field->get('param5')) {
            ($min = $field->get('param6')) || ($min = 0);
            ($max = $field->get('param7')) || ($max = 64);

            $val = false;
            switch ($length) {
                case 'minlength':
                    $val = $min;
                    break;
                case 'maxlength':
                    $val = $max;
                    break;
                case 'rangelength':
                    $val = array($min, $max);
                    break;
            }
            if ($val !== false) {
                $mform->addRule($fieldname, null, $length, $val, 'client');
            }
        }
    }

    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->_field;
        $fieldid = $field->id();
        $nolinkend = "";
        $nolinkstart = "";
        if (isset($entry->{"c{$fieldid}_content"}) && !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};

            $options = new stdClass();
            $options->para = false;

            $format = FORMAT_PLAIN;
            if ($field->get('param1') == '1') { // We are autolinking this field, so disable
                                                // linking.
                                                // Within us.
                $nolinkstart = '<span class="nolink">';
                $nolinkend = '</span>';
                $options->filter = false;
            }

            $str = $nolinkstart . format_string($content, $format, $options) . $nolinkend;
        } else {
            $str = '';
        }
        return $str;
    }

    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_$fieldid";
        $autocomplete = $field->get('param9');
        $arr = array();

        if ($autocomplete) {
            $fieldattr['class'] = "datalynxfield_datalynxview datalynxview_{$fieldid}";
            // If param10 is empty take the values of this field itself for autocomplete options.
            $reffieldid = $field->field->param10 ? $field->field->param10 : $field->field->id;
            $menu = array(
                    '_qf__force_multiselect_submission'
                    => get_string('choose')) + $field->df->get_distinct_textfieldvalues_by_id($reffieldid);
            $fieldattr['tags'] = true;
            $arr[] = &$mform->createElement('autocomplete', $fieldname, null, $menu, $fieldattr);
            $mform->setType($fieldname, PARAM_NOTAGS);
        } else {
            $arr[] = &$mform->createElement('text', $fieldname, null, array('size' => '32'));
            $mform->setType($fieldname, PARAM_NOTAGS);
            $mform->setDefault($fieldname, $value);
            $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');
        }
        return array($arr, null);
    }

    public function validate($entryid, $tags, $formdata) {
        global $DB;

        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";
        $param8 = $this->_field->get('param8');

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() && isset($formdata->$formfieldname)) {
                if (!clean_param($formdata->$formfieldname, PARAM_NOTAGS)) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                }
            }

            if (!empty($param8) && isset($formdata->$formfieldname)) {
                // Check uniquenes!
                if ($DB->record_exists_sql("SELECT id
                                              FROM {datalynx_contents} c
                                             WHERE c.fieldid = :fieldid
                                               AND c.entryid <> :entryid
                                               AND c.content LIKE :content",
                        array('fieldid' => $fieldid,
                                'entryid' => $entryid,
                                'content' => $formdata->$formfieldname))
                ) {
                    // It's not the first of it's kind!
                    $errors[$formfieldname] = get_string('unique_required', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
