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

namespace datalynxrule_updatefield\form;

use datalynxrule_updatefield\rule;
use mod_datalynx\form\rule_form as base_rule_form;
use mod_datalynx\local\field\datalynxfield_option_multiple;
use mod_datalynx\local\field\datalynxfield_option_single;
use stdClass;

/**
 * Update-field rule form.
 *
 * @package    datalynxrule_updatefield
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_form extends base_rule_form {
    /**
     * Plugin-specific form definition: which field to update, the new value, and the cascade flag.
     *
     * The base form already renders name/description/enabled, the triggering events and the
     * trigger-condition rows.
     */
    public function rule_definition() {
        $mform = &$this->_form;

        $mform->addElement('header', 'updatefieldhdr', get_string('action', 'datalynxrule_updatefield'));

        // Target field: only fields the rule can safely write to.
        $fieldmenu = [0 => get_string('choose')];
        $valuefields = [];
        foreach ($this->dlx->get_fields() as $fieldid => $field) {
            if (rule::is_supported_field($field)) {
                $fieldmenu[$fieldid] = $field->field->name;
                $valuefields[$fieldid] = $field;
            }
        }
        $mform->addElement('select', 'param2', get_string('targetfield', 'datalynxrule_updatefield'), $fieldmenu);
        $mform->addHelpButton('param2', 'targetfield', 'datalynxrule_updatefield');
        $mform->setType('param2', PARAM_INT);

        // One value control per eligible field; built-in hideIf shows only the selected one.
        foreach ($valuefields as $fieldid => $field) {
            $elname = "fieldvalue_$fieldid";
            $label = get_string('newvalue', 'datalynxrule_updatefield');

            if ($field instanceof datalynxfield_option_multiple) {
                // Checkbox / multiselect: pick one or more option keys.
                $mform->addElement(
                    'autocomplete',
                    $elname,
                    $label,
                    $field->options_menu(),
                    ['multiple' => true, 'noselectionstring' => get_string('noselection', 'form')]
                );
            } else if ($field instanceof datalynxfield_option_single) {
                // Select / radiobutton: pick a single option key.
                $mform->addElement(
                    'select',
                    $elname,
                    $label,
                    [0 => get_string('choose')] + $field->options_menu()
                );
                $mform->setType($elname, PARAM_INT);
            } else {
                // Text / textarea: free value.
                $mform->addElement('text', $elname, $label, ['size' => '48']);
                $mform->setType($elname, PARAM_TEXT);
            }
            $mform->addHelpButton($elname, 'newvalue', 'datalynxrule_updatefield');
            $mform->hideIf($elname, 'param2', 'neq', $fieldid);
        }

        // Optional cascade: let other rules/notifications react to the change (guarded, see rule).
        $mform->addElement(
            'advcheckbox',
            'param4',
            get_string('triggerfollowupevents', 'datalynxrule_updatefield'),
            '',
            null,
            [0, 1]
        );
        $mform->addHelpButton('param4', 'triggerfollowupevents', 'datalynxrule_updatefield');
        $mform->setDefault('param4', 0);
    }

    /**
     * Prefill the value control belonging to the stored target field.
     *
     * @param stdClass $data
     */
    public function set_data($data) {
        $fieldid = (int) ($data->param2 ?? 0);
        if ($fieldid && isset($data->param3)) {
            $field = $this->get_dlx()->get_field_from_id($fieldid);
            $elname = "fieldvalue_$fieldid";
            if ($field instanceof datalynxfield_option_multiple) {
                $decoded = json_decode((string) $data->param3, true);
                $data->$elname = is_array($decoded) ? $decoded : [];
            } else {
                $data->$elname = $data->param3;
            }
        }
        parent::set_data($data);
    }

    /**
     * Collapse the per-field value control back into param3, and normalise the cascade flag.
     *
     * @param bool $slashed
     * @return ?stdClass
     */
    public function get_data($slashed = true) {
        if ($data = parent::get_data($slashed)) {
            $fieldid = (int) ($data->param2 ?? 0);
            $data->param3 = null;
            if ($fieldid) {
                $field = $this->get_dlx()->get_field_from_id($fieldid);
                $value = $data->{"fieldvalue_$fieldid"} ?? null;
                if ($field instanceof datalynxfield_option_multiple) {
                    $keys = is_array($value)
                        ? array_values(array_filter($value, static fn($v) => $v !== '' && $v !== null))
                        : [];
                    $data->param3 = $keys ? json_encode($keys) : null;
                } else {
                    $data->param3 = ($value === '' || $value === null) ? null : (string) $value;
                }
            }

            $data->param4 = !empty($data->param4) ? '1' : null;

            // Drop the transient per-field value controls; only param3 is persisted.
            foreach (array_keys((array) $data) as $k) {
                if (strpos($k, 'fieldvalue_') === 0) {
                    unset($data->$k);
                }
            }
        }
        return $data;
    }

    /**
     * Require a supported target field and a non-empty value.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $fieldid = (int) ($data['param2'] ?? 0);
        if (!$fieldid) {
            $errors['param2'] = get_string('err_nofield', 'datalynxrule_updatefield');
            return $errors;
        }

        $field = $this->get_dlx()->get_field_from_id($fieldid);
        if (!$field || !rule::is_supported_field($field)) {
            $errors['param2'] = get_string('err_nofield', 'datalynxrule_updatefield');
            return $errors;
        }

        $elname = "fieldvalue_$fieldid";
        $value = $data[$elname] ?? null;
        if ($field instanceof datalynxfield_option_multiple) {
            $hasvalue = is_array($value)
                && count(array_filter($value, static fn($v) => $v !== '' && $v !== null)) > 0;
        } else if ($field instanceof datalynxfield_option_single) {
            $hasvalue = !empty($value); // 0 is the "choose" placeholder.
        } else {
            $hasvalue = ($value !== '' && $value !== null);
        }
        if (!$hasvalue) {
            $errors[$elname] = get_string('err_novalue', 'datalynxrule_updatefield');
        }

        return $errors;
    }
}
