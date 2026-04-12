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
use invalid_parameter_exception;
use mod_datalynx\local\field\datalynxfield_behavior;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service to toggle behavior settings in the behavior management table.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class toggle_behavior extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'behaviorid' => new external_value(PARAM_INT, 'Behavior ID'),
            'forproperty' => new external_value(PARAM_ALPHA, 'Behavior property to toggle'),
            'permissionid' => new external_value(PARAM_INT, 'Permission ID for permission-based toggles', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Toggle a behavior property and return the resulting state.
     *
     * @param int $behaviorid
     * @param string $forproperty
     * @param int $permissionid
     * @return array
     */
    public static function execute(int $behaviorid, string $forproperty, int $permissionid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'behaviorid' => $behaviorid,
            'forproperty' => $forproperty,
            'permissionid' => $permissionid,
        ]);

        if (!in_array($params['forproperty'], ['required', 'visibleto', 'editableby'], true)) {
            throw new invalid_parameter_exception('Invalid behavior property.');
        }

        if ($params['forproperty'] !== 'required' && $params['permissionid'] < 1) {
            throw new invalid_parameter_exception('Permission ID is required for permission-based behavior toggles.');
        }

        $behavior = datalynxfield_behavior::from_id($params['behaviorid']);
        if (!$behavior) {
            throw new invalid_parameter_exception('Invalid behavior ID.');
        }

        $cm = get_coursemodule_from_instance('datalynx', $behavior->get_dataid(), 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_login($cm->course, true, $cm);
        require_capability('mod/datalynx:managetemplates', $context);

        return [
            'behaviorid' => $params['behaviorid'],
            'forproperty' => $params['forproperty'],
            'permissionid' => $params['permissionid'],
            'enabled' => $behavior->toggle_property($params['forproperty'], $params['permissionid']),
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'behaviorid' => new external_value(PARAM_INT, 'Behavior ID'),
            'forproperty' => new external_value(PARAM_ALPHA, 'Behavior property'),
            'permissionid' => new external_value(PARAM_INT, 'Permission ID, or 0 when not applicable'),
            'enabled' => new external_value(PARAM_BOOL, 'The resulting enabled state'),
        ]);
    }
}
