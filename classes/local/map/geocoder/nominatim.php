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

namespace mod_datalynx\local\map\geocoder;

use mod_datalynx\local\map\place;

/**
 * Nominatim, the OpenStreetMap Foundation geocoder.
 *
 * Its usage policy explicitly forbids implementing autocomplete against the
 * API, so {@see supports_typeahead()} returns false and the interface waits for
 * the user to submit a query. The policy's other requirements — result caching,
 * at most one request per second, an identifying User-Agent — are met by
 * {@see \mod_datalynx\local\map\http_client}.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link       https://operations.osmfoundation.org/policies/nominatim/
 */
class nominatim extends base {
    /**
     * Build the endpoint URL and query for a forward lookup.
     *
     * @param string $query
     * @param array $options
     * @return array
     */
    protected function search_request(string $query, array $options): array {
        $params = [
            'format' => 'jsonv2',
            'q' => $query,
            'limit' => $options['limit'],
            'addressdetails' => 1,
            'accept-language' => $options['lang'],
        ];

        if ($options['countries'] !== '') {
            $params['countrycodes'] = $options['countries'];
        }

        return [$this->baseurl . '/search', $params];
    }

    /**
     * Build the endpoint URL and query for a reverse lookup.
     *
     * @param float $lat
     * @param float $lng
     * @param array $options
     * @return array
     */
    protected function reverse_request(float $lat, float $lng, array $options): array {
        return [$this->baseurl . '/reverse', [
            'format' => 'jsonv2',
            'lat' => $lat,
            'lon' => $lng,
            'zoom' => 18,
            'addressdetails' => 1,
            'accept-language' => $options['lang'],
        ]];
    }

    /**
     * Convert a Nominatim response into places.
     *
     * A forward lookup returns a list, a reverse lookup a single object.
     *
     * @param array $response
     * @return place[]
     */
    protected function parse(array $response): array {
        $entries = array_key_exists('display_name', $response) ? [$response] : $response;
        $places = [];

        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['display_name'], $entry['lat'], $entry['lon'])) {
                continue;
            }

            $places[] = new place(
                (string) $entry['display_name'],
                (float) $entry['lat'],
                (float) $entry['lon'],
                strtolower((string) ($entry['address']['country_code'] ?? ''))
            );
        }

        return $places;
    }

    /**
     * Whether this service may be queried on every keystroke.
     *
     * @return bool
     */
    public function supports_typeahead(): bool {
        return false;
    }
}
