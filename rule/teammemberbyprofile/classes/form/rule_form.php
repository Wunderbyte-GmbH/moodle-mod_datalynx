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
 * @package datalynxrule_teammemberbyprofile
 * @subpackage teammemberbyprofile
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxrule_teammemberbyprofile\form;

use datalynxfield_select\field as selectfield;

/**
 * Settings form for the teammemberbyprofile rule.
 *
 * @package datalynxrule_teammemberbyprofile
 */
class rule_form extends \mod_datalynx\form\rule_form {
    /**
     * Adds the rule-specific configuration fields.
     *
     * @return void
     */
    public function rule_definition() {
        $mform = &$this->_form;
        $comp = 'datalynxrule_teammemberbyprofile';

        $mform->addElement('header', 'teammemberbyprofilehdr', get_string('pluginname', $comp));
        $mform->setExpanded('teammemberbyprofilehdr');

        // Source single-select field.
        $selectfields = ['' => get_string('choosedots')] + $this->dlx->get_fields_by_type('select', true);
        $mform->addElement('select', 'param6', get_string('selectfield', $comp), $selectfields);
        $mform->addHelpButton('param6', 'selectfield', $comp);

        // Profile field to match against the selected option label.
        $profilemenu = ['' => get_string('choosedots')] + selectfield::get_profile_field_menu();
        $mform->addElement('select', 'param7', get_string('profilefield', $comp), $profilemenu);
        $mform->addHelpButton('param7', 'profilefield', $comp);

        // Target teammemberselect field.
        $teamfields = ['' => get_string('choosedots')] + $this->dlx->get_fields_by_type('teammemberselect', true);
        $mform->addElement('select', 'param8', get_string('teammemberfield', $comp), $teamfields);
        $mform->addHelpButton('param8', 'teammemberfield', $comp);

        // Required view privilege the candidate users must hold.
        $tiers = [
            'manager' => get_string('privilege_manager', $comp),
            'teacher' => get_string('privilege_teacher', $comp),
            'student' => get_string('privilege_student', $comp),
            'guest' => get_string('privilege_guest', $comp),
        ];
        $mform->addElement('select', 'param9', get_string('privilege', $comp), $tiers);
        $mform->addHelpButton('param9', 'privilege', $comp);
        $mform->setDefault('param9', 'teacher');

        // How to treat members already present in the team field.
        $modes = [
            'overwrite' => get_string('mode_overwrite', $comp),
            'merge' => get_string('mode_merge', $comp),
        ];
        $mform->addElement('select', 'param2', get_string('mode', $comp), $modes);
        $mform->addHelpButton('param2', 'mode', $comp);
        $mform->setDefault('param2', 'overwrite');
    }
}
