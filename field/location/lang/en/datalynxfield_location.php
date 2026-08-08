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

$string['activebasemap'] = 'Basemap: {$a->url}';
$string['activegeocoder'] = 'Address lookup: {$a->name} at {$a->url}';
$string['activeservices'] = 'Map services in use';
$string['address'] = 'Address / Location';
$string['api_key'] = 'API Key';
$string['api_key_help'] = 'API key required for Google Maps API or custom tile providers.';
$string['api_url'] = 'Geocoding API Base URL';
$string['api_url_help'] = 'Base URL for Nominatim or custom geocoding server.';
$string['changemapservices'] = 'Change the site map service settings';
$string['clearlocation'] = 'Clear';
$string['country_restriction'] = 'Country Restrictions (ISO Codes)';
$string['country_restriction_help'] = 'Comma-separated ISO country codes to restrict autocomplete results (e.g. de,at,ch).';
$string['default_radius'] = 'Default Search Radius (km)';
$string['default_zoom'] = 'Default Zoom Level';
$string['display_address_only'] = 'Address text only';
$string['display_format'] = 'Display Format';
$string['display_map_mini'] = 'Address + Interactive Mini-Map';
$string['display_route_link'] = 'Address + Clickable Route Link';
$string['geolocating'] = 'Locating…';
$string['geolocationdenied'] = 'Your location could not be determined.';
$string['geolocationunsupported'] = 'Your browser does not provide location access.';
$string['latitude'] = 'Latitude';
$string['locationsettings'] = 'Location field settings';
$string['longitude'] = 'Longitude';
$string['map_provider'] = 'Map & Geocoding Provider';
$string['map_provider_help'] = 'Choose the provider for interactive maps and location autocomplete.';
$string['maploadfailed'] = 'The map could not be loaded.';
$string['no_location_selected'] = 'No location selected';
$string['nosuggestions'] = 'No matching address found';
$string['pickerhint'] = 'Type an address and pick a suggestion, or click the map to drop the marker.';
$string['pickerhintnogeocoder'] = 'No address lookup service is configured on this site, so the address is stored as you type it. Click the map to set the marker.';
$string['pickerhintsearch'] = 'Type an address and press Enter or use the search button, then pick a suggestion. You can also click the map to drop the marker.';
$string['pluginname'] = 'Location (Map / Geocoding)';
$string['privacy:metadata:datalynx_contents'] = 'Location field values stored with a datalynx entry.';
$string['privacy:metadata:datalynx_contents:address'] = 'The address text of the chosen location.';
$string['privacy:metadata:datalynx_contents:latitude'] = 'The latitude of the chosen location.';
$string['privacy:metadata:datalynx_contents:longitude'] = 'The longitude of the chosen location.';
$string['privacy:metadata:geocoder'] = 'To turn an address into map coordinates, the address you type is sent to the geocoding service configured for this site. The request is made by the site itself, so your IP address is not disclosed to that service.';
$string['privacy:metadata:geocoder:address'] = 'The address text entered into a location field.';
$string['privacy:metadata:tileserver'] = 'Map images are loaded by your browser directly from the basemap service configured for this site, which therefore receives your IP address.';
$string['privacy:metadata:tileserver:ipaddress'] = 'Your IP address, as seen by the basemap service.';
$string['provider_google'] = 'Google Maps API';
$string['provider_osm'] = 'OpenStreetMap (Leaflet & Nominatim)';
$string['radiuskm'] = '{$a} km';
$string['search_radius_label'] = 'Search Radius (km)';
$string['searchaddress'] = 'Search address';
$string['select_on_map'] = 'Select location on map';
$string['use_current_location'] = 'Use Current Location';
