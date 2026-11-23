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

namespace mod_datalynx\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use invalid_parameter_exception;
use mod_datalynx\local\entry\entry_edit_form;

/**
 * The form to add or edit an entry through a view (mod_datalynx#272).
 *
 * Each field comes with the control that edits it and its inputs, named and valued as
 * mod_datalynx_submit_entry takes them back: send an input's value unchanged and the field stays
 * as it is. A list value is JSON, as submit_entry takes it.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_entry_edit_form extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'viewid' => new external_value(PARAM_INT, 'The view the entry is added or edited through'),
            'entryid' => new external_value(PARAM_INT, 'The entry to edit, 0 to add one', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The form of one entry.
     *
     * @param int $d
     * @param int $viewid
     * @param int $entryid
     * @return array
     */
    public static function execute(int $d, int $viewid, int $entryid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'viewid' => $viewid,
            'entryid' => $entryid,
        ]);

        $dlx = browse_request::require_access($params['d']);
        $records = $dlx->get_view_records();
        if (empty($records[$params['viewid']])) {
            throw new invalid_parameter_exception('View not available.');
        }
        $record = $records[$params['viewid']];

        $form = (new entry_edit_form($dlx->get_view($record->type, $record)))->build($params['entryid']);
        foreach ($form['fields'] as &$field) {
            foreach ($field['inputs'] as &$input) {
                $input['value'] = $input['list'] ? json_encode($input['value']) : (string) $input['value'];
            }
            unset($input);
            $field['fieldid'] = (string) $field['fieldid'];
        }
        unset($field);
        $form['warnings'] = [];

        return $form;
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'entryid' => new external_value(PARAM_INT, 'The entry, 0 for a new one'),
            'formentryid' => new external_value(PARAM_INT, 'The entry id the inputs belong to, -1 for a new entry'),
            'allowed' => new external_value(PARAM_BOOL, 'Whether the user may add or edit the entry through the view'),
            'denyreason' => new external_value(PARAM_TEXT, 'Why not, when not allowed'),
            'supported' => new external_value(PARAM_BOOL, 'Whether every required field has a control'),
            'unsupportedfields' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Field name'),
                'Editable fields without a control: edited on the web'
            ),
            'fields' => new external_multiple_structure(new external_single_structure([
                'fieldid' => new external_value(PARAM_ALPHANUMEXT, 'Field ID, or the name of an internal field'),
                'name' => new external_value(PARAM_TEXT, 'Field name'),
                'control' => new external_value(PARAM_ALPHA, 'text, number, textarea, url, select, radio, multiselect, '
                    . 'checkbox, datetime, duration, itinerary, schedule, toggle, hidden or static'),
                'required' => new external_value(PARAM_BOOL, 'Whether a value is required'),
                'multiple' => new external_value(PARAM_BOOL, 'Whether several choices can be chosen'),
                'inputs' => new external_multiple_structure(new external_single_structure([
                    'name' => new external_value(PARAM_ALPHANUMEXT, "The input, as submit_entry names it: '' for the value"),
                    'list' => new external_value(PARAM_BOOL, 'Whether the value is a list (JSON)'),
                    'value' => new external_value(PARAM_RAW, 'The current value; JSON for a list'),
                ])),
                'choices' => new external_multiple_structure(new external_single_structure([
                    'value' => new external_value(PARAM_RAW, 'Value'),
                    'label' => new external_value(PARAM_TEXT, 'Label'),
                    'disabled' => new external_value(PARAM_BOOL, 'Whether it cannot be chosen'),
                ])),
                'inputmode' => new external_value(PARAM_ALPHA, 'Keyboard hint', VALUE_OPTIONAL),
                'includetime' => new external_value(
                    PARAM_BOOL,
                    'For a datetime: whether the time of day is entered',
                    VALUE_OPTIONAL
                ),
                'description' => new external_value(PARAM_TEXT, 'Help text', VALUE_OPTIONAL),
                'html' => new external_value(PARAM_RAW, 'For a static field: its display', VALUE_OPTIONAL),
                'maxstops' => new external_value(
                    PARAM_INT,
                    'For an itinerary: the most stops. The waypoints input is a JSON list of stops; a stop with an '
                        . 'address and no lat/lng is looked up on save',
                    VALUE_OPTIONAL
                ),
                'modes' => self::choices('For a schedule: the shapes it may take (once, weekly)'),
                'times' => self::choices('For a schedule: the times of day, in minutes'),
                'weekdays' => self::choices('For a schedule: the weekdays, 1 (Monday) to 7'),
            ])),
            'hasstatus' => new external_value(PARAM_BOOL, 'Whether the form saves a draft or a final submission'),
            'status' => new external_value(PARAM_INT, 'The current status of the entry'),
            'browserurl' => new external_value(PARAM_URL, 'The entry form on the web'),
            'submitlabel' => new external_value(PARAM_TEXT, 'The label of the save button the view names, empty for the default'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * An optional list of choices of a control.
     *
     * @param string $description
     * @return external_multiple_structure
     */
    private static function choices(string $description): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'value' => new external_value(PARAM_RAW, 'Value'),
            'label' => new external_value(PARAM_TEXT, 'Label'),
            'disabled' => new external_value(PARAM_BOOL, 'Whether it cannot be chosen'),
        ]), $description, VALUE_OPTIONAL);
    }
}
