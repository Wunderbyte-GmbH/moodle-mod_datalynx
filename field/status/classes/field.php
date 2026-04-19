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
 * @package datalynxfield_status
 * @subpackage _status
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_status;

use coding_exception;
use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\field\datalynxfield_no_content;

/**
 * Status field class for datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_no_content {
    /** @var string Field type. */
    public $type = 'status';

    /** @var string Internal name for the status field object. */
    const _STATUS = 'status';

    /** @var int Status not set value. */
    const STATUS_NOT_SET = 0;

    /** @var int Status draft value. */
    const STATUS_DRAFT = 1;

    /** @var int Status final submission value. */
    const STATUS_FINAL_SUBMISSION = 2;

    /** @var int Status submission value. */
    const STATUS_SUBMISSION = 3;

    /**
     * Returns field objects for the internal status field.
     *
     * @param int $dataid The datalynx activity id.
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];

        $fieldobjects[self::_STATUS] = (object) ['id' => self::_STATUS, 'dataid' => $dataid,
                'type' => 'status', 'name' => get_string('status', 'datalynx'), 'description' => '',
                'visible' => 2, 'internalname' => 'status'];

        return $fieldobjects;
    }

    /**
     * informs about the internal status of the field.
     *
     * @return boolean always true
     */
    public static function is_internal() {
        return true;
    }

    /**
     * Returns true to indicate this field is editable.
     *
     * @return bool
     */
    public function is_editable() {
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
        return 'e.status';
    }

    /**
     * {@inheritDoc}
     * @param array $search Search criteria array.
     * @return array
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        static $i = 0;
        $not = $search[0];
        $value = $search[2];
        $name = "status_$i";
        $i++;
        $value = $value < 0 ? 0 : $value;
        return [" $not (e.status = :$name) ", [$name => $value], false];
    }

    /**
     * Parses submitted search form data for this field.
     *
     * @param object $formdata The submitted form data.
     * @param int $i The search index.
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
     *
     * @return array
     * @throws coding_exception
     */
    public function get_supported_search_operators() {
        return ['=' => get_string('equal', 'datalynx')];
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
