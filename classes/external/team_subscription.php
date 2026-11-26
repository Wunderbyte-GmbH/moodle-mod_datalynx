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

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use datalynxfield_teammemberselect\membership;
use moodle_exception;

/**
 * Web service to join and leave the team of an entry, and to decide on requests to join.
 *
 * One path for the web (buttons of the display) and the Moodle App. Every action is checked here,
 * on the server, by {@see membership}:
 *
 * | Action | Who | |
 * |---|---|---|
 * | subscribe, request | the user themselves | joins, or asks to join when the field needs approval |
 * | unsubscribe, withdraw | the user themselves | withdraws the request, or leaves the team |
 * | approve, decline | the entry author, or who manages entries | decides on a request |
 * | remove | the entry author, or who manages entries | removes a member |
 *
 * @package mod_datalynx
 * @copyright 2024 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class team_subscription extends external_api {
    /**
     * Define parameters for the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
                'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
                'entryid' => new external_value(PARAM_INT, 'Entry ID'),
                'fieldid' => new external_value(PARAM_INT, 'Field ID'),
                'userid' => new external_value(PARAM_INT, 'The user who joins, asks or leaves; for approve, decline '
                    . 'and remove the user decided on'),
                'action' => new external_value(PARAM_ALPHA, 'subscribe, unsubscribe, request, withdraw, approve, decline '
                    . 'or remove'),
                'data' => new external_value(
                    PARAM_RAW,
                    'For a request: JSON of extension data (e.g. a pickup point)',
                    VALUE_DEFAULT,
                    ''
                ),
        ]);
    }

    /**
     * Join, leave, request, or decide.
     *
     * @param int $d
     * @param int $entryid
     * @param int $fieldid
     * @param int $userid
     * @param string $action
     * @param string $data
     * @return array
     */
    public static function execute($d, $entryid, $fieldid, $userid, $action, $data = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact('d', 'entryid', 'fieldid', 'userid', 'action', 'data')
        );

        $cm = get_coursemodule_from_instance('datalynx', $params['d'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        require_login($cm->course, true, $cm);

        $dlx = new \mod_datalynx\datalynx($params['d']);
        $field = $dlx->get_field_from_id($params['fieldid']);
        if (empty($field) || $field->type !== 'teammemberselect') {
            throw new moodle_exception('invalidrecord', 'error', '', 'datalynx_fields');
        }
        // Inside a fieldgroup the field is a list of people: nobody subscribes to a line.
        if ($field->is_fieldgroup_subfield()) {
            return [
                    'success' => false,
                    'error' => get_string('teamsubscriptionfieldgroup', 'datalynxfield_teammemberselect'),
            ];
        }
        // The entry must belong to this instance.
        if (!$DB->record_exists('datalynx_entries', ['id' => $params['entryid'], 'dataid' => $params['d']])) {
            throw new moodle_exception('invalidrecord', 'error', '', 'datalynx_entries');
        }

        $membership = $field->membership((int) $params['entryid']);
        $userid = (int) $params['userid'];
        $action = $params['action'];
        try {
            if (in_array($action, ['subscribe', 'request', 'unsubscribe', 'withdraw'], true)) {
                // Only for oneself.
                if ($userid !== (int) $USER->id) {
                    throw new moodle_exception('invaliduserid', 'error');
                }
                require_capability('mod/datalynx:teamsubscribe', $context);
                if ($action === 'subscribe' || $action === 'request') {
                    if (!array_intersect($dlx->get_user_datalynx_permissions($USER->id), (array) $field->admissibleroles)) {
                        return ['success' => false, 'error' => get_string('notadmissible', 'datalynxfield_teammemberselect')];
                    }
                    $requestdata = json_decode((string) $params['data'], true);
                    $membership->request($userid, is_array($requestdata) ? $requestdata : []);
                } else {
                    $membership->withdraw($userid);
                }
            } else if (in_array($action, ['approve', 'decline', 'remove'], true)) {
                if (!$membership->can_decide((int) $USER->id)) {
                    throw new moodle_exception('nopermissions', 'error', '', get_string($action, 'datalynxfield_teammemberselect'));
                }
                $membership->$action($userid);
            } else {
                throw new moodle_exception('invalidaction', 'error');
            }
        } catch (moodle_exception $e) {
            // A refused action is an answer, not a failure of the call: full, declined, not allowed to leave.
            if ($e->module === 'datalynxfield_teammemberselect') {
                return ['success' => false, 'error' => $e->getMessage(), 'status' => $membership->status($userid)];
            }
            throw $e;
        }

        return ['success' => true, 'status' => $membership->status($userid)];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
                'success' => new external_value(PARAM_BOOL, 'Success status'),
                'error' => new external_value(PARAM_TEXT, 'Error message (if any)', VALUE_OPTIONAL),
                'status' => new external_value(
                    PARAM_ALPHA,
                    'Where the user now stands: member, requested, declined, none',
                    VALUE_OPTIONAL
                ),
        ]);
    }
}
