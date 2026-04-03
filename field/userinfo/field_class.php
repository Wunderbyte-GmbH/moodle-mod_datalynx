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
 * @package datalynxfield_userinfo
 * @subpackage userinfo
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\local\field\datalynxfield_base;

defined('MOODLE_INTERNAL') || die();



/**
 * User info field class.
 */
class datalynxfield_userinfo extends datalynxfield_base {
    /** @var string Field type */
    public $type = 'userinfo';

    /** @var int Profile field ID */
    public $infoid;

    /** @var string Profile field shortname */
    public $infoshortname;

    /** @var string Profile field type */
    public $infotype;

    /** @var string Default data */
    public $defaultdata;

    /** @var int Default data format */
    public $defaultdataformat;

    /** @var bool Is editable */
    public $editable;

    /** @var bool Is mandatory */
    public $mandatory;

    /** @var string Profile field param1 (e.g. dropdown options) */
    public $param8;

    /** @var string Profile field param3 (e.g. include time) */
    public $param10;

    /**
     * Constructor.
     *
     * @param int|object $df Datalynx ID or object
     * @param int|object $field Field ID or object
     */
    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        $this->infoid = $this->field->param1;
        $this->infoshortname = $this->field->param2;
        $this->infotype = $this->field->param3;
        $this->defaultdata = $this->field->param4;
        $this->defaultdataformat = $this->field->param5;
        $this->editable = $this->field->param6;
        $this->mandatory = $this->field->param7;
        $this->param8 = $this->field->param8;
        $this->param10 = $this->field->param10;
    }

    /**
     * Sets up a field object
     */
    public function set_field($forminput = null) {
        global $DB;

        $infoid = !empty($this->field->param1) ? $this->field->param1 : null;
        parent::set_field($forminput);

        if ($this->field->param1 && ($this->field->param1 != $infoid || !$this->field->param2)) {
            $infoitems = 'shortname,datatype,defaultdata,defaultdataformat,param1,param2,param3,param4,param5';
            if ($info = $DB->get_record('user_info_field', ['id' => $this->field->param1], $infoitems)) {
                $this->field->param2 = $info->shortname;
                $this->field->param3 = $info->datatype;
                $this->field->param4 = $info->defaultdata;
                $this->field->param5 = $info->defaultdataformat;

                $this->field->param8 = $info->param1;
                $this->field->param9 = $info->param2;
                $this->field->param10 = $info->param3;
            }
        }
    }

    /**
     * Check if the field is editable.
     *
     * @return bool
     */
    public function is_editable() {
        return true;
    }

    /**
     * Get SELECT SQL.
     *
     * @return string
     */
    public function get_select_sql() {
        $id = " c{$this->field->id}.id AS c{$this->field->id}_id ";
        $content = $this->get_sql_compare_text('data') . " AS c{$this->field->id}_content";
        $content1 = $this->get_sql_compare_text('dataformat') . " AS c{$this->field->id}_content1";
        return " $id , $content , $content1 ";
    }

    /**
     * Get SQL compare text.
     *
     * @param string $column
     * @return string
     */
    protected function get_sql_compare_text(string $column = 'data'): string {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.$column");
    }

    /**
     * Get SORT FROM SQL.
     *
     * @param string $paramname
     * @param string $paramcount
     * @return array|null
     */
    public function get_sort_from_sql($paramname = 'sortie', $paramcount = '') {
        $fieldid = $this->field->id;
        if (is_numeric($fieldid) && $fieldid > 0) {
            $sql = " LEFT JOIN {user_info_data} c$fieldid ON
                (c$fieldid.userid = e.userid AND c$fieldid.fieldid = :$paramname$paramcount) ";
            return [$sql, $this->infoid];
        } else {
            return null;
        }
    }

    /**
     * Get SEARCH FROM SQL.
     *
     * @return string
     */
    public function get_search_from_sql() {
        $fieldid = $this->field->id;
        if (is_numeric($fieldid) && $fieldid > 0) {
            return " JOIN {user_info_data} c$fieldid ON c$fieldid.userid = e.userid ";
        } else {
            return '';
        }
    }

    /**
     * Check if this is datalynx content.
     *
     * @return bool
     */
    public function is_datalynx_content() {
        return false;
    }
}
