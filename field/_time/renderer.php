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
 * @subpackage _time
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield__time_renderer extends datalynxfield_renderer {

    /**
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->get('internalname');

        // No edit mode.
        $replacements = array();

        foreach ($tags as $tag) {
            // Display nothing on new entries.
            if ($entry->id < 0) {
                $replacements[$tag] = '';
            } else {
                $format = (strpos($tag, "{$fieldname}:") !== false ? str_replace("{$fieldname}:",
                        '', trim($tag, '#@')) : '');
                switch ($format) {
                    case 'date':
                        $format = get_string('strftimedate');
                        break;
                    case 'timestamp':
                        $format = '';
                        break;
                    case 'minute':
                        $format = '%M';
                        break;
                    case 'hour':
                        $format = '%H';
                        break;
                    case 'day':
                        $format = '%a';
                        break;
                    case 'week':
                        $format = '%V';
                        break;
                    case 'month':
                        $format = '%b';
                        break;
                    case 'm':
                        $format = '%m';
                        break;
                    case 'year':
                    case 'Y':
                        $format = '%Y';
                        break;
                }
                $replacements[$tag] = array('html', userdate($entry->{$fieldname}, $format));
            }
        }

        return $replacements;
    }

    /**
     * Render the filter form for editing and updating the filter values for the time field.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $fieldid = $this->_field->id();

        $datesarray = json_decode($value);
        if (is_array($datesarray)) {
            $from = $datesarray[0];
            $to = $datesarray[1];
        } else {
            $from = 0;
            $to = 0;
        }

        if ($mform->_formName != 'mod_datalynx_customfilter_frontend_form') {
            $elements = [];
            $elements[] = &$mform->createElement('date_time_selector', "f_{$i}_{$fieldid}_from", get_string('from'));
            $elements[] = &$mform->createElement('date_time_selector', "f_{$i}_{$fieldid}_to", get_string('to'));

            $mform->setDefault("f_{$i}_{$fieldid}_from", (int) $from);
            $mform->setDefault("f_{$i}_{$fieldid}_to", (int) $to);
            foreach (array('year', 'month', 'day', 'hour', 'minute') as $fieldidentifier) {
                $mform->disabledIf("f_{$i}_{$fieldid}_to[$fieldidentifier]", "searchoperator$i", 'neq', 'BETWEEN');
            }
        }

        if ($mform->_formName == 'mod_datalynx_customfilter_frontend_form') {
            $attr = array('optional' => true); // Allows date_time to be enabled, passes 0 if disabled.
            $elements[] = $element = &$mform->createElement('date_time_selector', "f_{$i}_{$fieldid}_from", get_string('from'), $attr);
            $element->setAttributes(['size' => 1]);
            $elements[] = &$mform->createElement('date_time_selector', "f_{$i}_{$fieldid}_to", get_string('to'), $attr);
        }

        $separators = array('<div class="w-100"><br></div>', '<div class="w-100"><br></div>');
        return array($elements, $separators);
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->get('internalname');
        $cat = get_string('entryinfo', 'datalynx');

        $patterns = array();
        $patterns["##$fieldname##"] = array(true, $cat);
        // Date without time.
        $patterns["##$fieldname:date##"] = array(true, $cat);
        // Date with time.
        $patterns["##$fieldname:timestamp##"] = array(true, $cat);
        // Minute (M).
        $patterns["##$fieldname:minute##"] = array(false);
        // Hour (H).
        $patterns["##$fieldname:hour##"] = array(false);
        // Day (a).
        $patterns["##$fieldname:day##"] = array(false);
        $patterns["##$fieldname:d##"] = array(false);
        // Week (V).
        $patterns["##$fieldname:week##"] = array(false);
        // Month (b).
        $patterns["##$fieldname:month##"] = array(false);
        $patterns["##$fieldname:m##"] = array(false);
        // Year (G).
        $patterns["##$fieldname:year##"] = array(false);
        $patterns["##$fieldname:Y##"] = array(false);

        return $patterns;
    }
}
