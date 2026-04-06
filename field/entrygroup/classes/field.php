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
 * Entry group field class.
 *
 * @package    datalynxfield_entrygroup
 * @copyright  2013 onwards edulabs.org and associated programmers
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entrygroup;

use mod_datalynx\local\field\datalynxfield_no_content;

/**
 * Entry group field class.
 *
 * @package    datalynxfield_entrygroup
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field extends datalynxfield_no_content {
    /** @var string The field type. */
    public $type = 'entrygroup';

    /** @var string Group constant. */
    const _GROUP = 'entrygroup';

    /**
     * Check if the field is internal.
     *
     * @return bool
     */
    public static function is_internal() {
        return true;
    }

    /**
     * Get field objects for the group field.
     *
     * @param int $dataid The datalynx ID.
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];

        $fieldobjects[self::_GROUP] = (object) ['id' => self::_GROUP, 'dataid' => $dataid,
                'type' => 'entrygroup', 'name' => get_string('group', 'datalynxfield_entrygroup'),
                'description' => '', 'visible' => 2, 'internalname' => 'groupid'];

        return $fieldobjects;
    }

    /**
     * Get the internal name of the field.
     *
     * @return string
     */
    public function get_internalname() {
        return $this->field->internalname;
    }
}
