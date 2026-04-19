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
 *
 * @package datalynxfield_radiobutton
 * @subpackage radiobutton
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_radiobutton;
use datalynxfield_select\form as SelectForm;

/**
 * Field form for the radiobutton field type.
 *
 * @package datalynxfield_radiobutton
 */
class form extends SelectForm {
    /**
     * Define the field attributes.
     */
    public function field_definition() {
        parent::field_definition();

        $mform = &$this->_form;

        // Options separator.
        $mform->addElement(
            'select',
            'param3',
            get_string('fieldoptionsseparator', 'datalynx'),
            array_map('current', $this->field->separators)
        );

        // Hide autocomplete, it does not apply.
        $mform->hideIf('param6', '');
    }
}
