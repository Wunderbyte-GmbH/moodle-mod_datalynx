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
 * @package datalynx_field_behavior
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();
require_once(dirname(__FILE__) . '/../classes/datalynx.php');

class datalynx_field_behavior {

    private $id;

    private $name;

    private $description;

    private $dataid;

    private $visibleto;

    private $editableby;

    private $required;

    /**
     *
     * @var datalynx related datalynx instance object
     */
    private $datalynx;

    /**
     *
     * @var stdClass related datalynx behavior DB record
     */
    private $record;

    private function __construct($record) {
        $this->id = $record->id;
        $this->name = $record->name;
        $this->description = $record->description;
        $this->dataid = $record->dataid;
        $this->visibleto = isset($record->visibleto) ? unserialize($record->visibleto) : array();
        $this->editableby = isset($record->editableby) ? unserialize($record->editableby) : array();
        $this->required = isset($record->required) ? $record->required : false;

        if (isset($record->datalynx)) {
            $this->datalynx = $record->datalynx;
        } else {
            $this->datalynx = new mod_datalynx\datalynx($record->dataid);
        }

        $this->record = $record;
    }

    public static function from_name($name, $dataid) {
        global $DB;
        $record = $DB->get_record('datalynx_behaviors', array('name' => $name, 'dataid' => $dataid));
        if ($record) {
            return new datalynx_field_behavior($record);
        } else {
            return false; // TODO: or throw exception?
        }
    }

    public static function from_id($id) {
        global $DB;
        $record = $DB->get_record('datalynx_behaviors', array('id' => $id));
        if ($record) {
            return new datalynx_field_behavior($record);
        } else {
            return false; // TODO: or throw exception?
        }
    }

    private static $default = array('id' => 0, 'name' => '', 'description' => '',
            'visibleto' => array(mod_datalynx\datalynx::PERMISSION_MANAGER, mod_datalynx\datalynx::PERMISSION_TEACHER,
                    mod_datalynx\datalynx::PERMISSION_STUDENT, mod_datalynx\datalynx::PERMISSION_AUTHOR, mod_datalynx\datalynx::PERMISSION_GUEST),
            'editableby' => array(mod_datalynx\datalynx::PERMISSION_MANAGER, mod_datalynx\datalynx::PERMISSION_TEACHER,
                    mod_datalynx\datalynx::PERMISSION_STUDENT, mod_datalynx\datalynx::PERMISSION_AUTHOR), 'required' => false);

    public static function get_default_behavior(mod_datalynx\datalynx $datalynx) {
        $record = (object) self::$default;
        $record->visibleto = serialize($record->visibleto);
        $record->editableby = serialize($record->editableby);
        $record->datalynx = $datalynx;
        $record->dataid = $datalynx->id();
        return new datalynx_field_behavior($record);
    }

    private function user_is_admin($user) {
        $admins = get_admins();
        return in_array($user->id, array_keys($admins));
    }

    public function is_visible_to_user($user = null, $isentryauthor = false, $ismentor = false) {
        global $USER;
        $user = $user ? $user : $USER;
        $permissions = $this->datalynx->get_user_datalynx_permissions($user->id, 'view');
        return $this->user_is_admin($user) || (array_intersect($permissions, $this->visibleto)) ||
        ($isentryauthor && in_array(mod_datalynx\datalynx::PERMISSION_AUTHOR, $this->visibleto)) ||
        ($ismentor && in_array(mod_datalynx\datalynx::PERMISSION_MENTOR, $this->visibleto));
    }

    public function is_editable_by_user($user = null, $isentryauthor = false, $ismentor = false) {
        global $USER;
        $user = $user ? $user : $USER;
        $permissions = $this->datalynx->get_user_datalynx_permissions($user->id, 'edit');
        return (array_intersect($permissions, $this->editableby)) ||
        ($isentryauthor && in_array(mod_datalynx\datalynx::PERMISSION_AUTHOR, $this->editableby)) ||
        ($ismentor && in_array(mod_datalynx\datalynx::PERMISSION_MENTOR, $this->editableby));
    }

    public static function db_to_form($record) {
        $formdata = new stdClass();
        $formdata->id = isset($record->id) ? $record->id : 0;
        $formdata->d = $record->dataid;
        $formdata->name = $record->name;
        $formdata->description = $record->description;
        $formdata->visibleto = unserialize($record->visibleto);
        $formdata->editableby = unserialize($record->editableby);
        $formdata->required = $record->required;

        return $formdata;
    }

    public static function form_to_db($formdata) {
        $record = new stdClass();
        $record->id = isset($formdata->id) ? $formdata->id : 0;
        $record->dataid = $formdata->d;
        $record->name = $formdata->name;
        $record->description = $formdata->description;
        $record->visibleto = serialize(isset($formdata->visibleto) ? $formdata->visibleto : []);
        $record->editableby = serialize(isset($formdata->editableby) ? $formdata->editableby : []);
        $record->required = $formdata->required;

        return $record;
    }

    public static function insert_behavior($formdata) {
        global $DB;
        $record = self::form_to_db($formdata);
        return $DB->insert_record('datalynx_behaviors', $record);
    }

    public static function get_behavior($behaviorid) {
        global $DB;
        $record = $DB->get_record('datalynx_behaviors', array('id' => $behaviorid));
        return self::db_to_form($record);
    }

    public static function duplicate_behavior($behaviorid) {
        global $DB;
        $object = self::get_behavior($behaviorid);
        $i = 0;
        do {
            $i++;
            $newname = get_string('copyof', 'datalynx', $object->name) . ' ' . $i;
        } while ($DB->record_exists('datalynx_behaviors', array('name' => $newname)));
        $object->name = $newname;
        return self::insert_behavior($object);
    }

    public static function update_behavior($formdata) {
        global $DB;
        $record = self::form_to_db($formdata);
        $DB->update_record('datalynx_behaviors', $record);
        return $record->id;
    }

    public static function delete_behavior($behaviorid) {
        global $DB;
        return $DB->delete_records('datalynx_behaviors', array('id' => $behaviorid));
    }

    /**
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     *
     * @return bool
     */
    public function is_required() {
        return $this->required;
    }
}
