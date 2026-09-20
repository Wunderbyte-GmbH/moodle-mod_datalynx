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
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service to fetch all views for a datalynx instance.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_view_names extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
        ]);
    }

    /**
     * Return all views for a datalynx instance.
     *
     * @param int $d
     * @return array
     */
    public static function execute(int $d): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['d' => $d]);

        $cm = get_coursemodule_from_instance('datalynx', $params['d'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        require_login($cm->course, true, $cm);
        require_capability('mod/datalynx:managetemplates', $context);

        $views = $DB->get_records('datalynx_views', ['dataid' => $params['d']], 'name ASC', 'id, name');
        $result = [];

        foreach ($views as $view) {
            $result[] = [
                'id' => (int) $view->id,
                'name' => strip_tags(format_string($view->name, true, ['context' => $context])),
            ];
        }

        return $result;
    }

    /**
     * Define return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'View ID'),
                'name' => new external_value(PARAM_TEXT, 'View name'),
            ])
        );
    }
}
