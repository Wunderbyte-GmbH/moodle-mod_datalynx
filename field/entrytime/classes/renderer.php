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
 * @package datalynxfield_entrytime
 * @subpackage entrytime
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_entrytime;

use mod_datalynx\local\field\datalynxfield_renderer;
use mod_datalynx\local\field_format\manager as format_manager;
use MoodleQuickForm;

/**
 * Renderer class for the internal time field.
 *
 * @package    mod_datalynx
 */
class renderer extends datalynxfield_renderer {
    /**
     * Returns replacement values for time tags.
     *
     * Resolves the dateformat from the DB format record's settings, falling back to the
     * legacy keyword/strftime behaviour for backwards compatibility.
     *
     * @param ?array $tags The field tags.
     * @param mixed $entry The entry object.
     * @param ?array $options Rendering options.
     * @return array
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        $field     = $this->field;
        $fieldname = $field->get('internalname');
        $dlxid     = $field->df()->id();

        $replacements = [];

        foreach ($tags as $tag) {
            // Display nothing on new entries.
            if ($entry->id < 0) {
                $replacements[$tag] = '';
                continue;
            }

            // Extract the suffix (e.g. 'date', 'myformat') from ##fieldname:suffix##.
            $suffix = (strpos($tag, "{$fieldname}:") !== false)
                ? str_replace("{$fieldname}:", '', trim($tag, '#@'))
                : '';

            // Check if suffix is a DB format name.
            $format = format_manager::get_format_by_name($dlxid, $suffix);
            if ($format && $format->get_fieldtype() === 'entrytime') {
                $dateformat = $format->get_setting('dateformat') ?: $suffix;
            } else {
                $dateformat = $suffix;
            }

            // Resolve keyword aliases (legacy compat when dateformat is a keyword).
            switch ($dateformat) {
                case 'date':
                    $dateformat = get_string('strftimedate');
                    break;
                case 'timestamp':
                    $replacements[$tag] = ['html', (string) ($entry->{$fieldname} ?? '')];
                    continue 2;
                case 'minute':
                    $dateformat = '%M';
                    break;
                case 'hour':
                    $dateformat = '%H';
                    break;
                case 'day':
                case 'd':
                    $dateformat = '%a';
                    break;
                case 'week':
                    $dateformat = '%V';
                    break;
                case 'month':
                    $dateformat = '%b';
                    break;
                case 'm':
                    $dateformat = '%m';
                    break;
                case 'year':
                case 'Y':
                    $dateformat = '%Y';
                    break;
            }

            $replacements[$tag] = ['html', userdate($entry->{$fieldname}, $dateformat)];
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
        $fieldid = $this->field->id();

        $datesarray = json_decode($value);
        if (is_array($datesarray)) {
            $from = $datesarray[0];
            $to = $datesarray[1];
        } else {
            $from = 0;
            $to = 0;
        }

        if ($mform->_formName != 'mod_datalynx_form_datalynx_customfilter_frontend_form') {
            $elements = [];
            $elements[] = &$mform->createElement(
                'date_time_selector',
                "f_{$i}_{$fieldid}_from",
                get_string('fromdate', 'moodle')
            );
            $elements[] = &$mform->createElement(
                'date_time_selector',
                "f_{$i}_{$fieldid}_to",
                get_string('todate', 'moodle')
            );

            $mform->setDefault("f_{$i}_{$fieldid}_from", (int) $from);
            $mform->setDefault("f_{$i}_{$fieldid}_to", (int) $to);
            foreach (['year', 'month', 'day', 'hour', 'minute'] as $fieldidentifier) {
                $mform->disabledIf("f_{$i}_{$fieldid}_to[$fieldidentifier]", "searchoperator$i", 'neq', 'BETWEEN');
            }
        }

        if ($mform->_formName == 'mod_datalynx_form_datalynx_customfilter_frontend_form') {
            $attr = ['optional' => true]; // Allows date_time to be enabled, passes 0 if disabled.
            $elements[] = $element = &$mform->createElement(
                'date_time_selector',
                "f_{$i}_{$fieldid}_from",
                get_string('fromdate', 'moodle'),
                $attr
            );
            $element->setAttributes(['size' => 1]);
            $elements[] = &$mform->createElement(
                'date_time_selector',
                "f_{$i}_{$fieldid}_to",
                get_string('todate', 'moodle'),
                $attr
            );
        }

        $separators = ['<div class="w-100"><br></div>', '<div class="w-100"><br></div>'];
        return [$elements, $separators];
    }

    /**
     * Array of patterns this field supports.
     *
     * Patterns are dynamically generated from DB format records for the 'entrytime' type.
     * Both timecreated and timemodified instances include all entrytime formats.
     *
     * @return array
     */
    protected function patterns() {
        $fieldname = $this->field->get('internalname'); // 'timecreated' or 'timemodified'.
        $cat = get_string('entryinfo', 'datalynx');
        $dlxid = $this->field->df()->id();

        $patterns = [];

        $formats = format_manager::get_formats_for_instance($dlxid, 'entrytime');
        foreach ($formats as $format) {
            $patterns["##{$fieldname}:{$format->get_name()}##"] = [true, $cat];
        }

        return $patterns;
    }
}
