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
 * English language strings for datalynxrule_ridematch.
 *
 * @package    datalynxrule_ridematch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['itineraryfield'] = 'Itinerary field';
$string['itineraryfield_help'] = 'The itinerary field holding the journey. Both ride offers and ride requests must use the same field.';
$string['messagebody'] = 'Someone in "{$a->datalynxname}" is travelling a route that fits yours.

Your journey: {$a->yourroute}
Their journey: {$a->matchedroute}

Open their entry to see the details and get in touch: {$a->url}';
$string['messagebodyhtml'] = '<p>Someone in <strong>{$a->datalynxname}</strong> is travelling a route that fits yours.</p><ul><li>Your journey: {$a->yourroute}</li><li>Their journey: {$a->matchedroute}</li></ul><p><a href="{$a->url}">Open their entry</a> to see the details and get in touch.</p>';
$string['messageprovider:ridematch'] = 'Notification that a ride offer and a ride request match';
$string['messagesubjectoffer'] = 'Someone is looking for a ride along your route';
$string['messagesubjectrequest'] = 'Someone is offering a ride along your route';
$string['offervalue'] = 'Value meaning "offering a ride"';
$string['offervalue_help'] = 'Which option of the ride type field means "I am offering a ride". Enter the option label exactly as it appears in the field settings, for example "Offer a ride". Select and radio button fields store an option\'s position rather than its label, so the position (1, 2, ...) is accepted too.';
$string['pluginname'] = 'Notify matching ride offers and requests';
$string['privacy:metadata:core_message'] = 'The rule sends a notification to both entry authors when their journeys match.';
$string['privacy:metadata:datalynx_ride_matches'] = 'A note of which pairs of ride entries have already been announced, so that nobody is notified about the same match twice.';
$string['privacy:metadata:datalynx_ride_matches:offerentryid'] = 'The entry offering a ride.';
$string['privacy:metadata:datalynx_ride_matches:requestentryid'] = 'The entry looking for a ride.';
$string['privacy:metadata:datalynx_ride_matches:timenotified'] = 'When the two authors were told about each other.';
$string['radiuskmlabel'] = 'Match radius (km)';
$string['radiuskmlabel_help'] = 'How far from a route a start or destination may be and still count as a match. Leave empty to use the itinerary field\'s own default.';
$string['requestvalue'] = 'Value meaning "looking for a ride"';
$string['requestvalue_help'] = 'Which option of the ride type field means "I am looking for a ride". Enter the option label exactly as it appears in the field settings, or its position (1, 2, ...).';
$string['ridetypefield'] = 'Ride type field';
$string['ridetypefield_help'] = 'The radio button or select field that says whether an entry offers a ride or is looking for one. The rule always searches the opposite kind.';
$string['routesummary'] = '{$a->from} to {$a->to}';
$string['ruleintro'] = 'When a ride entry is saved, this rule looks for entries of the opposite kind whose journey overlaps, and notifies both authors about each new match. A pair is announced only once, so editing an entry does not notify the same people again. Matching runs in the background, so notifications arrive with the next cron run rather than instantly.';
$string['taskfindmatches'] = 'Find matching ride offers and requests';
$string['timetolerance'] = 'Departure tolerance (hours)';
$string['timetolerance_help'] = 'How far apart two departure times may be and still count as the same journey. Entries without a time are never excluded on time.';
$string['valuesmustdiffer'] = 'The offer and request values have to be different, otherwise every entry would match its own kind.';
$string['viewmatchedride'] = 'View the matching ride';
