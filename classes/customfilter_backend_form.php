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
 * Contains class mod_customfilter_form used at the BACKEND ("Manage") to create or edit a customfilter
 *
 * @package mod
 * @subpackage datalynx
 * @copyright 2016 Thomas Niedermaier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class mod_datalynx_customfilter_backend_form extends mod_datalynx_customfilter_base_form {

    /**
     *
     */
    public function definition() {

        if ($id = $this->_customfilter->id) {
            $customfilter = $this->_getcustomfilter($id);
        } else {
            $customfilter = new stdClass();
            $customfilter->name = "";
            $customfilter->description = "";
            $customfilter->fulltextsearch = false;
            $customfilter->visible = false;
            $customfilter->timecreated = false;
            $customfilter->timemodified = false;
            $customfilter->approve = false;
            $customfilter->status = false;
            $customfilter->fieldlist = "";
        }

        $name = empty($customfilter->name) ? get_string('filternew', 'datalynx') : $customfilter->name;

        $mform = &$this->_form;

        $mform->addElement('text', 'name', get_string('name'));
        $mform->addElement('text', 'description', get_string('description'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->setType('description', PARAM_TEXT);
        $mform->setDefault('name', $name);
        $mform->setDefault('description', $customfilter->description);

        $mform->addElement('advcheckbox', 'visible', get_string('visible'));
        $mform->setType('visible', PARAM_INT);
        $mform->setDefault('visible', $customfilter->visible);

        $mform->addElement('advcheckbox', 'fulltextsearch', get_string('fulltextsearch', 'datalynx'));
        $mform->setType('fulltextsearch', PARAM_INT);
        $mform->setDefault('fulltextsearch', $customfilter->fulltextsearch);

        $mform->addElement('advcheckbox', 'timecreated', get_string('timecreated', 'datalynx'));
        $mform->setType('timecreated', PARAM_INT);
        $mform->setDefault('timecreated', $customfilter->timecreated);

        $mform->addElement('advcheckbox', 'timemodified', get_string('timemodified', 'datalynx'));
        $mform->setType('timemodified', PARAM_INT);
        $mform->setDefault('timemodified', $customfilter->timemodified);

        $mform->addElement('advcheckbox', 'approve', get_string('approved', 'datalynx'));
        $mform->setType('approve', PARAM_INT);
        $mform->setDefault('approve', $customfilter->approve);

        $mform->addElement('advcheckbox', 'status', get_string('status', 'datalynx'));
        $mform->setType('status', PARAM_INT);
        $mform->setDefault('status', $customfilter->status);

        $mform->addElement('header', 'fieldlistheader', get_string('userfields', 'datalynx'));

        $fieldlist = array();
        if ($customfilter->fieldlist) {
            $fieldlist = json_decode($customfilter->fieldlist);
        }
        $fields = $this->_getfields($this->_df);
        foreach ($fields as $fieldid => $field) {
            $mform->addElement('advcheckbox', 'fieldlist[' . $field->field->name . ']',
                $field->field->name . ' (' . $field->type . ')', '', '', $field->field->id);
            $mform->setType('fieldlist[' . $field->field->name . ']', PARAM_TEXT);
            foreach ($fieldlist as $fname => $fid) {
                if ($field->field->id == $fid) {
                    $mform->setDefault('fieldlist[' . $field->field->name . ']', $field->field->id);
                    break;
                }
            }
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    protected function _getfields($df) {
        global $DB;

        $fields = array();
        $customfilterfieldtypes = $df->get_customfilterfields();
        $fieldsdb = $DB->get_records('datalynx_fields', array('dataid' => $df->id()), 'name asc');
        foreach ($fieldsdb as $fieldid => $field) {
            if (in_array($field->type, $customfilterfieldtypes)) {
                $fields[$fieldid] = $df->get_field($field);
            }
        }

        return $fields;
    }

    protected function _getcustomfilter($filterid) {
        global $DB;

        $customfilter = $DB->get_record('datalynx_customfilters', array('id' => $filterid));

        return $customfilter;
    }

    public function get_data() {

        if ($data = parent::get_data()) {
            if (!empty($data->fieldlist)) {
                $data->fieldlist = json_encode($data->fieldlist);
            } else {
                $data->fieldlist = null;
            }
        }
        return $data;
    }

    /**
     */
    public function html() {
        return $this->_form->toHtml();
    }

}
