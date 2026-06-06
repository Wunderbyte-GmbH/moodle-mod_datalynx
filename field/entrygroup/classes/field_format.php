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
 * Field format class for the entrygroup field type.
 *
 * @package    datalynxfield_entrygroup
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entrygroup;

use mod_datalynx\local\field_format\base;
use MoodleQuickForm;

/**
 * Field format for entrygroup: controls which group attribute is displayed.
 */
class field_format extends base {
    /** @var array Valid display_field values for entrygroup. */
    const VALID_FIELDS = ['id', 'name', 'picture', 'picturelarge'];

    /**
     * Adds a 'display_field' select to the format config form.
     *
     * @param MoodleQuickForm $mform The form object.
     */
    public function config_form(MoodleQuickForm &$mform): void {
        $options = [
            'id'           => get_string('groupid',          'datalynxfield_entrygroup'),
            'name'         => get_string('groupname',        'datalynxfield_entrygroup'),
            'picture'      => get_string('grouppicture',     'datalynxfield_entrygroup'),
            'picturelarge' => get_string('grouppicturelarge', 'datalynxfield_entrygroup'),
        ];
        $mform->addElement(
            'select',
            'display_field',
            get_string('fieldformat_displayfield', 'datalynxfield_entrygroup'),
            $options
        );
        $mform->setType('display_field', PARAM_ALPHA);
        $mform->addHelpButton('display_field', 'fieldformat_displayfield', 'datalynxfield_entrygroup');

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
