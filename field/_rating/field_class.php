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
 * @subpackage _rating
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . '/../field_class.php');

class datalynxfield__rating extends datalynxfield_no_content {

    public $type = '_rating';

    const AGGREGATE_AVG = 1;

    const AGGREGATE_COUNT = 2;

    const AGGREGATE_MAX = 3;

    const AGGREGATE_MIN = 4;

    const AGGREGATE_SUM = 5;

    const _RATING = 'rating';

    const _RATINGAVG = 'ratingavg';

    const _RATINGCOUNT = 'ratingcount';

    const _RATINGMAX = 'ratingmax';

    const _RATINGMIN = 'ratingmin';

    const _RATINGSUM = 'ratingsum';

    public static function is_internal() {
        return true;
    }

    public function is_joined() {
        return true;
    }

    public static function get_field_objects($dataid) {
        $fieldobjects = array();

        $fieldobjects[self::_RATING] = (object) array('id' => self::_RATING, 'dataid' => $dataid,
                'type' => '_rating', 'name' => get_string('ratings', 'datalynx'), 'description' => '',
                'visible' => 2, 'internalname' => 'ratings');

        $fieldobjects[self::_RATINGAVG] = (object) array('id' => self::_RATINGAVG,
                'dataid' => $dataid, 'type' => '_rating', 'name' => get_string('ratingsavg', 'datalynx'),
                'description' => '', 'visible' => 2, 'internalname' => 'avgratings');

        $fieldobjects[self::_RATINGCOUNT] = (object) array('id' => self::_RATINGCOUNT,
                'dataid' => $dataid, 'type' => '_rating',
                'name' => get_string('ratingscount', 'datalynx'), 'description' => '', 'visible' => 2,
                'internalname' => 'countratings');

        $fieldobjects[self::_RATINGMAX] = (object) array('id' => self::_RATINGMAX,
                'dataid' => $dataid, 'type' => '_rating', 'name' => get_string('ratingsmax', 'datalynx'),
                'description' => '', 'visible' => 2, 'internalname' => 'maxratings');

        $fieldobjects[self::_RATINGMIN] = (object) array('id' => self::_RATINGMIN,
                'dataid' => $dataid, 'type' => '_rating', 'name' => get_string('ratingsmin', 'datalynx'),
                'description' => '', 'visible' => 2, 'internalname' => 'minratings');

        $fieldobjects[self::_RATINGSUM] = (object) array('id' => self::_RATINGSUM,
                'dataid' => $dataid, 'type' => '_rating', 'name' => get_string('ratingssum', 'datalynx'),
                'description' => '', 'visible' => 2, 'internalname' => 'sumratings');

        return $fieldobjects;
    }

    public function get_select_sql() {
        return ' er.itemid, er.component, er.ratingarea, er.contextid,
                er.numratings, er.avgratings, er.sumratings, er.maxratings, er.minratings,
                er.ratingid, er.ratinguserid, er.scaleid, er.usersrating ';
    }

    protected function get_sql_compare_text($column = 'content') {
        return $this->get_sort_sql();
    }

    public function get_sort_sql() {
        $internalname = $this->field->internalname;
        if ($internalname == 'ratings') {
            return "er.usersrating";
        } else {
            if ($internalname == 'countratings') {
                return "er.numratings";
            } else {
                return "er.$internalname";
            }
        }
    }

    public function get_join_sql() {
        global $USER;

        $params = array();
        $params['rcontextid'] = $this->df()->context->id;
        $params['ruserid'] = $USER->id;
        $params['rcomponent'] = 'mod_datalynx';
        $params['ratingarea'] = 'entry';

        $sql = "LEFT JOIN
                (SELECT r.itemid, r.component, r.ratingarea, r.contextid,
                           COUNT(r.rating) AS numratings,
                           AVG(r.rating) AS avgratings,
                           SUM(r.rating) AS sumratings,
                           MAX(r.rating) AS maxratings,
                           MIN(r.rating) AS minratings,
                           ur.id as ratingid, ur.userid as ratinguserid, ur.scaleid, ur.rating AS usersrating
                    FROM {rating} r
                            LEFT JOIN {rating} ur ON ur.contextid = r.contextid
                                                    AND ur.itemid = r.itemid
                                                    AND ur.component = r.component
                                                    AND ur.ratingarea = r.ratingarea
                                                    AND ur.userid = :ruserid
                    WHERE r.contextid = :rcontextid
                            AND r.component = :rcomponent
                            AND r.ratingarea = :ratingarea
                    GROUP BY r.itemid, r.component, r.ratingarea, r.contextid, ratingid, ur.userid, ur.scaleid
                    ORDER BY r.itemid) AS er ON er.itemid = e.id ";
        return array($sql, $params);
    }
}
