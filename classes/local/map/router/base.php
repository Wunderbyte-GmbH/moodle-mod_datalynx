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

use mod_datalynx\local\map\http_client;
use mod_datalynx\local\map\route_result;
use mod_datalynx\local\map\router;

/**
 * Shared plumbing for the routing adapters.
 *
 * Subclasses describe their service by building a request and parsing a response;
 * caching, throttling, identification and error handling live in {@see http_client}.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base implements router {
    /** @var int Stops accepted in one request unless a subclass says otherwise. */
    const DEFAULT_MAX_STOPS = 25;

    /**
     * Constructor.
     *
     * @param string $baseurl Service base URL without a trailing slash.
     * @param string $apikey API key, empty when the service needs none.
     * @param http_client|null $client Injectable for testing.
     */
    public function __construct(
        /** @var string Service base URL without a trailing slash. */
        protected string $baseurl,
        /** @var string API key, empty when the service needs none. */
        protected string $apikey = '',
        /** @var http_client HTTP transport. */
        protected ?http_client $client = null
    ) {
        $this->client = $client ?? new http_client('route');
    }

    /**
     * Build the endpoint URL and query for a route request.
     *
     * @param array $stops Ordered [$lat, $lng] pairs.
     * @return array [$url, $params]
     */
    abstract protected function route_request(array $stops): array;

    /**
     * Convert a decoded service response into a route.
     *
     * @param array $response
     * @return route_result|null
     */
    abstract protected function parse(array $response): ?route_result;

    /**
     * Route through the given stops, in order.
     *
     * @param array $stops
     * @return route_result|null
     */
    public function route(array $stops): ?route_result {
        $stops = $this->clean_stops($stops);
        if (count($stops) < 2) {
            return null;
        }

        [$url, $params] = $this->route_request($stops);
        $response = $this->client->get_json($url, $params);
        if ($response === null) {
            return null;
        }

        return $this->parse($response);
    }

    /**
     * Largest number of stops this service accepts in one request.
     *
     * @return int
     */
    public function max_stops(): int {
        return self::DEFAULT_MAX_STOPS;
    }

    /**
     * Drop anything that is not a usable coordinate pair and cap the length.
     *
     * A stop the traveller has not filled in yet is a normal state of the picker,
     * so it is skipped rather than treated as an error.
     *
     * @param array $stops
     * @return array
     */
    protected function clean_stops(array $stops): array {
        $clean = [];
        foreach ($stops as $stop) {
            $lat = is_array($stop) ? ($stop[0] ?? $stop['lat'] ?? null) : null;
            $lng = is_array($stop) ? ($stop[1] ?? $stop['lng'] ?? null) : null;
            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }
            $lat = (float) $lat;
            $lng = (float) $lng;
            if (abs($lat) > 90 || abs($lng) > 180) {
                continue;
            }
            $clean[] = [$lat, $lng];
        }

        return array_slice($clean, 0, $this->max_stops());
    }
}
