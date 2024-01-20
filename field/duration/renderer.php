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
 * @subpackage duration
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield_duration_renderer extends datalynxfield_renderer {

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $mform->addElement('duration', $fieldname, '', array('optional' => null));
        $mform->setType($fieldname, PARAM_ALPHANUMEXT);

        if ($entryid > 0 && !empty($entry->{"c{$fieldid}_content"})) {
            $number = $entry->{"c{$fieldid}_content"};
            $mform->setDefault($fieldname, $number);
        }

        $required = !empty($options['required']);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }

    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->_field;
        $fieldid = $field->id();

        // A duration of 0 means that this field was not set by the user.
        if (isset($entry->{"c{$fieldid}_content"}) && $entry->{"c{$fieldid}_content"} != 0) {
            $duration = (int) $entry->{"c{$fieldid}_content"};
        } else {
            $duration = '';
        }
        // Durations always are exported as their value in seconds to csv.
        if ($exportcsv = optional_param('exportcsv', '', PARAM_ALPHA)) {
            return $duration;
        }

        $format = !empty($options['format']) ? $options['format'] : '';
        if ($duration !== '') {
            list($value, $unit) = $field->seconds_to_unit($duration);
            $units = $field->get_units();
            switch ($format) {
                case 'unit':
                    return $units[$unit];
                    break;

                case 'value':
                    return $value;
                    break;

                case 'seconds':
                    return $duration;
                    break;

                case 'interval':
                    return format_time($duration);
                    break;

                default:
                    return $value . ' ' . $units[$unit];
                    break;
            }
        }
        return '';
    }

    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = array();

        $arr[] = &$mform->createElement('duration', "{$fieldname}_from");
        $mform->setType("{$fieldname}_from", PARAM_INT);
        if (isset($value[0])) {
            $mform->setDefault("{$fieldname}_from", $value[0]);
        }
        $mform->disabledIf("{$fieldname}_from[number]", "searchoperator$i", 'eq', '');
        $mform->disabledIf("{$fieldname}_from[timeunit]", "searchoperator$i", 'eq', '');

        $arr[] = &$mform->createElement('duration', "{$fieldname}_to");
        $mform->setType("{$fieldname}_to", PARAM_INT);
        if (isset($value[1])) {
            $mform->setDefault("{$fieldname}_to", $value[1]);
        }
        $mform->disabledIf("{$fieldname}_to[number]", "searchoperator$i", 'neq', 'BETWEEN');
        $mform->disabledIf("{$fieldname}_to[timeunit]", "searchoperator$i", 'neq', 'BETWEEN');

        return array($arr, null);
    }

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

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() && isset($formdata->$formfieldname)) {
                $value = optional_param_array($formfieldname, [], PARAM_RAW)['number'];
                $intvalue = intval($value);
                if ($value !== "$intvalue") {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                }
            }
        }

        return $errors;
    }
}

