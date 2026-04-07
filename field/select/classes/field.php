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
 * @package datalynxfield_select
 * @subpackage select
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_select;

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\field\datalynxfield_option_single;

/**
 * Field class for the select field type.
 *
 * @package datalynxfield_select
 */
class field extends datalynxfield_option_single {
    /** @var string The field type. */
    public $type = 'select';

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     *
     * {@inheritDoc}
     * @param string $column The database column name.
     * @return string
     * @see datalynxfield_base::get_sql_compare_text()
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.$column", 255);
    }

    /**
     *
     * {@inheritDoc}
     * @param mixed $value The search value to look up.
     * @return mixed The corresponding option key or empty string.
     * @see datalynxfield_base::get_search_value()
     */
    public function get_search_value($value) {
        $options = $this->options_menu();
        if ($key = array_search($value, $options)) {
            return $key;
        } else {
            return '';
        }
    }

    /**
     * Returns the number of arguments required by the given operator.
     *
     * @param string $operator The operator.
     * @return int
     */
    public function get_argument_count(string $operator) {
        if ($operator === "") { // Empty operator.
            return 0;
        } else {
            return 1;
        }
    }
}
