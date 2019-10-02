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
 * @subpackage time
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield_time_renderer extends datalynxfield_renderer {

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = 0;
        if ($entryid > 0 and !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        }

        $includetime = empty($options['date']) && !isset($field->dateonly);

        if ($field->masked) {
            $this->render_masked_selector($mform, $entry, $content, $includetime, $options);
        } else {
            $this->render_standard_selector($mform, $entry, $content, $includetime, $options);
        }
    }

    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();

        $strtime = '';
        if (isset($entry->{"c{$fieldid}_content"})) {
            if ($content = $entry->{"c{$fieldid}_content"}) {
                if (!empty($params['format'])) {
                    $strtime = userdate($content, $params['format']);
                } else if (isset($params['date']) || $field->dateonly) {
                    $strtime = userdate($content, get_string("strftimedate"));
                } else if (isset($params['timestamp'])) {
                    $strtime = $content;
                } else if (!empty($field->displayformat)) {
                    $strtime = userdate($content, $field->displayformat);
                } else {
                    $date = getdate($content);
                    if ($date['seconds'] || $date['minutes'] || $date['hours']) {
                        $strtime = userdate($content, get_string("strftimedatetime"));
                    } else {
                        $strtime = userdate($content, get_string("strftimedate"));
                    }
                }
                // Time is exported as timestamp to csv.
                if (optional_param('exportcsv', '', PARAM_ALPHA)) {
                    return $content;
                }
            }
        }
        return $strtime;
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";

        $elements = array();

        // With normal filter display whole form and disable cases.
        if ($mform->_formName != 'mod_datalynx_customfilter_frontend_form') {
            $elements[] = &$mform->createElement('date_time_selector', "{$fieldname}_from", get_string('from'));
            $elements[] = &$mform->createElement('date_time_selector', "{$fieldname}_to", get_string('to'));
            if (isset($value[0])) {
                $mform->setDefault("{$fieldname}_from", $value[0]);
            }
            if (isset($value[1])) {
                $mform->setDefault("{$fieldname}_to", $value[1]);
            }
            foreach (array('year', 'month', 'day', 'hour', 'minute') as $fieldidentifier) {
                $mform->disabledIf("{$fieldname}_to[$fieldidentifier]", "searchoperator$i", 'neq', 'BETWEEN');
            }
            foreach (array('year', 'month', 'day', 'hour', 'minute') as $fieldidentifier) {
                $mform->disabledIf("{$fieldname}_from[$fieldidentifier]", "searchoperator$i", 'eq', '');
            }
            if ($field->dateonly) {
                // Deactivate form elements for min and seconds when field is date only and operator is "=".
                foreach (array('hour', 'minute') as $fieldidentifier) {
                    $mform->disabledIf("{$fieldname}_from[$fieldidentifier]", "searchoperator$i", 'eq', '=');
                }
            }
        }

        // With customfilter we have to simplify the form.
        if ($mform->_formName == 'mod_datalynx_customfilter_frontend_form') {
            $attr = array('optional' => true); // Allows date_time to be enabled, passes 0 if disabled.
            $elements[] = &$mform->createElement('date_time_selector', "{$fieldname}[0]", get_string('from'), $attr);
            $elements[] = &$mform->createElement('date_time_selector', "{$fieldname}[1]", get_string('to'));
        }

        $separators = array('<br>', '<br>');
        return array($elements, $separators);
    }

    /**
     */
    protected function render_standard_selector(&$mform, $entry, $content, $includetime = true,
            array $options = array()) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        // If date only don't add time to selector.
        $time = $includetime ? 'time_' : '';
        $elementoptions = array();
        // Optional.
        $elementoptions['optional'] = true;
        // Start year.
        if ($field->startyear) {
            $elementoptions['startyear'] = $field->startyear;
        }
        // End year.
        if ($field->stopyear) {
            $elementoptions['stopyear'] = $field->stopyear;
        }
        $mform->addElement("date_{$time}selector", $fieldname, null, $elementoptions);
        $mform->setDefault($fieldname, $content);
        $required = !empty($options['required']);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
    }

    /**
     *
     * @param MoodleQuickForm $mform
     * @param $entry
     * @param $content
     * @param bool $includetime
     * @param array $options
     * @throws coding_exception
     */
    protected function render_masked_selector(MoodleQuickForm &$mform, $entry, $content,
            $includetime = true, array $options = array()) {
        $field = $this->_field;
        $entryid = $entry->id;
        $fieldid = $field->id();
        $fieldname = "field_{$fieldid}_{$entryid}";

        // TODO some defaults that need to be set in the field settings.
        $step = 5;
        $startyear = $field->startyear ? $field->startyear : 1970;
        $stopyear = $field->stopyear ? $field->stopyear : 2020;
        $maskday = get_string('day');
        $maskmonth = get_string('month');
        $maskyear = get_string('year');

        $days = array();
        for ($i = 1; $i <= 31; $i++) {
            $days[$i] = $i;
        }
        $months = array();
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = userdate(mktime(0, 0, 0, $i, 10), "%B");
        }
        $years = array();
        for ($i = $startyear; $i <= $stopyear; $i++) {
            $years[$i] = $i;
        }

        $grp = array();
        $grp[] = &$mform->createElement('select', "{$fieldname}[day]", null,
                array(0 => $maskday) + $days);
        $grp[] = &$mform->createElement('select', "{$fieldname}[month]", null,
                array(0 => $maskmonth) + $months);
        $grp[] = &$mform->createElement('select', "{$fieldname}[year]", null,
                array(0 => $maskyear) + $years);

        // If time add hours and minutes.
        if ($includetime) {
            $maskhour = get_string('hour', 'datalynxfield_time');
            $maskminute = get_string('minute', 'datalynxfield_time');

            $hours = array();
            for ($i = 0; $i <= 23; $i++) {
                $hours[$i] = sprintf("%02d", $i);
            }
            $minutes = array();
            for ($i = 0; $i < 60; $i += $step) {
                $minutes[$i] = sprintf("%02d", $i);
            }

            $grp[] = &$mform->createElement('select', "{$fieldname}[hour]", null,
                    array(0 => $maskhour) + $hours);
            $grp[] = &$mform->createElement('select', "{$fieldname}[minute]", null,
                    array(0 => $maskminute) + $minutes);
        }

        $mform->addGroup($grp, "grp$fieldname", null, '', false);
        // Set field values.
        if ($content) {
            list($day, $month, $year, $hour, $minute) = explode(':', date('d:n:Y:G:i', $content));
            $mform->setDefault("{$fieldname}[day]", (int) $day);
            $mform->setDefault("{$fieldname}[month]", (int) $month);
            $mform->setDefault("{$fieldname}[year]", (int) $year);
            // Defaults for time.
            if ($includetime) {
                $mform->setDefault("{$fieldname}[hour]", (int) $hour);
                $mform->setDefault("{$fieldname}[minute]", (int) $minute);
            }
        }
        // Add enabled fake field.
        $mform->addElement('hidden', "{$fieldname}[enabled]", 1);
        $mform->setType("{$fieldname}[enabled]", PARAM_INT);
        $required = !empty($options['required']);
        if ($required) {
            if ($includetime) {
                $mform->addGroupRule("grp$fieldname",
                        array(
                                "{$fieldname}[day]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx', get_string('day')),
                                                'nonzero', null, 'client'
                                        )
                                ),
                                "{$fieldname}[month]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx',
                                                        get_string('month')), 'nonzero', null, 'client'
                                        )
                                ),
                                "{$fieldname}[year]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx',
                                                        get_string('year')), 'nonzero', null, 'client'
                                        )
                                ),
                                "{$fieldname}[hour]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx',
                                                        get_string('hour')), 'nonzero', null, 'client'
                                        )
                                ),
                                "{$fieldname}[minute]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx',
                                                        get_string('minute')), 'nonzero', null, 'client'
                                        )
                                )
                        )
                );
            } else {
                $mform->addGroupRule("grp$fieldname",
                        array(
                                "{$fieldname}[day]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx', get_string('day')),
                                                'nonzero', null, 'client'
                                        )
                                ),
                                "{$fieldname}[month]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx',
                                                        get_string('month')), 'nonzero', null, 'client'
                                        )
                                ),
                                "{$fieldname}[year]" => array(
                                        array(
                                                get_string('time_field_required', 'datalynx',
                                                        get_string('year')), 'nonzero', null, 'client'
                                        )
                                )
                        )
                );
            }
        }
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        // Date without time.
        $patterns["[[$fieldname:date]]"] = array(true);
        // Date with time.
        $patterns["[[$fieldname:timestamp]]"] = array(true);
        // Minute (M).
        $patterns["[[$fieldname:minute]]"] = array(false);
        // Hour (H).
        $patterns["[[$fieldname:hour]]"] = array(false);
        // Day (a).
        $patterns["[[$fieldname:day]]"] = array(false);
        $patterns["[[$fieldname:d]]"] = array(false);
        // Week (V).
        $patterns["[[$fieldname:week]]"] = array(false);
        // Month (b).
        $patterns["[[$fieldname:month]]"] = array(false);
        $patterns["[[$fieldname:m]]"] = array(false);
        // Year (G).
        $patterns["[[$fieldname:year]]"] = array(false);
        $patterns["[[$fieldname:Y]]"] = array(false);

        return $patterns;
    }

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() and
                    !isset(optional_param_array($formfieldname, [], PARAM_RAW)['enabled'])
            ) {
                $errors[$formfieldname] = get_string('check_enable', 'datalynx');
            }
        }

        return $errors;
    }
}
