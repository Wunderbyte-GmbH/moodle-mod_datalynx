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

namespace datalynxfield_schedule;

use mod_datalynx\form\datalynxfield_form;
use mod_datalynx\local\ride\schedule;

/**
 * Settings form for the schedule field type.
 *
 * @package    datalynxfield_schedule
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends datalynxfield_form {
    /**
     * Field specific definition.
     */
    public function field_definition() {
        $mform = &$this->_form;

        // Param1: step between the offered times of day.
        $granularities = [5 => 5, 10 => 10, 15 => 15, 30 => 30, 60 => 60];
        $mform->addElement('select', 'param1', get_string('granularity', 'datalynxfield_schedule'), $granularities);
        $mform->setType('param1', PARAM_INT);
        $mform->setDefault('param1', 30);
        $mform->addHelpButton('param1', 'granularity', 'datalynxfield_schedule');

        // Param2: how far apart two departures may be and still count as the same journey.
        $mform->addElement(
            'select',
            'param2',
            get_string('matchtolerance', 'datalynxfield_schedule'),
            renderer::tolerance_menu()
        );
        $mform->setType('param2', PARAM_INT);
        $mform->setDefault('param2', field::TOLERANCE_WHOLE_DAY);
        $mform->addHelpButton('param2', 'matchtolerance', 'datalynxfield_schedule');

        // Param3: which shapes an entry may use.
        $modes = [
            'both' => get_string('modeboth', 'datalynxfield_schedule'),
            schedule::MODE_ONCE => get_string('modeonce', 'datalynxfield_schedule'),
            schedule::MODE_WEEKLY => get_string('modeweekly', 'datalynxfield_schedule'),
        ];
        $mform->addElement('select', 'param3', get_string('allowedmodes', 'datalynxfield_schedule'), $modes);
        $mform->setType('param3', PARAM_ALPHA);
        $mform->setDefault('param3', 'both');

        // Param4: ask for an arrival time as well.
        $mform->addElement('selectyesno', 'param4', get_string('askarrival', 'datalynxfield_schedule'));
        $mform->setType('param4', PARAM_INT);
        $mform->setDefault('param4', 0);
        $mform->addHelpButton('param4', 'askarrival', 'datalynxfield_schedule');

        // Param5: leave schedules that have run out out of searches and matches.
        $mform->addElement('selectyesno', 'param5', get_string('hideexpired', 'datalynxfield_schedule'));
        $mform->setType('param5', PARAM_INT);
        $mform->setDefault('param5', 1);
        $mform->addHelpButton('param5', 'hideexpired', 'datalynxfield_schedule');
    }
}
