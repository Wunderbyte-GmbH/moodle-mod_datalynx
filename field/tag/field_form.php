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
 * @package datalynxfield
 * @subpackage tag
 * @copyright 2016 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_form.php");

class datalynxfield_tag_form extends datalynxfield_form {

    /**
     * The first option for this field is whether to make tags standard tags or not
     * {@inheritDoc}
     *
     * @see datalynxfield_form::field_definition()
     */
    public function field_definition() {
        $mform = &$this->_form;
        $mform->addElement('header', 'fieldattributeshdr',
                get_string('fieldattributes', 'datalynx'));
        // Make standard tags?
        $mform->addElement('selectyesno', 'param1', get_string('saveasstandardtags', 'datalynx'));
    }
}
