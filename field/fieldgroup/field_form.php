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
 * @package datalynxfield
 * @subpackage fieldgroup
 * @copyright 2018 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_fieldgroup_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        global $OUTPUT, $DB, $PAGE, $CFG;

        $mform = &$this->_form;

        // Fieldgroupfields is stored in param1
        $mform->addElement('text', 'param1', get_string('fieldgroupfields', 'datalynx'), array('size' => '64'));
        $mform->setType('param1', PARAM_TEXT);

        // Number of times the field group can be filled out.
        $mform->addElement('text', 'param2', 'beschreibung nummax'); // TODO: Multilang.
        $mform->setDefault('param2', 0); // Zero sets no limit.
        $mform->addRule('param2', null, 'numeric', null, 'client');
        $mform->setType('param2', PARAM_INT);

        // Number of fieldgroups to show by default.
        $mform->addElement('text', 'param3', 'beschreibung numshowdefault'); // TODO: Multilang.
        $mform->setDefault('param3', 3); // Defaults to three.
        $mform->addRule('param3', null, 'numeric', null, 'client');
        $mform->setType('param3', PARAM_INT);

        // Number of required fieldgroups to be filled out.
        $mform->addElement('text', 'param4', 'beschreibung numrequired'); // TODO: Multilang.
        $mform->setDefault('param4', 0);
        $mform->addRule('param4', null, 'numeric', null, 'client');
        $mform->setType('param4', PARAM_INT);

        // TODO: Select displaymode.
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // TODO: Check if all fieldnames are actually found.
        return $errors;
    }
}
