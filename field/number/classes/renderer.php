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
use MoodleQuickForm;
use stdClass;

/**
 * Renderer class for the datalynxfield_number field type.
 */
class renderer extends TextRenderer {
    // This is a datalynx field renderer, not a core_renderer: $this->page and
    // $this->output do not exist here.
    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal

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

        // A slider format keeps the text element as the value carrier — validation, submission and
        // format_content() stay exactly as they are, and without JavaScript the plain number input
        // is still usable. Only the slider chrome is added on top, and the module hides the input.
        $slidervalues = [];
        $fieldformat = $options['field_format'] ?? null;
        if ($fieldformat instanceof field_format) {
            $slidervalues = $fieldformat->get_slider_values();
        }

        $fieldattr = [];
        if ($slidervalues) {
            $fieldattr['id'] = "id_{$fieldname}_slidervalue";
        }
        $mform->addElement('text', $fieldname, null, $fieldattr);
        $mform->setType($fieldname, PARAM_RAW);
        $mform->addRule($fieldname, get_string('errnumeric', 'datalynx'), 'numeric', null, 'client');
        $mform->setDefault($fieldname, $content);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }

        if ($slidervalues) {
            $this->add_slider($mform, $fieldformat, $slidervalues, $fieldattr['id'], $content);
        }
    }

    /**
     * Adds the slider chrome for a number field rendered by a slider field format.
     *
     * @param MoodleQuickForm $mform The Moodle form instance.
     * @param field_format $fieldformat The slider format.
     * @param float[] $values The selectable values.
     * @param string $inputid Element id of the text input carrying the value.
     * @param string $content The current value of the entry.
     * @return void
     */
    protected function add_slider(
        MoodleQuickForm &$mform,
        field_format $fieldformat,
        array $values,
        string $inputid,
        string $content
    ): void {
        global $OUTPUT;

        $this->require_js();

        $unit = (string) $fieldformat->get_setting('sliderunit', '');
        $index = $this->get_slider_index($values, $content);
        // The readout labels are built here rather than in the browser so that the live value, the
        // tick labels and the number the entry stores agree on the language's decimal separator.
        $labels = [];
        foreach ($values as $value) {
            $labels[] = $this->format_slider_label($value, $unit);
        }
        $context = [
            'inputid' => $inputid,
            'values' => json_encode($values),
            'labels' => json_encode($labels),
            'label' => $this->field->name(),
            'max' => count($values) - 1,
            'index' => $index,
            'readout' => $labels[$index],
            'showticks' => !empty($fieldformat->get_setting('sliderticks')),
            'startlabel' => reset($labels),
            'endlabel' => end($labels),
        ];

        // The slider is plain markup: adding it as a form element would wrap it in another
        // .fitem grid row.
        $mform->addElement(
            'html',
            $OUTPUT->render_from_template('mod_datalynx/field_number_slider', $context)
        );
    }

    /**
     * Position of the slider for the entry's current value.
     *
     * Values that are not on the scale (for instance because the format was reconfigured after the
     * entry was saved) snap to the closest available position. An empty value starts at the bottom.
     *
     * @param float[] $values The selectable values.
     * @param string $content The current value of the entry.
     * @return int
     */
    protected function get_slider_index(array $values, string $content): int {
        if (!is_numeric($content)) {
            return 0;
        }

        $current = (float) $content;
        $closest = 0;
        $distance = null;
        foreach ($values as $index => $value) {
            $candidate = abs($value - $current);
            if ($distance === null || $candidate < $distance) {
                $distance = $candidate;
                $closest = $index;
            }
        }

        return $closest;
    }

    /**
     * Renders one of the tick labels printed under the ends of the slider track.
     *
     * @param float $value
     * @param string $unit
     * @return string
     */
    protected function format_slider_label(float $value, string $unit): string {
        return format_float($value, -1, true, true) . $unit;
    }

    /**
     * Request the client side initialiser for the current page.
     *
     * Harmless when the field is rendered inside a web service call: the page requirements of that
     * request are simply discarded.
     *
     * @return void
     */
    protected function require_js(): void {
        global $PAGE;

        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $PAGE->requires->js_call_amd('mod_datalynx/numberslider', 'init');
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
        $fieldformat = $options['field_format'] ?? null;
        if ($fieldformat) {
            $decimals = $fieldformat->get_setting('decimals');
        } else {
            $decimals = trim($field->get('param1'));
        }
        // Only apply number formatting if decimals is set and is an integer >= 0.
        if ($decimals !== null && $decimals !== '') {
            $decimals = (int) $decimals;
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
