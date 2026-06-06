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
 * Field format class for the entryauthor field type.
 *
 * @package    datalynxfield_entryauthor
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entryauthor;

use mod_datalynx\local\field_format\base;
use MoodleQuickForm;

/**
 * Field format for entryauthor: controls which user attribute is displayed.
 */
class field_format extends base {
    /** @var array Valid display_field values for entryauthor. */
    const VALID_FIELDS = [
        'id', 'name', 'firstname', 'lastname', 'username', 'idnumber',
        'email', 'institution', 'department', 'picture', 'picturelarge', 'badges', 'edit',
    ];

    /**
     * Adds a 'display_field' select to the format config form.
     *
     * @param MoodleQuickForm $mform The form object.
     */
    public function config_form(MoodleQuickForm &$mform): void {
        $options = [
            'id'           => get_string('userid',          'datalynxfield_entryauthor'),
            'name'         => get_string('username',        'datalynxfield_entryauthor'),
            'firstname'    => get_string('userfirstname',   'datalynxfield_entryauthor'),
            'lastname'     => get_string('userlastname',    'datalynxfield_entryauthor'),
            'username'     => get_string('userusername',    'datalynxfield_entryauthor'),
            'idnumber'     => get_string('useridnumber',    'datalynxfield_entryauthor'),
            'email'        => get_string('useremail',       'datalynxfield_entryauthor'),
            'institution'  => get_string('userinstitution', 'datalynxfield_entryauthor'),
            'department'   => get_string('userdepartment',  'datalynxfield_entryauthor'),
            'picture'      => get_string('userpicture',     'datalynxfield_entryauthor'),
            'picturelarge' => get_string('userpicturelarge', 'datalynxfield_entryauthor'),
            'badges'       => get_string('userbadges',      'datalynxfield_entryauthor'),
        ];
        $mform->addElement(
            'select',
            'display_field',
            get_string('fieldformat_displayfield', 'datalynxfield_entryauthor'),
            $options
        );
        $mform->setType('display_field', PARAM_ALPHA);
        $mform->addHelpButton('display_field', 'fieldformat_displayfield', 'datalynxfield_entryauthor');

        // Set default from existing settings.
        $current = $this->get_setting('display_field');
        if ($current !== null) {
            $mform->setDefault('display_field', $current);
        }
    }

    /**
     * Returns inferred default settings from a legacy hardcoded format name.
     *
     * @param string $name The format name detected from the template tag.
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        if (in_array($name, self::VALID_FIELDS, true)) {
            return ['display_field' => $name];
        }
        return [];
    }
}
