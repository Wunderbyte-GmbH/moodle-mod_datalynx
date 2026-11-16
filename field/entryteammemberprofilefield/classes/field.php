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
 * @package datalynxfield_entryteammemberprofilefield
 * @subpackage entryteammemberprofilefield
 * @copyright 2025 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entryteammemberprofilefield;

use datalynxfield_no_content_can_join;
use datalynxfield_teammemberselect\field as datalynxfield_teammemberselect;
use mod_datalynx\local\field\datalynxfield_base;
use stdClass;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/datalynx/field/datalynxfield_no_content_can_join.php');

/**
 * Field class for the entryteammemberprofilefield field type.
 *
 * @package    datalynxfield_entryteammemberprofilefield
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_no_content_can_join {
    /**
     * No input: nothing to enter.
     *
     * @param array $options
     * @return array
     */
    public function get_input_contract(array $options = []): array {
        return [];
    }

    /**
     * Never empty. The value is read from the profiles of the team members; there is nothing to enter.
     *
     * @param string $name
     * @param stdClass $data
     * @return bool
     */
    public function is_submitted_value_empty(string $name, stdClass $data): bool {
        return false;
    }

    /** @var string The field type. */
    public $type = 'entryteammemberprofilefield';

    /** SQL expression that is never true. */
    const SQL_NEVERTRUE = "1 = 0";

    /** Operator constant for matching current user's profile field. */
    const OPERATOR_MY_PROFILE_FIELD = 'MY_PROFILE_FIELD';
    /** Operator constant for matching a literal value. */
    const OPERATOR_LITERAL_VALUE = 'LITERAL_VALUE';

    /** @var string[] The profile fields of team members that can be searched. */
    const PROFILE_FIELDS = ['institution', 'department'];

    /**
     * Returns false to indicate this field does not support group by.
     *
     * @return bool
     */
    public function supports_group_by(): bool {
        return false;
    }

    /**
     * Returns true to indicate this is an internal field.
     */
    public static function is_internal(): bool {
        return true;
    }

    /**
     * Returns field objects for team member profile fields.
     *
     * @param int $dataid
     * @param array $fields
     * @return array
     */
    public static function get_field_objects($dataid, $fields = []): array {
        $fieldobjects = [];

        $teammemberselectfields = array_filter($fields, function ($field) {
            return $field instanceof datalynxfield_teammemberselect;
        });

        $userprofilefields = self::PROFILE_FIELDS;

        foreach ($teammemberselectfields as $field) {
            $fieldname = $field->field->name;
            foreach ($userprofilefields as $profilefield) {
                $fieldid = 'entryteammemberprofilefield_' . $field->field->id . "_$profilefield";
                $fieldobjects[$fieldid] = (object) ['id' => $fieldid,
                    'dataid' => $dataid, 'type' => 'entryteammemberprofilefield',
                    'name' => $fieldname . ' ' . get_string($profilefield), 'description' => '',
                    'internalname' => $fieldid];
            }
        }

        return $fieldobjects;
    }

    /**
     * Returns SQL for comparing the field content as text.
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
     * Return JOIN SQL string or empty string for search.
     *
     * @return string
     */
    public function get_search_from_sql(): string {
        // The search selects entry ids from the decoded member lists; it needs no join.
        return '';
    }

    /**
     * Get field id and name as an array.
     *
     * @return array
     */
    private function get_field_id_components(): array {
        $components = explode("_", $this->field->id);
        return [
            'queriedfieldid' => $components[1],
            'profilefieldname' => $components[2],
        ];
    }

    /**
     * Returns empty string as this field has no sort SQL.
     */
    public function get_sort_sql(): string {
        return "";
    }

    /**
     * {@inheritDoc}
     *
     * An entry matches when its team holds a user whose profile field has the value: a literal
     * value, or the current user's own value. The member lists are decoded, so they match in
     * whatever form they are stored. An empty value matches nothing (an empty own profile field
     * must not open up every team); deleted users are not members. NOT selects the entries
     * whose team holds no such user, entries without a team included.
     *
     * @param array $search Search parameters array [not, operator, value].
     * @return array SQL fragment, params, and join flag.
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        global $DB;
        static $i = 0;
        $i++;

        [$not, $operator, $value] = $search;
        if ($operator !== self::OPERATOR_LITERAL_VALUE && $operator !== self::OPERATOR_MY_PROFILE_FIELD) {
            return ['', [], false];
        }

        $entryids = [];
        $userids = $this->get_users_with_profile_field_value($operator, (string) $value);
        $teamfield = $userids ? $this->dlx->get_field_from_id($this->get_field_id_components()['queriedfieldid']) : null;
        if ($teamfield instanceof datalynxfield_teammemberselect) {
            $members = $teamfield->get_all_userids_in_all_entries();
            foreach ($userids as $userid) {
                foreach ($members[$userid] ?? [] as $entryid) {
                    $entryids[$entryid] = (int) $entryid;
                }
            }
        }

        if (!$entryids) {
            return [$not ? ' 1 = 1 ' : ' 1 = 0 ', [], false];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_values($entryids), SQL_PARAMS_NAMED, "etmpf{$i}_", !$not);
        return [" e.id $insql ", $params, false];
    }

    /**
     * The ids of the active users whose profile field has the searched value.
     *
     * @param string $operator LITERAL_VALUE or MY_PROFILE_FIELD
     * @param string $value the literal value
     * @return int[]
     */
    private function get_users_with_profile_field_value(string $operator, string $value): array {
        global $DB, $USER;

        $profilefield = $this->get_field_id_components()['profilefieldname'];
        if (!in_array($profilefield, self::PROFILE_FIELDS, true)) {
            return [];
        }
        $searchvalue = $operator === self::OPERATOR_MY_PROFILE_FIELD ? (string) ($USER->$profilefield ?? '') : $value;
        if (trim($searchvalue) === '') {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_select(
            'user',
            'id',
            "deleted = 0 AND $profilefield = :value",
            ['value' => trim($searchvalue)]
        ));
    }

    /**
     * Parses the search form data and returns the search value.
     *
     * @param stdClass $formdata
     * @param int $i
     * @return mixed
     */
    public function parse_search($formdata, $i) {
        $fieldid = $this->field->id;
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
     *
     * @param int $sortdir Sort direction: 0 for ASC, 1 for DESC.
     * @return array Array of distinct field values.
     */
    public function get_distinct_content($sortdir = 0): array {
        global $DB;
        $sortdir = $sortdir ? 'DESC' : 'ASC';
        $contentfull = $this->get_sort_sql();
        $sql = "SELECT DISTINCT $contentfull
                  FROM {user} u
                       JOIN {datalynx_entries} e ON u.id = e.userid
                 WHERE e.dataid = ? AND  $contentfull IS NOT NULL
                 ORDER BY $contentfull $sortdir";

        $distinctvalues = [];
        if ($options = $DB->get_records_sql($sql, [$this->dlx->id()])) {
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
     * Returns the supported search operators for this field.
     *
     * @return array
     */
    public function get_supported_search_operators(): array {
        return [
            '' => '&lt;' . get_string('choose') . '&gt;',
            self::OPERATOR_LITERAL_VALUE => get_string('literalvalue', 'datalynxfield_entryteammemberprofilefield'),
            self::OPERATOR_MY_PROFILE_FIELD => get_string('myprofilefield', 'datalynxfield_entryteammemberprofilefield'),
        ];
    }

    /**
     * Returns the number of arguments the given operator requires.
     *
     * @param string $operator
     * @return int
     */
    public function get_argument_count(string $operator): int {
        if ($operator === self::OPERATOR_MY_PROFILE_FIELD) {
            return 0;
        } else {
            return 1;
        }
    }
}
