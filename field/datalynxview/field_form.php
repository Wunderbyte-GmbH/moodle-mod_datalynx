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
 * @subpackage datalynxview
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_datalynxview_form extends datalynxfield_form {

    /**
     * Overrides the field_definition of field/field_form.php for the datalynx_view field-type
     */
    public function field_definition() {
        global $CFG, $PAGE, $DB;

        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr', get_string('fieldattributes', 'datalynx'));

        $dfmenu = $this->get_datalynx_instances_menu();
        $mform->addElement('selectgroups', 'param1', get_string('datalynx', 'datalynxfield_datalynxview'), $dfmenu);
        $mform->addHelpButton('param1', 'datalynx', 'datalynxfield_datalynxview');

        // Select view of given instance (stored in param2).
        $options = array(0 => get_string('choosedots'));
        $mform->addElement('select', 'param2', get_string('view', 'datalynxfield_datalynxview'), $options);
        $mform->disabledIf('param2', 'param1', 'eq', 0);
        $mform->addHelpButton('param2', 'view', 'datalynxfield_datalynxview');

        // Special filter by entry attributes "author" AND/OR "group" (to be stored in param6).
        $grp = array();
        $attr = array('size' => 1);
        $grp[] = &$mform->createElement('advcheckbox', 'entryauthor', null,
                get_string('entryauthor', 'datalynxfield_datalynxview'), $attr, array(0, 1));
        $grp[] = &$mform->createElement('advcheckbox', 'entrygroup', null,
                get_string('entrygroup', 'datalynxfield_datalynxview'), $attr, array(0, 1));
        $mform->addGroup($grp, 'filterbyarr', get_string('filterby', 'datalynxfield_datalynxview'),
                '<br />', false);
        $mform->addHelpButton('filterbyarr', 'filterby', 'datalynxfield_datalynxview');

        // Select textfields of given instance (stored in param7).
        $options = array(0 => get_string('choosedots'));
        $mform->addElement('select', 'param7', get_string('textfield', 'datalynxfield_datalynxview'), $options);
        $mform->disabledIf('param7', 'param1', 'eq', 0);
        $mform->addHelpButton('param7', 'textfield', 'datalynxfield_datalynxview');

        // Ajax view loading.
        $options = array(
                'dffield' => 'param1',
                'viewfield' => 'param2',
                'textfieldfield' => 'param7',
                'acturl' => "$CFG->wwwroot/mod/datalynx/loaddfviews.php",
                'presentdlid' => $this->_df->id(),
                'thisfieldstring' => get_string('thisfield', 'datalynx'),
                'update' => $this->_field->id() ? $this->_field->id() : 0,
                'fieldtype' => 'datalynxview'
        );

        // Add JQuery.
        $PAGE->requires->js_call_amd('mod_datalynx/datalynxloadviews', 'init', array($options));

    }

    /**
     */
    public function definition_after_data() {
        global $DB;

        if ($selectedarr = $this->_form->getElement('param1')->getSelected()) {
            $datalynxid = reset($selectedarr);
        } else {
            $datalynxid = 0;
        }

        if ($datalynxid) {
            if ($views = $DB->get_records_menu('datalynx_views', array('dataid' => $datalynxid), 'name', 'id,name')) {
                $configview = &$this->_form->getElement('param2');
                foreach ($views as $key => $value) {
                    $configview->addOption(strip_tags(format_string($value, true)), $key);
                }
            }

            if ($textfields = $DB->get_records_menu('datalynx_fields',
                    array('dataid' => $datalynxid, 'type' => 'text'), 'name', 'id,name')
            ) {
                $configtextfields = &$this->_form->getElement('param7');
                foreach ($textfields as $key => $value) {
                    $configtextfields->addOption(strip_tags(format_string($value, true)), $key);
                }
            }
        }
    }

    /**
     */
    public function data_preprocessing(&$data) {
        if (!empty($data->param6)) {
            list($data->entryauthor, $data->entrygroup) = explode(',', $data->param6);
        }
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
        if ($data = parent::get_data($slashed)) {
            // Set filter by (param6).
            if ($data->entryauthor || $data->entrygroup) {
                $data->param6 = "$data->entryauthor,$data->entrygroup";
            } else {
                $data->param6 = '';
            }
        }
        return $data;
    }

    /**
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['param1']) && empty($data['param2'])) {
            $errors['param2'] = get_string('missingview', 'datalynxfield_datalynxview');
        }

        return $errors;
    }
}
