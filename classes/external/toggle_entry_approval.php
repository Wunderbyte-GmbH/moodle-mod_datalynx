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

use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service to toggle the approval state of a datalynx entry.
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
     */
    public static function execute(int $d, int $viewid, int $entryid, string $action): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'viewid' => $viewid,
            'entryid' => $entryid,
            'action' => $action,
        ]);

        $cm = get_coursemodule_from_instance('datalynx', $params['d'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_login($cm->course, true, $cm);
        require_capability('mod/datalynx:approve', $context);

        $dlx = new datalynx($params['d']);
        $entries = new datalynx_entries($dlx);

        return $entries->toggle_entry_approval($params['entryid'], $params['viewid'], $params['action']);
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
