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
 * @package datalynxfield_gradeitem
 * @subpackage gradeitem
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_gradeitem;

use mod_datalynx\form\datalynxfield_form;
use html_writer;


/**
 * Datalynx gradeitem field form class.
 */
class form extends datalynxfield_form {
    /**
     * Define the field specific form elements.
     */
    public function field_definition() {
        global $DB;

        $mform = &$this->_form;

        $mform->addElement(
            'header',
            'fieldattributeshdr',
            get_string('fieldattributes', 'datalynx')
        );

        // Info field.
        $sql = "SELECT gi.id, c.fullname,
                       CASE
                            WHEN itemname IS NULL THEN 'Course grade'
                            ELSE itemname
                       END AS label
                  FROM {grade_items} gi
            INNER JOIN {course} c ON gi.courseid = c.id
              ORDER BY courseid";
        $ungroupedoptions = $DB->get_records_sql($sql);
        $options = [];
        foreach ($ungroupedoptions as $option) {
            if (!isset($options[$option->fullname])) {
                $options[$option->fullname] = [];
            }
            $options[$option->fullname][$option->id] = $option->label;
        }
        $actualoptions = [];
        foreach ($options as $key => $optionset) {
            $actualoptions[] = [$key => $optionset];
        }
        // Note: $options should be a flat associative array or grouped array for Moodle's select

        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        // Create a standard select element named 'param1'
        // Moodle handles the value setting automatically from the database/form data
        $mform->addElement('selectgroups', 'param1', get_string('gradeitem', 'datalynx'), $options);
        $mform->addRule('param1', null, 'required', null, 'client');
        $mform->setType('param1', PARAM_INT);
        /*
        $mform->addElement('hidden', 'param1');
        $mform->setType('param1', PARAM_INT);
        $mform->addElement(
            'static',
            '',
            get_string('gradeitem', 'datalynx'),
            html_writer::select($actualoptions, "param1", '', ['' => 'choosedots'])
        );

        global $PAGE;

        $PAGE->requires->js_amd_inline("
        require(['jquery'], function($) {
            $('select[name=param1]').val($('input[type=\"hidden\"][name=\"param1\"]').val());
        });");
        */
    }
}
