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

namespace datalynxfield_location\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;
use mod_datalynx\local\map\provider_config;

/**
 * Privacy provider implementation for datalynxfield_location.
 *
 * The field values themselves are stored in datalynx_contents and are exported
 * and deleted by mod_datalynx. What this provider has to declare is the part
 * mod_datalynx cannot know about: entering a location sends data to whichever
 * external map services the site has configured.
 *
 * @package    datalynxfield_location
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
            [
                'content' => 'privacy:metadata:datalynx_contents:address',
                'content1' => 'privacy:metadata:datalynx_contents:latitude',
                'content2' => 'privacy:metadata:datalynx_contents:longitude',
            ],
            'privacy:metadata:datalynx_contents'
        );

        // Address lookups are proxied by the site, so the geocoder receives the
        // text the user typed but not their IP address.
        if (provider_config::geocoder_engine() !== provider_config::GEOCODER_NONE) {
            $collection->add_external_location_link(
                'geocoder',
                ['address' => 'privacy:metadata:geocoder:address'],
                'privacy:metadata:geocoder'
            );
        }

        // Basemap tiles are the one map service the browser fetches itself, so
        // the tile operator does see the user's IP address.
        $collection->add_external_location_link(
            'tileserver',
            ['ipaddress' => 'privacy:metadata:tileserver:ipaddress'],
            'privacy:metadata:tileserver'
        );

        return $collection;
    }
}
