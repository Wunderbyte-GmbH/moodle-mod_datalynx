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
 * @package datalynxfield_teammemberselect
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['approval'] = 'Joining needs the author\'s approval';
$string['approval_help'] = 'Users ask to join; the author of the entry (or a manager) approves or declines each request. Approved users become members; the team size and the team size from field still apply. Without this setting users join directly.';
$string['approve'] = 'Approve';
$string['confirmed'] = 'Confirmed';
$string['decline'] = 'Decline';
$string['declined'] = 'Declined';
$string['detourfield'] = 'Detour field';
$string['detourfield_help'] = 'A number field of the entry holding how many km a pickup point may lie off the route. Without it, the match radius of the route field applies.';
$string['hasfreeplaces'] = 'has free places';
$string['ihaverequested'] = 'I have asked to join';
$string['leaveteam'] = 'Leave';
$string['limitbelowmembers'] = '{$a} people are already in the team of this entry; the value cannot be lower.';
$string['limitfield'] = 'Team size from field';
$string['limitfield_help'] = 'A number or select field of the same entry that sets the team size of each entry, for example the seats of a ride. The team size above stays the upper limit. When the field is empty, nobody can join. Users can then search for entries that have free places.';
$string['limitnone'] = 'None (the team size above for every entry)';
$string['nomember'] = 'This person is not a member.';
$string['norequest'] = 'There is no open request of this person.';
$string['notadmissible'] = 'You cannot join this team.';
$string['nounsubscription'] = 'You cannot leave this team.';
$string['openrequests'] = 'Requests';
$string['pickupaddress'] = 'Where should you be picked up?';
$string['pickupat'] = 'Pickup: {$a}';
$string['pickupfield'] = 'Pickup point into route field';
$string['pickupfield_help'] = 'A request to join asks for the pickup point of the user. It must lie within the detour around the route. When the request is approved, the pickup point becomes a stop of this route, bound to the user, and the route is calculated again; when the user leaves, the stop goes.';
$string['pickupnotfound'] = 'The pickup point could not be found. Please pick it from the suggestions.';
$string['pickupoutside'] = 'The pickup point lies outside the detour of {$a} km the driver accepts.';
$string['pickuprequired'] = 'Please give your pickup point.';
$string['pluginname'] = 'Team member select';
$string['privacy:metadata'] = 'The team member select field stores no data of its own. The user IDs of the selected team members are part of the entry content, which mod_datalynx discovers, exports and removes.';
$string['protectmembers'] = 'Entries with members can only be deleted by managers';
$string['protectmembers_help'] = 'An entry whose team has members cannot be deleted by its author, only by those who manage entries. For example a ride with confirmed passengers is cancelled rather than deleted.';
$string['remove'] = 'Remove';
$string['requestdeclined'] = 'Your request was declined; you cannot ask again.';
$string['requested'] = 'Requested';
$string['requestjoin'] = 'Ask to join';
$string['requestlabel'] = 'Label of the request button';
$string['requestlabel_help'] = 'The text of the button that asks to join when joining needs the author\'s approval, for example "Request a seat". Empty for the default.';
$string['teamfull'] = 'No free places';
$string['teammembersnotoffered'] = 'These users are no longer available for selection here, e.g. because they are no longer enrolled: {$a}. Saving the entry removes them from the team, and with it the access the team gives them.';
$string['teammembersremoved'] = 'Removed from the team in "{$a->field}" because they are no longer available for selection: {$a->users}.';
$string['teamoptionsfieldgroup'] = 'This field is a subfield of a field group. There it is a plain list of people: the options "user can add self", "notify team members", "allow unsubscription", the team field and "team members can edit the entry" have no effect, nor does making a field visible to its team members.';
$string['teamsubscriptionfieldgroup'] = 'You cannot add or remove yourself here: in a field group this field is a plain list of people, not a team.';
$string['withdrawrequest'] = 'Withdraw request';
