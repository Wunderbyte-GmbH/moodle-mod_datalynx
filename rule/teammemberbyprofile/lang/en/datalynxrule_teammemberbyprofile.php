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
 * @package datalynxrule_teammemberbyprofile
 * @subpackage teammemberbyprofile
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['mode'] = 'Existing team members';
$string['mode_help'] = 'How to treat members already present in the team member field when the rule runs.

"Overwrite" replaces the field with exactly the matched users (members not matching the criteria are removed). "Add only" keeps the existing members and adds the matched ones.';
$string['mode_merge'] = 'Add only (keep existing members)';
$string['mode_overwrite'] = 'Overwrite (replace with matched users)';
$string['pluginname'] = 'Add team members by profile match';
$string['privacy:metadata'] = 'The "Add team members by profile match" rule only stores its own configuration; it stores no personal data.';
$string['privilege'] = 'Required privilege';
$string['privilege_guest'] = 'Guest';
$string['privilege_help'] = 'Only users holding the matching datalynx view privilege in this activity are considered as candidates to be added to the team.';
$string['privilege_manager'] = 'Manager';
$string['privilege_student'] = 'Student';
$string['privilege_teacher'] = 'Teacher';
$string['profilefield'] = 'User profile field';
$string['profilefield_help'] = 'The user profile field whose value is compared with the selected option label of the select field. Both standard fields (such as department or institution) and custom profile fields are available. The comparison ignores case and surrounding whitespace.';
$string['selectfield'] = 'Select field';
$string['selectfield_help'] = 'The single-choice select field of the entry whose selected option is matched against the chosen user profile field.';
$string['teammemberfield'] = 'Team member field';
$string['teammemberfield_help'] = 'The team member field that is populated with the matching users when an entry is created or updated.';
