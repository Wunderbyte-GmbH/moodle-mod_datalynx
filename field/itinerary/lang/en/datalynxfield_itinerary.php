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
 * English language strings for datalynxfield_itinerary.
 *
 * @package    datalynxfield_itinerary
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activeservices'] = 'Map services in use';
$string['addwaypoint'] = 'Add a stop';
$string['default_zoom'] = 'Default zoom level';
$string['display_both'] = 'Map and list of stops';
$string['display_format'] = 'Display format';
$string['display_list'] = 'List of stops only';
$string['display_map'] = 'Map only';
$string['dropwaypoint'] = 'Remove this stop';
$string['durationhours'] = '{$a->hours} h {$a->minutes} min';
$string['durationminutes'] = '{$a} min';
$string['initialwaypoints'] = 'Stops shown initially';
$string['itinerarysettings'] = 'Itinerary field settings';
$string['journeylength'] = 'Approximately {$a} km';
$string['matchesroute'] = 'Route passes near';
$string['matchradius'] = 'Default match radius (km)';
$string['matchradius_help'] = 'How far from a route a pickup or drop-off may be and still count as a match. Used as the default in the search form; people searching can change it.';
$string['maxwaypoints'] = 'Maximum number of stops';
$string['maxwaypoints_help'] = 'An itinerary needs at least two stops to describe a journey. This sets the upper limit.';
$string['movedown'] = 'Move this stop later';
$string['moveup'] = 'Move this stop earlier';
$string['needtworoutepoints'] = 'Please give at least a start and a destination.';
$string['nowaypoints'] = 'No journey entered';
$string['pluginname'] = 'Itinerary (route with stops)';
$string['precision'] = 'Location precision for others';
$string['precision_approximate'] = 'Approximate until matched';
$string['precision_exact'] = 'Exact for everyone who can see the entry';
$string['precision_help'] = 'With "approximate", people browsing see stops rounded to roughly a kilometre, and exact positions only once they are the entry author or a confirmed passenger. Matching always uses the exact positions on the server either way.';
$string['privacy:metadata:datalynx_contents'] = 'Itinerary field values stored with a datalynx entry.';
$string['privacy:metadata:datalynx_contents:waypoints'] = 'The stops of the journey: a place name and its coordinates for each, and optionally a time. These describe the journey rather than the person travelling.';
$string['privacy:metadata:datalynx_waypoints'] = 'A derived copy of the itinerary coordinates, kept only so that journeys can be matched against each other efficiently. It contains nothing that is not already in the entry, and is rebuilt from it.';
$string['privacy:metadata:datalynx_waypoints:lat'] = 'Latitude of one stop.';
$string['privacy:metadata:datalynx_waypoints:lng'] = 'Longitude of one stop.';
$string['privacy:metadata:geocoder'] = 'To turn a place name into map coordinates, the text you type is sent to the geocoding service configured for this site. The request is made by the site itself, so your IP address is not disclosed to that service.';
$string['privacy:metadata:geocoder:address'] = 'The place name entered for a stop on an itinerary.';
$string['privacy:metadata:router'] = 'To work out the distance and travel time of a journey, its stops are sent to the routing service configured for this site. The request is made by the site itself, so your IP address is not disclosed to that service.';
$string['privacy:metadata:router:coordinates'] = 'The coordinates of the stops on an itinerary.';
$string['privacy:metadata:tileserver'] = 'Map images are loaded by your browser directly from the basemap service configured for this site, which therefore receives your IP address.';
$string['privacy:metadata:tileserver:ipaddress'] = 'Your IP address, as seen by the basemap service.';
$string['roaddistance'] = '{$a} km by road';
$string['routesummary'] = '{$a->distance} km, approx. {$a->duration}';
$string['searchfrom'] = 'Travelling from';
$string['searchradius'] = 'Within (km)';
$string['searchto'] = 'Travelling to';
$string['stopcount'] = '{$a->count} of at most {$a->max} stops';
$string['stopswithoutplace'] = '{$a} stop(s) have no place chosen yet and will not be saved. Pick a suggestion or click the map.';
$string['traveltime'] = 'Travel time approx. {$a}';
$string['waypoint'] = 'Stop {$a}';
$string['waypointaddress'] = 'Place';
$string['waypointlimitreached'] = 'This itinerary already has the maximum of {$a} stops.';
