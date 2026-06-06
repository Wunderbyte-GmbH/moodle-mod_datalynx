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
 * Field format class for the entrytime field type.
 *
 * @package    datalynxfield_entrytime
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entrytime;

use mod_datalynx\local\field_format\base;
use MoodleQuickForm;

/**
 * Field format for entrytime: controls the date/time display format.
 */
class field_format extends base {
    /** @var array Legacy keyword → strftime string mappings. */
    const KEYWORD_MAP = [
        'date'      => null, // Resolved dynamically via get_string('strftimedate').
        'timestamp' => 'timestamp',
        'minute'    => '%M',
        'hour'      => '%H',
        'day'       => '%a',
        'd'         => '%a',
        'week'      => '%V',
        'month'     => '%b',
        'm'         => '%m',
        'year'      => '%Y',
        'Y'         => '%Y',
    ];

    /**
     * Adds a 'dateformat' text field and an HTML cheatsheet to the format config form.
     *
     * @param MoodleQuickForm $mform The form object.
     */
    public function config_form(MoodleQuickForm &$mform): void {
        $mform->addElement(
            'text',
            'dateformat',
            get_string('fieldformat_dateformat', 'datalynxfield_entrytime'),
            ['size' => '48']
        );
        $mform->setType('dateformat', PARAM_TEXT);
        $mform->addHelpButton('dateformat', 'fieldformat_dateformat', 'datalynxfield_entrytime');

        $mform->addElement('html', get_string('fieldformat_dateformat_examples', 'datalynxfield_entrytime'));

        // Set default from existing settings.
        $current = $this->get_setting('dateformat');
        if ($current !== null) {
            $mform->setDefault('dateformat', $current);
        }
    }

    /**
     * Returns inferred default settings from a legacy hardcoded format name.
     *
     * @param string $name The format name detected from the template tag.
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        if ($name === 'date') {
            return ['dateformat' => get_string('strftimedate')];
        }
        if (isset(self::KEYWORD_MAP[$name]) && self::KEYWORD_MAP[$name] !== null) {
            return ['dateformat' => self::KEYWORD_MAP[$name]];
        }
        // For 'timestamp' and unknown values, return the name itself as dateformat.
        if (array_key_exists($name, self::KEYWORD_MAP)) {
            return ['dateformat' => $name];
        }
        return [];
    }
}
