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
 * @package datalynxfield_entryauthor
 * @subpackage entryauthor
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['pluginname'] = 'Entryauthor';
$string['privacy:metadata'] = 'The field entryauthor does not store personal data.';
$string['useremail'] = 'User email';
$string['userfirstname'] = 'User first name';
$string['userid'] = 'User id';
$string['useridnumber'] = 'User id number';
$string['userlastname'] = 'User last name';
$string['username'] = 'User name';
$string['userpicture'] = 'User picture';
$string['userusername'] = 'User username';
$string['userbadges'] = 'User badges';
$string['userinstitution'] = 'Institution';
$string['userdepartment'] = 'Department';
$string['userpicturelarge'] = 'Profile picture (large)';
$string['fieldformat_displayfield'] = 'Display attribute';
$string['fieldformat_displayfield_help'] = 'Select which piece of author information this format displays.
After saving, use ##author:formatname## in your Entry Template.
If you have existing templates with ##author:id##, an upgrade automatically creates a format named "id"
pointing to the User ID — you can rename or reconfigure it here.';
$string['fieldformat_description'] = 'Controls which attribute of the entry author is displayed.
Tip: use ##author:formatname## in the Entry Template.';
