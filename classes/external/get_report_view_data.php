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
use mod_datalynx\local\view\manager\report_view_manager;
use mod_datalynx\output\report_view_browser;

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
        return new external_function_parameters(browse_request::parameters('Report view ID') + [
            'timescopefield' => new external_value(PARAM_ALPHA, 'Time field (timecreated|timemodified)', VALUE_DEFAULT, ''),
            'timescopemode' => new external_value(PARAM_ALPHA, 'Date scope mode (all|year|month|range)', VALUE_DEFAULT, 'all'),
            'timescopeyear' => new external_value(PARAM_INT, 'Selected year for year/month scope', VALUE_DEFAULT, 0),
            'timescopemonth' => new external_value(PARAM_INT, 'Selected month (1-12) for month scope', VALUE_DEFAULT, 0),
            'timescopefrom' => new external_value(PARAM_RAW, 'Range start date (YYYY-MM-DD)', VALUE_DEFAULT, ''),
            'timescopeto' => new external_value(PARAM_RAW, 'Range end date (YYYY-MM-DD)', VALUE_DEFAULT, ''),
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
     * @param string $timescopefield
     * @param string $timescopemode
     * @param int $timescopeyear
     * @param int $timescopemonth
     * @param string $timescopefrom
     * @param string $timescopeto
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
        string $search = '',
        string $timescopefield = '',
        string $timescopemode = 'all',
        int $timescopeyear = 0,
        int $timescopemonth = 0,
        string $timescopefrom = '',
        string $timescopeto = ''
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
            'timescopefield' => $timescopefield,
            'timescopemode' => $timescopemode,
            'timescopeyear' => $timescopeyear,
            'timescopemonth' => $timescopemonth,
            'timescopefrom' => $timescopefrom,
            'timescopeto' => $timescopeto,
        ]);

        $dlx = browse_request::require_access($params['d']);
        $filteroptions = browse_request::filter_options($params, $dlx);
        $filteroptions['timescope'] = [
            'field' => $params['timescopefield'],
            'mode' => $params['timescopemode'],
            'year' => $params['timescopeyear'],
            'month' => $params['timescopemonth'],
            'fromdate' => $params['timescopefrom'],
            'todate' => $params['timescopeto'],
        ];

        $manager = new report_view_manager();
        $payload = $manager->get_browse_payload($dlx, $params['view'], $filteroptions);
        $exporter = new report_view_browser($payload, $dlx->context);

        return (array) $exporter->export($OUTPUT);
    }

    /**
     * Define return structure.
     *
     * The structure is derived from the exporter so the template context and the
     * web service contract share a single definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return report_view_browser::get_read_structure();
    }
}
