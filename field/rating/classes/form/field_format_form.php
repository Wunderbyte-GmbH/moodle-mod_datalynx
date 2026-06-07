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

namespace datalynxfield_rating\form;

/**
 * Form class for rating field formats.
 *
 * @package    datalynxfield_rating
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format_form extends \mod_datalynx\form\field_format_base_form {
    /**
     * Defines configuration elements on the form.
     */
    protected function format_definition() {
        $mform = &$this->_form;
        $options = [
            'rate' => 'Rate (widget)',
            'view' => 'View link',
            'viewurl' => 'View URL',
            'viewinline' => 'View inline (table)',
            'count' => 'Count',
            'avg' => 'Average (text)',
            'avgbar' => 'Average using bars',
            'avgstar' => 'Average using stars',
            'max' => 'Maximum',
            'min' => 'Minimum',
            'sum' => 'Sum',
        ];
        $mform->addElement('select', 'option', get_string('fieldformatoption', 'mod_datalynx'), $options);
        $mform->setType('option', PARAM_ALPHANUM);
        $mform->addRule('option', get_string('required'), 'required', null, 'client');
    }
}
