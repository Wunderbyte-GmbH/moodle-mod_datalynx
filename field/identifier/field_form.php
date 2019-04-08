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
 * @subpackage identifier
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_identifier_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        $field = &$this->_field;
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));

        // Salt (param1).
        $options = $field::get_salt_options();
        $mform->addElement('select', 'param1', get_string('saltsource', 'datalynxfield_identifier'), $options);
        $mform->setDefault('param1', 'random');

        // Field Salt length (param2).
        $mform->addElement('text', 'param2', get_string('saltsize', 'datalynxfield_identifier'),
                array('size' => '8'));
        $mform->setType('param2', PARAM_INT);
        $mform->addRule('param2', null, 'numeric', null, 'client');
        $mform->setDefault('param2', 10);
        $mform->disabledIf('param2', 'param1', 'neq', 'random');

        // Uniqueness (param3).
        $mform->addElement('selectyesno', 'param3', get_string('uniqueness', 'datalynxfield_identifier'));
        $mform->setDefault('param3', 1);
    }
}
