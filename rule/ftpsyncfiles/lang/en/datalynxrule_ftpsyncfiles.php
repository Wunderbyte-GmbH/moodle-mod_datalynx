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
 * @package datalynxrule
 * @subpackage ftpsyncfiles
 * @copyright 2023 Thomas Winkler
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['event'] = 'Datalynx event';
$string['pluginname'] = 'FTP Sync Data';
$string['triggerspecificevent'] = 'Nur bei markierter Checkbox senden';

$string['sftpsettings'] = 'SFTP Settings';
$string['sftpserver'] = 'STFP Server';
$string['sftpusername'] = 'STFP Username';
$string['sftppassword'] = 'STFP Password';
$string['sftppath'] = 'STFP Path';
$string['sftpport'] = 'STFP Port';

$string['matchfields'] = 'Match imported data to the matching form fields';
$string['identifier'] = 'Identifier used in the filename to match a specific user.';
$string['teammemberfield'] = 'Form field to use for selecting the user the file is assigned to';
$string['manager'] = 'User who manages all entries';
$string['filefield'] = 'Field where the file should be saved';
$string['regex'] = 'Regular expression used to extract the identifier from the filename.
If left empty it defaults to /^(\d+)_/.';
