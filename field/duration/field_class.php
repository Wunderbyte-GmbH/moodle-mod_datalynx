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
 * Field class for the duration field type.
 *
 * @package    datalynxfield_duration
 * @copyright  2014 onwards by edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");
require_once("$CFG->dirroot/mod/datalynx/field/number/field_class.php");

/** Field class for the duration field type. */
class datalynxfield_duration extends datalynxfield_base {
    /**
     * @var string
     */
    public $type = 'duration';

    /** @var array|null Cache for time units array. */
    protected $unitsarray = null; // phpcs:ignore

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * Returns time associative array of unit length.
     *
     * @return array unit length in seconds => string unit name.
     */
    public function get_units() {
        if (is_null($this->unitsarray)) {
            $this->unitsarray = [604800 => get_string('weeks'), 86400 => get_string('days'),
                    3600 => get_string('hours'), 60 => get_string('minutes'), 1 => get_string('seconds'),
            ];
        }
        return $this->unitsarray;
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
            return [0, 1];
        }
        foreach ($this->get_units() as $unit => $notused) {
            if (fmod($seconds, $unit) == 0) {
                return [$seconds / $unit, $unit];
            }
        }
        return [$seconds, 1];
    }

    /**
     * Return the SQL expression for comparing the content column as a number.
     *
     * @param string $column The column name to compare.
     * @return string The SQL fragment.
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_cast_char2int("c{$this->field->id}.$column", true);
    }

    /**
     * Format the raw form values into content and old-content arrays.
     *
     * @param stdClass $entry The entry object.
     * @param array|null $values The submitted values.
     * @return array Array with two elements: new contents and old contents.
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $contents = [];
        $oldcontents = [];
        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        $value = reset($values);
        $contents[] = $value;
        return [$contents, $oldcontents];
    }

    /**
     * Parse search form data and return from/to values for a range search.
     *
     * @param stdClass $formdata The form data object.
     * @param int $i The search filter index.
     * @return array|false Array of search values or false if empty.
     */
    public function parse_search($formdata, $i) {
        $values = [];

        $fromfield = optional_param_array(
            'f_' . $i . '_' . $this->field->id . '_from',
            ['number' => ''],
            PARAM_RAW
        );
        $tofield = optional_param_array(
            'f_' . $i . '_' . $this->field->id . '_to',
            ['number' => ''],
            PARAM_RAW
        );

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
     * Prepare import content from a CSV record for this field.
     *
     * @param stdClass $data The data object to populate.
     * @param array $importsettings Import settings array.
     * @param array|null $csvrecord The CSV record row.
     * @param int|null $entryid The entry id.
     * @return bool True on success.
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

        [$not, $operator, $value] = $search;

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
                $sql = " 1 = 1 ";
                break;
        }

        if ($excludeentries) {
            // Get entry ids for entries that meet the criterion.
            if ($eids = $this->get_entry_ids_for_content($sql, $params)) {
                // Get NOT IN sql.
                [$notinids, $params] = $DB->get_in_or_equal(
                    $eids,
                    SQL_PARAMS_NAMED,
                    "df_{$fieldid}_",
                    false
                );
                $sql = " e.id $notinids ";
                return [$sql, $params, false];
            } else {
                return ['', '', ''];
            }
        } else {
            return [$sql, $params, true];
        }
    }

    /**
     * Format a search parameter set for display as a human-readable string.
     *
     * @param array $searchparams Array of [not, operator, value].
     * @return string Human-readable representation.
     */
    public function format_search_value($searchparams) {
        [$not, $operator, $value] = $searchparams;
        if (is_array($value)) {
            if (count($value) > 1) {
                $value = '(' . implode(',', $value) . ')';
            } else {
                $value = $value[0];
            }
        }
        return $not . ' ' . $operator . ' ' . $value;
    }

    /**
     * Get the list of supported search operators for this field type.
     *
     * @return array Array of operator labels keyed by operator.
     */
    public function get_supported_search_operators() {
        return [
            '=' => get_string('equalto', 'datalynx'),
            '>' => get_string('greaterthan', 'datalynx'),
            '<' => get_string('lessthan', 'datalynx'),
            '>=' => get_string('greaterthanorequalto', 'datalynx'),
            '<=' => get_string('lessthanorequalto', 'datalynx'),
            'BETWEEN' => get_string('between', 'datalynx'),
        ];
    }
}
