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
 * @subpackage eventnotification
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/rule/rule_form.php");

class datalynx_rule_eventnotification_form extends datalynx_rule_form {

    public function rule_definition() {
        $br = html_writer::empty_tag('br');
        $mform = &$this->_form;

        // Message subject. When empty then use default subject in message.
        $mform->addElement('text', 'param6', get_string('asyncmessagesubject', 'backup'), array('size' => '64'));
        $mform->setType('param6', PARAM_TEXT);

        $mform->addElement('header', 'settingshdr', get_string('settings'));

        // Sender.
        $options = array(
                datalynx_rule_eventnotification::FROM_AUTHOR => get_string('author', 'datalynx'),
                datalynx_rule_eventnotification::FROM_CURRENT_USER => get_string('user'));
        $mform->addElement('select', 'param2', get_string('from'), $options);

        // Recipient.
        $grp = [];
        $grp[] = &$mform->createElement('checkbox', 'author', null, get_string('author', 'datalynx'), array('size' => 1));

        $options = array('multiple' => true);
        $grp[] = &$mform->createElement('static', '', '', "<br><h4 class=\"w-100 mt-3\">" . get_string('roles') . "</h4>");

        $grp[] = &$mform->createElement('autocomplete', 'roles', get_string('roles'),
                $this->_df->get_datalynx_permission_names(true), $options);
        $grp[] = &$mform->createElement('static', '', '', $br);

        $grp[] = &$mform->createElement('static', '', '',
                "<br><h4 class=\"w-100 mt-3\">" . get_string('teammembers', 'datalynx') . "</h4>");
        $grp[] = &$mform->createElement('autocomplete', 'teams', get_string('teams', 'datalynx'),
                $this->get_datalynx_team_fields(), $options);

        // Single userid can be selected.
        $grp[] = &$mform->createElement('static', '', '',
                "<br><h4 class=\"w-100 mt-3\">" . get_string('otheruser', 'datalynx') . "</h4>");
        $allusers = $this->get_allusers();
        $grp[] = $mform->createElement('autocomplete', 'specificuserid', get_string('otheruser', 'datalynx'), $allusers);

        $mform->addGroup($grp, 'recipientgrp', get_string('to'), $br, false);

        // Link settings.
        $mform->addElement('header', 'settingshdr', get_string('linksettings', 'datalynx'));
        $mform->addElement('static', '', get_string('targetviewforroles', 'datalynx'));

        foreach ($this->_df->get_datalynx_permission_names(true) as $permissionid => $permissionname) {
            $views = $this->get_views_visible_to_datalynx_permission($permissionid);
            if (!empty($views)) {
                $mform->addElement('select', "param4[$permissionid]", $permissionname, $views);
            } else {
                $mform->addElement('static', '', $permissionname,
                        get_string('noviewsavailable', 'datalynx'));
            }
        }

        // Content to be included.
        $mform->addElement('header', 'message', get_string('messagecontent', 'datalynxrule_eventnotification'));
        $dlfields = $this->_df->get_fields();
        $fieldmenu = array();
        foreach ($dlfields as $fieldid => $field) {
            if ($field->type == 'text' || $field->type == 'editor' || $field->type == 'textarea') {
                $fieldmenu[$fieldid] = $field->field->name;
            }
        }
        $options = array(
                'multiple' => true,
                'noselectionstring' => get_string('noselection', 'form'),
        );
        $mform->addElement('autocomplete', 'param7', get_string('searcharea', 'search'), $fieldmenu, $options);    }

    /**
     * Get all users in moodle instance for autocomplete list.
     *
     * @return array with userid -> firstname lastname.
     * @throws coding_exception
     */
    public function get_allusers(): array {
        global $DB;
        $tempusers = $DB->get_records('user', array(), '', $fields = 'id, firstname, lastname');

        $allusers[0] = get_string('noselection', 'datalynx');
        foreach ($tempusers as $userdata) {
            // Remove empties to make list more usable.
            if ($userdata->lastname == '') {
                continue;
            }
            $allusers[$userdata->id] = "$userdata->firstname $userdata->lastname";
        }
        return $allusers;
    }

    public function definition_after_data() {
        $mform = &$this->_form;
        $data = $this->get_submitted_data();

        foreach ($this->_df->get_datalynx_permission_names(true) as $permissionid => $permissionname) {
            $views = $this->get_views_visible_to_datalynx_permission($permissionid);
            $defaultview = $this->_df->get_default_view_id();
            if (isset($data) && isset($data->param4[$permissionid]) && $defaultview &&
                    in_array($defaultview, array_keys($views))
            ) {
                $mform->setDefault("param4[$permissionid]", $defaultview);
            }
        }
    }

    /**
     * Get the visible to permissions for a view.
     *
     * @param int $permissionid
     * @return array
     */
    private function get_views_visible_to_datalynx_permission(int $permissionid): array {
        global $DB;
        if ($permissionid == mod_datalynx\datalynx::PERMISSION_ADMIN) {
            $sql = "SELECT id, name FROM {datalynx_views} WHERE dataid = :dataid";
            return $DB->get_records_sql_menu($sql, ['dataid' => $this->_df->id()]);
        } else {
            $sql = "SELECT id, name FROM {datalynx_views} WHERE dataid = :dataid AND visible & :permissionid <> 0";
            return $DB->get_records_sql_menu($sql, ['dataid' => $this->_df->id(), 'permissionid' => $permissionid]);
        }
    }

    protected function get_datalynx_team_fields() {
        global $DB;
        $sql = "SELECT id, name
                  FROM {datalynx_fields}
                 WHERE dataid = :dataid
                   AND " . $DB->sql_like('type', ':type');
        $params = ['dataid' => $this->_df->id(), 'type' => 'teammemberselect'];
        return $DB->get_records_sql_menu($sql, $params);
    }

    /**
     * @return array
     */
    protected function menu_roles_used_in_context(): array {
        $roles = array();
        foreach (get_roles_used_in_context($this->_df->context) as $roleid => $role) {
            $roles[$roleid] = $role->coursealias ? $role->coursealias : ($role->name ? $role->name : $role->shortname);
        }
        return $roles;
    }

    public function set_data($data) {
        $recipients = unserialize($data->param3);
        if (isset($recipients['author'])) {
            $data->author = $recipients['author'];
        }
        if (isset($recipients['roles'])) {
            $data->roles = $recipients['roles'];
        }
        if (isset($recipients['teams'])) {
            $data->teams = $recipients['teams'];
        }
        if (isset($recipients['specificuserid'])) {
            $data->specificuserid = $recipients['specificuserid'];
        }

        $data->param4 = unserialize($data->param4);
        $data->param7 = json_decode($data->param7);
        parent::set_data($data);
    }

    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            // Set recipient.
            $recipients = array();
            if (isset($data->author)) {
                $recipients['author'] = 1;
            }

            if (isset($data->roles)) {
                $recipients['roles'] = $data->roles;
            }

            if (isset($data->teams)) {
                $recipients['teams'] = $data->teams;
            }
            if (isset($data->specificuserid)) {
                $recipients['specificuserid'] = $data->specificuserid;
            }
            $data->param3 = serialize($recipients);
            $data->param4 = serialize($data->param4);
            $data->param7 = json_encode($data->param7);
        }
        return $data;
    }
}
