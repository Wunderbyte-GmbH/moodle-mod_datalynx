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
 * @package mod_datalynx
 * @subpackage _approve
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\field\datalynxfield_no_content;

/**
 * Field class for the internal approval field.
 *
 * @package mod_datalynx
 * @subpackage _approve
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynxfield__approve extends datalynxfield_no_content {
    /** @var string The field type identifier. */
    public $type = '_approve';

    /** @var string Internal name constant for the approved field object. */
    const _APPROVED = 'approve';

    /**
     * Returns field objects for each internal approve field variant.
     *
     * @param int $dataid The datalynx instance id.
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];

        $fieldobjects[self::_APPROVED] = (object) ['id' => self::_APPROVED,
                'dataid' => $dataid, 'type' => '_approve', 'name' => get_string('approved', 'datalynx'),
                'description' => '', 'visible' => 2, 'internalname' => 'approved'];

        return $fieldobjects;
    }

    /**
     * Returns true because this is an internal field type.
     *
     * @return bool
     */
    public static function is_internal() {
        return true;
    }

    /**
     * Returns the internal DB column name for this field.
     *
     * @return string
     */
    public function get_internalname() {
        return $this->field->internalname;
    }

    /**
     * Returns the SQL fragment used to sort by this field.
     *
     * @return string
     */
    public function get_sort_sql() {
        return 'e.approved';
    }

    /**
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        $value = $search[2];
        return [" e.approved = $value ", [], false];
    }

    /**
     * Parses submitted search form data for this field.
     *
     * @param object $formdata The submitted form data.
     * @param int    $i        The search field index.
     * @return mixed The parsed search value or false.
     */
    public function parse_search($formdata, $i) {
        $fieldid = $this->field->id;
        if (isset($formdata->{"f_{$i}_$fieldid"})) {
            return $formdata->{"f_{$i}_$fieldid"};
        } else {
            return false;
        }
    }

    /**
     * returns an array of distinct content of the field
     */
    public function get_distinct_content($sortdir = 0) {
        return ['approved', 'Not approved'];
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
