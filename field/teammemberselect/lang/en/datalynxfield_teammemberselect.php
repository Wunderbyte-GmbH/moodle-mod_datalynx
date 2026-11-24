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

$string['pluginname'] = 'Team member select';
$string['privacy:metadata'] = 'The team member select field stores no data of its own. The user IDs of the selected team members are part of the entry content, which mod_datalynx discovers, exports and removes.';
$string['teammembersnotoffered'] = 'These users are no longer available for selection here, e.g. because they are no longer enrolled: {$a}. Saving the entry removes them from the team, and with it the access the team gives them.';
$string['teammembersremoved'] = 'Removed from the team in "{$a->field}" because they are no longer available for selection: {$a->users}.';
$string['teamoptionsfieldgroup'] = 'This field is a subfield of a field group. There it is a plain list of people: the options "user can add self", "notify team members", "allow unsubscription", the team field and "team members can edit the entry" have no effect, nor does making a field visible to its team members.';
$string['teamsubscriptionfieldgroup'] = 'You cannot add or remove yourself here: in a field group this field is a plain list of people, not a team.';
