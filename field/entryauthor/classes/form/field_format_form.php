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

namespace datalynxfield_entryauthor\form;

/**
 * Form class for entryauthor field formats.
 *
 * @package    datalynxfield_entryauthor
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format_form extends \mod_datalynx\form\field_format_base_form {
    /**
     * Defines configuration elements on the form.
     */
    protected function format_definition() {
        global $DB;
        $mform = &$this->_form;

        // Add the select dropdown for the display option.
        $options = $this->get_name_options();
        $mform->addElement('select', 'option', get_string('fieldformatoption', 'mod_datalynx'), $options);
        $mform->setType('option', PARAM_ALPHANUM);
        $mform->addRule('option', get_string('required'), 'required', null, 'client');

        // Add an info panel for each custom profile field; shown only when that field is selected.
        $customfields = $DB->get_records(
            'user_info_field',
            null,
            'sortorder ASC',
            'shortname, name, datatype, description, descriptionformat, required'
        );
        if ($customfields) {
            foreach ($customfields as $cf) {
                if (!preg_match('/^[a-zA-Z0-9]+$/', $cf->shortname)) {
                    continue;
                }
                $elementname = 'profileinfo_' . $cf->shortname;
                $mform->addElement('static', $elementname, '', $this->render_profile_field_info($cf));
                $mform->hideIf($elementname, 'option', 'neq', $cf->shortname);
            }
        }

        // Allow inline editing of the target user profile field (only meaningful for custom profile fields).
        $mform->addElement('advcheckbox', 'editable', get_string('turneditingon', 'moodle'));
        $mform->addHelpButton('editable', 'infofield_editable', 'datalynxfield_entryauthor');
        $mform->setType('editable', PARAM_BOOL);

        // Require a non-empty value when the profile field is edited inline.
        $mform->addElement('advcheckbox', 'mandatory', get_string('requiredelement', 'form'));
        $mform->addHelpButton('mandatory', 'infofield_mandatory', 'datalynxfield_entryauthor');
        $mform->setType('mandatory', PARAM_BOOL);
        $mform->disabledIf('mandatory', 'editable', 'notchecked');

        // Hide editable and mandatory when a built-in (non-profile-field) option is selected.
        foreach (\datalynxfield_entryauthor\field_format::get_builtin_options() as $builtin) {
            $mform->hideIf('editable', 'option', 'eq', $builtin);
            $mform->hideIf('mandatory', 'option', 'eq', $builtin);
        }
    }

    /**
     * Returns an HTML info block describing a custom user profile field.
     *
     * @param \stdClass $cf The custom profile field record.
     * @return string HTML fragment.
     */
    private function render_profile_field_info(\stdClass $cf): string {
        $typestr = get_string_manager()->string_exists('pluginname', 'profilefield_' . $cf->datatype)
            ? get_string('pluginname', 'profilefield_' . $cf->datatype)
            : $cf->datatype;

        $lines = [];
        $lines[] = \html_writer::tag('strong', get_string('profilefieldtype', 'datalynxfield_entryauthor'))
            . ': ' . \html_writer::tag('code', $typestr);

        if (!empty($cf->description)) {
            $desc = format_text($cf->description, $cf->descriptionformat);
            $lines[] = \html_writer::tag('strong', get_string('description', 'moodle')) . ': ' . $desc;
        }

        if (!empty($cf->required)) {
            $lines[] = \html_writer::tag('strong', get_string('required', 'moodle'));
        }

        return \html_writer::tag(
            'div',
            implode(\html_writer::empty_tag('br') . "\n", $lines),
            ['class' => 'alert alert-info p-2 mt-1 mb-2']
        );
    }

    /**
     * Returns the array of available options for the entryauthor format name.
     *
     * @return array
     */
    protected function get_name_options(): array {
        global $DB;

        $options = [
            'name' => get_string_manager()->string_exists('fullname', 'moodle') ?
                get_string('fullname', 'moodle') : 'Full name',
            'firstname' => get_string_manager()->string_exists('firstname', 'moodle') ?
                get_string('firstname', 'moodle') : 'First name',
            'lastname' => get_string_manager()->string_exists('lastname', 'moodle') ?
                get_string('lastname', 'moodle') : 'Last name',
            'username' => get_string_manager()->string_exists('username', 'moodle') ?
                get_string('username', 'moodle') : 'Username',
            'id' => get_string_manager()->string_exists('userid', 'mod_datalynx') ?
                get_string('userid', 'mod_datalynx') : 'User ID',
            'idnumber' => get_string_manager()->string_exists('idnumber', 'moodle') ?
                get_string('idnumber', 'moodle') : 'ID number',
            'picture' => get_string_manager()->string_exists('picture', 'mod_datalynx') ?
                get_string('picture', 'mod_datalynx') : 'Picture',
            'picturelarge' => get_string_manager()->string_exists('picturelarge', 'mod_datalynx') ?
                get_string('picturelarge', 'mod_datalynx') : 'Picture large',
            'email' => get_string_manager()->string_exists('email', 'moodle') ?
                get_string('email', 'moodle') : 'Email',
            'institution' => get_string_manager()->string_exists('institution', 'moodle') ?
                get_string('institution', 'moodle') : 'Institution',
            'department' => get_string_manager()->string_exists('department', 'moodle') ?
                get_string('department', 'moodle') : 'Department',
            'badges' => get_string_manager()->string_exists('badges', 'mod_datalynx') ?
                get_string('badges', 'mod_datalynx') : 'Badges',
            'edit' => get_string_manager()->string_exists('edit', 'moodle') ?
                get_string('edit', 'moodle') : 'Edit',
        ];

        // Retrieve custom user profile fields from the database.
        $customfields = $DB->get_records('user_info_field', null, 'sortorder ASC', 'shortname, name');
        if ($customfields) {
            foreach ($customfields as $cf) {
                // Ensure name is only alphanumeric to pass parent validation.
                if (preg_match('/^[a-zA-Z0-9]+$/', $cf->shortname)) {
                    $options[$cf->shortname] = format_string($cf->name);
                }
            }
        }

        return $options;
    }
}
