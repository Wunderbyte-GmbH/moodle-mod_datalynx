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
 * Renderer for the number field type.
 *
 * @package datalynxfield_number
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_number;
use datalynxfield_text\renderer as TextRenderer;
use stdClass;
use MoodleQuickForm;



/**
 * Renderer class for the datalynxfield_number field type.
 */
class renderer extends TextRenderer {
    /**
     *
     * {@inheritDoc}
     * @param MoodleQuickForm $mform The Moodle form instance.
     * @param stdClass $entry The entry object.
     * @param array $options Rendering options.
     * @see datalynxfield_text_renderer::render_edit_mode()
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);
        $content = '';
        if (
            isset($entry->{"c{$fieldid}_content"}) && $entry->{"c{$fieldid}_content"} === "0" || !empty(
                $entry->{"c{$fieldid}_content"}
            )
        ) {
            $content = $entry->{"c{$fieldid}_content"};
        }
        $fieldattr = [];
        $mform->addElement('text', $fieldname, null, $fieldattr);
        $mform->setType($fieldname, PARAM_RAW);
        $mform->addRule($fieldname, get_string('errnumeric', 'datalynx'), 'numeric', null, 'client');
        $mform->setDefault($fieldname, $content);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }

    /**
     *
     * {@inheritdoc}
     * @param stdClass $entry The entry object.
     * @param array $options Rendering options.
     * @return string
     * @see datalynxfield_text_renderer::render_display_mode()
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();
        $outputemptystring = !empty($field->get('param4')) ? $field->get('param4') : 0;
        if (!isset($entry->{"c{$fieldid}_content"}) && !$outputemptystring) {
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
     * {@inheritDoc}$str
     * @param MoodleQuickForm $mform The Moodle form instance.
     * @param int $i The search index.
     * @param string $value The current search value.
     * @return array
     * @see datalynxfield_text_renderer::render_search_mode()
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $fieldid = $this->field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = [];

        $arr[] = &$mform->createElement('text', "{$fieldname}[0]", null, ['size' => '6']);
        $mform->setType("{$fieldname}[0]", PARAM_FLOAT);
        $mform->setDefault("{$fieldname}[0]", isset($value[0]) ? $value[0] : '');
        $mform->disabledIf("{$fieldname}[0]", "searchoperator$i", 'eq', '');

        $arr[] = &$mform->createElement('text', "{$fieldname}[1]", null, ['size' => '6']);
        $mform->setType("{$fieldname}[1]", PARAM_FLOAT);
        $mform->setDefault("{$fieldname}[1]", isset($value[1]) ? $value[1] : '');
        $mform->disabledIf("{$fieldname}[1]", "searchoperator$i", 'neq', 'BETWEEN');

        return [$arr, null];
    }

    /**
     *
     * {@inheritDoc}
     * @param int $entryid The entry id.
     * @param array $tags The field tags.
     * @param mixed $formdata The submitted form data.
     * @return array
     * @see datalynxfield_text_renderer::validate()
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = [];
        foreach ($tags as $tag) {
            [, $behavior, ] = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() && isset($formdata->$formfieldname)) {
                $value = optional_param($formfieldname, '', PARAM_RAW);
                if (!is_numeric($value)) {
                    $errors[$formfieldname] = get_string('errnumeric', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
