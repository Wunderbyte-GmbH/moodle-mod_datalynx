<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * @package datalynxfield
 * @subpackage textarea
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_textarea_form extends datalynxfield_form {

    /**
     *
     */
    function field_definition() {
        global $CFG;

        $mform =& $this->_form;

    // editor settings
    //-------------------------------------------------------------------------------
        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        // editor enabled
        $mform->addElement('selectyesno', 'param1', get_string('editorenable', 'datalynx'));

        // field width (cols)
        $mform->addElement('text', 'param2', get_string('cols', 'datalynxfield_textarea'), array('size'=>'8'));
        $mform->setType('param2', PARAM_INT);
        $mform->addRule('param2', null, 'numeric', null, 'client');

        // field height (rows)
        $mform->addElement('text', 'param3', get_string('rows', 'datalynxfield_textarea'), array('size'=>'8'));
        $mform->setType('param3', PARAM_INT);
        $mform->addRule('param3', null, 'numeric', null, 'client');

        // trust text
        $mform->addElement('selectyesno', 'param4', get_string('trusttext', 'datalynx'));
        $mform->disabledIf('param4', 'param1', 'eq', 0);

        // word count
        //$mform->addElement('text', 'param7', get_string('wordcountmin', 'datalynxfield_textarea'), array('size'=>'4'));
        //$mform->addElement('text', 'param8', get_string('wordcountmax', 'datalynxfield_textarea'), array('size'=>'4'));

    // editor files settings
    //-------------------------------------------------------------------------------
        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        // max bytes
        $options = get_max_upload_sizes($CFG->maxbytes, $this->_df->course->maxbytes);
        $mform->addElement('select', 'param5', get_string('filemaxsize', 'datalynx'), $options);
        $mform->disabledIf('param5', 'param1', 'eq', 0);

        // max files
        $range = range(1, 100);
        $options = array(-1 => get_string('unlimited')) + array_combine($range, $range);
        $mform->addElement('select', 'param6', get_string('filesmax', 'datalynx'), $options);
        $mform->disabledIf('param6', 'param1', 'eq', 0);

    }
}
