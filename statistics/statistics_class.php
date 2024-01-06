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
 * @package datalynx
 * @subpackage statistics
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class datalynx_statistics_class {

    /*
     * number of total entries ever made / deleted entries
     * number of entries added for a selected time period (week, months, year)
     * number of visits (per view and per datalynx instance, week, months, years)
     * number of approved entries for a selected time period
     * number of not yet approved entries (work in progress) for a selected time period
     * number of total entries ever made (including deleted)
     * number of existing entries for a defined date
     * number of deleted entries for a defined date
     * So sollte es aussehen:
     * time period selector for all Stats with Time period
     * Date selector for all stats with "defined date"
     */
    const VIEW_TOTAL_ENTRIES_COUNT = 0;

    const VIEW_ADDED_ENTRIES_COUNT = 1;

    const VIEW_DELETED_ENTRIES_COUNT = 2;

    const VIEW_VISITS_COUNT = 3;

    const MODE_PERIOD = 0;

    const MODE_ON_DATE = 1;

    const MODE_UNTIL_DATE = 2;

    const MODE_FROM_DATE = 3;

    const MODE_ALL_TIME = 4;

    private $_df;

    public function __construct($df = 0) {
        if (empty($df)) {
            throw new coding_exception('Datalynx id or object must be passed to field constructor.');
        } else {
            if ($df instanceof \mod_datalynx\datalynx) {
                $this->_df = $df;
            } else {
                $this->_df = new mod_datalynx\datalynx($df);
            }
        }
    }

    public function print_statistics($params) {
        if (empty($params) || empty($params->show)) {
            echo "<hr />Nothing to display.<hr />";
        } else {
            switch ($params->mode) {
                case self::MODE_PERIOD:
                    $from = $params->from;
                    $to = $params->to;
                    break;
                case self::MODE_ON_DATE:
                    $from = $params->from;
                    $to = $params->from;
                    break;
                case self::MODE_UNTIL_DATE:
                    $from = 0;
                    $to = $params->to;
                    break;
                case self::MODE_FROM_DATE:
                    $from = $params->from;
                    $to = PHP_INT_MAX;
                    break;
                case self::MODE_ALL_TIME:
                    $from = 0;
                    $to = PHP_INT_MAX;
                    break;
                default:
                    throw new moodle_exception('generalexceptionmessage', 'error',
                            '', 'This should not happen');
            }
            list($total, $approved, $deleted, $visits) = $this->get_count($params->mode, $from, $to);
            $dateformat = get_string('strftimedate', 'langconfig');
            $title = get_string('statisticsfor', 'datalynx', $this->_df->name());
            $timestring = get_string("timestring{$params->mode}", 'datalynx',
                    array('from' => userdate($from, $dateformat),
                            'to' => userdate($to, $dateformat), 'now' => userdate(time(), $dateformat)
                    ));
            echo "<hr />$title $timestring";
            $first = true;
            if (isset($params->show[self::VIEW_TOTAL_ENTRIES_COUNT])) {
                if ($first) {
                    echo '<hr />';
                    $first = false;
                } else {
                    echo '<br />';
                }
                echo get_string('numtotalentries', 'datalynx') . ": {$total}";
            }
            if (isset($params->show[self::VIEW_ADDED_ENTRIES_COUNT])) {
                if ($first) {
                    echo '<hr />';
                    $first = false;
                } else {
                    echo '<br />';
                }
                echo get_string('numapprovedentries', 'datalynx') . ": {$approved}";
            }
            if (isset($params->show[self::VIEW_DELETED_ENTRIES_COUNT])) {
                if ($first) {
                    echo '<hr />';
                    $first = false;
                } else {
                    echo '<br />';
                }
                echo get_string('numdeletedentries', 'datalynx') . ": {$deleted}";
            }
            if (isset($params->show[self::VIEW_VISITS_COUNT])) {
                if ($first) {
                    echo '<hr />';
                    $first = false;
                } else {
                    echo '<br />';
                }
                echo get_string('numvisits', 'datalynx') . ": {$visits}";
            }
            echo "<hr />";
        }
    }

    public function get_form() {
        global $CFG;
        $formclass = 'datalynx_statistics_form';
        $formparams = array('d' => $this->_df->id());
        $actionurl = new moodle_url('/mod/datalynx/statistics/index.php', $formparams);
        require_once('statistics_form.php');
        return new $formclass($this, $actionurl);
    }

    private function get_count($mode, $from = 0, $to = PHP_INT_MAX) {
        global $DB;

        $params = array('dataid' => $this->_df->id(), 'fromdate' => $from,
                'todate' => $to + strtotime('+1 day', 0));

        $querytotal = "SELECT COUNT(de.id)
                    FROM {datalynx_entries} de
                   WHERE de.dataid = :dataid
                     AND de.timecreated > :fromdate
                     AND de.timecreated < :todate";

        $queryapproved = "SELECT COUNT(de.id)
                            FROM {datalynx_entries} de
                           WHERE de.dataid = :dataid
                             AND de.timecreated > :fromdate
                             AND de.timecreated < :todate
                             AND de.approved = 1";

        $querydeleted = "SELECT COUNT(l.id)
                           FROM {log} l
                          WHERE l.module LIKE 'datalynx'
                            AND l.action LIKE 'entry delete'
                            AND l.info = :dataid
                            AND l.time > :fromdate
                            AND l.time < :todate";

        $queryvisits = "SELECT COUNT(l.id)
                          FROM {log} l
                         WHERE l.module LIKE 'datalynx'
                           AND l.action LIKE 'view'
                           AND l.info = :dataid
                           AND l.time > :fromdate
                           AND l.time < :todate";

        return array($DB->get_field_sql($querytotal, $params),
                $DB->get_field_sql($queryapproved, $params), $DB->get_field_sql($querydeleted, $params),
                $DB->get_field_sql($queryvisits, $params));
    }
}
