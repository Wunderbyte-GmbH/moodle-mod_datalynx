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
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_time_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        // Date.
        $mform->addElement('checkbox', 'param1', get_string('dateonly', 'datalynxfield_time'));
        $mform->addHelpButton('param1', 'dateonly', 'datalynxfield_time');

        // Masked.
        $mform->addElement('checkbox', 'param5', get_string('masked', 'datalynxfield_time'));
        $mform->addHelpButton('param5', 'masked', 'datalynxfield_time');

        // Start year.
        $mform->addElement('text', 'param2', get_string('startyear', 'datalynxfield_time'));
        $mform->setType('param2', PARAM_INT);
        $mform->addRule('param2', null, 'numeric', null, 'client');
        $mform->addRule('param2', null, 'maxlength', 4, 'client');
        $mform->addHelpButton('param2', 'startyear', 'datalynxfield_time');

        // End year.
        $mform->addElement('text', 'param3', get_string('stopyear', 'datalynxfield_time'));
        $mform->setType('param3', PARAM_INT);
        $mform->addRule('param3', null, 'numeric', null, 'client');
        $mform->addRule('param3', null, 'maxlength', 4, 'client');
        $mform->addHelpButton('param3', 'stopyear', 'datalynxfield_time');

        // Display format.
        $mform->addElement('text', 'param4', get_string('displayformat', 'datalynxfield_time'));
        $mform->setType('param4', PARAM_TEXT);
        $mform->addHelpButton('param4', 'displayformat', 'datalynxfield_time');
    }
}
