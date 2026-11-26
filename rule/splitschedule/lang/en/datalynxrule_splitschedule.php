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
 * Strings for component 'datalynxrule_splitschedule', language 'en'.
 *
 * @package    datalynxrule_splitschedule
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Split';
$string['errnofield'] = 'Please choose a schedule field.';
$string['maxmonths'] = 'At most months ahead';
$string['maxmonths_help'] = 'Entries are made for the dates up to this many months from today, and never beyond the end date of the schedule.';
$string['pluginname'] = 'Split a weekly schedule into dated entries';
$string['privacy:metadata'] = 'The datalynxrule_splitschedule plugin does not store any personal data. The entries it makes belong to the author of the entry they are made from.';
$string['schedulefield'] = 'Schedule field';
$string['schedulefield_help'] = 'When an entry whose schedule is weekly triggers the rule, it becomes the entry of the first date, and a copy is made for every further date: same author, same contents (team members excepted), each with a one-off schedule on its date. Each copy fires "entry created", so other rules run for every date. Entries with a one-off schedule are left alone.';
