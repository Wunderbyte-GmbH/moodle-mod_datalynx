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

use core_text;
use mod_datalynx\local\map\place;

/**
 * Photon, komoot's open source OSM geocoder.
 *
 * The default engine, because it is the one designed for search-as-you-type,
 * needs no API key, and can be self-hosted from the same code the public
 * endpoint runs. Responses are GeoJSON with structured address components
 * rather than a ready made label, so the label is composed here.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @link       https://photon.komoot.io/
 */
class photon extends base {
    /**
     * Build the endpoint URL and query for a forward lookup.
     *
     * @param string $query
     * @param array $options
     * @return array
     */
    protected function search_request(string $query, array $options): array {
        // Photon has no country parameter; base::search() filters the results.
        // Over-fetch so that filtering still leaves a full page of suggestions.
        $limit = $options['countries'] !== '' ? $options['limit'] * 4 : $options['limit'];

        return [$this->baseurl . '/api', [
            'q' => $query,
            'limit' => $limit,
            'lang' => $this->supported_language($options['lang']),
        ]];
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
            'lat' => $lat,
            'lon' => $lng,
            'lang' => $this->supported_language($options['lang']),
        ]];
    }

    /**
     * Convert a Photon GeoJSON feature collection into places.
     *
     * @param array $response
     * @return place[]
     */
    protected function parse(array $response): array {
        $places = [];

        foreach ($response['features'] ?? [] as $feature) {
            $coordinates = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($coordinates) || count($coordinates) < 2) {
                continue;
            }

            $properties = $feature['properties'] ?? [];
            $street = trim(($properties['street'] ?? '') . ' ' . ($properties['housenumber'] ?? ''));
            $city = trim((string) ($properties['city'] ?? ''));
            $state = trim((string) ($properties['state'] ?? ''));

            // In a city-state such as Vienna, Berlin or Hamburg the state repeats
            // the city, and the postcode makes the two strings different enough
            // that place::join_parts() cannot spot the duplication itself.
            if ($state !== '' && core_text::strtolower($state) === core_text::strtolower($city)) {
                $state = '';
            }

            $label = place::join_parts([
                $properties['name'] ?? '',
                $street,
                trim(trim((string) ($properties['postcode'] ?? '')) . ' ' . $city),
                $state,
                $properties['country'] ?? '',
            ]);

            if ($label === '') {
                continue;
            }

            $places[] = new place(
                $label,
                (float) $coordinates[1],
                (float) $coordinates[0],
                strtolower((string) ($properties['countrycode'] ?? ''))
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
        return true;
    }

    /**
     * Shortest query worth sending to this service.
     *
     * @return int
     */
    public function minimum_query_length(): int {
        return 2;
    }

    /**
     * Restrict the language to the set Photon indexes.
     *
     * Photon only builds name indexes for a fixed list of languages and rejects
     * anything else, so fall back to the default rather than send a 400.
     *
     * @param string $lang
     * @return string
     */
    protected function supported_language(string $lang): string {
        $supported = ['de', 'en', 'fr', 'it'];

        return in_array($lang, $supported, true) ? $lang : 'en';
    }
}
