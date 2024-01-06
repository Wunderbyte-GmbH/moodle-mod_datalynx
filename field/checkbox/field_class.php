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
 * @package datalynxfield
 * @subpackage checkbox
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/multiselect/field_class.php");

class datalynxfield_checkbox extends datalynxfield_multiselect {

    /**
     * @var string
     */
    public $type = 'checkbox';

    /**
     * Can this field be used in fieldgroups?
     * @var boolean
     */
    protected $forfieldgroup = true;

    /**
     * Create an array that converts default values from the database to values the checkbox understands.
     * Assume three checkboxes a, b, c. Default should be b, c. In the database we store \n separated values.
     * $rawdefaults = 1b, 2c / $options = 1a, 2b, 3c / $defaults 2b, 3c
     * Checkbox reads defaults from array that is based on position and has value. Starts at one.
     * {@inheritDoc}
     * @see datalynxfield_multiselect::default_values()
     */
    public function default_values() {
        $rawdefaults = explode("\n", $this->field->param2); // Create array of default values.
        $rawdefaults = array_map('trim', $rawdefaults); // Trim random spaces (1b, 2c).
        $options = array_map('trim', $this->options_menu()); // Read available checkboxes (1a, 2b, 3c).
        $defaults = array(); // Should become 2b, 3c.

        foreach ($options as $key => $value) {
            if (in_array($value, $rawdefaults)) {
                $defaults[$key] = $value; // Create default checkbox values.
            }
        }

        return $defaults;
    }

    /**
     * Is $value a valid content or do we see an empty input?
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        // The array is always passed, check every value.
        foreach ($value as $val) {
            if ($val) {
                return false;
            }
        }

        return true;
    }
}
