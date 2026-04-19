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
 * @package datalynxfield_gradeitem
 * @subpackage gradeitem
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_gradeitem;

use mod_datalynx\local\field\datalynxfield_base;

/**
 * Datalynx gradeitem field class.
 */
class field extends datalynxfield_base {
    /** @var string Plugin type. */
    public $type = 'gradeitem';

    /** @var int Grade item ID. */
    public $itemid;

    /** @var string Grade item name. */
    public $itemname;

    /** @var string Grade item type. */
    public $itemtype;

    /** @var string Grade item module. */
    public $itemmodule;

    /** @var int Grade item instance. */
    public $iteminstance;

    /**
     * Constructor.
     *
     * @param int|object $df Datalynx object or ID.
     * @param int|object $field Field object or ID.
     */
    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        $this->itemid = $this->field->param1;
        $this->itemname = $this->field->param2;
        $this->itemtype = $this->field->param3;
        $this->itemmodule = $this->field->param4;
        $this->iteminstance = $this->field->param5;
    }

    /**
     * Sets up a field object
     *
     * @param mixed $forminput Form input data or null.
     * @return void
     */
    public function set_field($forminput = null) {
        global $DB;

        $itemid = !empty($this->field->param1) ? $this->field->param1 : null;
        parent::set_field($forminput);

        if ($this->field->param1 && ($this->field->param1 != $itemid || !$this->field->param2)) {
            $gradeiteminfo = 'id,itemname,itemtype,itemmodule,iteminstance';
            if ($item = $DB->get_record('grade_items', ['id' => $this->field->param1], $gradeiteminfo)) {
                $this->field->param2 = $item->itemname;
                $this->field->param3 = $item->itemtype;
                $this->field->param4 = $item->itemmodule;
                $this->field->param5 = $item->iteminstance;
            }
        }
    }

    /**
     * Get the SQL for selecting the field content.
     *
     * @return string The SQL.
     */
    public function get_select_sql() {
        $id = " c{$this->field->id}.id AS c{$this->field->id}_id ";
        $content = $this->get_sql_compare_text() . " AS c{$this->field->id}_content";
        return " $id , $content ";
    }

    /**
     * Get the SQL for comparing text.
     *
     * @param string $column The column name.
     * @return string The SQL.
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.finalgrade");
    }

    /**
     * Get the SQL for sorting by this field.
     *
     * @param string $paramname The parameter name.
     * @param string $paramcount The parameter count.
     * @return ?array The SQL and parameters.
     */
    public function get_sort_from_sql($paramname = 'sortie', $paramcount = '') {
        $fieldid = $this->field->id;
        if (is_numeric($fieldid) && $fieldid > 0) {
            $sql = " LEFT JOIN {grade_grades} c$fieldid ON (c$fieldid.userid = e.userid AND
                    c$fieldid.itemid = :$paramname$paramcount) ";
            return [$sql, $this->itemid];
        } else {
            return null;
        }
    }

    /**
     * Get the SQL for searching this field.
     *
     * @return string The SQL.
     */
    public function get_search_from_sql() {
        $fieldid = $this->field->id;
        if (is_numeric($fieldid) && $fieldid > 0) {
            return " JOIN {grade_grades} c$fieldid ON c$fieldid.userid = e.userid ";
        } else {
            return '';
        }
    }

    /**
     * Is this field a datalynx content field?
     *
     * @return bool True if it is.
     */
    public function is_datalynx_content() {
        return false;
    }
}
