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
 * Form class for the duration field type.
 *
 * @package    datalynxfield_duration
 * @copyright  2014 onwards by edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_duration;

use mod_datalynx\form\datalynxfield_form;

/**
 * Form class for the duration field type.
 */
class form extends datalynxfield_form {
    /**
     * Define the form elements for the duration field.
     */
    public function field_definition() {
        $mform = &$this->_form;
    }
}
