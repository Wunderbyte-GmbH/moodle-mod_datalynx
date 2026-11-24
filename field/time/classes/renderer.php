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
 * @package datalynxfield_time
 * @subpackage time
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_time;

use coding_exception;
use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;
use stdClass;

/**
 * Renderer class for the time field type.
 */
class renderer extends datalynxfield_renderer {
    /**
     * The masked selectors sit in the group grpfield_{fieldid}_{entryid}.
     *
     * @param string $name
     * @return string
     */
    public function get_error_element(string $name): string {
        return $this->field->masked ? "grp{$name}" : $name;
    }

    /**
     * Render the edit mode for the time field.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options): void {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = 0;
        if ($entryid > 0 && !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        }

        $includetime = $this->includes_time($options);

        if ($field->masked) {
            $this->render_masked_selector($mform, $entry, $content, $includetime, $options);
        } else {
            $this->render_standard_selector($mform, $entry, $content, $includetime, $options);
        }
    }

    /**
     * Render the display mode for the time field.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();

        $strtime = '';
        if (isset($entry->{"c{$fieldid}_content"})) {
            if ($content = $entry->{"c{$fieldid}_content"}) {
                $format = $options['field_format'] ?? null;
                if ($format) {
                    $settings = $format->get_settings();
                    $dateformat = $settings['dateformat'] ?? '';
                    if ($dateformat === 'timestamp') {
                        $strtime = $content;
                    } else if (!empty($dateformat)) {
                        $strtime = userdate($content, $dateformat);
                    }
                } else if (!empty($options['format'])) {
                    $strtime = userdate($content, $options['format']);
                } else if (isset($options['date']) || $field->dateonly) {
                    $strtime = userdate($content, get_string("strftimedate"));
                } else if (isset($options['timestamp'])) {
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
            }
        }
        return $strtime;
    }

    /**
     * Render the search mode for the time field.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = ''): array {
        // The bounds of a saved criterion arrive JSON-encoded.
        $value = $value === '' ? [] : (array) self::decode_saved_search_value($value);
        $field = $this->field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";

        $elements = [];

        $elements[] = &$mform->createElement(
            'date_time_selector',
            "{$fieldname}_from",
            get_string('fromdate', 'moodle')
        );
        $elements[] = &$mform->createElement(
            'date_time_selector',
            "{$fieldname}_to",
            get_string('todate', 'moodle')
        );
        if (isset($value[0])) {
            $mform->setDefault("{$fieldname}_from", $value[0]);
        }
        if (isset($value[1])) {
            $mform->setDefault("{$fieldname}_to", $value[1]);
        }
        foreach (['year', 'month', 'day', 'hour', 'minute'] as $fieldidentifier) {
            $mform->disabledIf("{$fieldname}_to[$fieldidentifier]", "searchoperator$i", 'neq', 'BETWEEN');
        }
        foreach (['year', 'month', 'day', 'hour', 'minute'] as $fieldidentifier) {
            $mform->disabledIf("{$fieldname}_from[$fieldidentifier]", "searchoperator$i", 'eq', '');
        }
        if ($field->dateonly) {
            // Deactivate form elements for min and seconds when field is date only and operator is "=".
            foreach (['hour', 'minute'] as $fieldidentifier) {
                $mform->disabledIf("{$fieldname}_from[$fieldidentifier]", "searchoperator$i", 'eq', '=');
            }
        }

        $separators = ['<br>', '<br>'];
        return [$elements, $separators];
    }

    /**
     * Render the standard date/time selector for the time field.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param mixed $content Current field content.
     * @param bool $includetime Whether to include time selector.
     * @param array $options Additional options.
     */
    protected function render_standard_selector(
        &$mform,
        $entry,
        $content,
        $includetime = true,
        array $options = []
    ): void {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        // If date only don't add time to selector.
        $time = $includetime ? 'time_' : '';
        $elementoptions = [];
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
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param mixed $content Current field content.
     * @param bool $includetime Whether to include time selector.
     * @param array $options Additional options.
     * @throws coding_exception
     */
    protected function render_masked_selector(
        MoodleQuickForm &$mform,
        $entry,
        $content,
        $includetime = true,
        array $options = []
    ): void {
        $field = $this->field;
        $entryid = $entry->id;
        $fieldid = $field->id();
        $fieldname = "field_{$fieldid}_{$entryid}";

        // TODO MDL-000000 some defaults that need to be set in the field settings.
        $step = 5;
        $startyear = $field->startyear ? $field->startyear : 1970;
        $stopyear = $field->stopyear ? $field->stopyear : 2020;
        $maskday = get_string('day');
        $maskmonth = get_string('month');
        $maskyear = get_string('year');

        $days = [];
        for ($i = 1; $i <= 31; $i++) {
            $days[$i] = $i;
        }
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[$i] = userdate(mktime(0, 0, 0, $i, 10), "%B");
        }
        $years = [];
        for ($i = $startyear; $i <= $stopyear; $i++) {
            $years[$i] = $i;
        }

        $grp = [];
        $grp[] = &$mform->createElement(
            'select',
            "{$fieldname}[day]",
            null,
            [0 => $maskday] + $days
        );
        $grp[] = &$mform->createElement(
            'select',
            "{$fieldname}[month]",
            null,
            [0 => $maskmonth] + $months
        );
        $grp[] = &$mform->createElement(
            'select',
            "{$fieldname}[year]",
            null,
            [0 => $maskyear] + $years
        );

        // If time add hours and minutes.
        if ($includetime) {
            $maskhour = get_string('hour', 'datalynxfield_time');
            $maskminute = get_string('minute', 'datalynxfield_time');

            $hours = [];
            for ($i = 0; $i <= 23; $i++) {
                $hours[$i] = sprintf("%02d", $i);
            }
            $minutes = [];
            for ($i = 0; $i < 60; $i += $step) {
                $minutes[$i] = sprintf("%02d", $i);
            }

            $grp[] = &$mform->createElement(
                'select',
                "{$fieldname}[hour]",
                null,
                [0 => $maskhour] + $hours
            );
            $grp[] = &$mform->createElement(
                'select',
                "{$fieldname}[minute]",
                null,
                [0 => $maskminute] + $minutes
            );
        }

        $mform->addGroup($grp, "grp$fieldname", null, '', false);
        // Set field values.
        if ($content) {
            [$day, $month, $year, $hour, $minute] = explode(':', date('d:n:Y:G:i', $content));
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
                $mform->addGroupRule(
                    "grp$fieldname",
                    [
                                "{$fieldname}[day]" => [
                                        [
                                                get_string('timefieldrequired', 'datalynx', get_string('day')),
                                                'nonzero', null, 'client',
                                        ],
                                ],
                                "{$fieldname}[month]" => [
                                        [
                                                get_string(
                                                    'timefieldrequired',
                                                    'datalynx',
                                                    get_string('month')
                                                ), 'nonzero', null, 'client',
                                        ],
                                ],
                                "{$fieldname}[year]" => [
                                        [
                                                get_string(
                                                    'timefieldrequired',
                                                    'datalynx',
                                                    get_string('year')
                                                ), 'nonzero', null, 'client',
                                        ],
                                ],
                                "{$fieldname}[hour]" => [
                                        [
                                                get_string(
                                                    'timefieldrequired',
                                                    'datalynx',
                                                    get_string('hour')
                                                ), 'nonzero', null, 'client',
                                        ],
                                ],
                                "{$fieldname}[minute]" => [
                                        [
                                                get_string(
                                                    'timefieldrequired',
                                                    'datalynx',
                                                    get_string('minute')
                                                ), 'nonzero', null, 'client',
                                        ],
                                ],
                        ]
                );
            } else {
                $mform->addGroupRule(
                    "grp$fieldname",
                    [
                                "{$fieldname}[day]" => [
                                        [
                                                get_string('timefieldrequired', 'datalynx', get_string('day')),
                                                'nonzero', null, 'client',
                                        ],
                                ],
                                "{$fieldname}[month]" => [
                                        [
                                                get_string(
                                                    'timefieldrequired',
                                                    'datalynx',
                                                    get_string('month')
                                                ), 'nonzero', null, 'client',
                                        ],
                                ],
                                "{$fieldname}[year]" => [
                                        [
                                                get_string(
                                                    'timefieldrequired',
                                                    'datalynx',
                                                    get_string('year')
                                                ), 'nonzero', null, 'client',
                                        ],
                                ],
                        ]
                );
            }
        }
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns(): array {
        // The parent::patterns() contributes [[fieldname]] plus a visible tag per user-created time format
        // (e.g. a "date" format with a configured strftime string). The legacy date/time suffixes stay
        // recognised for old templates but are hidden from the menu.
        $patterns = parent::patterns();

        return $this->add_legacy_suffix_patterns(
            $patterns,
            ['date', 'timestamp', 'minute', 'hour', 'day', 'd', 'week', 'month', 'm', 'year', 'Y']
        );
    }

    /**
     * Validate the time field entry data.
     *
     * @param int|string $entryid
     * @param array $tags
     * @param stdClass $formdata
     * @return array
     */
    public function validate($entryid, $tags, $formdata): array {
        // Whether a value is required is checked by the entry submission service; the selectors
        // only offer valid dates.
        return [];
    }

    /**
     * A time is searched in a range of days.
     *
     * @return string
     */
    public function get_customfilter_control(): string {
        return 'daterange';
    }

    /**
     * Whether a time of day is entered with the date: not for a date-only field or tag.
     *
     * @param array $options
     * @return bool
     */
    protected function includes_time(array $options): bool {
        return empty($options['date']) && !isset($this->field->dateonly);
    }

    /**
     * A date, with the time of day unless the field or tag is date only; not for the masked
     * selectors, which the app cannot show yet.
     *
     * @param stdClass $entry
     * @param array $options
     * @return array|null
     */
    public function get_edit_control(stdClass $entry, array $options): ?array {
        if ($this->field->masked) {
            return null;
        }
        $content = $entry->{"c{$this->field->id()}_content"} ?? '';

        return $this->edit_control('datetime', ['' => $content ?: ''], $options, [
            'includetime' => $this->includes_time($options),
        ]);
    }
}
