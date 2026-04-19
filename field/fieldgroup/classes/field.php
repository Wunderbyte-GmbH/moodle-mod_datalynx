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
 * Field class for the fieldgroup field type.
 *
 * @package    datalynxfield_fieldgroup
 * @subpackage fieldgroup
 * @copyright  2018 michael pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_fieldgroup;

use mod_datalynx\local\field\datalynxfield_base;
use stdClass;

/**
 * Field class for the fieldgroup field type.
 */
class field extends datalynxfield_base {
    /** @var string The field type identifier. */
    public $type = 'fieldgroup';

    /**
     * Fieldids of the fields belonging to the fieldgroup.
     * @var int[]
     */
    public $fieldids = [];

    /**
     * Constructor for the fieldgroup field.
     *
     * @param int $df The datalynx id.
     * @param int|stdClass $field The field id or object.
     */
    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        if (!empty($this->field->param1)) {
            $this->fieldids = json_decode($this->field->param1, true);
        }
    }

    /**
     * Get the content names for this field.
     *
     * @return string[]
     */
    protected function content_names() {
        return [''];
    }

    /**
     * Check if the field supports group by.
     *
     * @return bool
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * Get the supported search operators for this field.
     *
     * @return bool
     */
    public function get_supported_search_operators() {
        return false;
    }

    /**
     * Check if the field is a custom filter field.
     *
     * @return bool
     */
    public static function is_customfilterfield() {
        return false;
    }

    /**
     * Get the select SQL for this field.
     *
     * @return string
     */
    public function get_select_sql() {
        return '';
    }

    /**
     * Get the sort SQL for this field.
     *
     * @return string
     */
    public function get_sort_sql() {
        return '';
    }

    /**
     * Get the file area for this field.
     *
     * @param ?string $suffix
     * @return bool
     */
    protected function filearea($suffix = null) {
        return false;
    }
}
