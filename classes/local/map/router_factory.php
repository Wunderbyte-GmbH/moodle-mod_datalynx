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

use mod_datalynx\local\map\router\google;
use mod_datalynx\local\map\router\osrm;

/**
 * Builds the routing engine the site is configured to use.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class router_factory {
    /**
     * The configured router, or null when routing is switched off.
     *
     * @param http_client|null $client Injectable for testing.
     * @return router|null
     */
    public static function instance(?http_client $client = null): ?router {
        $engine = provider_config::router_engine();
        $url = provider_config::router_url();
        $key = provider_config::router_key();

        if ($engine === provider_config::ROUTER_NONE || $url === '') {
            return null;
        }

        // The Directions API refuses every request without a key, so an
        // unconfigured key is the same as having no router at all.
        if ($engine === provider_config::ROUTER_GOOGLE && $key === '') {
            return null;
        }

        return match ($engine) {
            provider_config::ROUTER_OSRM => new osrm($url, $key, $client),
            provider_config::ROUTER_GOOGLE => new google($url, $key, $client),
            default => null,
        };
    }
}
