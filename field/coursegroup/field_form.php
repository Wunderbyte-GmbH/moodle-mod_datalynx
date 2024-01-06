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
 * @subpackage coursegroup
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_coursegroup_form extends datalynxfield_form {

    /**
     */
    public function field_definition() {
        global $CFG, $PAGE, $DB, $SITE;

        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        // Course.
        $courses = get_courses("all", "c.sortorder ASC", "c.id,c.fullname");
        $options = array(0 => get_string('choosedots'));
        foreach ($courses as $courseid => $course) {
            $options[$courseid] = $course->fullname;
        }
        $mform->addElement('select', 'param1', get_string('course'), $options);

        // Group id.
        $options = array(0 => get_string('choosedots'));
        if (!empty($this->_field->field->param1)) {
            $course = $this->_field->field->param1;
            $groups = $DB->get_records_menu('groups', array('courseid' => $course), 'name', 'id,name');
        } else {
            // An arbitrary limit of 100 registered options.
            $options = $options + range(1, 100);
        }
        $mform->addElement('select', "param2", get_string('group'), $options);
        $mform->disabledIf("param2", "param1", 'eq', '');

        // Ajax.
        $options = array('coursefield' => 'param1', 'groupfield' => 'param2',
                'acturl' => "$CFG->wwwroot/mod/datalynx/field/coursegroup/loadgroups.php");

        // Add JQuery
        $PAGE->requires->js_call_amd('mod_datalynx/coursegroup', 'init', array($options));

    }
}
