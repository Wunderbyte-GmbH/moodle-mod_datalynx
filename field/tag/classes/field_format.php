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

namespace datalynxfield_tag;

/**
 * Field format implementation for tag fields.
 *
 * @package    datalynxfield_tag
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
        $mform->addElement('selectyesno', 'linked', get_string('fieldformattaglinked', 'datalynxfield_tag'));
        $mform->setDefault('linked', 1);
        $mform->setType('linked', PARAM_INT);
    }

    /**
     * Map the legacy [[tag:nolink]] suffix to the linked=0 setting so formats auto-created during
     * upgrade/restore reproduce the legacy "render tags without links" behaviour.
     *
     * @param string $name
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        if ($name === 'nolink') {
            return ['linked' => 0];
        }
        return [];
    }
}
