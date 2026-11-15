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
use mod_datalynx\datalynx;
use mod_datalynx\local\entry\entry_submission_service;
use stdClass;

/**
 * Add or edit an entry through a view, from the Moodle App or any other client.
 *
 * The values are handed to the entry submission service, the same one the entry form of the view
 * uses, so a submission is held to the same rules either way: only fields the view lets the user
 * edit are taken, each value is cleaned by its field's input contract, the behaviors decide what
 * is required, and each field validates its value. This class only turns the structured values
 * into the element names the service reads (field_{fieldid}_{entryid}[_{name}]) and its errors
 * back into structured ones.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submit_entry extends external_api {
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
            'data' => new external_multiple_structure(new external_single_structure([
                'fieldid' => new external_value(PARAM_ALPHANUMEXT, 'Field ID, or the name of an internal field (e.g. status)'),
                'name' => new external_value(
                    PARAM_ALPHANUMEXT,
                    "The input of the field, as its contract names it: '' for the value, e.g. 'url' or 'editor'",
                    VALUE_DEFAULT,
                    ''
                ),
                'fieldgroupid' => new external_value(PARAM_INT, 'For a subfield of a fieldgroup: the fieldgroup', VALUE_DEFAULT, 0),
                'line' => new external_value(PARAM_INT, 'For a subfield of a fieldgroup: the line, from 0', VALUE_DEFAULT, 0),
                'value' => new external_value(PARAM_RAW, 'The value; JSON for a list or a structure'),
            ]), 'The submitted values', VALUE_DEFAULT, []),
        ]);
    }

    /**
     * Add or edit one entry.
     *
     * @param int $d
     * @param int $viewid
     * @param int $entryid
     * @param array $data
     * @return array
     */
    public static function execute(int $d, int $viewid, int $entryid = 0, array $data = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'viewid' => $viewid,
            'entryid' => $entryid,
            'data' => $data,
        ]);

        $dlx = browse_request::require_access($params['d']);
        require_capability('mod/datalynx:writeentry', $dlx->context);

        $records = $dlx->get_view_records();
        if (empty($records[$params['viewid']])) {
            throw new invalid_parameter_exception('View not available.');
        }
        $record = $records[$params['viewid']];
        $view = $dlx->get_view($record->type, $record);
        // A web service has no page to print a confirmation on.
        $view->get_entries_manager()->interactive = false;

        // An existing entry is named by its id, a new one by the -1 of the entry form.
        $submitid = $params['entryid'] > 0 ? $params['entryid'] : -1;
        $result = (new entry_submission_service($view))->save([$submitid], self::to_submission($dlx, $submitid, $params['data']));

        $warnings = [];
        if (!$result['errors'] && !$result['entryids']) {
            // Nothing was saved without a validation error: the entry may not be changed by this
            // user (another author's, or the limit of entries reached).
            $warnings[] = ['item' => 'entry', 'itemid' => $params['entryid'], 'warningcode' => 'notsaved',
                'message' => strip_tags($result['message'])];
        }

        return [
            'status' => !$result['errors'] && (bool) $result['entryids'],
            'entryid' => (int) ($result['entryids'][0] ?? 0),
            'errors' => self::to_errors($result['errors']),
            'warnings' => $warnings,
        ];
    }

    /**
     * Turn the structured values into a submission keyed by element names.
     *
     * A value whose input is a list or a structure arrives as JSON and is decoded; the entry
     * submission service then cleans it by the field's contract, or drops it.
     *
     * @param datalynx $dlx
     * @param int $entryid the entry id of the submission, -1 for a new entry
     * @param array $items the submitted values
     * @return stdClass
     * @throws invalid_parameter_exception for an unknown field, malformed JSON or an input given twice
     */
    private static function to_submission(datalynx $dlx, int $entryid, array $items): stdClass {
        $data = new stdClass();
        foreach ($items as $item) {
            $field = $dlx->get_field_from_id($item['fieldid']);
            if (!$field) {
                throw new invalid_parameter_exception("Unknown field '{$item['fieldid']}'.");
            }
            $name = "field_{$item['fieldid']}_{$entryid}";
            if ($item['fieldgroupid'] > 0) {
                $name .= "_fieldgroup_{$item['fieldgroupid']}_{$item['line']}";
            }
            if ($item['name'] !== '') {
                $name .= "_{$item['name']}";
            }
            if (property_exists($data, $name)) {
                throw new invalid_parameter_exception("The value of '$name' is given twice.");
            }

            $value = $item['value'];
            $type = $field->get_input_contract()[$item['name']] ?? null;
            if (is_array($type)) {
                $value = json_decode($value, true);
                if (!is_array($value)) {
                    throw new invalid_parameter_exception("The value of '$name' must be a JSON list or object.");
                }
            }
            $data->$name = $value;
        }

        return $data;
    }

    /**
     * Turn the service's errors, keyed by element name, into structured ones.
     *
     * @param array $errors element name => message
     * @return array
     */
    private static function to_errors(array $errors): array {
        $structured = [];
        foreach ($errors as $element => $message) {
            // Some fields show their errors on a group around the input, e.g. grpfield_... of the
            // masked time selectors.
            $name = preg_replace('/^grp(?=field_)/', '', (string) $element);
            $error = ['fieldid' => '', 'name' => '', 'fieldgroupid' => 0, 'line' => 0, 'element' => (string) $element,
                'message' => strip_tags((string) $message)];
            if (preg_match(entry_submission_service::ELEMENT_REGEX, $name, $matches)) {
                $error['fieldid'] = $matches[1];
                $rest = $matches[3] ?? '';
                if (preg_match(entry_submission_service::LINE_REGEX, $rest, $line)) {
                    $error['fieldgroupid'] = (int) $line[1];
                    $error['line'] = (int) $line[2];
                    $rest = substr($rest, strlen($line[0]));
                }
                $error['name'] = ltrim($rest, '_');
            }
            $structured[] = $error;
        }

        return $structured;
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the entry was saved'),
            'entryid' => new external_value(PARAM_INT, 'The saved entry, 0 if none'),
            'errors' => new external_multiple_structure(new external_single_structure([
                'fieldid' => new external_value(PARAM_ALPHANUMEXT, 'The field the error is about'),
                'name' => new external_value(PARAM_ALPHANUMEXT, 'The input of the field, e.g. url or group'),
                'fieldgroupid' => new external_value(PARAM_INT, 'The fieldgroup, for a subfield'),
                'line' => new external_value(PARAM_INT, 'The fieldgroup line, for a subfield'),
                'element' => new external_value(PARAM_RAW, 'The element of the entry form the error belongs to'),
                'message' => new external_value(PARAM_TEXT, 'The error'),
            ])),
            'warnings' => new external_warnings(),
        ]);
    }
}
