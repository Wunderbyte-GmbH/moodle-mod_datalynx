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
 * @package datalynxfield_time
 * @subpackage time
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_time;

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\rule\match_compiler;
use stdClass;
use IntlDateFormatter;

/**
 * Time field class.
 */
class field extends datalynxfield_base {
    /** @var string Field type */
    public $type = 'time';

    /** @var bool Date only */
    public $dateonly;

    /** @var bool Masked */
    public $masked;

    /** @var int Start year */
    public $startyear;

    /** @var int Stop year */
    public $stopyear;

    /** @var string Display format */
    public $displayformat;

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * Constructor.
     *
     * @param int|object $dlx Datalynx ID or object
     * @param int|object $field Field ID or object
     */
    public function __construct($dlx = 0, $field = 0) {
        parent::__construct($dlx, $field);
        $this->dateonly = $this->field->param1;
        $this->masked = $this->field->param5;
        $this->startyear = $this->field->param2;
        $this->stopyear = $this->field->param3;
        $this->displayformat = $this->field->param4;
    }

    /**
     * Get the content names of the field.
     *
     * @return array
     */
    protected function content_names() {
        return ['', 'year', 'month', 'day', 'hour', 'minute', 'enabled'];
    }

    /**
     * Format the field content.
     *
     * @param stdClass $entry
     * @param ?array $values
     * @return array
     */
    protected function format_content(stdClass $entry, ?array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = [];
        $contents = [];
        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        // New contents.
        $timestamp = null;
        if (!empty($values)) {
            if (count($values) === 1) {
                $values = reset($values);
            }

            if (!is_array($values)) {
                // Assuming timestamp is passed (e.g. in import).
                $timestamp = $values;
            } else {
                // Assuming any of year, month, day, hour, minute is passed.
                $enabled = $year = $month = $day = $hour = $minute = 0;
                foreach ($values as $name => $val) {
                    if (!empty($name)) { // The time unit.
                        ${$name} = $val;
                    }
                }
                if ($enabled) {
                    if ($year || $month || $day || $hour || $minute) {
                        $timestamp = make_timestamp($year, $month, $day, $hour, $minute, 0);
                    }
                }
            }
        }

        // We consider 0 a valid input to be stored.
        $contents[] = $timestamp;

        return [$contents, $oldcontents];
    }

    /**
     * Parse the search parameters.
     *
     * @param stdClass $formdata
     * @param int $i
     * @return array|bool
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
     * Check if group by is supported.
     *
     * @return bool
     */
    public function supports_group_by() {
        return true;
    }

    /**
     * A time can also be matched within a tolerance of another entry's time.
     *
     * @return string[]
     */
    public function supported_relative_criteria(): array {
        return [match_compiler::OP_SAME, match_compiler::OP_DIFFERENT, match_compiler::OP_WITHIN];
    }

    /**
     * {@inheritDoc}
     *
     * Times are stored as unix timestamps, and the search values of this field type are arrays of
     * [from, to] - get_search_sql() reads $value[0] and $value[1]. A tolerance is therefore
     * expressed as an ordinary range around the other entry's timestamp.
     *
     * @param string $relation
     * @param string $storedvalue
     * @param array $options ['tolerance' => float] in seconds
     * @return array|null
     * @see datalynxfield_base::compile_relative_criterion()
     */
    public function compile_relative_criterion(string $relation, string $storedvalue, array $options = []): ?array {
        if (!in_array($relation, $this->supported_relative_criteria(), true) || !is_numeric(trim($storedvalue))) {
            return null;
        }
        $value = (float) trim($storedvalue);
        if ($value <= 0) {
            // A time field stores nothing (or 0) when the value is disabled; there is nothing to
            // compare against.
            return null;
        }

        if ($relation === match_compiler::OP_WITHIN) {
            $tolerance = (float) ($options['tolerance'] ?? 0);
            if ($tolerance <= 0) {
                return null;
            }
            return ['', 'BETWEEN', $this->tolerance_bounds($value, $tolerance)];
        }

        return [$relation === match_compiler::OP_DIFFERENT ? 'NOT' : '', '=', [$value, 0]];
    }

    /**
     * Returns the sql for selecting entries which match the given criterion for this field
     * Possible criterions: BETWEEN, equal(=), after(>), before(<), IS EMPTY, IS NOT EMPTY
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     * @param array $search Search criteria array.
     * @return array SQL fragment, params, and join flag.
     */
    public function get_search_sql(array $search): array {
        global $DB;

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
        $fieldid = $this->field->id;
        $namefrom = "df_{$fieldid}_{$i}_from";
        $nameto = "df_{$fieldid}_{$i}_to";

        // For all NOT criteria except NOT Empty, look up the entries meeting the positive
        // criterion and exclude those, rather than negating the condition on the joined content
        // table: an entry with no content record for this field cannot satisfy such a condition
        // and would be dropped from a NOT search although it belongs in the result. Same contract
        // as datalynxfield_base::get_search_sql().
        $excludeentries = (($not && $operator !== '') || (!$not && $operator === ''));

        // The exclusion lookup queries datalynx_contents directly, where the column is not aliased.
        $varcharcontent = $excludeentries ? $DB->sql_compare_text('content') : $this->get_sql_compare_text();

        $params = [];
        switch ($operator) {
            case '':
                // Empty: state it positively as "holds a value" and let the exclusion above
                // turn it into "holds none", which also covers entries with no content record.
                [$sql, $params] = $DB->get_in_or_equal('', SQL_PARAMS_NAMED, "df_{$fieldid}_", false);
                $sql = " $varcharcontent $sql ";
                break;
            case '=':
                if ($this->dateonly) {
                    $fromdate = date("Y-m-d", $from);
                    $from = strtotime($fromdate);
                }
                // Fall through.
            case '<':
            case '>':
                $params[$namefrom] = $from;
                $sql = " $varcharcontent $operator :$namefrom ";
                break;
            default:
                $params[$namefrom] = $from;
                $params[$nameto] = $to;
                // Parenthesised as one unit so that an enclosing NOT applies to the whole range.
                $sql = " ($varcharcontent >= :$namefrom AND $varcharcontent < :$nameto) ";
                break;
        } // End switch.

        if (!$excludeentries) {
            return [$sql, $params, true];
        }

        if (!$eids = $this->get_entry_ids_for_content($sql, $params)) {
            // No entry meets the positive criterion, so the NOT criterion matches every entry:
            // contribute no condition and let all entries through.
            return ['', [], false];
        }
        [$notinids, $params] = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, "df_{$fieldid}_", false);

        return [" e.id $notinids ", $params, false];
    }

    /**
     * Returns the entry-IDs of all the entries where the content field is empty or there is no content dataset at all
     */
    protected function get_entry_ids_for_empty_content() {
        global $DB;

        $params = [];
        $sql = "SELECT id FROM {datalynx_entries} e
                WHERE e.dataid = :dataid AND NOT EXISTS
                  (SELECT id FROM {datalynx_contents} c WHERE fieldid = :fieldid AND c.entryid =  e.id) ";
        $params['dataid'] = $this->field->dataid;
        $params['fieldid'] = $this->id();
        $eids = $DB->get_fieldset_sql($sql, $params);
        $sql = "SELECT entryid FROM {datalynx_contents}
                WHERE fieldid = :fieldid AND content =  '' ";
        $eids = array_merge($eids, $DB->get_fieldset_sql($sql, $params));
        return $eids;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::prepare_import_content()
     * @param mixed $data Data object to populate.
     * @param array $importsettings Import settings.
     * @param ?array $csvrecord CSV record data.
     * @param ?int $entryid Entry ID.
     * @return bool True on success.
     */
    public function prepare_import_content(&$data, $importsettings, ?array $csvrecord = null, ?int $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            $timestr = !empty($csvrecord[$csvname]) ? $csvrecord[$csvname] : null;

            if ($timestr) {
                $timestr = html_entity_decode($timestr);
                // Temp fix: German month names.
                $germanmonths = [
                        'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                        'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
                ];

                // English month names.
                $englishmonths = [
                        'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December',
                ];
                // It's a timestamp.
                if (
                    ((string) (int) $timestr === $timestr) && ($timestr <= PHP_INT_MAX) &&
                        ($timestr >= ~PHP_INT_MAX)
                ) {
                    $data->{"field_{$fieldid}_{$entryid}"} = $timestr;
                    // It's a valid time string.
                } else {
                    $timestr = str_replace($germanmonths, $englishmonths, $timestr);
                    if ($unixtimestamp = strtotime($timestr)) {
                        $data->{"field_{$fieldid}_{$entryid}"} = $unixtimestamp;
                    } else {
                        $fmt = new IntlDateFormatter(
                            'de_DE',
                            IntlDateFormatter::FULL,
                            IntlDateFormatter::FULL,
                            null,
                            IntlDateFormatter::GREGORIAN
                        );
                        $fmt->setLenient(true);
                        $unixtimestamp = $fmt->parse($timestr);
                        if ($unixtimestamp) {
                            $data->{"field_{$fieldid}_{$entryid}"} = $unixtimestamp;
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Format the search value.
     *
     * @param array $searchparams
     * @return string
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
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_sql_compare_text()
     * @param string $column Column name.
     * @return string SQL comparison expression.
     */
    public function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_cast_char2int("c{$this->field->id}.$column", true);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_supported_search_operators()
     */
    public function get_supported_search_operators() {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                '>' => get_string('after', 'datalynx'), '<' => get_string('before', 'datalynx'),
                'BETWEEN' => get_string('between', 'datalynx')];
    }

    /**
     * Is $value a valid content or do we see an empty input?
     * @param mixed $value Field value to check.
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        if ($value == 0) {
            return true;
        }
        return false;
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
