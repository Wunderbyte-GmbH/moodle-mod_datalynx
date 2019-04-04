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
 * @subpackage _time
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield__time extends datalynxfield_no_content {

    public $type = '_time';

    const _TIMECREATED = 'timecreated';

    const _TIMEMODIFIED = 'timemodified';

    /**
     */
    public static function is_internal() {
        return true;
    }

    /**
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = array();

        $fieldobjects[self::_TIMECREATED] = (object) array('id' => self::_TIMECREATED,
                'dataid' => $dataid, 'type' => '_time', 'name' => get_string('timecreated', 'datalynx'),
                'description' => '', 'visible' => 2, 'internalname' => 'timecreated');

        $fieldobjects[self::_TIMEMODIFIED] = (object) array('id' => self::_TIMEMODIFIED,
                'dataid' => $dataid, 'type' => '_time', 'name' => get_string('timemodified', 'datalynx'),
                'description' => '', 'visible' => 2, 'internalname' => 'timemodified');

        return $fieldobjects;
    }

    /**
     */
    public function get_internalname() {
        return $this->field->internalname;
    }

    /**
     */
    public function parse_search($formdata, $i) {
        $time = array();

        if (!empty($formdata->{'f_' . $i . '_' . $this->field->id . '_from'})) {
            $time[0] = $formdata->{'f_' . $i . '_' . $this->field->id . '_from'};
        }

        if (!empty($formdata->{'f_' . $i . '_' . $this->field->id . '_to'})) {
            $time[1] = $formdata->{'f_' . $i . '_' . $this->field->id . '_to'};
        }

        if (!empty($time)) {
            return $time;
        } else {
            return false;
        }
    }

    /**
     */
    public function get_search_sql($search) {
        list($not, $operator, $value) = $search;

        if (is_array($value)) {
            $from = $value[0];
            $to = $value[1];
        } else {
            $from = 0;
            $to = 0;
        }

        static $i = 0;
        $i++;
        $namefrom = "df__time_{$i}_from";
        $nameto = "df__time_{$i}_to";
        $varcharcontent = $this->get_sql_compare_text();
        $params = array();

        if ($operator != 'BETWEEN') {
            if (!$operator or $operator == 'LIKE') {
                $operator = '=';
            }
            $params[$namefrom] = $from;
            return array(" $not $varcharcontent $operator :$namefrom ", $params, false
            );
        } else {
            $params[$namefrom] = $from;
            $params[$nameto] = $to;
            return array(" ($not $varcharcontent >= :$namefrom AND $varcharcontent <= :$nameto) ",
                    $params, false
            );
        }
    }

    /**
     */
    protected function get_sql_compare_text($column = 'content') {
        global $DB;

        return $DB->sql_compare_text("e.{$this->field->internalname}");
    }

    /**
     */
    public function get_sort_sql() {
        return 'e.' . $this->field->internalname;
    }

    /**
     * returns an array of distinct content of the field
     */
    public function get_distinct_content($sortdir = 0) {
        global $DB;

        $sortdir = $sortdir ? 'DESC' : 'ASC';
        $contentfull = $this->get_sort_sql();

        $sql = "SELECT DISTINCT $contentfull
                    FROM {datalynx_entries} e
                    WHERE $contentfull IS NOT NULL
                    ORDER BY $contentfull $sortdir";

        $distinctvalues = array();
        if ($options = $DB->get_records_sql($sql)) {
            foreach ($options as $data) {
                $value = $data->{$this->field->internalname};
                if ($value === '') {
                    continue;
                }
                $distinctvalues[] = $value;
            }
        }
        return $distinctvalues;
    }

    /**
     */
    public function format_search_value($searchparams) {
        list($not, $operator, $value) = $searchparams;
        if (is_array($value)) {
            $from = userdate($value[0]);
            $to = userdate($value[1]);
        } else {
            $from = userdate(time());
            $to = userdate(time());
        }
        if ($operator != 'BETWEEN') {
            return $not . ' ' . $operator . ' ' . $from;
        } else {
            return $not . ' ' . $operator . ' ' . $from . ' and ' . $to;
        }
    }

    public function get_supported_search_operators() {
        return array('' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                '>' => get_string('after', 'datalynx'), '<' => get_string('before', 'datalynx'),
                'BETWEEN' => get_string('between', 'datalynx'));
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
