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
 *
 * @package datalynxrule_eventnotification
 * @subpackage eventnotification
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['condition'] = 'The value the selected field has to have';
$string['condition_help'] = 'The value the selected field has to meet in order to be true.
For checkboxes the selected rows have to be entered in comma separated way: First row and third row will be: 1,3
For radio buttons, 1 for the first choice, 2 for the second.';
$string['emailtemplate'] = 'Email template';
$string['emailtemplate_help'] = 'Select an internal Email view to render the notification body. If no template is selected, the legacy notification message body is used.';
$string['emailtemplatenone'] = 'No template';
$string['errmatchradius'] = 'Enter a match radius of 0 or more kilometres. Leave it at 0 to use the radius configured on the itinerary field itself.';
$string['errmatchreload'] = 'Choose how this field should be matched. Use Reload to load the relations the selected field offers.';
$string['errmatchtolerance'] = 'Enter a tolerance greater than zero.';
$string['event'] = 'Datalynx event';
$string['matchauthors'] = 'Authors of matching entries';
$string['matchauthors_help'] = 'Tell the author of every matching entry about the entry that triggered the rule.';
$string['matchdedupe'] = 'Announce each pair only once';
$string['matchdedupe_help'] = 'Remember which entries have already been introduced to each other, so that saving an entry again does not notify the same people a second time. Switch this off to notify on every save.';
$string['matchdedupescope'] = 'Shared with';
$string['matchdedupescope_help'] = 'Leave empty to keep the record of announced pairs to this rule. Enter the same name in two rules - for instance in one rule that searches from the offer side and one that searches from the request side - to have them share it, so a pair is announced once between them instead of once each.';
$string['matchdirection'] = 'Direction';
$string['matchdirection_help'] = 'Which route has to be able to carry which. "Either" also matches when the entry that triggered the rule is the one doing the carrying, which is what a single rule covering both sides needs.';
$string['matchdirectioneither'] = 'either route can carry the other';
$string['matchdirectionforward'] = 'the matched route can carry this entry';
$string['matchdirectionreverse'] = 'this entry\'s route can carry the matched one';
$string['matchforgetstale'] = 'Forget pairs that stop matching';
$string['matchforgetstale_help'] = 'When a pair no longer matches - a departure moved, a route changed - forget that it was announced, so it counts as news again if it comes back.';
$string['matchingentries'] = 'Matching entries';
$string['matchingentries_help'] = 'Look for other entries that relate to the one the event touched, and notify the people behind both. Each row compares one field against the value the triggering entry holds for it. Note that an entry with no value at all for a field counts as having a different value.';
$string['matchmax'] = 'Most matches per save';
$string['matchmax_help'] = 'An upper bound on how many matching entries one save will announce, so a broad set of criteria cannot produce an unbounded number of notifications.';
$string['matchradius'] = 'Match radius (km)';
$string['matchradius_help'] = 'How far from the route a stop may lie and still count as on the way. Leave at 0 to use the radius configured on the itinerary field itself.';
$string['matchreldifferent'] = 'has a different value from this entry';
$string['matchrelroute'] = 'matches this entry\'s route';
$string['matchrelsame'] = 'has the same value as this entry';
$string['matchrelwithin'] = 'is within';
$string['matchrequireapproved'] = 'Only approved entries';
$string['matchsubjectauthor'] = 'This entry\'s author, once per matching entry';
$string['matchsubjectauthor_help'] = 'Send the author of the triggering entry one message per matching entry, each linking to that matching entry rather than to their own.';
$string['matchunitdays'] = 'days';
$string['matchunithours'] = 'hours';
$string['matchunitminutes'] = 'minutes';
$string['matchunitvalue'] = 'of the value';
$string['messagecontent'] = 'Field content that is included in the message';
$string['onlyonchange'] = 'Only trigger when these field values change';
$string['onlyonchange_help'] = 'Select any fields. The notification will only be sent when at least one of these fields actually changed its value during the triggering update — not just when the current value happens to match a condition. This applies independently of the trigger conditions above. Has no effect for "entry created" events.';
$string['pluginname'] = 'Event notification';
$string['privacy:metadata'] = 'Even notifications do not store personal data.';
$string['roleshelpinfo'] = 'Note: Recipients in the selected roles must have the corresponding notification capability (e.g., "mod/datalynx:notifyentryadded" for entry creation) enabled in their Moodle permissions to receive these notifications.';
$string['taskfindmatches'] = 'Find the entries matching a saved entry';
$string['triggerconditions'] = 'Trigger conditions';
$string['triggerconditions_help'] = 'Only send the notification when the entry that triggered the event matches these conditions. Leave empty to always send. Add several rows to combine them with AND/OR, and use NOT to negate a row. Each field offers the operators and value input that fit it (e.g. approval, single select or text).';
$string['triggerspecificevent'] = 'Trigger only if the selected field meets a condition';
