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
 * @package datalynxfield_entryauthor
 * @subpackage entryauthor
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entryauthor;

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\field\datalynxfield_no_content;
use stdClass;

/**
 * Entry author field class.
 *
 * @package    datalynxfield_entryauthor
 * @copyright  2013 onwards edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_no_content {
    /** @var string The field type. */
    public $type = 'entryauthor';

    /** @var string User ID constant. */
    const _USERID = 'userid';

    /** @var string User full name constant. */
    const _USERNAME = 'username';

    /** @var string User first name constant. */
    const _USERFIRSTNAME = 'userfirstname';

    /** @var string User last name constant. */
    const _USERLASTNAME = 'userlastname';

    /** @var string User username constant. */
    const _USERUSERNAME = 'userusername';

    /** @var string User ID number constant. */
    const _USERIDNUMBER = 'useridnumber';

    /** @var string User picture constant. */
    const _USERPICTURE = 'userpicture';

    /** @var string User email constant. */
    const _USEREMAIL = 'useremail';

    /** @var string User institution constant. */
    const _USERINSTITUTION = 'userinstitution';

    /** @var string User department constant. */
    const _USERDEPARTMENT = 'userdepartment';

    /** @var string User badges constant. */
    const _BADGES = 'badges';

    /**
     * Supports grouping by this field.
     *
     * @return bool
     */
    public function supports_group_by() {
        return true;
    }

    /**
     * Check if the field is internal.
     *
     * @return bool
     */
    public static function is_internal() {
        return true;
    }

    /**
     * Get field objects for the author field.
     *
     * @param int $dataid The datalynx ID.
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];

        $fieldobjects[self::_USERID] = (object) ['id' => self::_USERID, 'dataid' => $dataid,
                'type' => 'entryauthor', 'name' => get_string('userid', 'datalynxfield_entryauthor'),
                'description' => '', 'visible' => 2, 'internalname' => 'id'];

        $fieldobjects[self::_USERNAME] = (object) ['id' => self::_USERNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('username', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'name'];

        $fieldobjects[self::_USERFIRSTNAME] = (object) ['id' => self::_USERFIRSTNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userfirstname', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'firstname'];

        $fieldobjects[self::_USERLASTNAME] = (object) ['id' => self::_USERLASTNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userlastname', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'lastname'];

        $fieldobjects[self::_USERUSERNAME] = (object) ['id' => self::_USERUSERNAME,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userusername', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'username'];

        $fieldobjects[self::_USERIDNUMBER] = (object) ['id' => self::_USERIDNUMBER,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('useridnumber', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'idnumber'];

        $fieldobjects[self::_USERPICTURE] = (object) ['id' => self::_USERPICTURE,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('userpicture', 'datalynxfield_entryauthor'), 'description' => '',
                'visible' => 2, 'internalname' => 'picture'];

        $fieldobjects[self::_USEREMAIL] = (object) ['id' => self::_USEREMAIL,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('useremail', 'datalynxfield_entryauthor'), 'description' => '',
                        'visible' => 2, 'internalname' => 'email'];

        $fieldobjects[self::_USERINSTITUTION] = (object) ['id' => self::_USERINSTITUTION,
                        'dataid' => $dataid, 'type' => 'entryauthor',
                        'name' => get_string('institution'), 'description' => '',
                        'visible' => 2, 'internalname' => 'institution'];

        $fieldobjects[self::_USERDEPARTMENT] = (object) ['id' => self::_USERDEPARTMENT,
                'dataid' => $dataid, 'type' => 'entryauthor',
                'name' => get_string('department'), 'description' => '',
                'visible' => 2, 'internalname' => 'department'];

        // MDL-0000 TODO: Multilang.
        $fieldobjects[self::_BADGES] = (object) ['id' => self::_BADGES,
                        'dataid' => $dataid, 'type' => 'entryauthor',
                        'name' => 'Badges', 'description' => '',
                        'visible' => 2, 'internalname' => 'badges'];

        return $fieldobjects;
    }

    /**
     * Get the SQL expression for comparing text.
     *
     * @param string $column
     * @return string
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        // The sort sql here returns the field's sql name.
        return $DB->sql_compare_text($this->get_sort_sql());
    }

    /**
     * Get the SQL for sorting.
     *
     * @return string
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
        [$not, $operator, $value] = $search;

        if ($operator == 'ME') {
            $value = $USER->id;
        }

        [$sql, $params] = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED);
        $sql = " $not ( e.userid $sql ) ";
        return [$sql, $params, false];
    }

    /**
     * Parse search data.
     *
     * @param stdClass $formdata
     * @param int $i
     * @return mixed
     */
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

        $distinctvalues = [];
        if ($options = $DB->get_records_sql($sql, [$this->df->id()])) {
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

    /**
     * Get the list of supported search operators for this field type.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        switch ($this->field->internalname) {
            case 'id':
            case 'name':
                return ['' => '&lt;' . get_string('choose') . '&gt;',
                        'ME' => get_string('me', 'datalynx'),
                        'OTHER_USER' => get_string('otheruser', 'datalynx'),
                ];
            default:
                return parent::get_supported_search_operators();
        }
    }
}
