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
 * @subpackage number
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_number_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));

        // Decimals.
        $options = array('' => 0) + array_combine(range(1, 10), range(1, 10));
        $mform->addElement('select', 'param1', get_string('decimals', 'datalynxfield_number'), $options);
        $mform->addElement('selectyesno', 'param4', get_string('outputemptystring', 'datalynxfield_number'), $options);
        // Param3 not used anymore former: px, em or %. Param2 was width in integer.
    }
}
