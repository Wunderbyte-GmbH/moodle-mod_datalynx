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
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\field;
use mod_datalynx\datalynx;
use mod_datalynx;
use stdClass;


/**
 * Field renderer class
 *
 * @package mod_datalynx
 */
class datalynxfield_layout {
    /*
     * Make this more readable:
     * shownothing = '___0___'
     * asdisplay = '___1___'
     * custom = '___2___'
     * disabled = '___3___'
     * none = '___4___'
     * If *template is a signifier we assume it is an option.
     */

    // TODO: MDL-66151 Name consts sth. sane and integrate these in renderer form.
    /** @var string Not visible show nothing */
    const NOT_VISIBLE_SHOW_NOTHING = '___0___';

    /** @var string Not visible show custom */
    const NOT_VISIBLE_SHOW_CUSTOM = '___2___';

    /** @var string Display mode template none */
    const DISPLAY_MODE_TEMPLATE_NONE = '___0___';

    /** @var string Display mode template custom */
    const DISPLAY_MODE_TEMPLATE_CUSTOM = '___2___';

    /** @var string No value show nothing */
    const NO_VALUE_SHOW_NOTHING = '___0___';

    /** @var string No value show display mode template */
    const NO_VALUE_SHOW_DISPLAY_MODE_TEMPLATE = '___1___';

    /** @var string No value show custom */
    const NO_VALUE_SHOW_CUSTOM = '___2___';

    /** @var string Edit mode template none */
    const EDIT_MODE_TEMPLATE_NONE = '___0___';

    /** @var string Edit mode template as display mode */
    const EDIT_MODE_TEMPLATE_AS_DISPLAY_MODE = '___1___';

    /** @var string Edit mode template custom */
    const EDIT_MODE_TEMPLATE_CUSTOM = '___2___';

    /** @var string Not editable show nothing */
    const NOT_EDITABLE_SHOW_NOTHING = '___0___';

    /** @var string Not editable show as display mode */
    const NOT_EDITABLE_SHOW_AS_DISPLAY_MODE = '___1___';

    /** @var string Not editable show disabled */
    const NOT_EDITABLE_SHOW_DISABLED = '___3___';

    /** @var string Not editable show custom */
    const NOT_EDITABLE_SHOW_CUSTOM = '___2___';

    /** @var string Tag field value */
    const TAG_FIELD_VALUE = "#value";

    /** @var string Tag field name */
    const TAG_FIELD_NAME = "#name";

    /** @var int Renderer ID */
    private $id;

    /** @var string Renderer name */
    private $name;

    /** @var string Renderer description */
    private $description;

    /** @var int Datalynx ID */
    private $dataid;

    /** @var string Not visible template */
    private $notvisibletemplate;

    /** @var string Display template */
    private $displaytemplate;

    /** @var string No value template */
    private $novaluetemplate;

    /** @var string Edit template */
    private $edittemplate;

    /** @var string Not editable template */
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
     *
     * @param stdClass $record
     */
    private function __construct($record) {
        $this->id = $record->id;
        $this->name = $record->name;
        $this->description = $record->description;
        $this->dataid = $record->dataid;

        if (isset($record->datalynx)) {
            $this->datalynx = $record->datalynx;
        } else {
            $this->datalynx = new mod_datalynx\datalynx($record->dataid);
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
     *
     * @param string $name
     * @param int $dataid
     * @return datalynxfield_layout
     */
    public static function get_renderer_by_name($name, $dataid) {
        global $DB;
        $record = $DB->get_record(
            'datalynx_renderers',
            ['name' => $name, 'dataid' => $dataid],
            '*',
            MUST_EXIST
        );
        return new datalynxfield_layout($record);
    }

    /**
     * Static constructor method for datalynx_field_renderer:
     * Return the renderer object from db providing the renderer id
     *
     * @param int $id
     * @return datalynxfield_layout
     */
    public static function get_renderer_by_id($id) {
        global $DB;
        $record = $DB->get_record('datalynx_renderers', ['id' => $id], '*', MUST_EXIST);
        return new datalynxfield_layout($record);
    }

    /** @var array Default renderer values */
    private static $default = ['id' => 0, 'name' => '', 'description' => '',
            'notvisibletemplate' => self::NOT_VISIBLE_SHOW_NOTHING,
            'displaytemplate' => self::DISPLAY_MODE_TEMPLATE_NONE,
            'novaluetemplate' => self::NO_VALUE_SHOW_NOTHING,
            'edittemplate' => self::EDIT_MODE_TEMPLATE_NONE,
            'noteditabletemplate' => self::NOT_EDITABLE_SHOW_NOTHING,
    ];

    /**
     * Static constructor method for default datalynx_field_renderer
     *
     * @param datalynx $datalynx
     * @return datalynxfield_layout
     */
    public static function get_default_renderer(mod_datalynx\datalynx $datalynx) {
        $record = (object) self::$default;
        $record->datalynx = $datalynx;
        $record->dataid = $datalynx->id();
        return new datalynxfield_layout($record);
    }

    /**
     * Get renderer by ID
     *
     * @param int $rendererid
     * @return stdClass
     */
    public static function get_renderer($rendererid) {
        global $DB;
        $record = $DB->get_record('datalynx_renderers', ['id' => $rendererid]);
        return self::db_to_form($record);
    }

    /**
     * Convert DB record to form data
     *
     * @param stdClass $record
     * @return stdClass
     */
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

    /**
     * Convert form data to DB record
     *
     * @param stdClass $formdata
     * @return stdClass
     */
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
     *
     * @param mixed $formdata Form data to save.
     * @return bool|int true or new id
     */
    public static function insert_renderer($formdata) {
        global $DB;
        $record = self::form_to_db($formdata);
        return $DB->insert_record('datalynx_renderers', $record);
    }

    /**
     * Get DB record of a field renderer
     *
     * @param integer $rendererid
     * @return stdClass
     */
    public static function get_record($rendererid) {
        global $DB;
        return $DB->get_record('datalynx_renderers', ['id' => $rendererid]);
    }

    /**
     * Create a copy of a renderer
     *
     * @param integer $rendererid
     */
    public static function duplicate_renderer($rendererid) {
        global $DB;
        $object = self::get_renderer($rendererid);
        $i = 0;
        do {
            $i++;
            $newname = get_string('copyof', 'datalynx', $object->name) . ' ' . $i;
        } while ($DB->record_exists('datalynx_renderers', ['name' => $newname]));
        $object->name = $newname;
        return self::insert_renderer($object);
    }

    /**
     * Update renderer with submitted form data
     *
     * @param object $formdata
     * @return integer id
     */
    public static function update_renderer($formdata) {
        global $DB;
        $formdata->dataid = $formdata->d;
        // When name is altered we have to replace patterns.
        if ($DB->get_field('datalynx_renderers', 'name', ['id' => $formdata->id]) != $formdata->name) {
            self::update_render_pattern($formdata->id, $formdata->name);
        }
        $DB->update_record('datalynx_renderers', $formdata);
        return $formdata->id;
    }

    /**
     * Delete renderer.
     *
     * @param integer $rendererid
     * @return integer id
     */
    public static function delete_renderer($rendererid) {
        global $DB;
        self::update_render_pattern($rendererid);
        return $DB->delete_records('datalynx_renderers', ['id' => $rendererid]);
    }

    /**
     * Delete renderer when $renderername is empty otherwise update view patterns.
     *
     * @param integer $rendererid
     * @param string $renderername
     * @return bool success status of deletion
     */
    public static function update_render_pattern($rendererid, $renderername = '') {
        global $DB;
        if (!empty($renderername)) {
            $renderername = '|' . $renderername;
        }
        // Read dataid from DB and find patterns and param2 from all connected views.
        $rendererinfo = $DB->get_record(
            'datalynx_renderers',
            ['id' => $rendererid],
            $fields = 'dataid, name',
            $strictness = IGNORE_MISSING
        );
        $connected = $DB->get_records(
            'datalynx_views',
            ['dataid' => $rendererinfo->dataid],
            null,
            'id, patterns, param2'
        );
        // Update every instance that still has the string ||renderername in it.
        foreach ($connected as $view) {
            // TODO: MDL-66151 Is one check enough or are these separate?
            if (
                    strpos($view->patterns, '|' . $rendererinfo->name) !== false ||
                    strpos($view->param2, '|' . $rendererinfo->name) !== false
            ) {
                if (strpos($view->param2, '||' . $rendererinfo->name) !== false) {
                    $view->patterns = str_replace('||' . $rendererinfo->name, $renderername, $view->patterns);
                    $view->param2 = str_replace('||' . $rendererinfo->name, $renderername, $view->param2);
                } else {
                    $view->patterns = str_replace('|' . $rendererinfo->name, $renderername, $view->patterns);
                    $view->param2 = str_replace('|' . $rendererinfo->name, $renderername, $view->param2);
                }
                $DB->update_record('datalynx_views', $view, $bulk = true);
            }
        }
    }

    /**
     * Process renderer pattern
     */
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
