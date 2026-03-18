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
 * @subpackage _status
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

/**
 * Internal status field.
 */
class datalynxfield__status extends datalynxfield_no_content {
    /** @var string Field type. */
    public $type = '_status';

    /** @var string Status name. */
    const _STATUS = 'status';

    /** @var int Constant for Not Set status. */
    const STATUS_NOT_SET = 0;

    /** @var int Constant for Draft status. */
    const STATUS_DRAFT = 1;

    /** @var int Constant for Final Submission status. */
    const STATUS_FINAL_SUBMISSION = 2;

    /** @var int Constant for Submission status. */
    const STATUS_SUBMISSION = 3;

    /**
     * Return field objects for this type.
     *
     * @param int $dataid
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];

        $fieldobjects[self::_STATUS] = (object) ['id' => self::_STATUS, 'dataid' => $dataid,
                'type' => '_status', 'name' => get_string('status', 'datalynx'), 'description' => '',
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
     * Check if field is editable.
     *
     * @return bool
     */
    public function is_editable() {
        return true;
    }

    /**
     * Return internal name.
     *
     * @return string
     */
    public function get_internalname() {
        return $this->field->internalname;
    }

    /**
     * Return SQL for ORDER BY clause.
     *
     * @return string
     */
    public function get_sort_sql() {
        return 'e.status';
    }

    /**
     * Parse search data.
     *
     * @param object $formdata
     * @param int $i
     * @return mixed
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
