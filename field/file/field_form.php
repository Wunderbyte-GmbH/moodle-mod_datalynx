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
 * @subpackage file
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_file_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        global $CFG;

        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));

        // Max bytes (param1).
        $options = get_max_upload_sizes($CFG->maxbytes, $this->_df->course->maxbytes);
        $mform->addElement('select', 'param1', get_string('filemaxsize', 'datalynx'), $options);

        // Max files (param2).
        $range = range(1, 100);
        $options = array_combine($range, $range);
        $options[-1] = get_string('unlimited');
        $mform->addElement('select', 'param2', get_string('filesmax', 'datalynx'), $options);
        $mform->setDefault('param2', -1);

        // Accetped types.
        $this->filetypes_definition();
    }

    /**
     */
    public function filetypes_definition() {
        $mform = &$this->_form;

        // Accetped types (param3).
        $options = array();
        $options['*'] = get_string('filetypeany', 'datalynx');
        $options['image'] = get_string('filetypeimage', 'datalynx');
        $options['.html'] = get_string('filetypehtml', 'datalynx');

        $mform->addElement('select', 'param3', get_string('filetypes', 'datalynx'), $options);
        $mform->setDefault('param3', '*');
    }
}
