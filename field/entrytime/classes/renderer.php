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
     * @param ?array $tags The field tags.
     * @param mixed $entry The entry object.
     * @param ?array $options Rendering options.
     * @return array
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        $field = $this->field;
        $fieldname = $field->get('internalname');
        $dlxid = $field->dlx()->id();

        // No edit mode.
        $replacements = [];

        foreach ($tags as $tag) {
            // Display nothing on new entries.
            if ($entry->id < 0) {
                $replacements[$tag] = '';
            } else {
                if (empty($entry->{$fieldname})) {
                    $replacements[$tag] = '';
                    continue;
                }

                $suffix = (strpos($tag, "{$fieldname}:") !== false ? str_replace(
                    "{$fieldname}:",
                    '',
                    trim($tag, '#@')
                ) : '');

                // Default rendering when no modifier format suffix is specified.
                if ($suffix === '') {
                    $replacements[$tag] = ['html', userdate($entry->{$fieldname})];
                    continue;
                }

                // WARNING: Hardcoded legacy modifiers (e.g. ##fieldname:date##) are deprecated and removed.
                // All modifiers must be implemented as Field Formats (Manage > Fields > Field Formats).
                // Do NOT re-introduce hardcoded/legacy modifier switch cases here.
                $format = \mod_datalynx\local\field_format\manager::get_format_by_name($dlxid, $suffix);
                if ($format && $format->get_fieldtype() === 'entrytime') {
                    $dateformat = $format->get_setting('dateformat');
                    if ($dateformat === 'timestamp') {
                        $replacements[$tag] = ['html', (string)$entry->{$fieldname}];
                    } else {
                        $replacements[$tag] = ['html', userdate($entry->{$fieldname}, $dateformat)];
                    }
                } else {
                    $replacements[$tag] = '';
                }
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
     * WARNING: Hardcoded legacy modifiers (e.g. ##fieldname:date##) are deprecated and removed.
     * All modifiers must be implemented as Field Formats (Manage > Fields > Field Formats).
     * Do NOT re-introduce hardcoded/legacy modifier lists here.
     */
    protected function patterns() {
        $fieldname = $this->field->get('internalname');
        $cat = get_string('entryinfo', 'datalynx');
        $patterns = [];

        $formats = \mod_datalynx\local\field_format\manager::get_formats_for_instance(
            $this->field->dlx()->id(),
            'entrytime'
        );

        $patterns["##$fieldname##"] = [true, $cat];
        foreach ($formats as $format) {
            $patterns["##{$fieldname}:{$format->get_name()}##"] = [true, $cat];
        }

        return $patterns;
    }
}
