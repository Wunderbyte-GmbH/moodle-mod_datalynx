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
 * @package datalynxfield_entrytime
 * @subpackage _time
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['fieldformat_dateformat'] = 'Date format';
$string['fieldformat_dateformat_examples'] = '<details class="mt-2 mb-3">
  <summary class="text-info" style="cursor: pointer;">Common strftime format examples (Click to expand)</summary>
  <table class="table table-sm table-bordered mt-2">
    <thead><tr><th>Format string</th><th>Example output</th><th>Description</th></tr></thead>
    <tbody>
      <tr><td><code>%d.%m.%Y</code></td><td>06.06.2026</td><td>Day.Month.Year (German style)</td></tr>
      <tr><td><code>%Y-%m-%d</code></td><td>2026-06-06</td><td>ISO 8601 date</td></tr>
      <tr><td><code>%d/%m/%Y %H:%M</code></td><td>06/06/2026 14:30</td><td>Date and time</td></tr>
      <tr><td><code>%H:%M</code></td><td>14:30</td><td>Time only (24-hour)</td></tr>
      <tr><td><code>%I:%M %p</code></td><td>02:30 PM</td><td>Time only (12-hour)</td></tr>
      <tr><td><code>%A, %d %B %Y</code></td><td>Saturday, 06 June 2026</td><td>Long date</td></tr>
      <tr><td><code>%b %Y</code></td><td>Jun 2026</td><td>Month and year</td></tr>
      <tr><td><code>%V/%Y</code></td><td>23/2026</td><td>Week number/Year</td></tr>
      <tr><td><code>timestamp</code></td><td>1749254400</td><td>Raw Unix timestamp</td></tr>
    </tbody>
  </table>
</details>';
$string['fieldformat_dateformat_help'] = 'Enter a PHP strftime format string to control how the date/time is shown. Enter "timestamp" to output a raw Unix integer. Leave empty to use Moodle\'s default date-time format.';
$string['fieldformat_desc'] = 'Controls display of entry timestamps. Specify the Date format below.';
$string['pluginname'] = 'Datalynx Time Field';
