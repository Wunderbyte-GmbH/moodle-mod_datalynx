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
use mod_datalynx\local\mobile\customfilter_form;
use mod_datalynx\local\mobile\entries_payload_builder;

/**
 * Browse the entries of a grid or tabular view from the Moodle App.
 *
 * A mobile-specific service rather than get_grid_view_data / get_tabular_view_data exposed to
 * the mobile service: those return desktop markup and two different shapes, and once a function
 * is in the mobile service its structure is frozen for every released app version.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_mobile_entries extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'viewid' => new external_value(PARAM_INT, 'View ID, 0 for the default view', VALUE_DEFAULT, 0),
            'filterid' => new external_value(PARAM_INT, 'Saved filter ID, 0 for the view filter', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Entries per page, 0 for the filter default', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_TEXT, 'Full-text search', VALUE_DEFAULT, ''),
            'criteria' => new external_value(PARAM_RAW, 'JSON search criteria by field id', VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_RAW, 'JSON sort directions by field id', VALUE_DEFAULT, ''),
            'eids' => new external_value(PARAM_SEQUENCE, 'Restrict to these entry ids', VALUE_DEFAULT, ''),
            'customfilterid' => new external_value(PARAM_INT, 'Custom filter of the view to apply, 0 for none', VALUE_DEFAULT, 0),
            'customvalues' => new external_value(
                PARAM_RAW,
                'JSON object of the custom filter inputs, named as the app form names them',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Return one page of entries of a view.
     *
     * @param int $d
     * @param int $viewid
     * @param int $filterid
     * @param int $page
     * @param int $perpage
     * @param string $search
     * @param string $criteria
     * @param string $sort
     * @param string $eids
     * @param int $customfilterid
     * @param string $customvalues
     * @return array
     */
    public static function execute(
        int $d,
        int $viewid = 0,
        int $filterid = 0,
        int $page = 0,
        int $perpage = 0,
        string $search = '',
        string $criteria = '',
        string $sort = '',
        string $eids = '',
        int $customfilterid = 0,
        string $customvalues = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'viewid' => $viewid,
            'filterid' => $filterid,
            'page' => $page,
            'perpage' => $perpage,
            'search' => $search,
            'criteria' => $criteria,
            'sort' => $sort,
            'eids' => $eids,
            'customfilterid' => $customfilterid,
            'customvalues' => $customvalues,
        ]);
        $params['customfilterinputs'] = customfilter_form::inputs([], $params['customvalues']);

        $builder = new entries_payload_builder(browse_request::require_access($params['d']));
        $payload = $builder->build($params);
        $payload['warnings'] = [];

        return $payload;
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'datalynxid' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'viewid' => new external_value(PARAM_INT, 'The view shown, 0 when the user can see none'),
            'viewname' => new external_value(PARAM_TEXT, 'View name'),
            'viewtype' => new external_value(PARAM_ALPHANUMEXT, 'View type'),
            'supported' => new external_value(PARAM_BOOL, 'Whether the app can browse this view'),
            'browserurl' => new external_value(PARAM_URL, 'The view on the web'),
            'canfilter' => new external_value(PARAM_BOOL, 'Whether the view lets the user search and switch filters'),
            'filterid' => new external_value(PARAM_INT, 'The filter applied'),
            'page' => new external_value(PARAM_INT, 'Page number'),
            'perpage' => new external_value(PARAM_INT, 'Entries per page'),
            'search' => new external_value(PARAM_TEXT, 'The full-text search applied'),
            'criteria' => new external_value(PARAM_RAW, 'The search criteria applied, JSON'),
            'sort' => new external_value(PARAM_RAW, 'The sort criteria applied, JSON'),
            'entriescount' => new external_value(PARAM_INT, 'Entries on this page'),
            'entriesfiltercount' => new external_value(PARAM_INT, 'Entries matching the filter'),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether more entries match than this page holds'),
            'canadd' => new external_value(PARAM_BOOL, 'Whether the user may add an entry through this view'),
            'section' => self::parts_structure('The view template around the entries'),
            'listsentries' => new external_value(PARAM_BOOL, 'Whether the view template lists the entries'),
            'entries' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Entry ID'),
                'parts' => self::parts_structure('The entry rendered with the view entry template, empty if none'),
                'title' => new external_value(PARAM_RAW, 'Plain text of the first field with a value, empty if none'),
                'fields' => new external_multiple_structure(new external_single_structure([
                    'fieldid' => new external_value(PARAM_INT, 'Field ID'),
                    'name' => new external_value(PARAM_TEXT, 'Field name'),
                    'valuehtml' => new external_value(PARAM_RAW, 'Rendered value'),
                    'valuetext' => new external_value(PARAM_RAW, 'Value as plain text'),
                ])),
                'userid' => new external_value(PARAM_INT, 'Author user ID'),
                'author' => new external_value(PARAM_TEXT, 'Author full name'),
                'approved' => new external_value(PARAM_BOOL, 'Whether the entry is approved'),
                'status' => new external_value(PARAM_INT, 'Entry status'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                'canedit' => new external_value(PARAM_BOOL, 'Whether the user may edit the entry through this view'),
                'candelete' => new external_value(PARAM_BOOL, 'Whether the user may delete the entry through this view'),
                'canapprove' => new external_value(
                    PARAM_BOOL,
                    'Whether the user may approve or disapprove the entry through this view'
                ),
            ])),
            'views' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'View ID'),
                'name' => new external_value(PARAM_TEXT, 'View name'),
                'type' => new external_value(PARAM_ALPHANUMEXT, 'View type'),
                'supported' => new external_value(PARAM_BOOL, 'Whether the app can browse this view'),
                'active' => new external_value(PARAM_BOOL, 'Whether this is the view shown'),
            ])),
            'filters' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Filter ID'),
                'name' => new external_value(PARAM_TEXT, 'Filter name'),
                'active' => new external_value(PARAM_BOOL, 'Whether this filter is applied'),
            ])),
            'customfilters' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Custom filter ID'),
                'name' => new external_value(PARAM_TEXT, 'Custom filter name'),
                'active' => new external_value(PARAM_BOOL, 'Whether this custom filter is applied'),
            ])),
            'customfilterid' => new external_value(PARAM_INT, 'The custom filter applied, 0 for none'),
            'emptycontent' => new external_value(PARAM_TEXT, 'Text to show when there are no entries'),
            'warnings' => new external_warnings(),
        ]);
    }

    /**
     * Rendered HTML cut at its links to other views (see \mod_datalynx\local\mobile\app_links).
     *
     * @param string $desc
     * @return external_multiple_structure
     */
    private static function parts_structure(string $desc): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'HTML, empty for a link or the entries'),
            'entries' => new external_value(PARAM_BOOL, 'Whether the entries are listed here'),
            'link' => new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Link text'),
                'title' => new external_value(PARAM_TEXT, 'Name of the target view'),
                'method' => new external_value(PARAM_ALPHANUMEXT, 'App content method to open, empty to open url in the browser'),
                'viewid' => new external_value(PARAM_INT, 'Target view ID'),
                'entryid' => new external_value(PARAM_INT, 'Entry to edit, 0 for a new entry'),
                'filterid' => new external_value(PARAM_INT, 'Saved filter to apply, 0 for the view filter'),
                'eids' => new external_value(PARAM_SEQUENCE, 'Entries to list, empty for all'),
                'url' => new external_value(PARAM_URL, 'The target on the web'),
                'fill' => new external_value(PARAM_ALPHA, 'Button fill: solid, outline or clear'),
                'expand' => new external_value(PARAM_BOOL, 'Whether the button takes the full width'),
            ], 'A link to another view; missing for HTML and the entries', VALUE_OPTIONAL),
        ]), $desc);
    }
}
