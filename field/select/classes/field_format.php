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

namespace datalynxfield_select;

/**
 * Field format implementation for select fields.
 *
 * @package    datalynxfield_select
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format extends \mod_datalynx\local\field_format\base {
    /**
     * The display formats a select format can choose from.
     *
     * @return array Option => human readable name.
     */
    public static function get_display_options(): array {
        return [
            'default' => get_string('fieldformatoptiondefault', 'datalynxfield_select'),
            'options' => get_string('fieldformatoptionoptions', 'datalynxfield_select'),
            'key' => get_string('fieldformatoptionkey', 'datalynxfield_select'),
        ];
    }

    /**
     * Defines configuration elements on the form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form(\MoodleQuickForm &$mform) {
        self::define_elements($mform);
    }

    /**
     * Adds every configuration element of this format to a form.
     *
     * Shared by {@see self::config_form()} and
     * {@see \datalynxfield_select\form\field_format_form::format_definition()} so the definition
     * rendered by fieldformat/edit.php cannot drift from the one declared by the format class.
     *
     * @param \MoodleQuickForm $mform
     * @return void
     */
    public static function define_elements(\MoodleQuickForm &$mform): void {
        // Which render mode a format applies to differs per field type, so spell it out on the
        // form rather than leaving it to the help icon.
        $mform->addElement(
            'static',
            'fieldformatscope',
            '',
            get_string('fieldformatscope', 'datalynxfield_select')
        );

        $mform->addElement(
            'select',
            'option',
            get_string('fieldformatoption', 'datalynxfield_select'),
            self::get_display_options()
        );
        $mform->setType('option', PARAM_ALPHA);
        $mform->addRule('option', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('option', 'fieldformatoption', 'datalynxfield_select');
    }

    /**
     * Get legacy/default settings for name.
     *
     * @param string $name
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        if (in_array($name, ['options', 'key'])) {
            return ['option' => $name];
        }
        return [];
    }
}
