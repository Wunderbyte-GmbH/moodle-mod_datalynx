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
 * Field format form for the entrytime field type.
 *
 * @package    datalynxfield_entrytime
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entrytime\form;

use mod_datalynx\form\field_format_form_base;

/**
 * Moodle form for creating/editing an entrytime field format.
 */
class field_format_form extends field_format_form_base {
    /**
     * Adds a dateformat text field and collapsible cheatsheet.
     */
    protected function format_definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'text',
            'dateformat',
            get_string('fieldformat_dateformat', 'datalynxfield_entrytime'),
            ['size' => '48']
        );
        $mform->setType('dateformat', PARAM_TEXT);
        $mform->addHelpButton('dateformat', 'fieldformat_dateformat', 'datalynxfield_entrytime');

        $mform->addElement('html', get_string('fieldformat_dateformat_examples', 'datalynxfield_entrytime'));
    }
}
