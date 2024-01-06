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
 * @subpackage multiselect
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_multiselect_form extends datalynxfield_option_form {

    public function field_definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));

        // Default options.
        $mform->addElement('textarea', 'param2', get_string('fieldoptionsdefault', 'datalynx'),
                'wrap="soft" rows="5" cols="50"');

        // Options separator.
        $mform->addElement('select', 'param3', get_string('fieldoptionsseparator', 'datalynx'),
                array_map('current', $this->_field->separators));

        // Enable autocompletion for edit mode.
        $mform->addElement('selectyesno', 'param6', get_string('autocompletion', 'datalynx'));
        $mform->setDefault('param6', 1);
        $mform->addHelpButton('param6', 'autocompletion', 'datalynx');
    }
}
