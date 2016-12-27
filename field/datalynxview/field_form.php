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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package datalynxfield
 * @subpackage datalynxview
 * @copyright 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once ("$CFG->dirroot/mod/datalynx/field/field_form.php");


class datalynxfield_datalynxview_form extends datalynxfield_form {

    /**
     * Overrides the field_definition of field/field_form.php for the datalynx_view field-type
     */
    function field_definition() {
        global $CFG, $PAGE, $DB;
        
        $mform = &$this->_form;
        
        // -------------------------------------------------------------------------------
        $mform->addElement('header', 'fieldattributeshdr', 
                get_string('fieldattributes', 'datalynx'));
        
        // Get all Datalynxs where user has managetemplate capability
        // TODO there may be too many
        if ($datalynxs = $DB->get_records('datalynx')) {
            foreach ($datalynxs as $dfid => $datalynx) {
                $df = new datalynx($datalynx);
                // Remove if user cannot manage
                if (!has_capability('mod/datalynx:managetemplates', $df->context)) {
                    unset($datalynxs[$dfid]);
                    continue;
                }
                $datalynxs[$dfid] = $df;
            }
        }
        
        // select Datalynx instance (to be stored in param1)
        if ($datalynxs) {
            $dfmenu = array('' => array(0 => get_string('choosedots')));
            foreach ($datalynxs as $dfid => $df) {
                if (!isset($dfmenu[$df->course->shortname])) {
                    $dfmenu[$df->course->shortname] = array();
                }
                $dfmenu[$df->course->shortname][$dfid] = strip_tags(
                        format_string($df->name(), true));
            }
        } else {
            $dfmenu = array('' => array(0 => get_string('nodatalynxs', 'datalynxfield_datalynxview')));
        }
        $mform->addElement('selectgroups', 'param1', 
                get_string('datalynx', 'datalynxfield_datalynxview'), $dfmenu);
        $mform->addHelpButton('param1', 'datalynx', 'datalynxfield_datalynxview');
        
        // Select view of given instance (stored in param2)
        $options = array(0 => get_string('choosedots'));
        $mform->addElement('select', 'param2', get_string('view', 'datalynxfield_datalynxview'), 
                $options);
        $mform->disabledIf('param2', 'param1', 'eq', 0);
        $mform->addHelpButton('param2', 'view', 'datalynxfield_datalynxview');

        // Special filter by entry attributes "author" AND/OR "group" (to be stored in param6)
        $grp = array();
        $grp[] = &$mform->createElement('advcheckbox', 'entryauthor', null, 
                get_string('entryauthor', 'datalynxfield_datalynxview'), null, array(0, 1));
        $grp[] = &$mform->createElement('advcheckbox', 'entrygroup', null, 
                get_string('entrygroup', 'datalynxfield_datalynxview'), null, array(0, 1));
        $mform->addGroup($grp, 'filterbyarr', get_string('filterby', 'datalynxfield_datalynxview'), 
                '<br />', false);
        $mform->addHelpButton('filterbyarr', 'filterby', 'datalynxfield_datalynxview');

        // Select textfields of given instance (stored in param7)
        $options = array(0 => get_string('choosedots'));
        $mform->addElement('select', 'param7', get_string('textfield', 'datalynxfield_datalynxview'),
            $options);
        $mform->disabledIf('param7', 'param1', 'eq', 0);
        $mform->addHelpButton('param7', 'textfield', 'datalynxfield_datalynxview');

        // ajax view loading
        $options = array(
            'dffield' => 'param1',
            'viewfield' => 'param2',
            'textfieldfield' => 'param7',
            'acturl' => "$CFG->wwwroot/mod/datalynx/loaddfviews.php"
        );
        
        $module = array(
            'name' => 'M.mod_datalynx_load_views',
            'fullpath' => '/mod/datalynx/datalynxloadviews.js', 
            'requires' => array('base', 'io', 'node')
        );
        
        $PAGE->requires->js_init_call('M.mod_datalynx_load_views.init', array($options), false, $module);
    }

    /**
     */
    function definition_after_data() {
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
                                array('dataid' => $datalynxid, 'type' => 'text'), 'name', 'id,name')) {
                $configtextfields = &$this->_form->getElement('param7');
                foreach ($textfields as $key => $value) {
                    $configtextfields->addOption(strip_tags(format_string($value, true)), $key);
                }
            }
        }
    }

    /**
     */
    function data_preprocessing(&$data) {
        if (!empty($data->param6)) {
            list($data->entryauthor, $data->entrygroup) = explode(',', $data->param6);
        }
    }

    /**
     */
    function set_data($data) {
        $this->data_preprocessing($data);
        parent::set_data($data);
    }

    /**
     */
    function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            // set filter by (param6)
            if ($data->entryauthor or $data->entrygroup) {
                $data->param6 = "$data->entryauthor,$data->entrygroup";
            } else {
                $data->param6 = '';
            }
        }
        return $data;
    }

    /**
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        $errors = array();
        
        if (!empty($data['param1']) and empty($data['param2'])) {
            $errors['param2'] = get_string('missingview', 'datalynxfield_datalynxview');
        }
        
        return $errors;
    }
}
