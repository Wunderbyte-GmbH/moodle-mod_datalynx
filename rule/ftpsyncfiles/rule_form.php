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
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/rule/rule_form.php");

class datalynx_rule_ftpsyncfiles_form extends datalynx_rule_form {

    public function rule_definition() {
        $br = html_writer::empty_tag('br');
        $mform = &$this->_form;

        $mform->addElement('header', 'settingshdr', get_string('sftpsettings',
                'datalynxrule_ftpsyncfiles'));
        $mform->addElement('text', 'param2', get_string('sftpserver', 'datalynxrule_ftpsyncfiles'));
        $mform->setType('param2', PARAM_TEXT);
        $mform->addElement('text', 'param3', get_string('sftpport', 'datalynxrule_ftpsyncfiles'));
        $mform->setType('param3', PARAM_INT);
        $mform->addElement('text', 'param4', get_string('sftpusername', 'datalynxrule_ftpsyncfiles'));
        $mform->setType('param4', PARAM_ALPHAEXT);
        $mform->addElement('text', 'param5', get_string('sftppassword', 'datalynxrule_ftpsyncfiles'));
        $mform->setType('param5', PARAM_TEXT);
        $mform->addElement('text', 'param6', get_string('sftppath', 'datalynxrule_ftpsyncfiles'));
        $mform->setType('param6', PARAM_SAFEPATH);
        $mform->addElement('header', 'settingsprofile', get_string('fields', 'datalynx'));
        $standardfields = array('idnumber' => 'idnumber', 'email' => 'email', 'id' => 'id', 'username' => 'username');
        $mform->addElement('autocomplete', 'param7', get_string('profilefields', 'core_admin'),
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
        $mform->addElement('autocomplete', 'param8', get_string('teammemberselect', 'datalynx'),
                $fieldnames, $options);
        $potentialusers = get_users_by_capability($this->_df->context, 'mod/datalynx:manageentries');
        $choosuser = [];
        foreach ($potentialusers as $user) {
            $choosuser[$user->id] = $user->firstname . ' ' . $user->lastname;
        }
        $mform->addElement('autocomplete', 'param9', get_string('user'),
                $choosuser, $options);
    }

    /**
     */
    public function data_preprocessing(&$data) {
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
