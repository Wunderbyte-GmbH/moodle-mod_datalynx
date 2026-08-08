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

namespace mod_datalynx\local\map\router;

use mod_datalynx\local\map\route_result;

/**
 * Google Directions API adapter.
 *
 * Only the Directions web service is used; rendering stays with Leaflet, because
 * Google's terms allow its tiles solely inside the Google Maps JavaScript API.
 * Distance and duration come per leg and are summed here.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google extends base {
    /** @var int Origin, destination and 23 waypoints is the documented ceiling. */
    const MAX_STOPS = 25;

    /**
     * Build the route request.
     *
     * @param array $stops
     * @return array [$url, $params]
     */
    protected function route_request(array $stops): array {
        $format = static function (array $stop): string {
            return round($stop[0], 6) . ',' . round($stop[1], 6);
        };

        $origin = array_shift($stops);
        $destination = array_pop($stops);

        $params = [
            'origin' => $format($origin),
            'destination' => $format($destination),
            'mode' => 'driving',
            'key' => $this->apikey,
            'language' => substr(current_language(), 0, 2),
        ];

        if ($stops) {
            // Prefixing "via:" keeps the intermediate stops on the line without turning
            // them into stopovers, which is what an itinerary describes.
            $params['waypoints'] = implode('|', array_map(
                static fn(array $stop): string => 'via:' . $format($stop),
                $stops
            ));
        }

        return [$this->baseurl . '/directions/json', $params];
    }

    /**
     * Sum the legs of the first route.
     *
     * @param array $response
     * @return route_result|null
     */
    protected function parse(array $response): ?route_result {
        if (($response['status'] ?? '') !== 'OK' || empty($response['routes'])) {
            return null;
        }

        $route = reset($response['routes']);
        $distance = 0.0;
        $duration = 0.0;
        foreach (($route['legs'] ?? []) as $leg) {
            $distance += (float) ($leg['distance']['value'] ?? 0);
            $duration += (float) ($leg['duration']['value'] ?? 0);
        }

        if ($distance <= 0) {
            return null;
        }

        return new route_result(
            $distance,
            $duration,
            (string) ($route['overview_polyline']['points'] ?? '')
        );
    }

    /**
     * Largest number of stops this service accepts in one request.
     *
     * @return int
     */
    public function max_stops(): int {
        return self::MAX_STOPS;
    }
}
