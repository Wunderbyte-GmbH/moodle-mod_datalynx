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

namespace mod_datalynx\customfilter;
use stdClass;
defined('MOODLE_INTERNAL') or die();

/**
 *
 * Contains class mod_customfilter_form used at the BACKEND ("Manage") to create or edit a
 * customfilter
 *
 * @package mod_datalynx
 * @copyright 2016 Thomas Niedermaier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backend_form extends base_form {

    /**
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function definition() {
        if ($id = $this->_customfilter->id) {
            $customfilter = $this->_getcustomfilter($id);
        } else {
            $customfilter = new stdClass();
            $customfilter->name = "";
            $customfilter->description = "";
            $customfilter->fulltextsearch = false;
            $customfilter->visible = true;
            $customfilter->timecreated = false;
            $customfilter->timecreated_sortable = false;
            $customfilter->timemodified = false;
            $customfilter->timemodified_sortable = false;
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

        $grp = array();
        $grp[] = $mform->createElement('advcheckbox', 'timecreated',
                get_string('timecreated', 'datalynx'));
        $grp[] = $mform->createElement('advcheckbox', 'timecreated_sortable', '',
                get_string('sortable', 'datalynx'), '', array(0, 1));
        $mform->addGroup($grp, '', null, ' ', false);
        $mform->setType('timecreated', PARAM_INT);
        $mform->setDefault('timecreated', $customfilter->timecreated);
        $mform->setType('timecreated_sortable', PARAM_INT);
        $mform->setDefault('timecreated_sortable', $customfilter->timecreated_sortable);

        $grp = array();
        $grp[] = $mform->createElement('advcheckbox', 'timemodified',
                get_string('timemodified', 'datalynx'));
        $grp[] = $mform->createElement('advcheckbox', 'timemodified_sortable', '',
                get_string('sortable', 'datalynx'), '', array(0, 1));
        $mform->addGroup($grp, '', null, ' ', false);
        $mform->setType('timemodified', PARAM_INT);
        $mform->setDefault('timemodified', $customfilter->timemodified);
        $mform->setType('timemodified_sortable', PARAM_INT);
        $mform->setDefault('timemodified_sortable', $customfilter->timemodified_sortable);

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
        $fields = $this->_getpossiblecustomfilterfields($this->_dl);
        foreach ($fields as $fieldid => $field) {
            $formfieldname = 'fieldlist[' . $field->field->id . '][name]';
            $formfieldsortablename = 'fieldlist[' . $field->field->id . '][sortable]';
            $grp = array();
            $grp[] = $mform->createElement('advcheckbox', $formfieldname,
                    $field->field->name . ' (' . $field->type . ')', '', '', $field->field->name);
            $grp[] = $mform->createElement('advcheckbox', $formfieldsortablename, '',
                    get_string('sortable', 'datalynx'), '', array(0, 1));
            $mform->addGroup($grp, '', null, ' ', false);
            $mform->setType($formfieldname, PARAM_TEXT);
            $mform->setType($formfieldsortablename, PARAM_INT);
            foreach ($fieldlist as $fid => $listfield) {
                if ($field->field->id == $fid) {
                    $mform->setDefault($formfieldname, $field->field->name);
                    if ($listfield->sortable) {
                        $mform->setDefault($formfieldsortablename, 1);
                    }
                    break;
                }
            }
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * @param $dl
     * @return array
     * @throws \dml_exception
     */
    protected function _getpossiblecustomfilterfields($dl) {
        global $DB;

        $fields = array();
        $customfilterfieldtypes = $dl->get_customfilterfieldtypes();
        $fieldsdb = $DB->get_records('datalynx_fields', array('dataid' => $dl->id()), 'name asc');
        foreach ($fieldsdb as $fieldid => $field) {
            if (in_array($field->type, $customfilterfieldtypes)) {
                $fields[$fieldid] = $dl->get_field($field);
            }
        }

        return $fields;
    }

    /**
     * @param $filterid
     * @return mixed
     * @throws \dml_exception
     */
    protected function _getcustomfilter($filterid) {
        global $DB;

        $customfilter = $DB->get_record('datalynx_customfilters', array('id' => $filterid));

        return $customfilter;
    }

    /**
     * @return object
     */
    public function get_data() {
        if ($data = parent::get_data()) {
            if (!empty($data->fieldlist)) {
                $fieldlistarray = array();
                foreach ($data->fieldlist as $fieldid => $field) {
                    if ($field['name']) {
                        $fieldlistarray[$fieldid]['name'] = $field['name'];
                        $fieldlistarray[$fieldid]['sortable'] = $field['sortable'];
                    }
                }
                $data->fieldlist = json_encode($fieldlistarray);
            } else {
                $data->fieldlist = null;
            }
        }
        return $data;
    }

    /**
     * @return string
     */
    public function html() {
        return $this->_form->toHtml();
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     * @throws coding_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $dl = $this->_dl;
        if (empty($data['name']) || $dl->name_exists('customfilters', $data['name'])) {
            $errors['name'] = get_string('invalidname', 'datalynx',
                    get_string('filter', 'datalynx'));
        }

        return $errors;
    }

}
