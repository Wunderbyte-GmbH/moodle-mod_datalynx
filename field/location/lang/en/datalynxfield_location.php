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

/**
 * English language strings for datalynxfield_location.
 *
 * @package    datalynxfield_location
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Location (Map / Geocoding)';
$string['privacy:metadata'] = 'Location fields store address text and geographical coordinates (latitude and longitude).';

$string['map_provider'] = 'Map & Geocoding Provider';
$string['map_provider_help'] = 'Choose the provider for interactive maps and location autocomplete.';
$string['provider_osm'] = 'OpenStreetMap (Leaflet & Nominatim)';
$string['provider_google'] = 'Google Maps API';

$string['api_url'] = 'Geocoding API Base URL';
$string['api_url_help'] = 'Base URL for Nominatim or custom geocoding server.';
$string['api_key'] = 'API Key';
$string['api_key_help'] = 'API key required for Google Maps API or custom tile providers.';

$string['default_zoom'] = 'Default Zoom Level';
$string['default_radius'] = 'Default Search Radius (km)';
$string['display_format'] = 'Display Format';
$string['display_address_only'] = 'Address text only';
$string['display_map_mini'] = 'Address + Interactive Mini-Map';
$string['display_route_link'] = 'Address + Clickable Route Link';
$string['country_restriction'] = 'Country Restrictions (ISO Codes)';
$string['country_restriction_help'] = 'Comma-separated ISO country codes to restrict autocomplete results (e.g. de,at,ch).';

$string['address'] = 'Address / Location';
$string['latitude'] = 'Latitude';
$string['longitude'] = 'Longitude';
$string['search_radius_label'] = 'Search Radius (km)';
$string['use_current_location'] = 'Use Current Location';
$string['select_on_map'] = 'Select location on map';
$string['no_location_selected'] = 'No location selected';
