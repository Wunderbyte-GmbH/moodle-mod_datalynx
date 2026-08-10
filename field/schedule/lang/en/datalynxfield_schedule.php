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
 * Strings for the schedule field type.
 *
 * @package    datalynxfield_schedule
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['allowedmodes'] = 'Allowed patterns';
$string['anytime'] = 'any time';
$string['arrival'] = 'Arrival';
$string['askarrival'] = 'Ask for an arrival time';
$string['askarrival_help'] = 'Adds a second time control. The arrival is shown to readers but never used to match or to search - only the departure is.';
$string['departure'] = 'Departure';
$string['errnodate'] = 'Choose the date this happens on.';
$string['errnodays'] = 'Choose at least one weekday.';
$string['errnoschedule'] = 'Say when this happens.';
$string['erruntilbeforefrom'] = 'The end of the period is before its start.';
$string['everyweek'] = 'Every {$a}';
$string['granularity'] = 'Time steps (minutes)';
$string['granularity_help'] = 'How far apart the offered times of day are. Half an hour suits a ride board; five minutes suits a timetable.';
$string['hideexpired'] = 'Hide schedules that have run out';
$string['hideexpired_help'] = 'Leaves entries whose period has passed out of searches and out of matching. Switch it off on a board that keeps a history.';
$string['matchtolerance'] = 'Counts as the same journey within';
$string['matchtolerance_help'] = 'How far apart two departures may be and still be treated as the same journey. The whole day is the default on purpose: the point is to introduce two people who might travel together, and the exact hour is theirs to settle. Narrow it only on a board that really does run several departures a day.';
$string['mode'] = 'How often';
$string['modeboth'] = 'One-off and recurring';
$string['modeonce'] = 'Once';
$string['modeweekly'] = 'Every week';
$string['ondate'] = 'On';
$string['ondays'] = 'On these days';
$string['pluginname'] = 'Schedule';
$string['privacy:metadata'] = 'The schedule field does not store personal data.';
$string['runson'] = 'Runs on';
$string['searchdate'] = 'Travelling on';
$string['searchoverlaps'] = 'at the same time as this entry';
$string['searchtime'] = 'around';
$string['searchtolerance'] = 'give or take';
$string['toleranceday'] = 'anywhere that day';
$string['tolerancehours'] = '{$a} h';
$string['toleranceminutes'] = '{$a} min';
$string['untildate'] = 'until {$a}';
$string['validfrom'] = 'Runs from';
$string['validuntil'] = 'Runs until';
