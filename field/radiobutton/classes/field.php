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
namespace datalynxfield_radiobutton;
use datalynxfield_select\field as SelectField;

/**
 * Field class for the radiobutton field type.
 *
 * @package datalynxfield_radiobutton
 */
class field extends SelectField {
    /** @var string The field type. */
    public $type = 'radiobutton';

    /**
     * Can this field be used in fieldgroups?
     * Radiobuttons don't pass form data if nothing is selected.
     * @var bool
     */
    protected $forfieldgroup = false;

    /**
     * @var array The separators for the field options.
     */
    public $separators = [['name' => 'New line', 'chr' => '<br />'],
            ['name' => 'Space', 'chr' => '&#32;'],
            ['name' => ',', 'chr' => '&#44;'],
            ['name' => ', (with space)', 'chr' => '&#44;&#32;'],
    ];
}
