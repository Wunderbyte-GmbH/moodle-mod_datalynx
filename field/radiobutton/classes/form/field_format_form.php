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

namespace datalynxfield_radiobutton\form;

/**
 * Form class for radiobutton field formats.
 *
 * @package    datalynxfield_radiobutton
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format_form extends \mod_datalynx\form\field_format_base_form {
    /**
     * Define the specific elements for the radiobutton format.
     */
    protected function format_definition() {
        $mform = &$this->_form;

        $options = [
            'default' => get_string('fieldformat_option_default', 'datalynxfield_radiobutton'),
            'options' => get_string('fieldformat_option_options', 'datalynxfield_radiobutton'),
            'key'     => get_string('fieldformat_option_key', 'datalynxfield_radiobutton'),
            'stepper' => get_string('fieldformat_option_stepper', 'datalynxfield_radiobutton'),
        ];
        $mform->addElement(
            'select',
            'option',
            get_string('fieldformatoption', 'mod_datalynx'),
            $options
        );
        $mform->setType('option', PARAM_ALPHA);
        $mform->addRule('option', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('option', 'fieldformat_option', 'datalynxfield_radiobutton');

        $mform->addElement(
            'text',
            'stepper_icons',
            get_string('fieldformat_option_stepper_icons', 'datalynxfield_radiobutton'),
            ['size' => 60]
        );
        $mform->setType('stepper_icons', PARAM_TEXT);
        $mform->addHelpButton('stepper_icons', 'fieldformat_option_stepper_icons', 'datalynxfield_radiobutton');
        $mform->hideIf('stepper_icons', 'option', 'neq', 'stepper');
    }
}
