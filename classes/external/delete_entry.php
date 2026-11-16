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
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use invalid_parameter_exception;
use moodle_exception;

/**
 * Delete one entry through a view, from the Moodle App or any other client.
 *
 * The entry is deleted the way the delete action of a view deletes it on the web: the user must
 * be allowed to manage the entry (datalynx::user_can_manage_entry()) and the view must offer the
 * delete action (view\base::confirm_view_action()), and process_entries() removes it with its
 * contents and files, fires entry_deleted and updates completion. Both checks are made here
 * before process_entries() runs, so a refusal is an error the client can show, not a silent
 * success. One entry per call on purpose: a bulk delete is too easy to trigger by accident on
 * a phone.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_entry extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'viewid' => new external_value(PARAM_INT, 'The view the entry is deleted through'),
            'entryid' => new external_value(PARAM_INT, 'The entry to delete'),
        ]);
    }

    /**
     * Delete one entry.
     *
     * @param int $d
     * @param int $viewid
     * @param int $entryid
     * @return array
     * @throws invalid_parameter_exception for a view the user cannot see or an entry not in this instance
     * @throws moodle_exception when the user may not delete the entry or the view offers no delete
     */
    public static function execute(int $d, int $viewid, int $entryid): array {
        global $DB;

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
        $entry = $DB->get_record('datalynx_entries', ['id' => $params['entryid'], 'dataid' => $dlx->id()]);
        if (!$entry) {
            throw new invalid_parameter_exception('Entry not found.');
        }
        if (!$dlx->user_can_manage_entry($entry)) {
            throw new moodle_exception('entrydeletenotallowed', 'datalynx');
        }

        $record = $records[$params['viewid']];
        $view = $dlx->get_view($record->type, $record);
        if (!$view->confirm_view_action('delete', (int) $record->id)) {
            throw new moodle_exception('viewdeletenotallowed', 'datalynx');
        }

        $entries = $view->get_entries_manager();
        // A web service has no page to print a confirmation on.
        $entries->interactive = false;
        [$message, $processed] = $entries->process_entries('delete', (string) $entry->id, null, true);

        $deleted = is_array($processed) && in_array((int) $entry->id, array_map('intval', $processed), true);
        $warnings = [];
        if (!$deleted) {
            $warnings[] = ['item' => 'entry', 'itemid' => (int) $entry->id, 'warningcode' => 'notdeleted',
                'message' => strip_tags((string) $message)];
        }

        return [
            'status' => $deleted,
            'entryid' => (int) $entry->id,
            'warnings' => $warnings,
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the entry was deleted'),
            'entryid' => new external_value(PARAM_INT, 'The entry'),
            'warnings' => new external_warnings(),
        ]);
    }
}
