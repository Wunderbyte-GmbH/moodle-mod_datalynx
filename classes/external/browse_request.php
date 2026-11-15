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
use core_external\external_value;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\local\filter\search_criteria;

/**
 * What the view browse services have in common: their filter parameters, the access check and
 * the conversion of the parameters into filter options.
 *
 * Every service that lists entries (the grid, tabular, CSV, PDF and report view data services and
 * the mobile entries service) goes through this class, so a criterion one of them accepts is a
 * criterion all of them accept, and none of them hands unchecked client criteria to the filter.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class browse_request {
    /**
     * The parameters of a view browse service.
     *
     * @param string $viewdescription description of the view parameter, e.g. 'Grid view ID'
     * @return external_value[]
     */
    public static function parameters(string $viewdescription): array {
        return [
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'view' => new external_value(PARAM_INT, $viewdescription),
            'filterid' => new external_value(PARAM_INT, 'Active filter ID', VALUE_DEFAULT, 0),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Entries per page override', VALUE_DEFAULT, 0),
            'eids' => new external_value(PARAM_SEQUENCE, 'Optional entry ids filter', VALUE_DEFAULT, ''),
            'users' => new external_value(PARAM_SEQUENCE, 'Optional user ids filter', VALUE_DEFAULT, ''),
            'groups' => new external_value(PARAM_SEQUENCE, 'Optional group ids filter', VALUE_DEFAULT, ''),
            'groupby' => new external_value(PARAM_RAW, 'Optional group-by field', VALUE_DEFAULT, ''),
            'selection' => new external_value(PARAM_INT, 'Optional selection mode override', VALUE_DEFAULT, 0),
            'customsort' => new external_value(PARAM_RAW, 'Optional JSON sort directions by field id', VALUE_DEFAULT, ''),
            'customsearch' => new external_value(PARAM_RAW, 'Optional JSON search criteria by field id', VALUE_DEFAULT, ''),
            'search' => new external_value(PARAM_RAW, 'Optional search string', VALUE_DEFAULT, ''),
        ];
    }

    /**
     * Check the current user may browse an instance, and load it.
     *
     * @param int $datalynxid
     * @return datalynx
     */
    public static function require_access(int $datalynxid): datalynx {
        $cm = get_coursemodule_from_instance('datalynx', $datalynxid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        external_api::validate_context($context);
        require_capability('mod/datalynx:viewentry', $context);

        return new datalynx($datalynxid);
    }

    /**
     * Turn the validated browse parameters into the filter options view\base::set_filter() reads.
     *
     * Search and sort criteria must pass search_criteria: JSON only, fields of this instance,
     * operators those fields take. Entry ids are always set, so that set_filter() never takes
     * them from the request itself.
     *
     * @param array $params validated parameters; only the keys of parameters() are read
     * @param datalynx $dlx the instance the criteria are for
     * @return array
     * @throws invalid_parameter_exception for criteria search_criteria rejects
     */
    public static function filter_options(array $params, datalynx $dlx): array {
        $options = [
            'filterid' => (int) ($params['filterid'] ?? 0),
            'page' => max(0, (int) ($params['page'] ?? 0)),
            'eids' => clean_param((string) ($params['eids'] ?? ''), PARAM_SEQUENCE),
        ];
        foreach (['perpage', 'users', 'groups', 'groupby', 'selection'] as $name) {
            if (!empty($params[$name])) {
                $options[$name] = $params[$name];
            }
        }
        if (($sort = search_criteria::decode_sort((string) ($params['customsort'] ?? ''), $dlx))) {
            $options['customsort'] = $sort;
        }
        if (($search = search_criteria::decode_search((string) ($params['customsearch'] ?? ''), $dlx))) {
            $options['customsearch'] = $search;
        }
        if (($params['search'] ?? '') !== '') {
            $options['search'] = $params['search'];
        }

        return $options;
    }
}
