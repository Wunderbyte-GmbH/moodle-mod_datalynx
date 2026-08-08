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
use mod_datalynx\local\map\geocoder;
use mod_datalynx\local\map\http_client;
use mod_datalynx\local\map\place;
use mod_datalynx\local\map\provider_config;

/**
 * Shared plumbing for the geocoding adapters.
 *
 * Subclasses describe their service by building a request and parsing a
 * response; caching, throttling, identification and error handling all live in
 * {@see http_client}.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base implements geocoder {
    /** @var int Places returned when the caller does not ask for a specific number. */
    const DEFAULT_LIMIT = 5;

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
        $this->client = $client ?? new http_client();
    }

    /**
     * Build the endpoint URL and query for a forward lookup.
     *
     * @param string $query
     * @param array $options
     * @return array [$url, $params]
     */
    abstract protected function search_request(string $query, array $options): array;

    /**
     * Build the endpoint URL and query for a reverse lookup.
     *
     * @param float $lat
     * @param float $lng
     * @param array $options
     * @return array [$url, $params]
     */
    abstract protected function reverse_request(float $lat, float $lng, array $options): array;

    /**
     * Convert a decoded service response into places.
     *
     * @param array $response
     * @return place[]
     */
    abstract protected function parse(array $response): array;

    /**
     * Find places matching a free text address query.
     *
     * @param string $query
     * @param array $options
     * @return place[]
     */
    public function search(string $query, array $options = []): array {
        $query = trim($query);
        if (core_text::strlen($query) < $this->minimum_query_length()) {
            return [];
        }

        $options['countries'] = provider_config::clean_countries((string) ($options['countries'] ?? ''));
        $options['limit'] = (int) ($options['limit'] ?? self::DEFAULT_LIMIT);
        $options['lang'] = $this->language($options);

        [$url, $params] = $this->search_request($query, $options);
        $response = $this->client->get_json($url, $params);
        if ($response === null) {
            return [];
        }

        $places = $this->parse($response);

        // Applied here rather than in each adapter because not every service can
        // filter by country server side: Photon has no country parameter.
        if ($options['countries'] !== '') {
            $allowed = explode(',', $options['countries']);
            $places = array_values(array_filter($places, static function (place $place) use ($allowed) {
                return $place->countrycode === '' || in_array($place->countrycode, $allowed, true);
            }));
        }

        return array_slice($places, 0, $options['limit']);
    }

    /**
     * Find the address of a coordinate pair.
     *
     * @param float $lat
     * @param float $lng
     * @param array $options
     * @return place|null
     */
    public function reverse(float $lat, float $lng, array $options = []): ?place {
        if (abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        $options['lang'] = $this->language($options);

        [$url, $params] = $this->reverse_request($lat, $lng, $options);
        $response = $this->client->get_json($url, $params);
        if ($response === null) {
            return null;
        }

        $places = $this->parse($response);

        return $places ? reset($places) : null;
    }

    /**
     * Shortest query worth sending to this service.
     *
     * @return int
     */
    public function minimum_query_length(): int {
        return 3;
    }

    /**
     * Language to ask the service to label results in.
     *
     * @param array $options
     * @return string Two letter language code.
     */
    protected function language(array $options): string {
        $lang = (string) ($options['lang'] ?? current_language());

        return substr(strtolower($lang), 0, 2);
    }
}
