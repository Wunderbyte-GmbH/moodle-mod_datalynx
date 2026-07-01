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

namespace datalynxfield_multiselect;

/**
 * Field format implementation for multiselect fields.
 *
 * @package    datalynxfield_multiselect
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
        $options = [
            'newline' => 'New line',
            'space' => 'Space',
            'comma' => 'Comma',
            'commaspace' => 'Comma (with space)',
            'list' => 'Unordered list',
        ];
        $mform->addElement('select', 'option', get_string('fieldformatoption', 'mod_datalynx'), $options);
        $mform->setType('option', PARAM_ALPHA);

        // Edit-mode behaviour: allow the user to type and add new options (autocomplete fields only).
        // Replaces the legacy [[field:addnew]] suffix.
        $mform->addElement(
            'advcheckbox',
            'addnew',
            get_string('fieldformatmultiselectaddnew', 'datalynxfield_multiselect')
        );
        $mform->setType('addnew', PARAM_INT);
    }

    /**
     * Map the legacy display/edit suffixes to settings so formats auto-created during upgrade/restore
     * reproduce the legacy rendering.
     *
     * @param string $name
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        if (in_array($name, ['newline', 'space', 'comma', 'commaspace', 'list'])) {
            return ['option' => $name];
        }
        if ($name === 'addnew') {
            return ['addnew' => 1];
        }
        return [];
    }
}
