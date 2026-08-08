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

$string['activebasemap'] = 'Grundkarte: {$a->url}';
$string['activegeocoder'] = 'Adresssuche: {$a->name} über {$a->url}';
$string['activeservices'] = 'Verwendete Kartendienste';
$string['address'] = 'Adresse / Ort';
$string['api_key'] = 'API Schlüssel (Key)';
$string['api_key_help'] = 'Erforderlich bei der Nutzung der Google Maps API oder spezieller Karten-Tiles.';
$string['api_url'] = 'Geocoding API Basis-URL';
$string['api_url_help'] = 'Basis-URL für Nominatim oder eigene Geocoding-Server.';
$string['changemapservices'] = 'Kartendienst-Einstellungen der Website ändern';
$string['clearlocation'] = 'Zurücksetzen';
$string['country_restriction'] = 'Ländereinschränkung (ISO Codes)';
$string['country_restriction_help'] = 'Kommagetrennte ISO-Ländercodes zur Einschränkung der Autocomplete-Ergebnisse (z. B. de,at,ch).';
$string['default_radius'] = 'Standard-Suchradius (km)';
$string['default_zoom'] = 'Standard Zoom-Stufe';
$string['display_address_only'] = 'Reiner Adress-Text';
$string['display_format'] = 'Anzeige-Format';
$string['display_map_mini'] = 'Adresse + Interaktive Mini-Karte';
$string['display_route_link'] = 'Adresse + Klickbarer Routen-Link';
$string['geolocating'] = 'Standort wird ermittelt…';
$string['geolocationdenied'] = 'Der Standort konnte nicht ermittelt werden.';
$string['geolocationunsupported'] = 'Dein Browser gibt den Standort nicht frei.';
$string['latitude'] = 'Breitengrad';
$string['locationsettings'] = 'Einstellungen des Ortsfeldes';
$string['longitude'] = 'Längengrad';
$string['map_provider'] = 'Karten- & Geocoding-Anbieter';
$string['map_provider_help'] = 'Wähle den Anbieter für interaktive Karten und Autocomplete-Vorschläge.';
$string['maploadfailed'] = 'Die Karte konnte nicht geladen werden.';
$string['no_location_selected'] = 'Kein Standort ausgewählt';
$string['nosuggestions'] = 'Keine passende Adresse gefunden';
$string['pickerhint'] = 'Adresse eingeben und einen Vorschlag auswählen oder in die Karte klicken, um die Markierung zu setzen.';
$string['pickerhintnogeocoder'] = 'Für diese Website ist kein Adresssuchdienst konfiguriert; die Adresse wird daher so gespeichert, wie sie eingegeben wird. Klicke in die Karte, um die Markierung zu setzen.';
$string['pickerhintsearch'] = 'Adresse eingeben und Eingabetaste drücken oder die Suchschaltfläche verwenden, dann einen Vorschlag auswählen. Du kannst auch in die Karte klicken, um die Markierung zu setzen.';
$string['pluginname'] = 'Ort / Standort (Karte & Geocoding)';
$string['privacy:metadata:datalynx_contents'] = 'Werte eines Ortsfeldes, die zu einem Datalynx-Eintrag gespeichert werden.';
$string['privacy:metadata:datalynx_contents:address'] = 'Der Adresstext des gewählten Standorts.';
$string['privacy:metadata:datalynx_contents:latitude'] = 'Der Breitengrad des gewählten Standorts.';
$string['privacy:metadata:datalynx_contents:longitude'] = 'Der Längengrad des gewählten Standorts.';
$string['privacy:metadata:geocoder'] = 'Um eine Adresse in Kartenkoordinaten umzuwandeln, wird der eingegebene Adresstext an den für diese Website konfigurierten Geocoding-Dienst gesendet. Die Anfrage stellt die Website selbst; deine IP-Adresse wird diesem Dienst daher nicht offengelegt.';
$string['privacy:metadata:geocoder:address'] = 'Der in ein Ortsfeld eingegebene Adresstext.';
$string['privacy:metadata:tileserver'] = 'Kartenbilder lädt dein Browser direkt vom für diese Website konfigurierten Grundkartendienst, der dadurch deine IP-Adresse erhält.';
$string['privacy:metadata:tileserver:ipaddress'] = 'Deine IP-Adresse, wie sie der Grundkartendienst sieht.';
$string['provider_google'] = 'Google Maps API';
$string['provider_osm'] = 'OpenStreetMap (Leaflet & Nominatim)';
$string['radiuskm'] = '{$a} km';
$string['search_radius_label'] = 'Suchradius (km)';
$string['searchaddress'] = 'Adresse suchen';
$string['select_on_map'] = 'Standort auf Karte wählen';
$string['use_current_location'] = 'Aktuellen Standort verwenden';
