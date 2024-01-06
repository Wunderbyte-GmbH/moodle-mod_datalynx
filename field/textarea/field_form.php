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
 * @subpackage textarea
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_textarea_form extends datalynxfield_form {

    public function field_definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        // Field width (cols).
        $mform->addElement('text', 'param2', get_string('cols', 'datalynxfield_textarea'),
                array('size' => '8'));
        $mform->setType('param2', PARAM_INT);
        $mform->addRule('param2', null, 'numeric', null, 'client');

        // Field height (rows).
        $mform->addElement('text', 'param3', get_string('rows', 'datalynxfield_textarea'),
                array('size' => '8'));
        $mform->setType('param3', PARAM_INT);
        $mform->addRule('param3', null, 'numeric', null, 'client');
    }
}
