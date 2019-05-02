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
 * @subpackage number
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/text/field_class.php");

class datalynxfield_number extends datalynxfield_text {

    public $type = 'number';

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var boolean
     */
    protected $forfieldgroup = true;

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql($search) {
        global $DB;

        list($not, $operator, $value) = $search;

        static $i = 0;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        // For all NOT criteria except NOT Empty, exclude entries which don't meet the positive criterion.
        $excludeentries = (($not and $operator !== '') or (!$not and $operator === ''));

        if ($excludeentries) {
            $varcharcontent = $DB->sql_compare_text('content');
        } else {
            $varcharcontent = $this->get_sql_compare_text();
        }

        $params = [];
        switch ($operator) {
            case '=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                $paramname = "{$name}_$i";
                $params[$paramname] = trim($value[0]);
                $sql = " $varcharcontent $operator :$paramname ";
                break;
            case 'BETWEEN':
                $paramname = "{$name}_$i";
                $params["{$paramname}_l"] = floatval(trim($value[0]));
                $params["{$paramname}_u"] = floatval(trim($value[1]));
                $sql = " ($varcharcontent > :{$paramname}_l AND $varcharcontent < :{$paramname}_u) ";
                break;
            default:
                $sql = " 1 ";
                break;
        }

        if ($excludeentries) {
            // Get entry ids for entries that meet the criterion.
            if ($eids = $this->get_entry_ids_for_content($sql, $params)) {
                // Get NOT IN sql.
                list($notinids, $params) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                        "df_{$fieldid}_", false);
                $sql = " e.id $notinids ";
                return array($sql, $params, false);
            } else {
                return array('', '', '');
            }
        } else {
            return array($sql, $params, true);
        }
    }

    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $contents = array();
        $oldcontents = array();

        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        $value = reset($values);

        // We want to store empty numbers as well.
        $contents[] = $value;

        return array($contents, $oldcontents);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::parse_search()
     */
    public function parse_search($formdata, $i) {
        $values = array();

        $name = 'f_' . $i . '_' . $this->field->id;

        $data = isset($formdata->$name) ? $formdata->$name : optional_param_array($name, [], PARAM_RAW);
        if (empty($data)) {
            return false;
        }

        $field0 = isset($data[0]) ? $data[0] : '';
        if (!empty($field0) || "$field0" === "0") {
            $values[0] = $field0;
        }

        $field1 = isset($data[1]) ? $data[1] : '';
        if (!empty($field1) || "$field1" === "0") {
            $values[1] = $field1;
        }

        if (!empty($values)) {
            return $values;
        } else {
            return false;
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::format_search_value()
     */
    public function format_search_value($searchparams) {
        list($not, $operator, $value) = $searchparams;
        if (is_array($value)) {
            if (count($value) > 1 && $operator == 'BETWEEN') {
                $value = '(' . implode(',', $value) . ')';
            } else {
                $value = $value[0];
            }
        }
        return $not . ' ' . $operator . ' ' . $value;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_text::get_supported_search_operators()
     */
    public function get_supported_search_operators() {
        return array('' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                '>' => get_string('greater_than', 'datalynx'),
                '>=' => get_string('greater_equal', 'datalynx'),
                '<' => get_string('less_than', 'datalynx'), '<=' => get_string('less_equal', 'datalynx'),
                'BETWEEN' => get_string('between', 'datalynx'));
    }
}
