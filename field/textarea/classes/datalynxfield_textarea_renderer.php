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
 * @package datalynxfield_textarea
 * @subpackage textarea
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use mod_datalynx\local\field\datalynxfield_renderer;

defined('MOODLE_INTERNAL') || die();
/**
 * Class datalynxfield_textarea_renderer Renderer for textarea field type
 */
class datalynxfield_textarea_renderer extends datalynxfield_renderer {
    /**
     * Render the field in edit mode.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $attr = [];
        $attr['cols'] = !$field->get('param2') ? 40 : $field->get('param2');
        $attr['rows'] = !$field->get('param3') ? 20 : $field->get('param3');

        $data = new stdClass();
        $data->$fieldname = isset($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : '';
        $required = !empty($options['required']);

        $mform->addElement('textarea', $fieldname, null, $attr);
        $mform->setDefault($fieldname, $data->$fieldname);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
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

        if (isset($entry->{"c{$fieldid}_content"})) {
            $text = $entry->{"c{$fieldid}_content"};
            $text = str_replace("\r", "", $text); // Remove carriage returns, bug#887.
            $format = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : FORMAT_PLAIN;

            $options = new stdClass();
            $options->para = false;
            $str = format_text($text, $format, $options);
            return $str;
        } else {
            return '';
        }
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
        $fieldid = $this->field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = [];
        $arr[] = &$mform->createElement('text', $fieldname, null, ['size' => '32']);
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');

        return [$arr, null];
    }

    /**
     * Validate the field input.
     *
     * @param int $entryid
     * @param array $tags
     * @param stdClass $formdata
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = [];
        foreach ($tags as $tag) {
            [, $behavior, ] = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() && isset($formdata->$formfieldname)) {
                if (!clean_param($formdata->$formfieldname, PARAM_NOTAGS)) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
