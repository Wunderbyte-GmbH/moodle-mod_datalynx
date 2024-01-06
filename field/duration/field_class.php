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
 * @subpackage duration
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");
require_once("$CFG->dirroot/mod/datalynx/field/number/field_class.php");

class datalynxfield_duration extends datalynxfield_base {

    /**
     * @var string
     */
    public $type = 'duration';

    protected $_units = null;

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var boolean
     */
    protected $forfieldgroup = true;

    /**
     * Returns time associative array of unit length.
     *
     * @return array unit length in seconds => string unit name.
     */
    public function get_units() {
        if (is_null($this->_units)) {
            $this->_units = array(604800 => get_string('weeks'), 86400 => get_string('days'),
                    3600 => get_string('hours'), 60 => get_string('minutes'), 1 => get_string('seconds')
            );
        }
        return $this->_units;
    }

    /**
     * Converts seconds to the best possible time unit.
     * for example
     * 1800 -> array(30, 60) = 30 minutes.
     *
     * @param int $seconds an amout of time in seconds.
     * @return array associative array ($number => $unit)
     */
    public function seconds_to_unit($seconds) {
        if ($seconds === 0) {
            return array(0, 1);
        }
        foreach ($this->get_units() as $unit => $notused) {
            if (fmod($seconds, $unit) == 0) {
                return array($seconds / $unit, $unit);
            }
        }
        return array($seconds, 1);
    }

    protected function get_sql_compare_text($column = 'content') {
        global $DB;
        return $DB->sql_cast_char2int("c{$this->field->id}.$column", true);
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
        $contents[] = $value;
        return array($contents, $oldcontents);
    }

    /**
     */
    public function parse_search($formdata, $i) {
        $values = array();

        $fromfield = optional_param_array('f_' . $i . '_' . $this->field->id . '_from',
                ['number' => ''], PARAM_RAW);
        $tofield = optional_param_array('f_' . $i . '_' . $this->field->id . '_to',
                ['number' => ''], PARAM_RAW);

        $fromfield = isset($formdata->{'f_' . $i . '_' . $this->field->id . '_from'}) ? $formdata->{'f_' .
        $i . '_' . $this->field->id . '_from'} : $fromfield['number'];
        $tofield = isset($formdata->{'f_' . $i . '_' . $this->field->id . '_to'}) ? $formdata->{'f_' .
        $i . '_' . $this->field->id . '_to'} : $tofield['number'];

        if (!empty($fromfield) || "$fromfield" === "0") {
            $values[0] = $fromfield;
        }

        if (!empty($tofield) || "$tofield" === "0") {
            $values[1] = $tofield;
        }

        if (!empty($values)) {
            return $values;
        } else {
            return false;
        }
    }


    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $data->{"field_{$fieldid}_{$entryid}"} = $csvrecord[$fieldname];
        }
        return true;
    }

    /**
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        global $DB;

        list($not, $operator, $value) = $search;

        static $i = 0;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        // For all NOT criteria except NOT Empty, exclude entries which don't meet the positive.
        // Criterion.
        $excludeentries = (($not && $operator !== '') || (!$not && $operator === ''));

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
                $sql = "$not $varcharcontent $operator :$paramname ";
                break;
            case 'BETWEEN':
                $paramname = "{$name}_$i";
                $params["{$paramname}_l"] = floatval(trim($value[0]));
                $params["{$paramname}_u"] = floatval(trim($value[1]));
                $sql = "$not ($varcharcontent > :{$paramname}_l AND $varcharcontent < :{$paramname}_u) ";
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

    /**
     */
    public function format_search_value($searchparams) {
        list($not, $operator, $value) = $searchparams;
        if (is_array($value)) {
            if (count($value) > 1) {
                $value = '(' . implode(',', $value) . ')';
            } else {
                $value = $value[0];
            }
        }
        return $not . ' ' . $operator . ' ' . $value;
    }

    public function get_supported_search_operators() {
        return array('' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                '>' => get_string('greater_than', 'datalynx'),
                '>=' => get_string('greater_equal', 'datalynx'),
                '<' => get_string('less_than', 'datalynx'), '<=' => get_string('less_equal', 'datalynx'),
                'BETWEEN' => get_string('between', 'datalynx'));
    }

    /**
     * Is $value a valid content or do we see an empty input?
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        if ($value == 0) {
            return true;
        }
        return false;
    }
}
