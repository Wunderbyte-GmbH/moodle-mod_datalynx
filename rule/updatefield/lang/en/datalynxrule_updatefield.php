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
 * Strings for component 'datalynxrule_updatefield', language 'en'.
 *
 * @package datalynxrule_updatefield
 * @copyright 2026 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Field update';
$string['err_nofield'] = 'Please choose a field to update.';
$string['err_novalue'] = 'Please provide the value to set.';
$string['newvalue'] = 'New value';
$string['newvalue_help'] = 'The value that will be written to the selected field when the rule is triggered. For dropdown, radio button and checkbox fields, choose from the field\'s options; for text fields, type the value.';
$string['pluginname'] = 'Update field';
$string['privacy:metadata'] = 'The datalynxrule_updatefield plugin does not store any personal data.';
$string['targetfield'] = 'Field to update';
$string['targetfield_help'] = 'The field whose value is set when the rule is triggered. Only text, dropdown (select/radio button) and checkbox/multiselect fields can be updated.';
$string['triggerfollowupevents'] = 'Trigger follow-up events';
$string['triggerfollowupevents_help'] = 'When enabled, an "entry updated" event is fired after the value changes, so other rules and notifications can react to it. The update is skipped silently when the value did not actually change, and a re-entrancy guard prevents loops. Leave disabled (the default) for a completely silent update.';
