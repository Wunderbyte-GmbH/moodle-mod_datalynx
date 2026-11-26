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

namespace datalynxrule_splitschedule\form;

use datalynxrule_splitschedule\rule;
use mod_datalynx\form\rule_form as base_rule_form;
use stdClass;

/**
 * Form of the split schedule rule: the schedule field, how far ahead, and "only on change".
 *
 * @package    datalynxrule_splitschedule
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends base_rule_form {
    /**
     * Plugin-specific form definition.
     */
    public function rule_definition(): void {
        $mform = &$this->_form;

        // Only when one of these fields changed (e.g. a "published" checkbox), on entry_updated.
        $dlfields = $this->dlx->get_fields(['entry'], true);
        if (!empty($dlfields)) {
            $mform->addElement(
                'autocomplete',
                'onlyonchangefields',
                get_string('onlyonchange', 'datalynxrule_updatefield'),
                $dlfields,
                ['multiple' => true, 'noselectionstring' => get_string('noselection', 'form')]
            );
            $mform->addHelpButton('onlyonchangefields', 'onlyonchange', 'datalynxrule_updatefield');
            $mform->hideIf('onlyonchangefields', 'entry_updated', 'notchecked');
        }

        $mform->addElement('header', 'splithdr', get_string('action', 'datalynxrule_splitschedule'));
        $fieldmenu = [0 => get_string('choose')];
        foreach ($this->dlx->get_fields() as $fieldid => $field) {
            if ($field->type === 'schedule') {
                $fieldmenu[$fieldid] = $field->field->name;
            }
        }
        $mform->addElement('select', 'param2', get_string('schedulefield', 'datalynxrule_splitschedule'), $fieldmenu);
        $mform->addHelpButton('param2', 'schedulefield', 'datalynxrule_splitschedule');
        $mform->setType('param2', PARAM_INT);

        $mform->addElement('text', 'param3', get_string('maxmonths', 'datalynxrule_splitschedule'), ['size' => 3]);
        $mform->addHelpButton('param3', 'maxmonths', 'datalynxrule_splitschedule');
        $mform->setType('param3', PARAM_INT);
        $mform->setDefault('param3', rule::DEFAULT_MONTHS);
    }

    /**
     * Fill "only on change" from param9.
     *
     * @param stdClass $data
     */
    public function set_data($data) {
        $data->onlyonchangefields = [];
        if (!empty($data->param9)) {
            $conditions = json_decode($data->param9, true);
            if (is_array($conditions) && !empty($conditions[rule::ONCHANGE_KEY])) {
                $data->onlyonchangefields = array_values((array) $conditions[rule::ONCHANGE_KEY]);
            }
        }
        parent::set_data($data);
    }

    /**
     * Keep "only on change" in param9 beside the trigger conditions.
     *
     * @param bool $slashed
     * @return ?stdClass
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            $onchangefields = !empty($data->onlyonchangefields)
                ? array_values(array_unique(array_map(
                    static fn($id) => ctype_digit((string) $id) ? (int) $id : (string) $id,
                    (array) $data->onlyonchangefields
                )))
                : [];
            $conditions = !empty($data->param9) ? (json_decode($data->param9, true) ?: []) : [];
            unset($conditions[rule::ONCHANGE_KEY]);
            if ($onchangefields) {
                $conditions[rule::ONCHANGE_KEY] = $onchangefields;
            }
            $data->param9 = $conditions ? json_encode($conditions) : null;
            unset($data->onlyonchangefields);
        }
        return $data;
    }

    /**
     * A schedule field is required.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $field = !empty($data['param2']) ? $this->get_dlx()->get_field_from_id((int) $data['param2']) : null;
        if (!$field || $field->type !== 'schedule') {
            $errors['param2'] = get_string('errnofield', 'datalynxrule_splitschedule');
        }
        return $errors;
    }
}
