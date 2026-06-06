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

namespace datalynxfield_submit;

use mod_datalynx\local\field\datalynxfield_no_content;


/**
 * Submit field class for datalynx.
 *
 * @package    datalynxfield_submit
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_no_content {
    /** @var string Field type. */
    public $type = 'submit';

    /**
     * Returns field objects for the internal submit field.
     *
     * @param int $dataid The datalynx activity id.
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];
        $fieldobjects['submit'] = (object) [
            'id' => 'submit',
            'dataid' => $dataid,
            'type' => 'submit',
            'name' => get_string('submit', 'datalynxfield_submit'),
            'description' => '',
            'internalname' => 'submit',
        ];
        return $fieldobjects;
    }

    /**
     * Informs about the internal status of the field.
     *
     * @return boolean always true
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
}
