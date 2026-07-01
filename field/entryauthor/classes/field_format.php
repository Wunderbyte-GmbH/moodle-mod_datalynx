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

namespace datalynxfield_entryauthor;

/**
 * Field format implementation for entryauthor fields.
 *
 * @package    datalynxfield_entryauthor
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format extends \mod_datalynx\local\field_format\base {
    /**
     * Defines configuration elements on the form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form(\MoodleQuickForm &$mform) {
        $options = $this->get_name_options();
        $mform->addElement('select', 'option', get_string('fieldformatoption', 'mod_datalynx'), $options);
        $mform->setType('option', PARAM_ALPHANUM);
        $mform->addRule('option', get_string('required'), 'required', null, 'client');

        // Allow inline editing of the target user profile field (only meaningful for custom profile fields).
        $mform->addElement('advcheckbox', 'editable', get_string('turneditingon', 'moodle'));
        $mform->addHelpButton('editable', 'infofieldeditable', 'datalynxfield_entryauthor');
        $mform->setType('editable', PARAM_BOOL);

        // Require a non-empty value when the profile field is edited inline.
        $mform->addElement('advcheckbox', 'mandatory', get_string('requiredelement', 'form'));
        $mform->addHelpButton('mandatory', 'infofieldmandatory', 'datalynxfield_entryauthor');
        $mform->setType('mandatory', PARAM_BOOL);
        $mform->disabledIf('mandatory', 'editable', 'notchecked');
    }

    /**
     * The built-in entryauthor option keys (mapped to user record fields / hardcoded renderers),
     * as opposed to custom user profile field shortnames.
     *
     * @return string[]
     */
    public static function get_builtin_options(): array {
        return ['name', 'firstname', 'lastname', 'username', 'id', 'idnumber',
                'picture', 'picturelarge', 'email', 'institution', 'department', 'badges', 'edit'];
    }

    /**
     * Whether this format targets a custom user profile field (and can therefore be edited inline),
     * rather than a built-in user record field.
     *
     * @return bool
     */
    public function is_profile_editor(): bool {
        $option = (string) $this->get_setting('option', '');
        return $option !== '' && !in_array($option, self::get_builtin_options(), true);
    }

    /**
     * The shortname of the targeted custom user profile field, or null for built-in options.
     *
     * @return ?string
     */
    public function get_profile_shortname(): ?string {
        return $this->is_profile_editor() ? (string) $this->get_setting('option') : null;
    }

    /**
     * Whether inline editing of the profile field is enabled for this format.
     *
     * @return bool
     */
    public function is_editable(): bool {
        return $this->is_profile_editor() && !empty($this->get_setting('editable'));
    }

    /**
     * Whether a value is mandatory when editing the profile field inline.
     *
     * @return bool
     */
    public function is_mandatory(): bool {
        return $this->is_editable() && !empty($this->get_setting('mandatory'));
    }

    /**
     * Returns inferred default settings when a format is auto-created from a legacy
     * hardcoded tag name (e.g. ##author:firstname## -> name='firstname').
     *
     * @param string $name The format name as detected from the template tag.
     * @return array Key-value settings array, empty if no defaults apply.
     */
    public function get_default_settings_for_name(string $name): array {
        return ['option' => $name];
    }

    /**
     * Returns the array of available options for the entryauthor format option.
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
                // Ensure name is only alphanumeric to pass validation.
                if (preg_match('/^[a-zA-Z0-9]+$/', $cf->shortname)) {
                    $options[$cf->shortname] = format_string($cf->name);
                }
            }
        }

        return $options;
    }
}
