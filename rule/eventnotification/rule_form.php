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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * @package dataform_rule
 * @subpackage eventnotification
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("$CFG->dirroot/mod/dataform/rule/rule_form.php");

class dataform_rule_eventnotification_form extends dataform_rule_form {

    function rule_definition() {
        $br = html_writer::empty_tag('br');
        $sp = '    ';
        $mform = &$this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'settingshdr', get_string('settings'));

        // sender
        $options = array(
            dataform_rule_eventnotification::FROM_AUTHOR => get_string('author', 'dataform'),
            dataform_rule_eventnotification::FROM_CURRENT_USER => get_string('user')
        );
        $mform->addElement('select', 'param2', get_string('from'), $options);

        // recipient
        $grp = array();
        $grp[] = &$mform->createElement('checkbox', 'author', null, get_string('author', 'dataform'), null);
        $grp[] = &$mform->createElement('checkbox', 'rolesenable', null, get_string('roles'), null);
        $select = $grp[] = &$mform->createElement('select', 'roles', get_string('roles'), $this->menu_roles_used_in_context());
        $select->setMultiple(true);
        $mform->addGroup($grp, 'recipientgrp', get_string('to'), array($br, $sp), false);
        $mform->disabledIf('roles', 'rolesenable', 'notchecked');
    }

    protected function menu_roles_used_in_context() {
        $roles = array(); // 0 => 'Manager', 1 => 'Teacher', 2 => 'Student', 3 => 'Guest');
        foreach (get_roles_used_in_context($this->_df->context) as $roleid => $role) {
            $roles[$roleid] = $role->name ? $role->name : $role->shortname;
        }
        return $roles;
    }

    function set_data($data) {
        $recipients = unserialize($data->param3);
        if (isset($recipients['author'])) {
            $data->author = $recipients['author'];
        }
        if (isset($recipients['roles'])) {
            $data->roles = $recipients['roles'];
            $data->rolesenable = 1;
        }
        parent::set_data($data);
    }

    function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            // set recipient
            $recipients = array();
            if (isset($data->author)) {
                $recipients['author'] = 1;
            }

            if (isset($data->rolesenable)) {
                $recipients['roles'] = $data->roles;
            }
            $data->param3 = serialize($recipients);
        }
        return $data;
    }
}
