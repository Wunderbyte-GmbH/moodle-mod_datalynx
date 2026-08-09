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
 * @package datalynxfield_radiobutton
 * @subpackage radiobutton
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['fieldformatoption'] = 'Display format';
$string['fieldformatoption_help'] = 'What is printed in views in place of the tag "[[fieldname:formatname]]".

"The choices" are the options you defined for this field itself, under *Manage > Fields > (your radio button field) > Options* — one per line. Each option has a position (1 for the first line, 2 for the second, and so on); that position is what an entry actually stores.

Take a field with the options *Draft*, *In review* and *Approved*, in an entry where *In review* is selected:

* **Label only** — "In review". The label of the selected option, which is what you want in almost every view.
* **Key-value pairs** — "0 Draft,1 In review,0 Approved". Every option in order, each prefixed with 1 if it is the selected one and 0 if it is not, separated by commas. Useful for exports and for CSS or JavaScript that has to know about the options that were *not* chosen.
* **Key/index** — "2". Only the stored position of the selected option, without the label. Useful for exports and for comparing values across views. Nothing is printed when no option is selected.
* **Stepper progress** — a horizontal row of steps, one per option, with every step up to and including the selected one marked as completed: *Draft* and *In review* filled in, *Approved* still open. Turns a status field into a progress indicator. Unlike the other choices this one is also shown when the entry has no value yet, with no step completed.

Note that the position, not the label, is stored: if you later reorder or rename the options, existing entries keep their number and therefore change meaning.';
$string['fieldformatoptiondefault'] = 'Label only';
$string['fieldformatoptionkey'] = 'Selected option key/index';
$string['fieldformatoptionoptions'] = 'Key-value pairs';
$string['fieldformatoptionstepper'] = 'Stepper progress';
$string['fieldformatoptionsteppericons'] = 'Stepper icons';
$string['fieldformatoptionsteppericons_help'] = 'A comma-separated list of FontAwesome icon classes (e.g. "shopping-cart, cogs, medal, car, home") to display inside each step\'s circle, one per option in the order the options are defined. If empty or missing, step numbers will be used.';
$string['fieldformatscope'] = 'This format changes how the field is shown <strong>in views (display mode) only</strong>. On the entry form the field is always a group of radio buttons, one per option defined for the field — a format cannot change that, and the stepper in particular is never clickable.';
$string['pluginname'] = 'Radio button';
$string['privacy:metadata'] = 'Radiobuttons do not store personal data.';
