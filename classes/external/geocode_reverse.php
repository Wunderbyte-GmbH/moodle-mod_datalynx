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
use external_single_structure;
use external_value;
use mod_datalynx\local\map\field_context;
use mod_datalynx\local\map\geocoder_factory;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Look up the address of a coordinate pair, on behalf of the browser.
 *
 * Called when the user drops the map marker or uses their device location.
 * See {@see geocode_search} for why this does not happen in the browser.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class geocode_reverse extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fieldid' => new external_value(PARAM_INT, 'Location field ID the lookup belongs to'),
            'lat' => new external_value(PARAM_FLOAT, 'Latitude'),
            'lng' => new external_value(PARAM_FLOAT, 'Longitude'),
        ]);
    }

    /**
     * Find the address at a coordinate pair.
     *
     * @param int $fieldid
     * @param float $lat
     * @param float $lng
     * @return array
     */
    public static function execute(int $fieldid, float $lat, float $lng): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'fieldid' => $fieldid,
            'lat' => $lat,
            'lng' => $lng,
        ]);

        $field = field_context::load($params['fieldid']);
        $field->require_can_geocode();
        self::validate_context($field->get_context());

        $geocoder = geocoder_factory::instance();
        $place = $geocoder?->reverse($params['lat'], $params['lng']);

        return [
            'found' => $place !== null,
            'address' => $place->address ?? '',
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'found' => new external_value(PARAM_BOOL, 'Whether an address was resolved'),
            'address' => new external_value(PARAM_TEXT, 'Address label, empty when not resolved'),
        ]);
    }
}
