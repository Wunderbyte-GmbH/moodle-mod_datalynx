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

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use mod_datalynx\local\map\field_context;
use mod_datalynx\local\map\router_factory;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Work out distance, travel time and route geometry for a journey.
 *
 * Called by the itinerary picker while a journey is being edited, so the traveller
 * sees the travel time before saving, and by the display renderer when an entry has
 * no cached route yet. The browser never talks to the routing service directly: the
 * usage policies want an identifying User-Agent that fetch() may not set, the
 * results have to be cached site-wide, and a key must never reach a browser.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class route_calculate extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fieldid' => new external_value(PARAM_INT, 'Itinerary field ID the journey belongs to'),
            'stops' => new external_multiple_structure(
                new external_single_structure([
                    'lat' => new external_value(PARAM_FLOAT, 'Latitude'),
                    'lng' => new external_value(PARAM_FLOAT, 'Longitude'),
                ]),
                'Ordered stops of the journey'
            ),
        ]);
    }

    /**
     * Route through the given stops.
     *
     * @param int $fieldid
     * @param array $stops
     * @return array
     */
    public static function execute(int $fieldid, array $stops): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'fieldid' => $fieldid,
            'stops' => $stops,
        ]);

        $field = field_context::load($params['fieldid']);
        $field->require_can_geocode();
        self::validate_context($field->get_context());

        $empty = ['found' => false, 'distance' => 0.0, 'duration' => 0, 'polyline' => ''];

        $router = router_factory::instance();
        if ($router === null) {
            return $empty;
        }

        $route = $router->route(array_map(
            static fn(array $stop): array => [$stop['lat'], $stop['lng']],
            $params['stops']
        ));

        if ($route === null) {
            return $empty;
        }

        return ['found' => true] + $route->to_array();
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether a route was found'),
            'distance' => new external_value(PARAM_FLOAT, 'Road distance in metres'),
            'duration' => new external_value(PARAM_INT, 'Driving time in seconds'),
            'polyline' => new external_value(PARAM_RAW, 'Encoded route geometry, precision 5'),
        ]);
    }
}
