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

namespace datalynxrule_ridematch\form;

use mod_datalynx\form\rule_form as base_rule_form;

/**
 * Ride match rule form.
 *
 * @package    datalynxrule_ridematch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends base_rule_form {
    /**
     * Definition of the rule settings form.
     */
    public function rule_definition() {
        $mform = &$this->_form;

        $mform->addElement(
            'static',
            'ridematchintro',
            '',
            get_string('ruleintro', 'datalynxrule_ridematch')
        );

        // Param2: which itinerary field holds the journey.
        $itineraryfields = $this->get_fields_of_type(['itinerary']);
        $mform->addElement(
            'select',
            'param2',
            get_string('itineraryfield', 'datalynxrule_ridematch'),
            $itineraryfields
        );
        $mform->addHelpButton('param2', 'itineraryfield', 'datalynxrule_ridematch');

        // Param3: which field says whether the entry offers or wants a ride.
        $typefields = $this->get_fields_of_type(['radiobutton', 'select']);
        $mform->addElement(
            'select',
            'param3',
            get_string('ridetypefield', 'datalynxrule_ridematch'),
            $typefields
        );
        $mform->addHelpButton('param3', 'ridetypefield', 'datalynxrule_ridematch');

        // Param4 and param5: the two values of that field.
        $mform->addElement(
            'text',
            'param4',
            get_string('offervalue', 'datalynxrule_ridematch'),
            ['size' => 30]
        );
        $mform->setType('param4', PARAM_TEXT);
        $mform->addHelpButton('param4', 'offervalue', 'datalynxrule_ridematch');

        $mform->addElement(
            'text',
            'param5',
            get_string('requestvalue', 'datalynxrule_ridematch'),
            ['size' => 30]
        );
        $mform->setType('param5', PARAM_TEXT);
        $mform->addHelpButton('param5', 'requestvalue', 'datalynxrule_ridematch');

        // Param6: corridor radius.
        $mform->addElement(
            'text',
            'param6',
            get_string('radiuskmlabel', 'datalynxrule_ridematch'),
            ['size' => 6]
        );
        $mform->setType('param6', PARAM_INT);
        $mform->addHelpButton('param6', 'radiuskmlabel', 'datalynxrule_ridematch');

        // Param7: departure tolerance in hours.
        $mform->addElement(
            'text',
            'param7',
            get_string('timetolerance', 'datalynxrule_ridematch'),
            ['size' => 6]
        );
        $mform->setType('param7', PARAM_INT);
        $mform->setDefault('param7', 24);
        $mform->addHelpButton('param7', 'timetolerance', 'datalynxrule_ridematch');
    }

    /**
     * Menu of this activity's fields of the given types.
     *
     * @param string[] $types
     * @return array fieldid => name
     */
    protected function get_fields_of_type(array $types): array {
        global $DB;

        $options = [0 => get_string('choosedots')];

        [$insql, $params] = $DB->get_in_or_equal($types, SQL_PARAMS_NAMED, 'type');
        $params['dataid'] = $this->get_dlx()->id();

        $fields = $DB->get_records_select(
            'datalynx_fields',
            "dataid = :dataid AND type {$insql}",
            $params,
            'name ASC',
            'id, name'
        );
        foreach ($fields as $field) {
            $options[$field->id] = format_string($field->name);
        }

        return $options;
    }

    /**
     * Reject a configuration that cannot work.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['param2'])) {
            $errors['param2'] = get_string('required');
        }
        if (empty($data['param3'])) {
            $errors['param3'] = get_string('required');
        }
        if (trim((string) ($data['param4'] ?? '')) === '') {
            $errors['param4'] = get_string('required');
        }
        if (trim((string) ($data['param5'] ?? '')) === '') {
            $errors['param5'] = get_string('required');
        }

        // Both sides pointing at the same value would make every entry match itself.
        if (
            !isset($errors['param4'], $errors['param5'])
            && trim((string) $data['param4']) === trim((string) $data['param5'])
        ) {
            $errors['param5'] = get_string('valuesmustdiffer', 'datalynxrule_ridematch');
        }

        return $errors;
    }
}
