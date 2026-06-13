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
 * Language strings for the fieldgroup field type.
 *
 * @package    datalynxfield_fieldgroup
 * @subpackage fieldgroup
 * @copyright  2018 michael pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['fieldformat_agg_avg'] = 'Average';
$string['fieldformat_agg_count'] = 'Count';
$string['fieldformat_agg_max'] = 'Maximum';
$string['fieldformat_agg_min'] = 'Minimum';
$string['fieldformat_agg_sum'] = 'Sum';
$string['fieldformat_aggregation'] = 'Aggregation';
$string['fieldformat_aggregation_help'] = 'How the values of each number subfield are combined across all lines of the fieldgroup. "Sum" adds the values, "Average" takes the mean, "Minimum"/"Maximum" take the smallest/largest value, and "Count" reports how many lines have a value.';
$string['fieldformat_decimals'] = 'Decimal places';
$string['fieldformat_decimals_help'] = 'Number of decimal places to display for the aggregated value. Leave empty to use each number subfield\'s own decimal setting.';
$string['fieldformat_label'] = 'Totals label';
$string['fieldformat_label_help'] = 'Text shown next to the totals row. Leave empty to use the default "Total".';
$string['fieldformat_total'] = 'Total';
$string['pluginname'] = 'Fieldgroup';
$string['privacy:metadata'] = 'Fieldgroups do not store personal data.';
