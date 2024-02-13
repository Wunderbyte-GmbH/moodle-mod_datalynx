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
 * @subpackage time
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_time extends datalynxfield_base {

    public $type = 'time';

    public $dateonly;

    public $masked;

    public $startyear;

    public $stopyear;

    public $displayformat;

    /**
     * Can this field be used in fieldgroups?
     * @var boolean
     */
    protected $forfieldgroup = true;

    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        $this->dateonly = $this->field->param1;
        $this->masked = $this->field->param5;
        $this->startyear = $this->field->param2;
        $this->stopyear = $this->field->param3;
        $this->displayformat = $this->field->param4;
    }

    /**
     */
    protected function content_names() {
        return array('', 'year', 'month', 'day', 'hour', 'minute', 'enabled');
    }

    /**
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = array();
        $contents = array();
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

        return array($contents, $oldcontents);
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

    public function supports_group_by() {
        return true;
    }

    /**
     * Returns the sql for selecting entries which match the given criterion for this field
     * Possible criterions: BETWEEN, equal(=), after(>), before(<), IS EMPTY, IS NOT EMPTY
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
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
        $namefrom = "df_{$this->field->id}_{$i}_from";
        $nameto = "df_{$this->field->id}_{$i}_to";
        $varcharcontent = $this->get_sql_compare_text();
        $params = array();

        switch ($operator) {
            case '=':
                if ($this->dateonly) {
                    $fromdate = date("Y-m-d", $from);
                    $from = strtotime($fromdate);
                }
            case '<':
            case '>':
                $params[$namefrom] = $from;
                $return = array(" $not $varcharcontent $operator :$namefrom ", $params, true);
                break;
            default:
                $params[$namefrom] = $from;
                $params[$nameto] = $to;
                $return = array(" ($not $varcharcontent >= :$namefrom AND $varcharcontent < :$nameto) ",
                        $params, true);
                break;
        } // End switch.

        return $return;
    }

    /**
     * Returns the entry-IDs of all the entries where the content field is empty or there is no content dataset at all
     */
    protected function get_entry_ids_for_empty_content() {
        global $DB;

        $params = array();
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
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
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
                        'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
                ];

                // English month names
                $englishmonths = [
                        'January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'
                ];
                // It's a timestamp.
                if (((string) (int) $timestr === $timestr) && ($timestr <= PHP_INT_MAX) &&
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
                        datefmt_set_lenient($fmt, true);
                        $unixtimestamp = $fmt->parse($timestr);
                        $errormsg = datefmt_get_error_message($fmt);
                        $errorcode = datefmt_get_error_code($fmt);
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

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_sql_compare_text()
     */
    public function get_sql_compare_text($column = 'content') {
        global $DB;
        return $DB->sql_cast_char2int("c{$this->field->id}.$column", true);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_supported_search_operators()
     */
    public function get_supported_search_operators() {
        return array('' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                '>' => get_string('after', 'datalynx'), '<' => get_string('before', 'datalynx'),
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

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
