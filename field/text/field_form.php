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
 * @subpackage text
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_text_form extends datalynxfield_form {

    /**
     * @return void
     */
    public function field_definition() {
        global $OUTPUT, $DB, $PAGE, $CFG;

        $mform = &$this->_form;

        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));

        // Auto link.
        $mform->addElement('checkbox', 'param1', get_string('fieldallowautolink', 'datalynx'));

        // Param2 (integer) and param3 (px,em/%) were used for width. They are not used anymore. Number field now
        // uses param2 for displaying default value when field is left empty.

        // Check for duplicate entries.
        $duplicates = $this->has_duplicates();

        $mform->addElement('selectyesno', 'param8', get_string('unique', 'datalynx'));
        $mform->setType('param8', PARAM_BOOL);

        if ($duplicates) {
            // We set it constantly to 'no' if there are duplicates!
            $mform->setConstant('param8', 0);
            $mform->freeze('param8');
            // Display the duplicate-entries-message and the list of duplicate entries.
            $mform->addElement('static', 'duplicatestext', '',
                    $OUTPUT->notification(get_string('field_has_duplicate_entries', 'datalynx'), 'notifymessage'));
        } else {
            // If there are no duplicates the default option for unique is "No" as well, but the user can change it.
            $mform->setDefault('param8', 0);
        }

        // Get all Datalynxs where user has managetemplate capability.
        // TODO there may be too many.
        $sql = "SELECT DISTINCT d.*
                FROM {datalynx} d
                INNER JOIN {course_modules} cm ON d.id = cm.instance
                INNER JOIN {modules} m ON m.id = cm.module
                WHERE m.name = 'datalynx'";
        if ($CFG->branch >= 32) {
            $sql .= " AND cm.deletioninprogress = 0";
        }
        if ($datalynxs = $DB->get_records_sql($sql)) {
            foreach ($datalynxs as $dfid => $datalynx) {
                if ($dfid != $this->_df->id()) {
                    $df = new mod_datalynx\datalynx($datalynx);
                    // Remove if user cannot manage.
                    if (!has_capability('mod/datalynx:managetemplates', $df->context)) {
                        unset($datalynxs[$dfid]);
                        continue;
                    }
                    $datalynxs[$dfid] = $df;
                } else {
                    unset($datalynxs[$dfid]);
                }
            }
        }

        // Autocompletion with content of other textfield from the same or other datalynx instance.
        //
        // Select Datalynx instance (to be stored in param9).
        if ($datalynxs || $this->_df->id() > 0) {
            $dfmenu = array('' => array(0 => get_string('noautocompletion', 'datalynx')));
            $dfmenu[''][$this->_df->id()] = get_string('thisdatalynx', 'datalynx') .
                    " (" . strip_tags(format_string($this->_df->name(), true)) . ")";
            foreach ($datalynxs as $dfid => $df) {
                if (!isset($dfmenu[$df->course->shortname])) {
                    $dfmenu[$df->course->shortname] = array();
                }
                $dfmenu[$df->course->shortname][$dfid] = strip_tags(
                        format_string($df->name(), true));
            }
        } else {
            $dfmenu = array('' => array(0 => get_string('nodatalynxs', 'datalynx')));
        }
        $mform->addElement('selectgroups', 'param9', get_string('autocompletion', 'datalynx'), $dfmenu);
        $mform->addHelpButton('param9', 'autocompletion_textfield', 'datalynx');

        // Select textfields of given instance (stored in param10).
        $options = array(0 => get_string('choosedots'));
        $mform->addElement('select', 'param10', get_string('textfield', 'datalynx'), $options);
        $mform->disabledIf('param10', 'param9', 'eq', 0);
        $mform->addHelpButton('param10', 'textfield', 'datalynx');
        $mform->setType('param10', PARAM_INT);

        // Ajax view loading.
        $options = array(
                'dffield' => 'param9',
                'textfieldfield' => 'param10',
                'acturl' => "$CFG->wwwroot/mod/datalynx/loaddfviews.php",
                'presentdlid' => $this->_df->id(),
                'thisfieldstring' => get_string('thisfield', 'datalynx'),
                'update' => $this->_field->id() ? $this->_field->id() : 0,
                'fieldtype' => 'text'
        );

        // Add JQuery
        $PAGE->requires->js_call_amd('mod_datalynx/datalynxloadviews', 'init', array($options));

        // Rules.
        $mform->addElement('header', 'fieldruleshdr', get_string('fieldrules', 'datalynx'));

        // Format rules.
        $options = array('' => get_string('choosedots'),
                'alphanumeric' => get_string('err_alphanumeric', 'form'),
                'lettersonly' => get_string('err_lettersonly', 'form'),
                'numeric' => get_string('err_numeric', 'form'),
                'email' => get_string('err_email', 'form'),
                'nopunctuation' => get_string('err_nopunctuation', 'form'));
        $mform->addElement('select', 'param4', get_string('format'), $options);

        // Length (param5, 6, 7) minimum, maximum, range.
        $options = array('' => get_string('choosedots'),
                'minlength' => get_string('min', 'datalynx'),
                'maxlength' => get_string('max', 'datalynx'),
                'rangelength' => get_string('range', 'datalynx'));
        $grp = array();
        $grp[] = &$mform->createElement('select', 'param5', null, $options);
        $grp[] = &$mform->createElement('text', 'param6', null, array('size' => 8));
        $grp[] = &$mform->createElement('text', 'param7', null, array('size' => 8));
        $mform->addGroup($grp, 'lengthgrp', get_string('numcharsallowed', 'datalynx'), '    ', false);
        $mform->addGroupRule('lengthgrp',
                array('param6' => array(array(null, 'numeric', null, 'client'))));
        $mform->addGroupRule('lengthgrp',
                array('param7' => array(array(null, 'numeric', null, 'client'))));
        $mform->disabledIf('param6', 'param5', 'eq', '');
        $mform->disabledIf('param6', 'param5', 'eq', 'maxlength');
        $mform->disabledIf('param7', 'param5', 'eq', '');
        $mform->disabledIf('param7', 'param5', 'eq', 'minlength');
        $mform->setType('param6', PARAM_INT);
        $mform->setType('param7', PARAM_INT);

    }

    /**
     */
    public function definition_after_data() {
        global $DB;

        if ($selectedarr = $this->_form->getElement('param9')->getSelected()) {
            $refdatalynxid = reset($selectedarr);
        } else {
            $refdatalynxid = 0;
        }
        if ($selectedarr = $this->_form->getElement('param10')->getSelected()) {
            $textfieldid = reset($selectedarr);
        } else {
            $textfieldid = 0;
        }

        if ($refdatalynxid) {
            if ($textfields = $DB->get_records_menu('datalynx_fields',
                    array('dataid' => $refdatalynxid, 'type' => 'text'), 'name', 'id,name')
            ) {
                $formfield = &$this->_form->getElement('param10');
                // Add the option to choose this new field itself as autocompletion reference field.
                if ($this->_df->id() == $refdatalynxid && $textfieldid == 0) {
                    $formfield->addOption(get_string('thisfield', 'datalynx'), -1);
                }
                foreach ($textfields as $key => $value) {
                    $formfield->addOption(strip_tags(format_string($value, true)), $key);
                }
                $this->_form->setDefault('param10', $textfieldid);
            }
        }
    }

    /**
     * Ensures there are no duplicate entries right now if unique is set to 'yes'!
     *
     * @param array $data
     * @param array $files
     * @return string[] Associative array with errors
     */
    public function validation($data, $files) {
        $mform = &$this->_form;
        $errors = parent::validation($data, $files);
        $fieldid = $this->_field->id();
        if (!empty($data['param8']) && !empty($fieldid)) {
            // Unique is activated, we check if there are doubles!
            // Should never happen, because we freeze it to 'no' if there are duplicates!
            if ($this->has_duplicates()) {
                $errors['param8'] = get_string('field_has_duplicate_entries', 'datalynx');
            }
        }

        return $errors;
    }

    /**
     * Check if duplicate content is found for the given text field.
     *
     * @return bool true if duplicates are found.
     */
    public function has_duplicates() {
        global $DB;

        $fieldid = $this->_field->id();

        if (empty($fieldid)) {
            return false;
        }

        // Added id to records to make the first column something unique.
        $records = $DB->get_records_sql("SELECT c.fieldid, c.content, COUNT(*) as cnt
                                    FROM {datalynx_contents} c
                                    WHERE c.fieldid = :fieldid AND c.content IS NOT NULL
                                    GROUP BY c.fieldid, c.content
                                    HAVING COUNT(*) > 1", array('fieldid' => $fieldid));
        if (empty($records)) {
            return false;
        }
        return true;
    }
}
