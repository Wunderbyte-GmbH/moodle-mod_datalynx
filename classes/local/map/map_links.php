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

namespace mod_datalynx\local\map;

/**
 * Links that open a place or a journey in a maps service.
 *
 * Used where no map can be drawn, in the Moodle App: one tap hands the stops to the maps app or
 * website of the phone. The service follows the site's map configuration: Google when routing or
 * address lookup run on Google, OpenStreetMap otherwise.
 *
 * The caller passes the coordinates the viewer may see, so a coarsened journey stays coarsened in
 * its link.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class map_links {
    /**
     * Whether the links point to Google Maps.
     *
     * @return bool
     */
    public static function uses_google(): bool {
        return provider_config::router_engine() === provider_config::ROUTER_GOOGLE
            || provider_config::geocoder_engine() === provider_config::GEOCODER_GOOGLE;
    }

    /**
     * Directions along a journey, from its first to its last stop.
     *
     * Google Maps takes the stops in between as waypoints. openstreetmap.org can only route
     * from one point to another, so its link covers start and destination.
     *
     * @param array $points Ordered [lat, lng] pairs, at least two.
     * @return string Empty when there are fewer than two points.
     */
    public static function directions(array $points): string {
        $points = array_values($points);
        if (count($points) < 2) {
            return '';
        }
        $first = self::coordinates($points[0]);
        $last = self::coordinates($points[count($points) - 1]);

        if (self::uses_google()) {
            $params = ['api' => 1, 'origin' => $first, 'destination' => $last, 'travelmode' => 'driving'];
            $between = array_map([self::class, 'coordinates'], array_slice($points, 1, -1));
            if ($between) {
                $params['waypoints'] = implode('|', $between);
            }
            return 'https://www.google.com/maps/dir/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route='
            . rawurlencode($first . ';' . $last);
    }

    /**
     * Directions to one place, from wherever the viewer is.
     *
     * @param float $lat
     * @param float $lng
     * @return string
     */
    public static function directions_to(float $lat, float $lng): string {
        $destination = self::coordinates([$lat, $lng]);

        if (self::uses_google()) {
            return 'https://www.google.com/maps/dir/?'
                . http_build_query(['api' => 1, 'destination' => $destination], '', '&', PHP_QUERY_RFC3986);
        }

        // The empty part before the semicolon is the origin, filled in from the device's position.
        return 'https://www.openstreetmap.org/directions?engine=fossgis_osrm_car&route=' . rawurlencode(';' . $destination);
    }

    /**
     * A coordinate pair as "lat,lng", without trailing zeros.
     *
     * @param array $point [lat, lng]
     * @return string
     */
    private static function coordinates(array $point): string {
        $format = static fn($value): string => rtrim(rtrim(sprintf('%.6F', (float) $value), '0'), '.');

        return $format($point[0]) . ',' . $format($point[1]);
    }
}
