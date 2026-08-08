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

use cache;
use core\lock\lock_config;
use curl;

/**
 * Cached, rate limited and correctly identified JSON requests to a map service.
 *
 * Every requirement here comes from the providers' usage policies rather than
 * from performance: Nominatim mandates client side caching and at most one
 * request per second, and both Nominatim and the OSM tile servers reject
 * requests that do not identify the application in the User-Agent.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class http_client {
    /** @var int Seconds to wait for the remote service. */
    const TIMEOUT = 10;

    /** @var int Longest a request will pause to respect the rate limit, in seconds. */
    const MAX_THROTTLE_WAIT = 3;

    /** @var string Cache key holding the timestamp of the last outbound request. */
    const THROTTLE_KEY = 'lastrequest';

    /**
     * Constructor.
     *
     * @param string $cachename Cache definition results are stored in ('geocode' or 'route').
     */
    public function __construct(
        /** @var string Cache definition results are stored in. */
        protected string $cachename = 'geocode'
    ) {
    }

    /**
     * Fetch and decode a JSON document, reusing a cached copy when allowed.
     *
     * @param string $url Absolute URL without a query string.
     * @param array $params Query parameters.
     * @return array|null Decoded response, or null when the service failed.
     */
    public function get_json(string $url, array $params): ?array {
        $cache = cache::make('mod_datalynx', $this->cachename);
        $ttl = provider_config::cache_ttl();
        $key = sha1($url . '?' . http_build_query($params));

        $cached = $cache->get($key);
        if (is_array($cached) && isset($cached['time'], $cached['data'])) {
            if ($ttl === 0 || (time() - (int) $cached['time']) < $ttl) {
                return $cached['data'];
            }
        }

        $data = $this->request($url, $params);
        if ($data === null) {
            return null;
        }

        $cache->set($key, ['time' => time(), 'data' => $data]);

        return $data;
    }

    /**
     * Perform one throttled request.
     *
     * @param string $url
     * @param array $params
     * @return array|null
     */
    protected function request(string $url, array $params): ?array {
        global $CFG;

        // Web requests have this already; CLI (the seeding script, cron tasks) does not.
        require_once($CFG->libdir . '/filelib.php');

        // Serialise outbound requests across concurrent PHP processes, otherwise
        // the per-second limit is only respected within a single request.
        $factory = lock_config::get_lock_factory('mod_datalynx_geocode');
        $lock = $factory->get_lock('outbound', self::MAX_THROTTLE_WAIT + self::TIMEOUT);

        try {
            if ($lock) {
                $this->wait_for_slot();
            }

            $curl = new curl();
            $curl->setopt([
                'CURLOPT_TIMEOUT' => self::TIMEOUT,
                'CURLOPT_CONNECTTIMEOUT' => self::TIMEOUT,
                'CURLOPT_USERAGENT' => provider_config::user_agent(),
                'CURLOPT_FOLLOWLOCATION' => 0,
            ]);
            $curl->setHeader(['Accept: application/json']);

            $response = $curl->get($url, $params);
            $info = $curl->get_info();
            $status = (int) ($info['http_code'] ?? 0);

            if ($curl->get_errno() || $status < 200 || $status >= 300 || $response === false) {
                debugging(
                    "datalynx map service request to {$url} failed with status {$status}",
                    DEBUG_DEVELOPER
                );

                return null;
            }

            $decoded = json_decode((string) $response, true);

            return is_array($decoded) ? $decoded : null;
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Pause until the configured request rate allows another call.
     */
    protected function wait_for_slot(): void {
        $cache = cache::make('mod_datalynx', 'geocodethrottle');
        $interval = 1 / provider_config::rate_limit();
        $now = microtime(true);
        $last = (float) $cache->get(self::THROTTLE_KEY);

        $wait = ($last > 0) ? ($last + $interval) - $now : 0;
        if ($wait > 0) {
            // A caller that would have to wait longer than this is better served
            // by no result than by holding the request open.
            usleep((int) round(min($wait, self::MAX_THROTTLE_WAIT) * 1000000));
        }

        $cache->set(self::THROTTLE_KEY, microtime(true));
    }
}
