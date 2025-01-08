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
     * @param $dataid
     * @param $fields
     * @return array
     */
    public static function get_field_objects($dataid, $fields = array()) {
        $fieldobjects = [];

        $teammemberselectfields = array_filter($fields, function($field) {
            return $field instanceof datalynxfield_teammemberselect;
        });

        $userprofilefields = array('institution', 'department');

        foreach ($teammemberselectfields as $field) {
            $fieldname = $field->field->name;
            foreach ($userprofilefields as $profilefield) {
                $fieldid = 'entryteammemberprofilefield_' . $field->field->id . "_$profilefield";
                $fieldobjects[$fieldid] = (object) array('id' => $fieldid,
                    'dataid' => $dataid, 'type' => 'entryteammemberprofilefield',
                    'name' => $fieldname . ' ' . get_string($profilefield), 'description' => '',
                    'visible' => 2, 'internalname' => $fieldid);
            }
        }

        return $fieldobjects;
    }

    /**
     * @param string $column
     * @return string
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        // The sort sql here returns the field's sql name.
        return $DB->sql_compare_text($this->get_sort_sql());
    }

    /**
     * Return JOIN SQL string or empty string for search.
     *
     * @return string
     */
    public function get_search_from_sql(): string {
        $fieldidcomponents = $this->get_field_id_components();
        $queriedfieldid = $fieldidcomponents["queriedfieldid"];

        if (is_numeric($queriedfieldid) && $queriedfieldid > 0) {
            return " LEFT JOIN {datalynx_contents} c$queriedfieldid ON c$queriedfieldid.entryid = e.id AND c$queriedfieldid.fieldid = $queriedfieldid ";
        } else {
            return "";
        }
    }

    /**
     * Get field id and name as an array.
     *
     * @return array
     */
    private function get_field_id_components(): array {
        $components = explode("_", $this->field->id);
        return array(
            'queriedfieldid' => $components[1],
            'profilefieldname' => $components[2]
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

        list($not, $operator, $value) = $search;
    
        $fieldidcomponents = $this->get_field_id_components();
        $queriedfieldid = $fieldidcomponents["queriedfieldid"];
        $profilefieldname = $fieldidcomponents["profilefieldname"];

        $paramprefix = "eid_";

        $userswithprofilefieldvalue = $this->get_users_with_profile_field_value($profilefieldname, $operator, $value, $not);

        if (empty($userswithprofilefieldvalue)) {
            $sql = self::SQL_NEVERTRUE;
            $params = [];
        } else {
            $userids = array_keys($userswithprofilefieldvalue);
            $useridsasstring = array_map('strval', $userids);
            // Get entryids for the users that have the selected field value:
            $conditions = [];
            $params = [
                    'dataid' => $this->df->id(),
                    'fieldid' => $queriedfieldid
            ];

            foreach ($userids as $key => $userid) {
                // Use placeholders for user IDs to prevent SQL injection
                $conditions[] = $DB->sql_like('dc.content', ':userid' . $key, false, false, false);
                // Add user ID to the parameters array
                $params['userid' . $key] = '%"' . $userid . '"%';
            }

            $like = implode(' OR ', $conditions);

            $eidsql = "
                SELECT dc.entryid 
                FROM {datalynx_contents} dc
                JOIN {datalynx_fields} df ON dc.fieldid = df.id
                WHERE df.dataid = :dataid 
                  AND dc.fieldid = :fieldid 
                  AND ($like)
            ";

            $eids = $DB->get_fieldset_sql($eidsql, $params);
            if (empty($eids)) {
                return array(self::SQL_NEVERTRUE, [], false);
            }
            [$insql, $inparams] = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, $paramprefix);
            $sql = "e.id $insql";
            $params = $inparams;
        }
        $usecontent = false;
        return array($sql, $params, $usecontent);
    }

    private function wrap_ids($value) {
        return "\"$value\"";
    }

    private function get_users_with_profile_field_value($profilefieldname, $operator, $value, $not) {
        global $DB, $USER;

        $sql = $not ? 
                "SELECT u.id
                FROM {user} u
                WHERE u.$profilefieldname != ?"
                : "SELECT u.id
                FROM {user} u
                WHERE u.$profilefieldname = ?";

        $searchvalue = ($operator == self::OPERATOR_MY_PROFILE_FIELD) ? $USER->$profilefieldname : $value;

        return $DB->get_records_sql($sql, array($searchvalue));
    }

    public function parse_search($formdata, $i) {
        global $USER;
        $fieldid = $this->field->id;
        $internalname = $this->field->internalname;
        $operator = !empty($formdata->{"searchoperator{$i}"}) ? $formdata->{"searchoperator{$i}"} : '';
        $fieldvalue = !empty($formdata->{"f_{$i}_$fieldid"}) ? $formdata->{"f_{$i}_$fieldid"} : false;
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

        $distinctvalues = [];
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
