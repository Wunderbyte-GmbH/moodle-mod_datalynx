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
 * @package datalynxfield_text
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
    /**
     * Render the field in edit mode.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     * @return void
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);
        $autocomplete = $field->get('param9');

        $content = '';
        if ($entryid > 0 && !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        }

        // Render disabled input as raw HTML — Moodle's QuickForm does not reliably
        // propagate the disabled attribute through its template-based rendering.
        if (!empty($options['disabled'])) {
            $mform->addElement(
                'html',
                '<input type="text" name="' . $fieldname . '" value="' . s($content) .
                '" size="30" disabled="disabled" class="form-control">'
            );
            return;
        }

        $fieldattr = [];
        $fieldattr['size'] = 30;

        if ($field->get('param4')) {
            $fieldattr['class'] = s($field->get('param4'));
        }
        if ($autocomplete) {
            $fieldattr['class'] = "datalynxfield_datalynxview datalynxview_{$fieldid}_{$entryid}";
            // If param10 is empty take the values of this field itself for autocomplete options.
            $reffieldid = $field->field->param10 ? $field->field->param10 : $field->field->id;
            $menu = [
                    '_qf__force_multiselect_submission'
                    => get_string('choose')] + $field->df->get_distinct_textfieldvalues_by_id($reffieldid);
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
            // Special handling for lettersonly to allow Unicode letters.
            if ($format === 'lettersonly') {
                $mform->addRule($fieldname, get_string('err_lettersonly', 'form'), 'regex', '/^[\p{L}]+$/u', 'client');
            } else {
                $mform->addRule($fieldname, null, $format, null, 'client');
            }
            // Adjust type.
            switch ($format) {
                case 'alphanumeric':
                    $mform->setType($fieldname, PARAM_ALPHANUM);
                    break;
                case 'lettersonly':
                    $mform->setType($fieldname, PARAM_TEXT); // Use PARAM_TEXT to allow Unicode.
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
                    $val = [$min, $max];
                    break;
            }
            if ($val !== false) {
                $mform->addRule($fieldname, null, $length, $val, 'client');
            }
        }
    }

    /**
     * Render the field in display mode.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();
        $nolinkend = "";
        $nolinkstart = "";
        $formatoptions = [];
        if (isset($entry->{"c{$fieldid}_content"}) && !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};

            $format = FORMAT_PLAIN;
            if ($field->get('param1') == '1') { // We are autolinking this field, so disable
                                                // linking.
                                                // Within us.
                $nolinkstart = '<span class="nolink">';
                $nolinkend = '</span>';
                $formatoptions['filter'] = false;
            }

            $str = $nolinkstart . format_string($content, $format, $formatoptions) . $nolinkend;
        } else {
            $str = '';
        }
        return $str;
    }

    /**
     * Render the field in search mode.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $field = $this->field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_$fieldid";
        $autocomplete = $field->get('param9');
        $arr = [];

        if ($autocomplete) {
            $fieldattr['class'] = "datalynxfield_datalynxview datalynxview_{$fieldid}";
            // If param10 is empty take the values of this field itself for autocomplete options.
            $reffieldid = $field->field->param10 ? $field->field->param10 : $field->field->id;
            $menu = [
                    '_qf__force_multiselect_submission'
                    => get_string('choose')] + $field->df->get_distinct_textfieldvalues_by_id($reffieldid);
            $fieldattr['tags'] = true;
            $arr[] = &$mform->createElement('autocomplete', $fieldname, null, $menu, $fieldattr);
            $mform->setType($fieldname, PARAM_NOTAGS);
        } else {
            $arr[] = &$mform->createElement('text', $fieldname, null, ['size' => '32']);
            $mform->setType($fieldname, PARAM_NOTAGS);
            $mform->setDefault($fieldname, $value);
            $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');
        }
        return [$arr, null];
    }

    /**
     * Validate the field input.
     *
     * @param int $entryid
     * @param array $tags
     * @param stdClass $formdata
     * @return array
     */
    public function validate($entryid, $tags, $formdata) {
        global $DB;

        $fieldid = $this->field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";
        $param8 = $this->field->get('param8');

        $errors = [];
        foreach ($tags as $tag) {
            [, $behavior, ] = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() && isset($formdata->$formfieldname)) {
                if (!clean_param($formdata->$formfieldname, PARAM_NOTAGS)) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                }
            }

            if (!empty($param8) && isset($formdata->$formfieldname)) {
                // Check uniquenes!
                if (
                    $DB->record_exists_sql(
                        "SELECT id
                                              FROM {datalynx_contents} c
                                             WHERE c.fieldid = :fieldid
                                               AND c.entryid <> :entryid
                                               AND c.content LIKE :content",
                        ['fieldid' => $fieldid,
                                'entryid' => $entryid,
                        'content' => $formdata->$formfieldname]
                    )
                ) {
                    // It's not the first of it's kind!
                    $errors[$formfieldname] = get_string('uniquerequired', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
