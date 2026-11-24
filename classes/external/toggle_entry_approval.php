<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
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
use datalynxfield_approve\field as datalynxfield_approve;
use invalid_parameter_exception;
use moodle_exception;

/**
 * Web service to toggle the approval state of a datalynx entry: the ##approve## switch on the web
 * and the approve button of the Moodle App.
 *
 * Who may approve through which view is one rule, view\base::user_can_approve_through_view(): the
 * user may approve, and the view shows ##approve## (where the switch and the app's button are).
 * Without a view, the default view counts, as on the web. The approval itself is the web's
 * (datalynx_entries::process_entries()), with its team copies, events and completion.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_entry_approval extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'viewid' => new external_value(PARAM_INT, 'Current view ID'),
            'entryid' => new external_value(PARAM_INT, 'Entry ID'),
            'action' => new external_value(PARAM_SAFEDIR, 'Action: approve, disapprove, or toggle-approval'),
        ]);
    }

    /**
     * Toggle a datalynx entry approval state.
     *
     * @param int $d Datalynx instance ID.
     * @param int $viewid Current view ID.
     * @param int $entryid Entry ID.
     * @param string $action Approval action.
     * @return array
     * @throws invalid_parameter_exception for a view the user cannot see or an entry not in this instance
     * @throws moodle_exception when approval is off or the user may not approve
     */
    public static function execute(int $d, int $viewid, int $entryid, string $action): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'viewid' => $viewid,
            'entryid' => $entryid,
            'action' => $action,
        ]);

        $dlx = browse_request::require_access($params['d']);
        if (!$dlx->user_can_approve_entries()) {
            throw new moodle_exception('entryapprovenotallowed', 'datalynx');
        }
        // As on the web, the view the switch is in; without one, the view the activity opens.
        $records = $dlx->get_view_records();
        $record = $records[$params['viewid'] ?: $dlx->get_default_view_id()] ?? null;
        if (!$record) {
            throw new invalid_parameter_exception('View not available.');
        }
        $entry = $DB->get_record('datalynx_entries', ['id' => $params['entryid'], 'dataid' => $dlx->id()]);
        if (!$entry) {
            throw new invalid_parameter_exception('Entry not found.');
        }
        $view = $dlx->get_view($record->type, $record);
        if (!$view->user_can_approve_through_view()) {
            throw new moodle_exception('viewapprovenotallowed', 'datalynx');
        }

        $action = $params['action'];
        if ($action === 'toggle-approval') {
            $action = $entry->approved ? 'disapprove' : 'approve';
        }
        if (!in_array($action, ['approve', 'disapprove'], true)) {
            throw new invalid_parameter_exception('Invalid action.');
        }

        // The same approval as the web's ?approve= / ?disapprove= and the bulk actions.
        $entries = $view->get_entries_manager();
        $entries->interactive = false;
        $entries->process_entries($action, (string) $entry->id, null, true);

        $entry = $DB->get_record('datalynx_entries', ['id' => $entry->id], '*', MUST_EXIST);
        $approvefield = $dlx->get_field_from_id(datalynxfield_approve::_APPROVED);

        return [
            'entryid' => (int) $entry->id,
            'approved' => (bool) $entry->approved,
            'controlhtml' => $approvefield ? $approvefield->renderer()->render_toggle($entry, ['viewid' => (int) $record->id]) : '',
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'entryid' => new external_value(PARAM_INT, 'Entry ID'),
            'approved' => new external_value(PARAM_BOOL, 'The resulting approval state'),
            'controlhtml' => new external_value(PARAM_RAW, 'Rendered approval toggle HTML'),
        ]);
    }
}
