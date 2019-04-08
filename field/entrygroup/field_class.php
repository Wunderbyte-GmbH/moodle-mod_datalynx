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
 * @package datalynxfield
 * @subpackage entrygroup
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once($CFG->dirroot . '/mod/datalynx/field/field_class.php');

class datalynxfield_entrygroup extends datalynxfield_no_content {

    public $type = 'entrygroup';

    const _GROUP = 'entrygroup';

    /**
     */
    public static function is_internal() {
        return true;
    }

    /**
     */
    public static function get_field_objects($dataid) {
        $fieldobjects = array();

        $fieldobjects[self::_GROUP] = (object) array('id' => self::_GROUP, 'dataid' => $dataid,
                'type' => 'entrygroup', 'name' => get_string('group', 'datalynxfield_entrygroup'),
                'description' => '', 'visible' => 2, 'internalname' => 'groupid');

        return $fieldobjects;
    }

    /**
     */
    public function get_internalname() {
        return $this->field->internalname;
    }
}
