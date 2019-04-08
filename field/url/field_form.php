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
 * @subpackage url
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_url_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        // Use url picker.
        $mform->addElement('selectyesno', 'param1', get_string('usepicker', 'datalynxfield_url'));

        // Force link name.
        $mform->addElement('text', 'param2', get_string('forcename', 'datalynxfield_url'), array('size' => '32'));
        $mform->setType('param2', PARAM_TEXT);

        $mform->addElement('text', 'param3', get_string('urlclass', 'datalynx'), array('size' => '32'));
        $mform->setType('param3', PARAM_TEXT);

        $mform->addElement('text', 'param4', get_string('urltarget', 'datalynx'), array('size' => '32'));
        $mform->setType('param4', PARAM_TEXT);

        $mform->addElement('selectyesno', 'param5', get_string('displaylinktext', 'datalynxfield_url'));
    }
}
