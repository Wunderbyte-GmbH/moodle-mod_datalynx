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

use mod_datalynx\local\map\geocoder\google;
use mod_datalynx\local\map\geocoder\nominatim;
use mod_datalynx\local\map\geocoder\photon;

/**
 * Builds the geocoder the site is configured to use.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class geocoder_factory {
    /**
     * The configured geocoder, or null when geocoding is switched off.
     *
     * @param http_client|null $client Injectable for testing.
     * @return geocoder|null
     */
    public static function instance(?http_client $client = null): ?geocoder {
        $engine = provider_config::geocoder_engine();
        $url = provider_config::geocoder_url();
        $key = provider_config::geocoder_key();

        if ($engine === provider_config::GEOCODER_NONE || $url === '') {
            return null;
        }

        // The Google Geocoding API refuses every request without a key, so an
        // unconfigured key is the same as having no geocoder at all.
        if ($engine === provider_config::GEOCODER_GOOGLE && $key === '') {
            return null;
        }

        return match ($engine) {
            provider_config::GEOCODER_PHOTON => new photon($url, $key, $client),
            provider_config::GEOCODER_NOMINATIM => new nominatim($url, $key, $client),
            provider_config::GEOCODER_GOOGLE => new google($url, $key, $client),
            default => null,
        };
    }
}
