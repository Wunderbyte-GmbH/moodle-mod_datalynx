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
 * @package datalynxfield_entrytime
 * @subpackage entrytime
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entrytime;

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\field\datalynxfield_no_content;

/**
 * Internal time field.
 */
class field extends datalynxfield_no_content {
    /** @var string Field type. */
    public $type = 'entrytime';

    /** @var string Time created name. */
    const _TIMECREATED = 'timecreated';

    /** @var string Time modified name. */
    const _TIMEMODIFIED = 'timemodified';

    /**
     * Check if it is internal field.
     *
     * @return bool
     */
    public static function is_internal() {
        return true;
    }

    /**
     * Return field objects for this type.
     *
     * @param int $dataid
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];

        $fieldobjects[self::_TIMECREATED] = (object) ['id' => self::_TIMECREATED,
                'dataid' => $dataid, 'type' => 'entrytime', 'name' => get_string('timecreated', 'datalynx'),
                'description' => '', 'internalname' => 'timecreated'];

        $fieldobjects[self::_TIMEMODIFIED] = (object) ['id' => self::_TIMEMODIFIED,
                'dataid' => $dataid, 'type' => 'entrytime', 'name' => get_string('timemodified', 'datalynx'),
                'description' => '', 'internalname' => 'timemodified'];

        return $fieldobjects;
    }

    /**
     * Return internal name.
     *
     * @return string
     */
    public function get_internalname() {
        return $this->field->internalname;
    }

    /**
     * Parse search data.
     *
     * @param object $formdata
     * @param int $i
     * @return array|false
     */
    public function parse_search($formdata, $i) {
        $time = [];

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
     * {@inheritDoc}
     * @param array $search Search criteria array.
     * @return array
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        [$not, $operator, $value] = $search;

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
        $params = [];

        if ($operator != 'BETWEEN') {
            if (!$operator || $operator == 'LIKE') {
                $operator = '=';
            }
            $params[$namefrom] = $from;
            return [" $not $varcharcontent $operator :$namefrom ", $params, false,
            ];
        } else {
            $params[$namefrom] = $from;
            $params[$nameto] = $to;
            return [" ($not $varcharcontent >= :$namefrom AND $varcharcontent <= :$nameto) ",
                    $params, false,
            ];
        }
    }

    /**
     * Return SQL to compare text in database.
     *
     * @param string $column
     * @return string
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_compare_text("e.{$this->field->internalname}");
    }

    /**
     * Return SQL for ORDER BY clause.
     *
     * @return string
     */
    public function get_sort_sql() {
        return 'e.' . $this->field->internalname;
    }

    /**
     * returns an array of distinct content of the field
     *
     * @param int $sortdir Sort direction: 0 for ASC, 1 for DESC.
     * @return array
     */
    public function get_distinct_content($sortdir = 0) {
        global $DB;

        $sortdir = $sortdir ? 'DESC' : 'ASC';
        $contentfull = $this->get_sort_sql();

        $sql = "SELECT DISTINCT $contentfull
                    FROM {datalynx_entries} e
                    WHERE $contentfull IS NOT NULL
                    ORDER BY $contentfull $sortdir";

        $distinctvalues = [];
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
     * Format search value for display.
     *
     * @param array $searchparams
     * @return string
     */
    public function format_search_value($searchparams) {
        [$not, $operator, $value] = $searchparams;
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

    /**
     * Return supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                '>' => get_string('after', 'datalynx'), '<' => get_string('before', 'datalynx'),
                'BETWEEN' => get_string('between', 'datalynx')];
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
