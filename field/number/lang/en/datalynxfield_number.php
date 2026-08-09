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
 * @package datalynxfield_number
 * @subpackage number
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['decimals'] = 'Decimals';
$string['errorslidernovalues'] = 'This configuration does not produce at least two selectable values. Widen the range or choose a smaller step size.';
$string['errorsliderrange'] = 'The end value must be greater than the start value.';
$string['errorsliderstep'] = 'The step size must be a number greater than 0.';
$string['errorslidertoomanyvalues'] = 'This configuration produces {$a} or more slider positions, which is too many. Choose a larger step size or a narrower range.';
$string['fieldformatdecimals'] = 'Decimal places';
$string['fieldformatdecimals_help'] = 'Number of decimal places to display for the number value. Leave empty to use the field\'s own decimal setting. For example, a value of 1 renders 3.5, while 0 renders 4.';
$string['fieldformatinputtype'] = 'Input in edit mode';
$string['fieldformatinputtype_help'] = 'How the field is presented on the entry form.

* **Number input** — the standard text box, unchanged.
* **Slider** — a slider the user drags; the selected value is written into the number field and saved as usual.

This setting only affects edit mode. In views the value is always printed as a number, using the decimal places configured above.';
$string['fieldformatinputtypeslider'] = 'Slider';
$string['fieldformatinputtypetext'] = 'Number input';
$string['fieldformatsliderend'] = 'End value';
$string['fieldformatsliderend_help'] = 'The value at the right-hand end of the slider. On the Fibonacci and 1-2-5 scales it is an upper limit rather than a selectable value: the highest position is the last value of the sequence that does not exceed it, so 0 to 50 on the Fibonacci scale ends at 34.';
$string['fieldformatsliderscale'] = 'Scale';
$string['fieldformatsliderscale_help'] = 'Which values the slider can be set to between the start and the end value.

* **Linear** — even steps of the step size configured below: 0, 1, 2, 3, ...
* **Fibonacci** — the start value followed by the Fibonacci numbers up to the end value: 0, 1, 2, 3, 5, 8, 13, 21, 34
* **1-2-5 series** — the start value followed by 1, 2, 5, 10, 20, 50, 100, ... up to the end value

The non-linear scales are useful when small values need to be distinguished precisely while large values only need a rough estimate. The slider always snaps to one of these values.';
$string['fieldformatsliderscalefibonacci'] = 'Fibonacci';
$string['fieldformatsliderscalelinear'] = 'Linear';
$string['fieldformatsliderscaleonetwofive'] = '1-2-5 series';
$string['fieldformatsliderstart'] = 'Start value';
$string['fieldformatsliderstart_help'] = 'The value at the left-hand end of the slider. It is always selectable, on every scale.';
$string['fieldformatsliderstep'] = 'Step size';
$string['fieldformatsliderstep_help'] = 'The distance between two slider positions on the linear scale, for example 1, 5 or 0.5. Ignored by the Fibonacci and 1-2-5 scales, which generate their own steps.';
$string['fieldformatsliderticks'] = 'Show start and end labels';
$string['fieldformatsliderticks_help'] = 'Prints the start and end value underneath the ends of the slider track, so users can see the available range without dragging.';
$string['fieldformatsliderunit'] = 'Unit';
$string['fieldformatsliderunit_help'] = 'Text appended to the value shown next to the slider, for example " km" or " %". Include the leading space if you want one. The unit is only a display aid — the stored value stays a plain number.';
$string['outputemptystring'] = 'When the field ist left empty: Output empty string instead of 0.';
$string['pluginname'] = 'Number';
$string['privacy:metadata'] = 'Numbers do not store personal data.';
