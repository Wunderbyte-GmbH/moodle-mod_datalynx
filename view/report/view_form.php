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
 * @package datalynxview
 * @subpackage report
 * @copyright 2013 onwards Wunderbyte GmbH and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/view/view_form.php");
require_once("$CFG->libdir/csvlib.class.php");

class datalynxview_report_form extends datalynxview_base_form {

    /**
     * @return void
     */
    public function view_definition_after_gps(): void {
        $mform = &$this->_form;
        $options =  [];
        $mform->addElement('header', 'settingshdr', get_string('settings'));

        // Report count for this field (only select supported right now: A sum of the values choson for this field is being created.
        // Get all fields from the datalynx instance.
        $fields = $this->_df->get_fields(null, false, true);
        $fieldnames = [];
        foreach ($fields as $fieldid => $field) {
            if ($field->type === "select") {
                $fieldnames[$fieldid] = $field->name();
            }
        }
        if (!empty($fieldnames)) {
            asort($fieldnames);
            $options = ['multiple' => false];
            $mform->addElement('autocomplete', 'param1', get_string('fieldtobecounted', 'datalynxview_report'),
                    $fieldnames, $options);
            $mform->addHelpButton('param1', 'fieldtobecounted', 'datalynxview_report');
        } else {
            // If there are no select fields, display a static message.
            $mform->addElement('static', 'noselectfields', get_string('selectfield', 'datalynxview_report'),
                    get_string('noselectfieldsavailable', 'datalynxview_report'));
        }

        // The fields to be grouped in order to get the sum: for example the sum of selected values for the author x.
        // Or the sum of all selected values of the field chosen above for the entry with this teammember

        $fieldnames = [];
        if (!empty($fields)) {
            foreach ($fields as $fieldid => $field) {
                if ($field->type === "teammemberselect") {
                    $fieldnames[$fieldid] = $field->name();
                }
            }
            // Add entry author as additional field. We assign value -1 to that "field" in order to keep it an int value.
            $fieldnames[-1] = get_string('entryauthor', 'datalynxfield_datalynxview');
            asort($fieldnames);
            $mform->addElement('select', 'param4', get_string('groupbyfield', 'datalynxview_report'),
                    $fieldnames, $options);
            $mform->addHelpButton('param4', 'groupbyfield', 'datalynxview_report');
        } else {
            // If there are no select fields, display a static message.
            $mform->addElement('static', 'noselectfields', get_string('selectfield', 'datalynxview_report'),
                    get_string('noselectfieldsavailable', 'datalynxview_report'));
        }


        // Report type.
        $options = ['sumoffield' => get_string('sumoffield', 'datalynxview_report')];
        $mform->addElement('select', 'param3', get_string('reporttype', 'datalynxview_report'), $options);

        // Calculate sums for this period
        $fieldnames = [];
        $fieldnames['nosums'] = get_string('nosums', 'datalynxview_report');
        $fieldnames['month'] = get_string('month');
        $mform->addElement('select', 'param2', get_string('sumfield', 'datalynxview_report'),
                $fieldnames, $options);
        $mform->addHelpButton('param2', 'sumfield', 'datalynxview_report');

        // Show a list of fields in this view.
        $view = $this->_view;
        $tags = $view->field_tags();
        if (isset($tags['Fields']['Fields']) && !empty($tags['Fields']['Fields'])) {
            $tags = implode("<br>", $tags['Fields']['Fields']);
            $mform->addElement('static', 'availablefields', get_string('fields', 'datalynx'), $tags);
        }
    }

    /**
     */
    public function data_preprocessing(&$data) {
        parent::data_preprocessing($data);
    }

    /**
     */
    public function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }

    /**
     */
    public function get_data($slashed = true) {
        return parent::get_data($slashed);
    }
}
