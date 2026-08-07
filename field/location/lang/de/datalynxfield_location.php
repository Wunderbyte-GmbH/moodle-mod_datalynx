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
 * German language strings for datalynxfield_location.
 *
 * @package    datalynxfield_location
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Ort / Standort (Karte & Geocoding)';
$string['privacy:metadata'] = 'Ortsfelder speichern Adressdaten sowie geografische Koordinaten (Breiten- und Längengrad).';

$string['map_provider'] = 'Karten- & Geocoding-Anbieter';
$string['map_provider_help'] = 'Wähle den Anbieter für interaktive Karten und Autocomplete-Vorschläge.';
$string['provider_osm'] = 'OpenStreetMap (Leaflet & Nominatim)';
$string['provider_google'] = 'Google Maps API';

$string['api_url'] = 'Geocoding API Basis-URL';
$string['api_url_help'] = 'Basis-URL für Nominatim oder eigene Geocoding-Server.';
$string['api_key'] = 'API Schlüssel (Key)';
$string['api_key_help'] = 'Erforderlich bei der Nutzung der Google Maps API oder spezieller Karten-Tiles.';

$string['default_zoom'] = 'Standard Zoom-Stufe';
$string['default_radius'] = 'Standard-Suchradius (km)';
$string['display_format'] = 'Anzeige-Format';
$string['display_address_only'] = 'Reiner Adress-Text';
$string['display_map_mini'] = 'Adresse + Interaktive Mini-Karte';
$string['display_route_link'] = 'Adresse + Klickbarer Routen-Link';
$string['country_restriction'] = 'Ländereinschränkung (ISO Codes)';
$string['country_restriction_help'] = 'Kommagetrennte ISO-Ländercodes zur Einschränkung der Autocomplete-Ergebnisse (z. B. de,at,ch).';

$string['address'] = 'Adresse / Ort';
$string['latitude'] = 'Breitengrad';
$string['longitude'] = 'Längengrad';
$string['search_radius_label'] = 'Suchradius (km)';
$string['use_current_location'] = 'Aktuellen Standort verwenden';
$string['select_on_map'] = 'Standort auf Karte wählen';
$string['no_location_selected'] = 'Kein Standort ausgewählt';
