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
 * OSRM adapter.
 *
 * OSRM puts the stops in the path rather than the query string, in lng,lat order,
 * and answers with distance in metres, duration in seconds and an encoded polyline
 * at precision 5 - which is exactly the shape {@see route_result} stores.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class osrm extends base {
    /**
     * Build the route request.
     *
     * @param array $stops
     * @return array [$url, $params]
     */
    protected function route_request(array $stops): array {
        $coordinates = [];
        foreach ($stops as [$lat, $lng]) {
            // OSRM reads lng,lat, the opposite order to everything else here.
            $coordinates[] = round($lng, 6) . ',' . round($lat, 6);
        }

        $url = $this->baseurl . '/route/v1/driving/' . implode(';', $coordinates);

        return [$url, [
            // The "simplified" overview is the geometry OSRM intends for drawing a
            // route on a small map: a tenth of the size of the full geometry, and the
            // distance and duration are unaffected.
            'overview' => 'simplified',
            'geometries' => 'polyline',
            'steps' => 'false',
            'alternatives' => 'false',
        ]];
    }

    /**
     * Read the first route out of the response.
     *
     * @param array $response
     * @return route_result|null
     */
    protected function parse(array $response): ?route_result {
        if (($response['code'] ?? '') !== 'Ok' || empty($response['routes'])) {
            return null;
        }

        $route = reset($response['routes']);
        if (!isset($route['distance'], $route['duration'])) {
            return null;
        }

        return new route_result(
            (float) $route['distance'],
            (float) $route['duration'],
            (string) ($route['geometry'] ?? '')
        );
    }
}
