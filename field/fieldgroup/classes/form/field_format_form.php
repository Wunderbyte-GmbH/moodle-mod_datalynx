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

namespace datalynxfield_fieldgroup\form;

/**
 * Form class for fieldgroup field formats.
 *
 * @package    datalynxfield_fieldgroup
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_format_form extends \mod_datalynx\form\field_format_base_form {
    /**
     * Define the specific elements for the fieldgroup format.
     */
    protected function format_definition() {
        $mform = &$this->_form;

        // Which aggregation to apply across the fieldgroup's number subfields.
        $mform->addElement(
            'select',
            'aggregation',
            get_string('fieldformat_aggregation', 'datalynxfield_fieldgroup'),
            \datalynxfield_fieldgroup\field_format::get_aggregation_options()
        );
        $mform->setDefault('aggregation', 'sum');
        $mform->addHelpButton('aggregation', 'fieldformat_aggregation', 'datalynxfield_fieldgroup');

        // Decimal places for the aggregated value (blank = use each subfield's own setting).
        $mform->addElement(
            'text',
            'decimals',
            get_string('fieldformat_decimals', 'datalynxfield_fieldgroup'),
            ['size' => 3]
        );
        $mform->setType('decimals', PARAM_INT);
        $mform->addHelpButton('decimals', 'fieldformat_decimals', 'datalynxfield_fieldgroup');

        // Optional label shown next to the totals row (blank = default "Total").
        $mform->addElement(
            'text',
            'label',
            get_string('fieldformat_label', 'datalynxfield_fieldgroup')
        );
        $mform->setType('label', PARAM_TEXT);
        $mform->addHelpButton('label', 'fieldformat_label', 'datalynxfield_fieldgroup');
    }
}
