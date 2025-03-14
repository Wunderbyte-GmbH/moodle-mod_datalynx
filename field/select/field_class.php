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
 * @subpackage select
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . "/mod/datalynx/field/field_class.php");

class datalynxfield_select extends datalynxfield_option_single {

    public $type = 'select';

    /**
     * Can this field be used in fieldgroups?
     * @var boolean
     */
    protected $forfieldgroup = true;

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_sql_compare_text()
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.$column", 255);
    }

    /**
     *
     * {@inheritDoc}
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

    public function get_argument_count(string $operator) {
        if ($operator === "") { // "Empty" operator
            return 0;
        } else {
            return 1;
        }
    }
}
