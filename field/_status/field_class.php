<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package dataformfield
 * @subpackage _status
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/dataform/field/field_class.php");

class dataformfield__status extends dataformfield_no_content {

    public $type = '_status';

    const _STATUS = 'status';

    const STATUS_NOT_SET            = 0; // 00
    const STATUS_DRAFT              = 1; // 01
    const STATUS_FINAL_SUBMISSION   = 2; // 10
    const STATUS_SUBMISSION         = 3; // 11

    /**
     *
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = array();

        $fieldobjects[self::_STATUS] = (object) array(
                'id' => self::_STATUS,
                'dataid' => $dataid,
                'type' => '_status',
                'name' => get_string('status', 'dataform'),
                'description' => '',
                'visible' => 2,
                'internalname' => 'status');

        return $fieldobjects;
    }

    /**
     * informs about the internal status of the field.
     * @return boolean always true
     */
    public static function is_internal() {
        return true;
    }

    public function is_editable() {
        return true;
    }

    /**
     *
     */
    public function get_internalname() {
        return $this->field->internalname;
    }

    /**
     *
     */
    public function get_sort_sql() {
        return 'e.status';
    }

    /**
     *
     */
    public function get_search_sql($search) {
        $value = $search[2];
        return array(" e.status = $value ", array());
    }

    /**
     *
     */
    public function parse_search($formdata, $i) {
        $fieldid = $this->field->id;
        if (isset($formdata->{"f_{$i}_$fieldid"})) {
            return $formdata->{"f_{$i}_$fieldid"};
        } else {
            return false;
        }
    }
}
