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
use mod_datalynx\local\view\manager\report_view_manager;
use mod_datalynx\output\report_view_browser;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service to fetch structured Report view browse data.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_report_view_data extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'view' => new external_value(PARAM_INT, 'Report view ID'),
            'filterid' => new external_value(PARAM_INT, 'Active filter ID', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Entries per page override', VALUE_DEFAULT, 0),
            'eids' => new external_value(PARAM_SEQUENCE, 'Optional entry ids filter', VALUE_DEFAULT, ''),
            'users' => new external_value(PARAM_SEQUENCE, 'Optional user ids filter', VALUE_DEFAULT, ''),
            'groups' => new external_value(PARAM_SEQUENCE, 'Optional group ids filter', VALUE_DEFAULT, ''),
            'groupby' => new external_value(PARAM_RAW, 'Optional group-by field', VALUE_DEFAULT, ''),
            'selection' => new external_value(PARAM_INT, 'Optional selection mode override', VALUE_DEFAULT, 0),
            'customsort' => new external_value(PARAM_RAW, 'Optional serialized custom sort options', VALUE_DEFAULT, ''),
            'customsearch' => new external_value(PARAM_RAW, 'Optional serialized custom search options', VALUE_DEFAULT, ''),
            'search' => new external_value(PARAM_RAW, 'Optional search string', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return structured browse data for one Report view page.
     *
     * @param int $d
     * @param int $view
     * @param int $filterid
     * @param int $page
     * @param int $perpage
     * @param string $eids
     * @param string $users
     * @param string $groups
     * @param string $groupby
     * @param int $selection
     * @param string $customsort
     * @param string $customsearch
     * @param string $search
     * @return array
     */
    public static function execute(
        int $d,
        int $view,
        int $filterid = 0,
        int $page = 0,
        int $perpage = 0,
        string $eids = '',
        string $users = '',
        string $groups = '',
        string $groupby = '',
        int $selection = 0,
        string $customsort = '',
        string $customsearch = '',
        string $search = ''
    ): array {
        global $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'view' => $view,
            'filterid' => $filterid,
            'page' => $page,
            'perpage' => $perpage,
            'eids' => $eids,
            'users' => $users,
            'groups' => $groups,
            'groupby' => $groupby,
            'selection' => $selection,
            'customsort' => $customsort,
            'customsearch' => $customsearch,
            'search' => $search,
        ]);

        $cm = get_coursemodule_from_instance('datalynx', $params['d'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        require_login($cm->course, true, $cm);
        require_capability('mod/datalynx:viewentry', $context);

        $filteroptions = [
            'filterid' => $params['filterid'],
            'page' => $params['page'],
        ];
        if (!empty($params['perpage'])) {
            $filteroptions['perpage'] = $params['perpage'];
        }
        if (!empty($params['eids'])) {
            $filteroptions['eids'] = $params['eids'];
        }
        if (!empty($params['users'])) {
            $filteroptions['users'] = $params['users'];
        }
        if (!empty($params['groups'])) {
            $filteroptions['groups'] = $params['groups'];
        }
        if (!empty($params['groupby'])) {
            $filteroptions['groupby'] = $params['groupby'];
        }
        if (!empty($params['selection'])) {
            $filteroptions['selection'] = $params['selection'];
        }
        if (!empty($params['customsort'])) {
            $filteroptions['customsort'] = unserialize($params['customsort']);
        }
        if (!empty($params['customsearch'])) {
            $filteroptions['customsearch'] = unserialize($params['customsearch']);
        }
        if ($params['search'] !== '') {
            $filteroptions['search'] = $params['search'];
        }

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($params['d'], $params['view'], $filteroptions);
        $renderable = new report_view_browser($payload);

        return $renderable->export_for_template($OUTPUT);
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $optioncell = new external_single_structure([
            'count' => new external_value(PARAM_INT, 'Count for one option'),
        ]);
        $datarow = new external_single_structure([
            'userhtml' => new external_value(PARAM_RAW, 'Rendered user column HTML'),
            'month' => new external_value(PARAM_RAW, 'Month key', VALUE_DEFAULT, ''),
            'totalentries' => new external_value(PARAM_INT, 'Total entries for the row'),
            'optioncells' => new external_multiple_structure($optioncell),
            'notyetanswered' => new external_value(PARAM_INT, 'Entries without one of the counted options'),
        ]);

        return new external_single_structure([
            'datalynxid' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'viewid' => new external_value(PARAM_INT, 'View ID'),
            'viewname' => new external_value(PARAM_TEXT, 'View name'),
            'viewtype' => new external_value(PARAM_ALPHA, 'View type'),
            'ismonthly' => new external_value(PARAM_BOOL, 'Whether the report is grouped into month sections'),
            'hasdata' => new external_value(PARAM_BOOL, 'Whether the report has data'),
            'hasrows' => new external_value(PARAM_BOOL, 'Whether the flat rows section has data'),
            'hasmonthlysections' => new external_value(PARAM_BOOL, 'Whether the monthly sections have data'),
            'hasoverall' => new external_value(PARAM_BOOL, 'Whether the overall summary has data'),
            'userlabel' => new external_value(PARAM_TEXT, 'User column heading'),
            'monthlabel' => new external_value(PARAM_TEXT, 'Month column heading'),
            'totallabel' => new external_value(PARAM_TEXT, 'Total column heading'),
            'notyetansweredlabel' => new external_value(PARAM_TEXT, 'Not yet answered heading'),
            'aggregationsumlabel' => new external_value(PARAM_TEXT, 'Overall aggregation heading'),
            'optioncolumns' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Option column label'),
                ])
            ),
            'rows' => new external_multiple_structure($datarow),
            'monthlysections' => new external_multiple_structure(
                new external_single_structure([
                    'heading' => new external_value(PARAM_TEXT, 'Section heading'),
                    'rows' => new external_multiple_structure(
                        new external_single_structure([
                            'userhtml' => new external_value(PARAM_RAW, 'Rendered user column HTML'),
                            'totalentries' => new external_value(PARAM_INT, 'Total entries for the row'),
                            'optioncells' => new external_multiple_structure($optioncell),
                            'notyetanswered' => new external_value(PARAM_INT, 'Entries without one of the counted options'),
                        ])
                    ),
                    'totalentries' => new external_value(PARAM_INT, 'Total entries for the section'),
                    'optioncells' => new external_multiple_structure($optioncell),
                    'notyetanswered' => new external_value(PARAM_INT, 'Section not yet answered total'),
                ])
            ),
            'overall' => new external_single_structure([
                'heading' => new external_value(PARAM_TEXT, 'Overall summary heading'),
                'totalentries' => new external_value(PARAM_INT, 'Overall total entries'),
                'optioncells' => new external_multiple_structure($optioncell),
                'notyetanswered' => new external_value(PARAM_INT, 'Overall not yet answered total'),
            ]),
            'emptycontent' => new external_value(PARAM_RAW, 'Fallback content when no report data is available'),
        ]);
    }
}
