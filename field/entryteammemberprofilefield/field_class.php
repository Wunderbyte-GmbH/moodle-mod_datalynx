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
 * @subpackage entryteammemberprofilefield
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/datalynx/field/field_class.php');
require_once($CFG->dirroot . '/mod/datalynx/field/datalynxfield_no_content_can_join.php');

class datalynxfield_entryteammemberprofilefield extends datalynxfield_no_content_can_join {

    public $type = 'entryteammemberprofilefield';

    const SQL_NEVERTRUE = "1 = 0";

    const OPERATOR_MY_PROFILE_FIELD = 'MY_PROFILE_FIELD';
    const OPERATOR_LITERAL_VALUE = 'LITERAL_VALUE';

    public function supports_group_by() {
        return false;
    }

    /**
     */
    public static function is_internal() {
        return true;
    }

    /**
     */
    public static function get_field_objects($dataid, $fields = array()) {
        $fieldobjects = array();

        $team_member_select_fields = array_filter($fields, function($field) {
            return $field instanceof datalynxfield_teammemberselect;
        });

        $user_profile_fields = array('institution', 'department');

        foreach ($team_member_select_fields as $field) {
            $field_name = $field->field->name;
            foreach ($user_profile_fields as $profile_field) {
                $field_id = 'entryteammemberprofilefield_' . $field->field->id . "_$profile_field";
                $fieldobjects[$field_id] = (object) array('id' => $field_id,
                    'dataid' => $dataid, 'type' => 'entryteammemberprofilefield',
                    'name' => $field_name . ' -> ' . get_string($profile_field), 'description' => '',
                    'visible' => 2, 'internalname' => $field_id);
            }
        }

        return $fieldobjects;
    }

    /**
     */
    protected function get_sql_compare_text($column = 'content') {
        global $DB;
        // The sort sql here returns the field's sql name.
        return $DB->sql_compare_text($this->get_sort_sql());
    }

    public function get_search_from_sql() {
        $field_id_components = $this->get_field_id_components();
        $queried_field_id = $field_id_components["queried_field_id"];

        if (is_numeric($queried_field_id) && $queried_field_id > 0) {
            return " LEFT JOIN {datalynx_contents} c$queried_field_id ON c$queried_field_id.entryid = e.id AND c$queried_field_id.fieldid = $queried_field_id ";
        } else {
            return "";
        }
    }

    private function get_field_id_components() {
        $components = explode("_", $this->field->id);
        return array(
            'queried_field_id' => $components[1],
            'profile_field_name' => $components[2]
        );
    }

    /**
     */
    public function get_sort_sql() {
        return "";
    }

    /**
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        global $DB;
        global $USER;

        list($not, $operator, $value) = $search;
    
        $field_id_components = $this->get_field_id_components();
        $queried_field_id = $field_id_components["queried_field_id"];
        $profile_field_name = $field_id_components["profile_field_name"];

        $field_id = $this->field->id;
        $param_prefix = "df_{$field_id}";

        $users_with_profile_field_value = $this->get_users_with_profile_field_value($profile_field_name, $operator, $value, $not);

        if (empty($users_with_profile_field_value)) {
            $sql = self::SQL_NEVERTRUE;
            $params = array();
            $usecontent = false;
        } else {
            $user_ids = array_map(function($user) {return $user->id;}, $users_with_profile_field_value);
            $user_ids_json = array_map(function($id) {return $this->wrap_as_json_string_array($id);}, $user_ids);

            [$insql, $inparams] = $DB->get_in_or_equal($user_ids_json, $type = SQL_PARAMS_NAMED, $param_prefix);
            
            $sql = "c{$queried_field_id}.content $insql";
            $params = $inparams;
            $usecontent = true;
        }

        return array($sql, $params, $usecontent);
    }

    private function get_users_with_profile_field_value($profile_field_name, $operator, $value, $not) {
        global $DB;
        global $USER;

        $sql = $not ? 
                "SELECT u.id
                FROM {user} u
                WHERE u.$profile_field_name != ?" 
                : "SELECT u.id
                FROM {user} u
                WHERE u.$profile_field_name = ?";

        $search_value = ($operator == self::OPERATOR_MY_PROFILE_FIELD) ? $USER->$profile_field_name : $value;

        return $DB->get_records_sql($sql, array($search_value)); 
    }

    private function wrap_as_json_string_array($value) {
        return "[\"$value\"]";
    }

    public function parse_search($formdata, $i) {
        global $USER;
        $field_id = $this->field->id;
        $internalname = $this->field->internalname;
        $operator = !empty($formdata->{"searchoperator{$i}"}) ? $formdata->{"searchoperator{$i}"} : '';
        $fieldvalue = !empty($formdata->{"f_{$i}_$field_id"}) ? $formdata->{"f_{$i}_$field_id"} : false;
        if ($operator == self::OPERATOR_MY_PROFILE_FIELD) {
            return ""; 
        } else {
            if ($operator == self::OPERATOR_LITERAL_VALUE) {
                return $fieldvalue;
            } else {
                return false;
            }
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
        return array(
            '' => '&lt;' . get_string('choose') . '&gt;',
            self::OPERATOR_LITERAL_VALUE => get_string('literalvalue', 'datalynxfield_entryteammemberprofilefield'),
            self::OPERATOR_MY_PROFILE_FIELD => get_string('myprofilefield', 'datalynxfield_entryteammemberprofilefield')
        );
    }

    public function get_argument_count(string $operator) {
        if ($operator === self::OPERATOR_MY_PROFILE_FIELD) {
            return 0;
        } else {
            return 1;
        }
    }
}
