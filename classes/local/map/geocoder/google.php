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
 * The Google Geocoding API.
 *
 * Geocoding only. The map itself always renders with Leaflet over raster tiles,
 * because Google's terms do not allow its tiles to be used outside the Google
 * Maps JavaScript API — a genuine Google basemap needs a second map renderer and
 * a consent flow, which is deliberately out of scope here.
 *
 * This is also not an autocomplete service; Places Autocomplete is a separate,
 * separately billed API. So {@see supports_typeahead()} returns false.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link       https://developers.google.com/maps/documentation/geocoding
 */
class google extends base {
    /**
     * Build the endpoint URL and query for a forward lookup.
     *
     * @param string $query
     * @param array $options
     * @return array
     */
    protected function search_request(string $query, array $options): array {
        $params = [
            'address' => $query,
            'key' => $this->apikey,
            'language' => $options['lang'],
        ];

        if ($options['countries'] !== '') {
            $components = [];
            foreach (explode(',', $options['countries']) as $code) {
                $components[] = 'country:' . $code;
            }
            $params['components'] = implode('|', $components);
        }

        return [$this->baseurl . '/geocode/json', $params];
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
        return [$this->baseurl . '/geocode/json', [
            'latlng' => $lat . ',' . $lng,
            'key' => $this->apikey,
            'language' => $options['lang'],
        ]];
    }

    /**
     * Convert a Google Geocoding API response into places.
     *
     * @param array $response
     * @return place[]
     */
    protected function parse(array $response): array {
        if (($response['status'] ?? '') !== 'OK') {
            if (!empty($response['error_message'])) {
                debugging('Google Geocoding API: ' . $response['error_message'], DEBUG_DEVELOPER);
            }

            return [];
        }

        $places = [];

        foreach ($response['results'] ?? [] as $result) {
            $location = $result['geometry']['location'] ?? null;
            if (!isset($result['formatted_address'], $location['lat'], $location['lng'])) {
                continue;
            }

            $places[] = new place(
                (string) $result['formatted_address'],
                (float) $location['lat'],
                (float) $location['lng'],
                $this->country_code($result['address_components'] ?? [])
            );
        }

        return $places;
    }

    /**
     * Pull the country out of the address components.
     *
     * @param array $components
     * @return string Lower case ISO 3166-1 alpha-2 code, empty when absent.
     */
    protected function country_code(array $components): string {
        foreach ($components as $component) {
            if (in_array('country', $component['types'] ?? [], true)) {
                return strtolower((string) ($component['short_name'] ?? ''));
            }
        }

        return '';
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
