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
 * @package datalynxfield_select
 * @subpackage select
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_select;

use core_text;
use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\field\datalynxfield_option_single;

/**
 * Field class for the select field type.
 *
 * @package datalynxfield_select
 */
class field extends datalynxfield_option_single {
    /** @var string The field type. */
    public $type = 'select';

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     *
     * {@inheritDoc}
     * @param string $column The database column name.
     * @return string
     * @see datalynxfield_base::get_sql_compare_text()
     */
    protected function get_sql_compare_text(string $column = 'content'): string {
        global $DB;
        return $DB->sql_compare_text("c{$this->field->id}.$column", 255);
    }

    /**
     *
     * {@inheritDoc}
     * @param mixed $value The search value to look up.
     * @return mixed The corresponding option key or empty string.
     * @see datalynxfield_base::get_search_value()
     */
    public function get_search_value($value) {
        $options = $this->options_menu();
        if ($key = array_search($value, $options)) {
            return $key;
        } else {
            return '';
        }
    }

    /**
     * Returns the number of arguments required by the given operator.
     *
     * @param string $operator The operator.
     * @return int
     */
    public function get_argument_count(string $operator) {
        if ($operator === "") { // Empty operator.
            return 0;
        } else {
            return 1;
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_option_single::get_supported_search_operators()
     * @return array
     */
    public function get_supported_search_operators() {
        return [
            'ANY_OF' => get_string('anyof', 'datalynx'),
            'MY_PROFILE' => get_string('matchesmyprofilefield', 'datalynxfield_select'),
            '' => get_string('empty', 'datalynx'),
        ];
    }

    /**
     * Extracts the search value for this field from submitted form data.
     *
     * For the MY_PROFILE operator the operand is the shortname of a user profile field
     * (rendered as a separate dropdown), so it is read from its own form element.
     *
     * {@inheritDoc}
     * @see \mod_datalynx\local\field\datalynxfield_option::parse_search()
     * @param \stdClass $formdata Form data object.
     * @param int $i Filter index.
     * @return mixed
     */
    public function parse_search($formdata, $i) {
        // Read operator from $formdata first (AJAX / dynamic-form context), fall back to $_POST.
        $operatorkey = "searchoperator$i";
        $operator = isset($formdata->$operatorkey)
            ? clean_param($formdata->$operatorkey, PARAM_ALPHANUMEXT)
            : optional_param($operatorkey, '', PARAM_ALPHANUMEXT);
        if ($operator === 'MY_PROFILE') {
            $profilekey = "f_{$i}_{$this->field->id}_profile";
            $shortname = isset($formdata->$profilekey)
                ? clean_param($formdata->$profilekey, PARAM_ALPHANUMEXT)
                : optional_param($profilekey, '', PARAM_ALPHANUMEXT);
            return $shortname !== '' ? $shortname : false;
        }
        return parent::parse_search($formdata, $i);
    }

    /**
     * Get search sql for this field.
     *
     * Adds the MY_PROFILE operator on top of the standard option search: it resolves the
     * current user's value for the chosen profile field, maps it to the matching option key
     * and then reuses the regular equality search so NOT handling and joins stay consistent.
     *
     * {@inheritDoc}
     * @see datalynxfield_option_single::get_search_sql()
     * @param array $search Search criteria array [$not, $operator, $value].
     * @return array
     */
    public function get_search_sql(array $search): array {
        if (($search[1] ?? '') === 'MY_PROFILE') {
            $not = $search[0];
            $optionkey = $this->resolve_my_profile_option_key($search[2]);
            if ($optionkey === false) {
                // The user's profile value matches no option: use an impossible key so the
                // standard equality search matches nothing (and NOT matches everything else).
                $optionkey = -1;
            }
            return parent::get_search_sql([$not, '=', $optionkey]);
        }
        return parent::get_search_sql($search);
    }

    /**
     * Resolves the current user's value for the given profile field and maps it to the
     * matching select option key.
     *
     * @param mixed $value The stored operand: the profile field shortname.
     * @return int|false The matching option key, or false if there is no match.
     */
    protected function resolve_my_profile_option_key($value) {
        global $USER;

        $shortname = is_array($value) ? reset($value) : $value;
        if (empty($shortname)) {
            return false;
        }

        $profilevalue = $this->resolve_user_profile_value($USER, (string) $shortname);
        return $this->get_option_key_for_value($profilevalue);
    }

    /**
     * Maps a value to the option key whose label matches it (case-insensitive, trimmed).
     *
     * Shared by the MY_PROFILE filter operator and the teammemberbyprofile rule so both match
     * a profile value against the field options in exactly the same way.
     *
     * @param string|null $value The value to look up against this field's option labels.
     * @return int|false The matching option key, or false if there is no match.
     */
    public function get_option_key_for_value(?string $value) {
        if ($value === null || trim($value) === '') {
            return false;
        }

        $target = core_text::strtolower(trim($value));
        foreach ($this->options_menu() as $key => $label) {
            if (core_text::strtolower(trim((string) $label)) === $target) {
                return (int) $key;
            }
        }
        return false;
    }

    /**
     * Reads a user's profile field value, supporting both standard user fields
     * (e.g. department, institution) and custom profile fields (by shortname).
     *
     * @param \stdClass $user The user record (typically $USER).
     * @param string $shortname The profile field shortname.
     * @return string|null The value, or null if the field is not set for the user.
     */
    public function resolve_user_profile_value($user, string $shortname): ?string {
        global $CFG;

        // Standard user-table field.
        if (isset($user->$shortname) && is_scalar($user->$shortname)) {
            return (string) $user->$shortname;
        }

        // Custom profile field by shortname.
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $custom = profile_user_record($user->id);
        if (isset($custom->$shortname) && is_scalar($custom->$shortname)) {
            return (string) $custom->$shortname;
        }

        return null;
    }

    /**
     * Builds the menu of profile fields that can be matched against this field's options:
     * a curated set of standard user fields plus all custom profile fields.
     *
     * @return array shortname => human readable label
     */
    public static function get_profile_field_menu(): array {
        global $DB;

        $menu = [];
        $standard = ['department', 'institution', 'city', 'address', 'country', 'idnumber'];
        foreach ($standard as $name) {
            $menu[$name] = get_string($name) . ' (' . get_string('user') . ')';
        }

        $custom = $DB->get_records_menu('user_info_field', null, 'name', 'shortname, name');
        foreach ($custom as $shortname => $name) {
            $menu[$shortname] = format_string($name);
        }

        return $menu;
    }
}
