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
use mod_datalynx\local\map\geocoder_factory;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Look up places matching an address query, on behalf of the browser.
 *
 * The browser is deliberately not allowed to call the geocoding provider itself:
 * the Nominatim usage policy forbids client-side autocomplete, these services
 * require a User-Agent that fetch() may not set, results have to be cached
 * site-wide, and an API key must never be shipped to a browser.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class geocode_search extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fieldid' => new external_value(PARAM_INT, 'Location field ID the lookup belongs to'),
            'query' => new external_value(PARAM_TEXT, 'Address query'),
        ]);
    }

    /**
     * Search for places matching an address query.
     *
     * @param int $fieldid
     * @param string $query
     * @return array
     */
    public static function execute(int $fieldid, string $query): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'fieldid' => $fieldid,
            'query' => $query,
        ]);

        $field = field_context::load($params['fieldid']);
        $field->require_can_geocode();
        self::validate_context($field->get_context());

        $geocoder = geocoder_factory::instance();
        if ($geocoder === null) {
            return ['places' => []];
        }

        $places = $geocoder->search($params['query'], ['countries' => $field->get_countries()]);

        return ['places' => array_map(static fn($place) => $place->to_array(), $places)];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'places' => new external_multiple_structure(
                new external_single_structure([
                    'address' => new external_value(PARAM_TEXT, 'Address label'),
                    'lat' => new external_value(PARAM_FLOAT, 'Latitude'),
                    'lng' => new external_value(PARAM_FLOAT, 'Longitude'),
                    'countrycode' => new external_value(PARAM_ALPHA, 'ISO 3166-1 alpha-2 code, may be empty'),
                ])
            ),
        ]);
    }
}
