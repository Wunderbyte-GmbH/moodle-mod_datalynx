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
 * @subpackage _entry
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

/**
 * Entry field class for datalynx.
 *
 * @package mod_datalynx
 */
class datalynxfield__entry extends datalynxfield_no_content {
    /** @var string Field type. */
    public $type = '_entry';

    /** @var string Entry constant. */
    const _ENTRY = 'entry';

    /**
     * Check if internal.
     *
     * @return bool
     */
    public static function is_internal() {
        return true;
    }

    /**
     * Get field objects.
     *
     * @param int $dataid
     * @return array
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = [];

        $fieldobjects[self::_ENTRY] = (object) ['id' => self::_ENTRY, 'dataid' => $dataid,
                'type' => '_entry', 'name' => get_string('entry', 'datalynx'), 'description' => '',
                'visible' => 2, 'internalname' => ''];

        return $fieldobjects;
    }
}
