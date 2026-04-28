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
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_datalynx\local\view\manager\grid_view_manager;
use mod_datalynx\output\grid_view_browser;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service to fetch structured Grid view browse data.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_grid_view_data extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'view' => new external_value(PARAM_INT, 'Grid view ID'),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Entries per page override', VALUE_DEFAULT, 0),
            'eids' => new external_value(PARAM_SEQUENCE, 'Optional entry ids filter', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return structured browse data for one Grid view page.
     *
     * @param int $d
     * @param int $view
     * @param int $page
     * @param int $perpage
     * @param string $eids
     * @return array
     */
    public static function execute(int $d, int $view, int $page = 0, int $perpage = 0, string $eids = ''): array {
        global $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'view' => $view,
            'page' => $page,
            'perpage' => $perpage,
            'eids' => $eids,
        ]);

        $cm = get_coursemodule_from_instance('datalynx', $params['d'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        require_login($cm->course, true, $cm);
        require_capability('mod/datalynx:viewentry', $context);

        $filteroptions = [
            'page' => $params['page'],
        ];
        if (!empty($params['perpage'])) {
            $filteroptions['perpage'] = $params['perpage'];
        }
        if (!empty($params['eids'])) {
            $filteroptions['eids'] = $params['eids'];
        }

        $manager = new grid_view_manager();
        $payload = $manager->get_browse_payload($params['d'], $params['view'], $filteroptions);
        $renderable = new grid_view_browser($payload);

        return $renderable->export_for_template($OUTPUT);
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'datalynxid' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'viewid' => new external_value(PARAM_INT, 'View ID'),
            'viewname' => new external_value(PARAM_TEXT, 'View name'),
            'viewtype' => new external_value(PARAM_ALPHA, 'View type'),
            'entriescount' => new external_value(PARAM_INT, 'Visible entries count'),
            'entriesfiltercount' => new external_value(PARAM_INT, 'Filtered entries count'),
            'hasentries' => new external_value(PARAM_BOOL, 'Whether the payload has entries'),
            'groups' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'Group name'),
                    'hasname' => new external_value(PARAM_BOOL, 'Whether the group heading should be shown'),
                    'entries' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Entry ID'),
                            'fields' => new external_multiple_structure(
                                new external_single_structure([
                                    'name' => new external_value(PARAM_TEXT, 'Field name'),
                                    'size' => new external_value(PARAM_INT, 'Grid column size'),
                                    'valuehtml' => new external_value(PARAM_RAW, 'Rendered field value HTML'),
                                ])
                            ),
                            'edithtml' => new external_value(PARAM_RAW, 'Rendered edit action HTML'),
                            'deletehtml' => new external_value(PARAM_RAW, 'Rendered delete action HTML'),
                            'hasactions' => new external_value(PARAM_BOOL, 'Whether any entry actions are available'),
                        ])
                    ),
                ])
            ),
            'emptycontent' => new external_value(PARAM_RAW, 'Fallback message when no entries are available'),
        ]);
    }
}
