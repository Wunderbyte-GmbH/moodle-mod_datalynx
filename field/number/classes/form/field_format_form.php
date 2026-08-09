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

namespace datalynxfield_number\form;

use datalynxfield_number\field_format;

/**
 * Form class for number field formats.
 *
 * @package    datalynxfield_number
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format_form extends \mod_datalynx\form\field_format_base_form {
    /**
     * Define the specific elements for the number format.
     */
    protected function format_definition() {
        $mform = &$this->_form;

        field_format::define_elements($mform);
    }

    /**
     * Validate the slider configuration.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    protected function format_validation($data, $files) {
        return field_format::validate_settings($data);
    }
}
