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
 * Site level configuration of the map services datalynx talks to.
 *
 * A "map provider" is really three independent services, and this class keeps
 * them apart:
 *
 * - the basemap tiles, fetched by the browser straight from the tile server;
 * - geocoding, always proxied through this site (see {@see geocoder});
 * - routing, which the ridesharing feature will add here later.
 *
 * The engine setting selects the request and response adapter, the URL setting
 * selects where to send it. Running a self-hosted instance is therefore the same
 * engine pointed at a different URL, not a separate code path.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_config {
    /** @var string Geocoding disabled: the address field stays free text. */
    const GEOCODER_NONE = 'none';

    /** @var string Photon, komoot's OSM geocoder. Built for type-ahead. */
    const GEOCODER_PHOTON = 'photon';

    /** @var string Nominatim, the OSM Foundation geocoder. Forbids type-ahead. */
    const GEOCODER_NOMINATIM = 'nominatim';

    /** @var string Google Geocoding API. Server side only, needs an API key. */
    const GEOCODER_GOOGLE = 'google';

    /** @var array Public endpoint of each engine, used as the setting default. */
    const PUBLIC_ENDPOINTS = [
        self::GEOCODER_PHOTON => 'https://photon.komoot.io',
        self::GEOCODER_NOMINATIM => 'https://nominatim.openstreetmap.org',
        self::GEOCODER_GOOGLE => 'https://maps.googleapis.com/maps/api',
    ];

    /**
     * Endpoints run by volunteers or offered free of charge for evaluation.
     *
     * Using one of these in production breaches the operators' usage policies,
     * so the admin settings page warns while one is configured.
     *
     * @var array
     */
    const SHARED_ENDPOINTS = [
        'photon.komoot.io',
        'nominatim.openstreetmap.org',
        'tile.openstreetmap.org',
        'router.project-osrm.org',
    ];

    /** @var string Default basemap: the OSM standard tile layer. */
    const DEFAULT_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    /** @var string Attribution the OSM tile usage policy requires. */
    const DEFAULT_TILE_ATTRIBUTION =
        '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    /** @var int Zoom level used when no default centre is configured. */
    const WORLD_ZOOM = 2;

    /**
     * Read one plugin setting, falling back to a default.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected static function setting(string $name, $default = '') {
        $value = get_config('mod_datalynx', $name);

        return ($value === false || $value === null || $value === '') ? $default : $value;
    }

    /**
     * The configured geocoding engine.
     *
     * @return string One of the GEOCODER_* constants.
     */
    public static function geocoder_engine(): string {
        $engine = (string) self::setting('geocoder', self::GEOCODER_PHOTON);

        return array_key_exists($engine, self::PUBLIC_ENDPOINTS) ? $engine : self::GEOCODER_NONE;
    }

    /**
     * Base URL of the geocoding service, without a trailing slash.
     *
     * @return string
     */
    public static function geocoder_url(): string {
        $engine = self::geocoder_engine();
        $default = self::PUBLIC_ENDPOINTS[$engine] ?? '';

        return rtrim((string) self::setting('geocoderurl', $default), '/');
    }

    /**
     * API key for the geocoding service. Never leaves the server.
     *
     * @return string
     */
    public static function geocoder_key(): string {
        return (string) self::setting('geocoderkey');
    }

    /**
     * Site wide default country restriction for address lookups.
     *
     * @return string Comma separated lower case ISO 3166-1 alpha-2 codes.
     */
    public static function geocoder_countries(): string {
        return self::clean_countries((string) self::setting('geocodercountries'));
    }

    /**
     * Normalise a country restriction string.
     *
     * Validates the shape only, not membership of any country list: geocoders
     * recognise codes for territories that Moodle's own country list omits.
     *
     * @param string $countries
     * @return string
     */
    public static function clean_countries(string $countries): string {
        $codes = [];
        foreach (explode(',', strtolower($countries)) as $code) {
            $code = trim($code);
            if (preg_match('/^[a-z]{2}$/', $code) && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return implode(',', $codes);
    }

    /**
     * How long a geocoding result may be reused, in seconds.
     *
     * @return int
     */
    public static function cache_ttl(): int {
        return max(0, (int) self::setting('geocodercachettl', 30 * DAYSECS));
    }

    /**
     * Maximum outbound geocoding requests per second this site will make.
     *
     * @return float
     */
    public static function rate_limit(): float {
        return max(0.1, (float) self::setting('geocoderrate', 1));
    }

    /**
     * Basemap settings the browser needs.
     *
     * @return array
     */
    public static function tile_config(): array {
        $url = (string) self::setting('map_tileurl', self::DEFAULT_TILE_URL);
        $key = (string) self::setting('map_tilekey');
        if ($key !== '') {
            $url = str_replace('{key}', rawurlencode($key), $url);
        }

        return [
            'tileurl' => $url,
            'attribution' => (string) self::setting('map_tileattribution', self::DEFAULT_TILE_ATTRIBUTION),
            'maxzoom' => (int) self::setting('map_maxzoom', 19),
        ];
    }

    /**
     * Where a map centres when the field has no coordinates yet.
     *
     * @return array [lat, lng, zoom]; lat and lng are null for a world view.
     */
    public static function default_view(): array {
        $centre = trim((string) self::setting('map_defaultcentre'));
        $zoom = (int) self::setting('map_defaultzoom', self::WORLD_ZOOM);

        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $centre, $matches)) {
            $lat = (float) $matches[1];
            $lng = (float) $matches[2];
            if (abs($lat) <= 90 && abs($lng) <= 180) {
                return ['lat' => $lat, 'lng' => $lng, 'zoom' => $zoom];
            }
        }

        return ['lat' => null, 'lng' => null, 'zoom' => self::WORLD_ZOOM];
    }

    /**
     * The User-Agent this site identifies itself with.
     *
     * Both the Nominatim and the OSM tile usage policies require a unique
     * User-Agent that names the application and offers a contact, and reject
     * library defaults. This is one of the reasons geocoding cannot run in the
     * browser: User-Agent is a forbidden header for fetch().
     *
     * @return string
     */
    public static function user_agent(): string {
        global $CFG;

        $contact = (string) self::setting('map_contactemail', $CFG->supportemail ?? '');
        $identity = $CFG->wwwroot;
        if ($contact !== '') {
            $identity .= '; contact: ' . $contact;
        }

        return "Moodle-mod_datalynx/1.0 (+{$identity})";
    }

    /**
     * Whether any configured service is a free shared community endpoint.
     *
     * @return bool
     */
    public static function uses_shared_endpoint(): bool {
        $urls = [self::geocoder_url(), self::tile_config()['tileurl']];

        foreach ($urls as $url) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            foreach (self::SHARED_ENDPOINTS as $shared) {
                if ($host === $shared || str_ends_with($host, '.' . $shared)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Menu of geocoding engines for the admin settings page.
     *
     * @return array
     */
    public static function geocoder_menu(): array {
        return [
            self::GEOCODER_PHOTON => get_string('geocoder_photon', 'datalynx'),
            self::GEOCODER_NOMINATIM => get_string('geocoder_nominatim', 'datalynx'),
            self::GEOCODER_GOOGLE => get_string('geocoder_google', 'datalynx'),
            self::GEOCODER_NONE => get_string('geocoder_none', 'datalynx'),
        ];
    }
}
