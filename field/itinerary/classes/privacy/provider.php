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

namespace datalynxfield_itinerary\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use mod_datalynx\local\map\provider_config;

/**
 * Privacy provider implementation for datalynxfield_itinerary.
 *
 * The field values live in `datalynx_contents` and are exported and deleted by
 * mod_datalynx. What this provider declares is the derived index table, and the
 * external services an address lookup reaches.
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements metadata_provider {
    /**
     * Describe the data this field type handles and where it is sent.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'datalynx_contents',
            ['content' => 'privacy:metadata:datalynx_contents:waypoints'],
            'privacy:metadata:datalynx_contents'
        );

        $collection->add_database_table(
            'datalynx_waypoints',
            [
                'lat' => 'privacy:metadata:datalynx_waypoints:lat',
                'lng' => 'privacy:metadata:datalynx_waypoints:lng',
            ],
            'privacy:metadata:datalynx_waypoints'
        );

        // Same external transfers as the location field: the address text goes to
        // the geocoder through this site, and the browser fetches map tiles itself.
        if (provider_config::geocoder_engine() !== provider_config::GEOCODER_NONE) {
            $collection->add_external_location_link(
                'geocoder',
                ['address' => 'privacy:metadata:geocoder:address'],
                'privacy:metadata:geocoder'
            );
        }

        // The stops of a journey are sent to the routing service to work out the
        // travel time. As with the geocoder the site makes the request, so the
        // service never sees the traveller's IP address.
        if (provider_config::router_engine() !== provider_config::ROUTER_NONE) {
            $collection->add_external_location_link(
                'router',
                ['coordinates' => 'privacy:metadata:router:coordinates'],
                'privacy:metadata:router'
            );
        }

        $collection->add_external_location_link(
            'tileserver',
            ['ipaddress' => 'privacy:metadata:tileserver:ipaddress'],
            'privacy:metadata:tileserver'
        );

        return $collection;
    }
}
