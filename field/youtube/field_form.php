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
 * @subpackage youtube
 * @copyright 2021 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_youtube_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        global $OUTPUT, $DB, $PAGE, $CFG;

        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));

        // Store height and width.
        $mform->addElement('text', 'param1', get_string('heightpx', 'datalynxfield_youtube'));
        $mform->setType('param1', PARAM_INT);
        $mform->setDefault('param1', 560);
        $mform->addElement('text', 'param2', get_string('widthpx', 'datalynxfield_youtube'));
        $mform->setType('param2', PARAM_INT);
        $mform->setDefault('param2', 315);

    }
}
