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

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_module;
use moodle_exception;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service to subscribe/unsubscribe users in datalynx team fields.
 */
class team_subscription extends external_api {

    /**
     * Define parameters for the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
                'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
                'entryid' => new external_value(PARAM_INT, 'Entry ID'),
                'fieldid' => new external_value(PARAM_INT, 'Field ID'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'action' => new external_value(PARAM_ALPHA, 'Action: subscribe or unsubscribe')
        ]);
    }

    /**
     * Main function to handle the subscription or unsubscription.
     *
     * @param int $d
     * @param int $entryid
     * @param int $fieldid
     * @param int $userid
     * @param string $action
     * @return array
     */
    public static function execute($d, $entryid, $fieldid, $userid, $action) {
        global $DB, $USER;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), compact('d', 'entryid', 'fieldid', 'userid', 'action'));

        // Get context.
        $cm = get_coursemodule_from_instance('datalynx', $params['d'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        require_login($cm->course, true, $cm);
        require_capability('mod/datalynx:teamsubscribe', $context);

        // Ensure the user performing the action matches the session user.
        if ($params['userid'] != $USER->id) {
            throw new moodle_exception('invaliduserid', 'error');
        }

        // Fetch existing users.
        $users = json_decode($DB->get_field('datalynx_contents', 'content', [
                'fieldid' => $params['fieldid'],
                'entryid' => $params['entryid']
        ]), true) ?? [];

        // Fetch max team size setting from the field configuration.
        $maxteamsize = $DB->get_field('datalynx_fields', 'param1', ['id' => $params['fieldid']]);

        if ($params['action'] === 'subscribe') {
            // Check if max team size is exceeded.
            if ($maxteamsize > 0 && count($users) >= $maxteamsize) {
                return [
                        'success' => false,
                        'error' => get_string('maxteamsizeexceeded', 'mod_datalynx', $maxteamsize)
                ];
            }

            $users[] = (string) $params['userid'];
            $users = array_unique(array_filter($users));

            $record = $DB->get_record('datalynx_contents', [
                    'fieldid' => $params['fieldid'],
                    'entryid' => $params['entryid']
            ]);

            $data = new stdClass();
            $data->fieldid = $params['fieldid'];
            $data->entryid = $params['entryid'];
            $data->content = json_encode(array_values($users));

            if ($record) {
                // Update existing record.
                $DB->set_field('datalynx_contents', 'content', $data->content, [
                        'fieldid' => $params['fieldid'],
                        'entryid' => $params['entryid']
                ]);
            } else {
                // Insert new record.
                $DB->insert_record('datalynx_contents', $data);
            }
        } elseif ($params['action'] === 'unsubscribe') {
            $users = array_values(array_diff($users, [(string) $params['userid']]));

            if (empty($users)) {
                $DB->delete_records('datalynx_contents', [
                        'fieldid' => $params['fieldid'],
                        'entryid' => $params['entryid']
                ]);
            } else {
                $DB->set_field('datalynx_contents', 'content', json_encode($users), [
                        'fieldid' => $params['fieldid'],
                        'entryid' => $params['entryid']
                ]);
            }
        } else {
            throw new moodle_exception('invalidaction', 'error');
        }

        return ['success' => true];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
                'success' => new external_value(PARAM_BOOL, 'Success status'),
                'error' => new external_value(PARAM_TEXT, 'Error message (if any)', VALUE_OPTIONAL)
        ]);
    }
}
