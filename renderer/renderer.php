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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package datalynx_field_renderer
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();
require_once (dirname(__FILE__) . '/../mod_class.php');


class datalynx_field_renderer {

    const NOT_VISIBLE_SHOW_NOTHING = 0;

    const NOT_VISIBLE_SHOW_CUSTOM = 1;

    const DISPLAY_MODE_TEMPLATE_NONE = 0;

    const DISPLAY_MODE_TEMPLATE_CUSTOM = 1;

    const NO_VALUE_SHOW_NOTHING = 0;

    const NO_VALUE_SHOW_DISPLAY_MODE_TEMPLATE = 1;

    const NO_VALUE_SHOW_CUSTOM = 2;

    const EDIT_MODE_TEMPLATE_NONE = 0;

    const EDIT_MODE_TEMPLATE_AS_DISPLAY_MODE = 1;

    const EDIT_MODE_TEMPLATE_CUSTOM = 2;

    const NOT_EDITABLE_SHOW_NOTHING = 0;

    const NOT_EDITABLE_SHOW_AS_DISPLAY_MODE = 1;

    const NOT_EDITABLE_SHOW_DISABLED = 2;

    const NOT_EDITABLE_SHOW_CUSTOM = 3;

    const TAG_FIELD_VALUE = "#value";

    const TAG_FIELD_NAME = "#name";

    private $id;

    private $name;

    private $description;

    private $dataid;

    private $notvisibletemplate;

    private $displaytemplate;

    private $novaluetemplate;

    private $edittemplate;

    private $noteditabletemplate;

    /**
     *
     * @var datalynx related datalynx instance object
     */
    private $datalynx;

    /**
     *
     * @var stdClass related datalynx renderer DB record
     */
    private $record;


    /**
     * Constructor: Create the datalynx_field_renderer object given the db record
     * @param fieldset record $record
     */
    private function __construct($record) {
        $this->id = $record->id;
        $this->name = $record->name;
        $this->description = $record->description;
        $this->dataid = $record->dataid;
        
        if (isset($record->datalynx)) {
            $this->datalynx = $record->datalynx;
        } else {
            $this->datalynx = new datalynx($record->dataid);
        }
        
        $this->notvisibletemplate = $record->notvisibletemplate;
        $this->displaytemplate = $record->displaytemplate;
        $this->novaluetemplate = $record->novaluetemplate;
        $this->edittemplate = $record->edittemplate;
        $this->noteditabletemplate = $record->noteditabletemplate;
        
        $this->record = $record;
    }

    /**
     * Static constructor method for datalynx_field_renderer: 
     * Return the renderer object using the name of the renderer and the dataid (datalynx id)
     * @param string $name
     * @param int $dataid
     * @return datalynx_field_renderer
     */
    public static function get_renderer_by_name($name, $dataid) {
        global $DB;
        $record = $DB->get_record('datalynx_renderers', array('name' => $name, 'dataid' => $dataid),
            '*', MUST_EXIST);
        return new datalynx_field_renderer($record);
    }

    /**
     * Static constructor method for datalynx_field_renderer: 
     * Return the renderer object from db providing the renderer id
     * @param int $id
     * @return datalynx_field_renderer
     */
    public static function get_renderer_by_id($id) {
        global $DB;
        $record = $DB->get_record('datalynx_renderers', array('id' => $id), '*', MUST_EXIST);
        return new datalynx_field_renderer($record);
    }

    private static $default = array('id' => 0, 'name' => '', 'description' => '', 
        'notvisibletemplate' => self::NOT_VISIBLE_SHOW_NOTHING, 
        'displaytemplate' => self::DISPLAY_MODE_TEMPLATE_NONE, 
        'novaluetemplate' => self::NO_VALUE_SHOW_NOTHING, 
        'edittemplate' => self::EDIT_MODE_TEMPLATE_NONE, 
        'noteditabletemplate' => self::NOT_EDITABLE_SHOW_NOTHING
    );

    /**
     * Static constructor method for default datalynx_field_renderer
     * @param datalynx $datalynx
     * @return datalynx_field_renderer
     */
    public static function get_default_renderer(datalynx $datalynx) {
        $record = (object) self::$default;
        $record->datalynx = $datalynx;
        $record->dataid = $datalynx->id();
        return new datalynx_field_renderer($record);
    }

    public static function get_renderer($rendererid) {
        global $DB;
        $record = $DB->get_record('datalynx_renderers', array('id' => $rendererid));
        return self::db_to_form($record);

    }

    public static function db_to_form($record) {
        $formdata = new stdClass();
        $formdata->id = isset($record->id) ? $record->id : 0;
        $formdata->d = $record->dataid;
        $formdata->name = $record->name;
        $formdata->description = $record->description;
        $formdata->notvisibletemplate = $record->notvisibletemplate;
        $formdata->displaytemplate = $record->displaytemplate;
        $formdata->novaluetemplate = $record->novaluetemplate;
        $formdata->edittemplate = $record->edittemplate;
        $formdata->noteditabletemplate = $record->noteditabletemplate;

        return $formdata;
    }

    public static function form_to_db($formdata) {
        $record = new stdClass();
        $record->id = isset($formdata->id) ? $formdata->id : 0;
        $record->dataid = $formdata->d;
        $record->name = $formdata->name;
        $record->description = $formdata->description;
        $record->notvisibletemplate = $formdata->notvisibletemplate;
        $record->displaytemplate = $formdata->displaytemplate;
        $record->novaluetemplate = $formdata->novaluetemplate;
        $record->edittemplate = $formdata->edittemplate;
        $record->noteditabletemplate = $formdata->noteditabletemplate;

        return $record;
    }

    /**
     * Save renderer to db
     * @param datalynx_field_renderer $record
     * @return bool|int true or new id  
     */
    public static function insert_renderer($formdata) {
        global $DB;
        $record = self::form_to_db($formdata);
        return $DB->insert_record('datalynx_renderers', $record);
    }

    /**
     * Get DB record of a field renderer
     * @param integer $rendererid
     * @return Ambigous <mixed, stdClass, false>
     */
    public static function get_record($rendererid) {
        global $DB;
        return $DB->get_record('datalynx_renderers', array('id' => $rendererid));
    }

    /**
     * Create a copy of a renderer
     * @param integer $rendererid
     */
    public static function duplicate_renderer($rendererid) {
        global $DB;
        $object = self::get_renderer($rendererid);
        $i = 0;
        do {
            $i++;
            $newname = get_string('copyof', 'datalynx', $object->name) . ' ' . $i;
        } while ($DB->record_exists('datalynx_renderers', array('name' => $newname)));
        $object->name = $newname;
        return self::insert_renderer($object);
    }

    /**
     * Update renderer with submitted form data
     * @param object $formdata
     */
    public static function update_renderer($formdata) {
        global $DB;
        $formdata->dataid = $formdata->d;
        $DB->update_record('datalynx_renderers', $formdata);
        return $formdata->id;
    }

    /**
     * delete a renderer
     * @param integer $rendererid
     */
    public static function delete_renderer($rendererid) {
        global $DB;
        return $DB->delete_records('datalynx_renderers', array('id' => $rendererid));
    }

    public function process_renderer_pattern() {
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
     * @return mixed
     */
    public function get_no_value_template() {
        if (is_numeric($this->novaluetemplate)) {
            return intval($this->novaluetemplate);
        } else {
            return $this->novaluetemplate;
        }
    }

    /**
     *
     * @return mixed
     */
    public function get_not_visible_template() {
        if (is_numeric($this->notvisibletemplate)) {
            return intval($this->notvisibletemplate);
        } else {
            return $this->notvisibletemplate;
        }
    }

    /**
     *
     * @return mixed
     */
    public function get_not_editable_template() {
        if (is_numeric($this->noteditabletemplate)) {
            return intval($this->noteditabletemplate);
        } else {
            return $this->noteditabletemplate;
        }
    }

    /**
     *
     * @return mixed
     */
    public function get_display_template() {
        if (is_numeric($this->displaytemplate)) {
            return intval($this->displaytemplate);
        } else {
            return $this->displaytemplate;
        }
    }

    /**
     *
     * @return mixed
     */
    public function get_edit_template() {
        if (is_numeric($this->edittemplate)) {
            return intval($this->edittemplate);
        } else {
            return $this->edittemplate;
        }
    }
}
