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

namespace datalynxfield_entrytime;

/**
 * Field format implementation for entrytime fields.
 *
 * @package    datalynxfield_entrytime
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format extends \mod_datalynx\local\field_format\base {
    /**
     * Defines configuration elements on the form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form(\MoodleQuickForm &$mform) {
        $mform->addElement('text', 'dateformat', get_string('fieldformat_dateformat', 'datalynxfield_entrytime'));
        $mform->setType('dateformat', PARAM_TEXT);
        $mform->addHelpButton('dateformat', 'fieldformat_dateformat', 'datalynxfield_entrytime');
        $mform->addElement(
            'static',
            'dateformat_examples',
            '',
            get_string('fieldformat_dateformat_examples', 'datalynxfield_entrytime')
        );
    }


    /**
     * Inferred defaults mapping legacy tag suffixes.
     *
     * @param string $name
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        $map = [
            'date'      => get_string('strftimedate'),
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
        if (isset($map[$name])) {
            return ['dateformat' => $map[$name]];
        }
        return [];
    }
}
