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
 * @package datalynx_rule
 * @subpackage ftpsyncfiles
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/rule/rule_form.php");

class datalynx_rule_ftpsyncfiles_form extends datalynx_rule_form {

    public function rule_definition() {
        $br = html_writer::empty_tag('br');
        $mform = &$this->_form;

        $mform->addElement('header', 'settingshdr', get_string('sftpsettings',
                'datalynxrule_ftpsyncfiles'));

        $mform->addElement('hidden', 'param2', '');
        $mform->setType('param2', PARAM_TEXT);

        // SFTP connection and credentials. Save in param2 as serialized data.
        $grp = [];
        $grp[] = &$mform->createElement('static', '', '',
                "<br><b class=\"w-100 mt-3\">" . get_string('sftpserver', 'datalynxrule_ftpsyncfiles') . "</b>");
        $grp[] = &$mform->createElement('text', 'sftpserver', null,
                get_string('sftpserver', 'datalynxrule_ftpsyncfiles'));

        $grp[] = &$mform->createElement('static', '', '',
                "<br><b class=\"w-100 mt-3\">" . get_string('sftpport', 'datalynxrule_ftpsyncfiles') . "</b>");
        $grp[] = &$mform->createElement('text', 'sftpport',
                get_string('sftpport', 'datalynxrule_ftpsyncfiles'));

        $grp[] = &$mform->createElement('static', '', '',
                "<br><b class=\"w-100 mt-3\">" . get_string('sftpusername', 'datalynxrule_ftpsyncfiles') . "</b>");
        $grp[] = &$mform->createElement('text', 'sftpusername',
                get_string('sftpusername', 'datalynxrule_ftpsyncfiles'));

        $grp[] = &$mform->createElement('static', '', '',
                "<br><b class=\"w-100 mt-3\">" . get_string('sftppassword', 'datalynxrule_ftpsyncfiles') . "</b>");
        $grp[] = &$mform->createElement('text', 'sftppassword',
                get_string('sftppassword', 'datalynxrule_ftpsyncfiles'));

        $grp[] = &$mform->createElement('static', '', '',
                "<br><b class=\"w-100 mt-3\">" . get_string('sftppath', 'datalynxrule_ftpsyncfiles') . "</b>");
        $grp[] = &$mform->createElement('text', 'sftppath',
                get_string('sftppath', 'datalynxrule_ftpsyncfiles'));

        $mform->addGroup($grp, 'sftpgrp',
                get_string('sftpsettings', 'datalynxrule_ftpsyncfiles'), $br, false);

        $mform->setType('sftppath', PARAM_SAFEPATH);
        $mform->setType('sftpserver', PARAM_TEXT);
        $mform->setType('sftppassword', PARAM_TEXT);
        $mform->setType('sftpport', PARAM_INT);
        $mform->setType('sftpusername', PARAM_TEXT);

        $mform->addElement('header', 'settingsprofile',
                get_string('matchfields', 'datalynxrule_ftpsyncfiles'));

        $standardfields = array('idnumber' => 'idnumber', 'email' => 'email', 'id' => 'id', 'username' => 'username');
        $mform->addElement('autocomplete', 'param7',
                get_string('identifier', 'datalynxrule_ftpsyncfiles'),
                $standardfields);

        $fields = $this->_df->get_fields(null, false, true);
        $fieldnames = array();
        foreach ($fields as $fieldid => $field) {
            if ($field->type == 'teammemberselect') {
                $fieldnames[$fieldid] = $field->name();
            }
        }
        asort($fieldnames);
        $options = array('multiple' => false);
        $mform->addElement('autocomplete', 'param8',
                get_string('teammemberfield', 'datalynxrule_ftpsyncfiles'), $fieldnames, $options);
        $potentialusers = get_users_by_capability($this->_df->context, 'mod/datalynx:manageentries');
        $choosuser = [];
        foreach ($potentialusers as $user) {
            $choosuser[$user->id] = $user->firstname . ' ' . $user->lastname;
        }
        $mform->addElement('autocomplete', 'param9',
                get_string('manager', 'datalynxrule_ftpsyncfiles'), $choosuser, $options);

        $fields = $this->_df->get_fields_by_type('file', true);
        $mform->addElement('autocomplete', 'param3',
                get_string('filefield', 'datalynxrule_ftpsyncfiles'), $fields, $options);

        // Regular expression for extracting the user identifier from the filename.
        $mform->addElement('text', 'param6', get_string('regex', 'datalynxrule_ftpsyncfiles'));
        $mform->setType('param6', PARAM_TEXT);

    }

    /**
     * @param $data
     * @return void
     */
    public function data_preprocessing(&$data) {
    }

    /**
     */
    public function set_data($data) {
        if (!empty($data->param2)) {
            $sftpsetting = unserialize($data->param2);
            if (isset($sftpsetting['sftpserver'])) {
                $data->sftpserver = $sftpsetting['sftpserver'];
            }
            if (isset($sftpsetting['sftpport'])) {
                $data->sftpport = $sftpsetting['sftpport'];
            }
            if (isset($sftpsetting['sftpusername'])) {
                $data->sftpusername = $sftpsetting['sftpusername'];
            }
            if (isset($sftpsetting['sftppassword'])) {
                $data->sftppassword = $sftpsetting['sftppassword'];
            }
            if (isset($sftpsetting['sftppath'])) {
                $data->sftppath = $sftpsetting['sftppath'];
            }
        }
        parent::set_data($data);
    }

    /**
     * @param $slashed
     * @return object
     */
    public function get_data($slashed = true) {
        $data = parent::get_data($slashed);
        $sftpsetting = [];
        if (isset($data->sftpserver)) {
            $sftpsetting['sftpserver'] = $data->sftpserver;
        }
        if (isset($data->sftpport)) {
            $sftpsetting['sftpport'] = $data->sftpport;
        }
        if (isset($data->sftpusername)) {
            $sftpsetting['sftpusername'] = $data->sftpusername;
        }
        if (isset($data->sftppassword)) {
            $sftpsetting['sftppassword'] = $data->sftppassword;
        }
        if (isset($data->sftppath)) {
            $sftpsetting['sftppath'] = $data->sftppath;
        }
        // Aggregate all form fields into param2 table field.
        if (isset($sftpsetting) && isset($data)) {
            $data->param2 = serialize($sftpsetting);
        }
        return $data;
    }
}
