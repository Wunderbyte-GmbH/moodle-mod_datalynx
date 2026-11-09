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

namespace datalynxfield_radiobutton;

/**
 * Field format implementation for radiobutton fields.
 *
 * @package    datalynxfield_radiobutton
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format extends \mod_datalynx\local\field_format\base {
    /** @var string[] Styles a special state of the stepper can use (Bootstrap contextual colours). */
    public const SPECIAL_STYLES = ['warning', 'danger', 'info', 'success', 'secondary'];

    /**
     * The display formats a radiobutton format can choose from.
     *
     * @return array Option => human readable name.
     */
    public static function get_display_options(): array {
        return [
            'default' => get_string('fieldformatoptiondefault', 'datalynxfield_radiobutton'),
            'options' => get_string('fieldformatoptionoptions', 'datalynxfield_radiobutton'),
            'key' => get_string('fieldformatoptionkey', 'datalynxfield_radiobutton'),
            'stepper' => get_string('fieldformatoptionstepper', 'datalynxfield_radiobutton'),
        ];
    }

    /**
     * Defines configuration elements on the form.
     *
     * @param \MoodleQuickForm $mform
     */
    public function config_form(\MoodleQuickForm &$mform): void {
        self::define_elements($mform);
    }

    /**
     * Adds every configuration element of this format to a form.
     *
     * Shared by {@see self::config_form()} and
     * {@see \datalynxfield_radiobutton\form\field_format_form::format_definition()} so the
     * definition rendered by fieldformat/edit.php cannot drift from the one declared by the
     * format class.
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
            get_string('fieldformatscope', 'datalynxfield_radiobutton')
        );

        $mform->addElement(
            'select',
            'option',
            get_string('fieldformatoption', 'datalynxfield_radiobutton'),
            self::get_display_options()
        );
        $mform->setType('option', PARAM_ALPHA);
        $mform->addRule('option', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('option', 'fieldformatoption', 'datalynxfield_radiobutton');

        $mform->addElement(
            'text',
            'stepper_icons',
            get_string('fieldformatoptionsteppericons', 'datalynxfield_radiobutton'),
            ['size' => 60]
        );
        $mform->setType('stepper_icons', PARAM_TEXT);
        $mform->addHelpButton('stepper_icons', 'fieldformatoptionsteppericons', 'datalynxfield_radiobutton');
        $mform->hideIf('stepper_icons', 'option', 'neq', 'stepper');

        $mform->addElement(
            'textarea',
            'stepper_special',
            get_string('fieldformatoptionstepperspecial', 'datalynxfield_radiobutton'),
            ['rows' => 4, 'cols' => 40]
        );
        $mform->setType('stepper_special', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('stepper_special', 'fieldformatoptionstepperspecial', 'datalynxfield_radiobutton');
        $mform->hideIf('stepper_special', 'option', 'neq', 'stepper');
    }

    /**
     * Validates the special states setting of the stepper.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation(array $data, array $files): array {
        return self::validate_elements($data);
    }

    /**
     * Validates the configuration elements of this format.
     *
     * @param array $data Form data.
     * @return array Validation errors keyed by element name.
     */
    public static function validate_elements(array $data): array {
        $errors = [];
        if (($data['option'] ?? '') === 'stepper' && !empty($data['stepper_special'])) {
            $invalid = [];
            self::parse_special_states((string) $data['stepper_special'], $invalid);
            if ($invalid) {
                $errors['stepper_special'] = get_string(
                    'fieldformatoptionstepperspecialerror',
                    'datalynxfield_radiobutton',
                    implode(', ', $invalid)
                );
            }
        }
        return $errors;
    }

    /**
     * Parses the special states of the stepper.
     *
     * One state per line: "option:step:style". The option is the number of the option that is
     * not shown as a step of its own. While an entry has this option, the stepper shows progress
     * up to the given step (the number of another option) and marks that step with the style.
     * Without a step (or with 0) no step is reached and the state is shown below the stepper.
     * The style defaults to "warning".
     *
     * @param string $setting The raw setting.
     * @param array|null $invalid Receives the lines that could not be parsed.
     * @return array Option number => ['step' => int, 'style' => string].
     */
    public static function parse_special_states(string $setting, ?array &$invalid = null): array {
        $states = [];
        $invalid = [];
        foreach (preg_split('/\R/', $setting) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode(':', $line));
            $option = $parts[0];
            $step = $parts[1] ?? '';
            $style = strtolower($parts[2] ?? '') ?: 'warning';
            if (
                count($parts) > 3 || !ctype_digit($option) || (int) $option < 1
                || ($step !== '' && !ctype_digit($step)) || !in_array($style, self::SPECIAL_STYLES)
            ) {
                $invalid[] = s($line);
                continue;
            }
            $states[(int) $option] = ['step' => (int) $step, 'style' => $style];
        }
        return $states;
    }

    /**
     * Get legacy/default settings for name.
     *
     * @param string $name
     * @return array
     */
    public function get_default_settings_for_name(string $name): array {
        if (in_array($name, ['options', 'key', 'stepper'])) {
            return ['option' => $name];
        }
        return [];
    }
}
