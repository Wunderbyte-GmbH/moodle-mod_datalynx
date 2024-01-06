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
 * @subpackage entryauthor
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/datalynx/field/field_class.php');

class datalynxfield_entryauthor extends datalynxfield_no_content {

    public $type = 'entryauthor';

    const _USERID = 'userid';

    const _USERNAME = 'username';

    const _USERFIRSTNAME = 'userfirstname';

    const _USERLASTNAME = 'userlastname';

    const _USERUSERNAME = 'userusername';

    const _USERIDNUMBER = 'useridnumber';

    const _USERPICTURE = 'userpicture';

    const _USEREMAIL = 'useremail';

    const _USERINSTITUTION = 'userinstitution';

    const _BADGES = 'badges';

    public function supports_group_by() {
        return true;
    }

    /**
     */
    public static function is_internal() {
        return true;
    }

    /**
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = array();

        $fieldobjects[self::_USERID] = (object) array('id' => self::_USERID, 'dataid' => $dataid,
                'type' => 'entryauthor', 'name' => get_string('userid', 'datalynxfield_entryauthor'),
                'description' => '', 'visible' => 2, 'internalname' => 'id');

        $fieldobjects[self::_USERNAME] = (object) array('id' => self::_USERNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('username', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'name');

        $fieldobjects[self::_USERFIRSTNAME] = (object) array('id' => self::_USERFIRSTNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userfirstname', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'firstname');

        $fieldobjects[self::_USERLASTNAME] = (object) array('id' => self::_USERLASTNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userlastname', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'lastname');

        $fieldobjects[self::_USERUSERNAME] = (object) array('id' => self::_USERUSERNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userusername', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'username');

        $fieldobjects[self::_USERIDNUMBER] = (object) array('id' => self::_USERIDNUMBER,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('useridnumber', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'idnumber');

        $fieldobjects[self::_USERPICTURE] = (object) array('id' => self::_USERPICTURE,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userpicture', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'picture');

        $fieldobjects[self::_USEREMAIL] = (object) array('id' => self::_USEREMAIL,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('useremail', 'datalynxfield_entryauthor'), 'description' => '',
                        'visible' => 2, 'internalname' => 'email');

        $fieldobjects[self::_USERINSTITUTION] = (object) array('id' => self::_USERINSTITUTION,
                        'dataid' => $dataid, 'type' => 'entryauthor',
                        'name' => get_string('institution'), 'description' => '',
                        'visible' => 2, 'internalname' => 'institution');

        // TODO: Multilang.
        $fieldobjects[self::_BADGES] = (object) array('id' => self::_BADGES,
                        'dataid' => $dataid, 'type' => 'entryauthor',
                        'name' => 'Badges', 'description' => '',
                        'visible' => 2, 'internalname' => 'badges');

        return $fieldobjects;
    }

    /**
     */
    protected function get_sql_compare_text($column = 'content') {
        global $DB;
        // The sort sql here returns the field's sql name.
        return $DB->sql_compare_text($this->get_sort_sql());
    }

    /**
     */
    public function get_sort_sql() {
        if ($this->field->internalname != 'picture') {
            if ($this->field->internalname == 'name') {
                $internalname = 'id';
            } else {
                $internalname = $this->field->internalname;
            }
            return 'u.' . $internalname;
        } else {
            return '';
        }
    }

    /**
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        global $USER, $DB;
        list($not, $operator, $value) = $search;

        if ($operator == 'ME') {
            $value = $USER->id;
        }

        list($sql, $params) = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED);
        $sql = " $not ( e.userid $sql ) ";
        return array($sql, $params, false);
    }

    public function parse_search($formdata, $i) {
        global $USER;
        $fieldid = $this->field->id;
        $internalname = $this->field->internalname;
        $operator = !empty($formdata->{"searchoperator{$i}"}) ? $formdata->{"searchoperator{$i}"} : '';
        $fieldvalue = !empty($formdata->{"f_{$i}_$fieldid"}) ? $formdata->{"f_{$i}_$fieldid"} : false;
        if ($internalname == 'id' || $internalname == 'name') {
            if ($operator == 'ME') {
                return $USER->id;
            } else {
                if ($operator == 'OTHER_USER') {
                    return $fieldvalue;
                } else {
                    return false;
                }
            }
        } else {
            return parent::parse_search($this->df(), $i);
        }
    }

    /**
     * returns an array of distinct content of the field
     */
    public function get_distinct_content($sortdir = 0) {
        global $DB;

        $sortdir = $sortdir ? 'DESC' : 'ASC';
        $contentfull = $this->get_sort_sql();
        $sql = "SELECT DISTINCT $contentfull
                  FROM {user} u
                       JOIN {datalynx_entries} e ON u.id = e.userid
                 WHERE e.dataid = ? AND  $contentfull IS NOT NULL
                 ORDER BY $contentfull $sortdir";

        $distinctvalues = array();
        if ($options = $DB->get_records_sql($sql, array($this->df->id()))) {
            if ($this->field->internalname == 'name') {
                $internalname = 'id';
            } else {
                $internalname = $this->field->internalname;
            }
            foreach ($options as $data) {
                $value = $data->{$internalname};
                if ($value === '') {
                    continue;
                }
                $distinctvalues[] = $value;
            }
        }
        return $distinctvalues;
    }

    public function get_supported_search_operators() {
        switch ($this->field->internalname) {
            case 'id':
            case 'name':
                return array('' => '&lt;' . get_string('choose') . '&gt;',
                        'ME' => get_string('me', 'datalynx'),
                        'OTHER_USER' => get_string('otheruser', 'datalynx')
                );
            default:
                return parent::get_supported_search_operators();
        }
    }
}
