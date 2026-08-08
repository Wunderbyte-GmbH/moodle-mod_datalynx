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
 * Turns addresses into coordinates and back.
 *
 * Implementations always run on the server. Geocoding from the browser is not an
 * option: the Nominatim usage policy forbids client side autocomplete outright,
 * these services require a User-Agent that fetch() is not allowed to set, and an
 * API key must never be shipped to a browser.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface geocoder {
    /**
     * Find places matching a free text address query.
     *
     * @param string $query
     * @param array $options Supported keys: countries, limit, lang.
     * @return place[]
     */
    public function search(string $query, array $options = []): array;

    /**
     * Find the address of a coordinate pair.
     *
     * @param float $lat
     * @param float $lng
     * @param array $options Supported keys: lang.
     * @return place|null
     */
    public function reverse(float $lat, float $lng, array $options = []): ?place;

    /**
     * Whether this service may be queried on every keystroke.
     *
     * False means the interface must wait for the user to submit the query: for
     * Nominatim because its usage policy forbids type-ahead, and for the Google
     * Geocoding API because it is not an autocomplete service.
     *
     * @return bool
     */
    public function supports_typeahead(): bool;

    /**
     * Shortest query worth sending to this service.
     *
     * @return int
     */
    public function minimum_query_length(): int;
}
