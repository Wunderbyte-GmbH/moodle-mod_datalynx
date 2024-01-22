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
 * @copyright David Bogner 2021 based on 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
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

    /**
     * @var stdClass the db record
     */
    private $record;

    /**
     * Constructor for behavior instance. Unserializes serialized data fetched from behavior table.
     *
     * @param $record
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
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

    /**
     * Given the name and tha datalynx id of the behavior instantiate the object.
     *
     * @param $name
     * @param $dataid
     * @return datalynx_field_behavior|false
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function from_name($name, $dataid) {
        global $DB;
        $record = $DB->get_record('datalynx_behaviors', array('name' => $name, 'dataid' => $dataid));
        if ($record) {
            return new datalynx_field_behavior($record);
        } else {
            return false; // TODO: or throw exception?
        }
    }

    /**
     * Get behavior object from the behavior id.
     *
     * @param $id
     * @return datalynx_field_behavior|false
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function from_id($id) {
        global $DB;
        $record = $DB->get_record('datalynx_behaviors', array('id' => $id));
        if ($record) {
            return new datalynx_field_behavior($record);
        } else {
            return false; // TODO: or throw exception?
        }
    }

    /**
     * @var array default behavior for any field.
     */
    private static $default = array('id' => 0, 'name' => '', 'description' => '',
            'visibleto' => array('permissions' => array(mod_datalynx\datalynx::PERMISSION_MANAGER,
                    mod_datalynx\datalynx::PERMISSION_TEACHER,
                    mod_datalynx\datalynx::PERMISSION_STUDENT,
                    mod_datalynx\datalynx::PERMISSION_AUTHOR,
                    mod_datalynx\datalynx::PERMISSION_GUEST)),
            'editableby' => [mod_datalynx\datalynx::PERMISSION_MANAGER, mod_datalynx\datalynx::PERMISSION_TEACHER,
                    mod_datalynx\datalynx::PERMISSION_STUDENT, mod_datalynx\datalynx::PERMISSION_AUTHOR], 'required' => false);

    /**
     * The default behavior used in any instance without user settings applied.
     *
     * @param \mod_datalynx\datalynx $datalynx
     * @return datalynx_field_behavior
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
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

    /**
     * Checks if a field in an entry is visible to the current user.
     *
     * @param null $user
     * @param stdClass $entry
     * @return bool
     */
    public function is_visible_to_user(stdClass $entry): bool {
        global $USER;
        if (!isset($entry->userid)) {
            // This is for creating a new entry and no author yet defined. Assuming the user is going to be the author.
            $isentryauthor = true;
        } else {
            $isentryauthor = $entry->userid == $USER->id;
        }
        // If user is member of teammemberselect field allowed to view overrule.
        if ($this->is_visible_to_teammember($entry)) {
            return true;
        }
        // If special visibletouser is set overrule other visibility options.
        if (isset($this->visibleto['users']) && in_array($USER->id, $this->visibleto['users'])) {
            return true;
        }

        $permissions = $this->datalynx->get_user_datalynx_permissions($USER->id, 'view');
        $visible = [];
        if (isset($this->visibleto['permissions'])) {
            $visible = array_values($this->visibleto['permissions']);
        }
        // Make a simple array.
        return $this->user_is_admin($USER) || (array_intersect($permissions, $this->visibleto['permissions'])) ||
                ($isentryauthor && in_array(mod_datalynx\datalynx::PERMISSION_AUTHOR, $visible));
    }

    /**
     * Checks if user is a teammember of the teammemberselect field allowed to view the field.
     *
     * @param stdClass $entry
     * @return bool
     */
    public function is_visible_to_teammember(stdClass $entry): bool {
        global $USER;
        // Teammemberselect fields that allow viewing of the field to the members of the field.
        if (isset($this->visibleto['teammember'])) {
            // Teammemberselect fields that allow viewing of the field to the members of the field.
            $allowedfieldids = $this->visibleto['teammember'];
            if (!empty($allowedfieldids)) {
                foreach ($allowedfieldids as $fieldid) {
                    $userids = isset($entry->{"c{$fieldid}_content"}) ? json_decode(
                            $entry->{"c{$fieldid}_content"}, true) : [];
                    if (in_array($USER->id, $userids)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     *  Checks if a field in an entry is editable by the current user.
     *
     * @param null $user
     * @param bool $isentryauthor
     * @param bool $ismentor
     * @return bool
     * @throws coding_exception
     */
    public function is_editable_by_user($user = null, bool $isentryauthor = false, bool $ismentor = false) {
        global $USER;
        $user = $user ? $user : $USER;
        $permissions = $this->datalynx->get_user_datalynx_permissions($user->id, 'edit');
        return (array_intersect($permissions, $this->editableby)) ||
                ($isentryauthor && in_array(mod_datalynx\datalynx::PERMISSION_AUTHOR, $this->editableby)) ||
                ($ismentor && in_array(mod_datalynx\datalynx::PERMISSION_MENTOR, $this->editableby)) ||
                (isguestuser() && in_array(mod_datalynx\datalynx::PERMISSION_GUEST, $this->editableby));
    }

    /**
     * Given a db record make it ready for the form.
     *
     * @param stdClass $record
     * @return stdClass
     */
    public static function db_to_form(stdClass $record): stdClass {
        $formdata = new stdClass();
        $formdata->id = isset($record->id) ? $record->id : 0;
        $formdata->d = $record->dataid;
        $formdata->name = $record->name;
        $formdata->description = $record->description;
        $visible = unserialize($record->visibleto);
        if (isset($visible['permissions'])) {
            $formdata->visibletopermission = $visible['permissions'];
        }
        $formdata->visibletouser = $visible['users'] ?? [];
        $formdata->visibletoteammember = $visible['teammember'] ?? [];
        $formdata->editableby = unserialize($record->editableby);
        $formdata->required = $record->required;
        return $formdata;
    }

    /**
     * Prepare submitted form data for writing to db.
     *
     * @param stdClass $formdata
     * @return stdClass
     */
    public static function form_to_db(stdClass $formdata): stdClass {
        $record = new stdClass();
        $record->id = isset($formdata->id) ? $formdata->id : 0;
        $record->dataid = $formdata->d;
        $record->name = $formdata->name;
        $record->description = $formdata->description;

        // Prepare formdata for serialization to be saved into a single db column.
        if ($formdata->visibletopermission) {
            $formdata->visibleto['permissions'] = $formdata->visibletopermission;
        } else {
            $formdata->visibleto['permissions'] = [];
        }
        if ($formdata->visibletouser) {
            $formdata->visibleto['users'] = $formdata->visibletouser;
        } else {
            $formdata->visibleto['users'] = [];
        }
        if ($formdata->visibletoteammember) {
            $formdata->visibleto['teammember'] = $formdata->visibletoteammember;
        } else {
            $formdata->visibleto['teammember'] = [];
        }

        $record->visibleto = serialize($formdata->visibleto);
        $record->editableby = serialize(isset($formdata->editableby) ? $formdata->editableby : []);
        $record->required = $formdata->required;

        return $record;
    }

    /**
     * Prepare formdata and write prepared data to db.
     *
     * @param stdClass $formdata
     * @return bool|int
     * @throws dml_exception
     */
    public static function insert_behavior(stdClass $formdata) {
        global $DB;
        $record = self::form_to_db($formdata);
        return $DB->insert_record('datalynx_behaviors', $record);
    }

    /**
     * Given the behavior id, get data from db formatted for moodle form.
     *
     * @param int $behaviorid
     * @return stdClass
     * @throws dml_exception
     */
    public static function get_behavior(int $behaviorid): stdClass {
        global $DB;
        $record = $DB->get_record('datalynx_behaviors', array('id' => $behaviorid));
        return self::db_to_form($record);
    }

    /**
     * Duplicate a behavior instance.
     *
     * @param $behaviorid
     * @return bool|int
     * @throws coding_exception
     * @throws dml_exception
     */
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

    /**
     * Write new formdata to existing behavior.
     *
     * @param $formdata
     * @return mixed
     * @throws dml_exception
     */
    public static function update_behavior($formdata) {
        global $DB;
        $record = self::form_to_db($formdata);
        if ($DB->get_field('datalynx_renderers', 'name', ['id' => $record->id]) != $record->name) {
            self::update_behavior_pattern($record->id, $record->name);
        }
        $DB->update_record('datalynx_behaviors', $record);
        return $record->id;
    }

    /**
     * Deleta a behavior instance.
     *
     * @param $behaviorid
     * @return bool
     * @throws dml_exception
     */
    public static function delete_behavior($behaviorid) {
        global $DB;
        self::update_behavior_pattern($behaviorid);
        return $DB->delete_records('datalynx_behaviors', array('id' => $behaviorid));
    }

    /**
     * Delete behaviorer when $behaviorname is empty otherwise update view patterns.
     *
     * @param integer $behaviorid
     * @param string $behaviorname empty if it is deleted
     * @return bool success status of deletion
     */
    public static function update_behavior_pattern($behaviorid, $behaviorname = '') {
        global $DB;
        // Read dataid from DB and find patterns and param2 from all connected views.
        $behaviorinfo = $DB->get_record('datalynx_behaviors', array('id' => $behaviorid),
                $fields = 'dataid, name', $strictness = IGNORE_MISSING);
        $connected = $DB->get_records('datalynx_views', array('dataid' => $behaviorinfo->dataid),
                null, 'id, patterns, param2');
        // Update every instance that still has the string ||behaviorname in it.
        foreach ($connected as $view) {
            // TODO: Is one check enough or are these separate?
            if (strpos($view->patterns, '|' . $behaviorinfo->name) !== false ||
                    strpos($view->param2, '|' . $behaviorinfo->name) !== false) {
                if (strpos($view->param2, '|' . $behaviorinfo->name . '|')) {
                    $view->patterns = str_replace('|' . $behaviorinfo->name . '|', '|' . $behaviorname . '|', $view->patterns);
                    $view->param2 = str_replace('|' . $behaviorinfo->name . '|', '|' . $behaviorname . '|', $view->param2);
                } else {
                    if (!empty($behaviorname)) {
                        $behaviorname = '|' . $behaviorname;
                    }
                    $view->patterns = str_replace('|' . $behaviorinfo->name, $behaviorname, $view->patterns);
                    $view->param2 = str_replace('|' . $behaviorinfo->name, $behaviorname, $view->param2);
                }
            }
            $DB->update_record('datalynx_views', $view, $bulk = true);
        }
    }

    /**
     * Get id of behavior instance.
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the name of a behavior instance.
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get the description of a behavior instance.
     *
     * @return string
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Is the field required to be filled out by the user in the form?
     *
     * @return bool
     */
    public function is_required() {
        return $this->required;
    }
}
