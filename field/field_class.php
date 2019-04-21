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
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . '/../classes/datalynx.php');
require_once(dirname(__FILE__) . '/../behavior/behavior.php');
require_once(dirname(__FILE__) . '/../renderer/renderer.php');

/**
 * Base class for Datalynx Field Types
 */
abstract class datalynxfield_base {

    const VISIBLE_NONE = 0;

    const VISIBLE_OWNER = 1;

    const VISIBLE_ALL = 2;

    /**
     * Subclasses must override the type with their name.
     * @var string Fieldtype usually datalynxfield_fieldtype.
     */
    public $type = 'unknown';

    /**
     * The datalynx object that this field belongs to.
     * @var datalynx
     */
    public $df = null;

    /**
     * The field object itself, if we know it
     * @var object
     */
    public $field = null;

    /**
     * @var datalynxfield_renderer
     */
    protected $_renderer = null;

    /**
     * @var array
     */
    protected $_distinctvalues = null;

    /**
     * Can this field be used in fieldgroups?
     * Only fields where user can enter data via a form can be used in a fieldgroup.
     * Override if yes.
     * @var boolean
     */
    protected $forfieldgroup = false;

    /**
     * Class constructor
     *
     * @param number $df datalynx id or class object
     * @param number $field fieldid or fieldobject
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct($df = 0, $field = 0) {
        if (empty($df)) {
            throw new coding_exception('Datalynx id or object must be passed to view constructor.');
        } else {
            if ($df instanceof \mod_datalynx\datalynx) {
                $this->df = $df;
            } else { // Datalynx id/object.
                $this->df = new mod_datalynx\datalynx($df);
            }
        }

        if (!empty($field)) {
            // Variable field is the field record.
            if (is_object($field)) {
                $this->field = $field; // Programmer knows what they are doing, we hope.
                // Variable $field is a field id.
            } else {
                if ($fieldobj = $this->df->get_field_from_id($field)) {
                    $this->field = $fieldobj->field;
                } else {
                    throw new moodle_exception('invalidfield', 'datalynx', null, null, $field);
                }
            }
        }

        if (empty($this->field)) { // We need to define some default values.
            $this->set_field();
        }
    }

    /**
     * Sets up a field object
     *
     * @param stdClass $forminput
     */
    public function set_field($forminput = null) {
        $this->field = new stdClass();
        $this->field->id = !empty($forminput->id) ? $forminput->id : 0;
        $this->field->type = $this->type;
        $this->field->dataid = $this->df->id();
        $this->field->name = !empty($forminput->name) ? trim($forminput->name) : '';
        $this->field->description = !empty($forminput->description) ? trim($forminput->description) : '';
        $this->field->visible = isset($forminput->visible) ? $forminput->visible : 2;
        $this->field->edits = isset($forminput->edits) ? $forminput->edits : -1;
        $this->field->label = !empty($forminput->label) ? $forminput->label : '';
        for ($i = 1; $i <= 10; $i++) {
            $this->field->{"param$i"} = !empty($forminput->{"param$i"}) ? trim(
                    $forminput->{"param$i"}) : null;
        }
    }

    /**
     * Insert a new field in the database
     */
    public function insert_field($fromform = null) {
        global $DB, $OUTPUT;

        if (!empty($fromform)) {
            $this->set_field($fromform);
        }

        if (!$this->field->id = $DB->insert_record('datalynx_fields', $this->field)) {
            echo $OUTPUT->notification('Insertion of new field failed!');
            return false;
        } else {
            return $this->field->id;
        }
    }

    /**
     * Update a field in the database
     */
    public function update_field($fromform = null) {
        global $DB, $OUTPUT;
        if (!empty($fromform)) {
            $this->set_field($fromform);
        }

        if (!$DB->update_record('datalynx_fields', $this->field)) {
            echo $OUTPUT->notification('updating of field failed!');
            return false;
        }
        return true;
    }

    /**
     * Delete a field completely
     */
    public function delete_field() {
        global $DB;

        if (!empty($this->field->id)) {
            if ($filearea = $this->filearea()) {
                $fs = get_file_storage();
                $success = $fs->delete_area_files($this->df->context->id, 'mod_datalynx', $filearea);
            }
            $this->delete_content();
            $DB->delete_records('datalynx_fields', array('id' => $this->field->id));
        }
        return true;
    }

    /**
     * Getter
     */
    public function get($var) {
        if (isset($this->field->$var)) {
            return $this->field->$var;
        } else {
            // TODO throw an exception if $var is not a property of field.
            return false;
        }
    }

    /**
     * Returns the field id
     */
    public function id() {
        return $this->field->id;
    }

    /**
     * Returns the field type
     */
    public function type() {
        return $this->type;
    }

    /**
     * Returns the name of the field
     */
    public function name() {
        return $this->field->name;
    }

    /**
     * Returns the type name of the field
     */
    public function typename() {
        return get_string('pluginname', "datalynxfield_{$this->type}");
    }

    /**
     * Prints the respective type icon
     */
    public function image() {
        global $OUTPUT;
        return $OUTPUT->pix_icon('icon', $this->type, "datalynxfield_{$this->type}");
    }

    /**
     */
    public function df() {
        return $this->df;
    }

    /**
     */
    public function get_form() {
        global $CFG;

        if (file_exists($CFG->dirroot . '/mod/datalynx/field/' . $this->type . '/field_form.php')) {
            require_once($CFG->dirroot . '/mod/datalynx/field/' . $this->type . '/field_form.php');
            $formclass = 'datalynxfield_' . $this->type . '_form';
        } else {
            require_once($CFG->dirroot . '/mod/datalynx/field/field_form.php');
            $formclass = 'datalynxfield_form';
        }
        $actionurl = new moodle_url('/mod/datalynx/field/field_edit.php',
                array('d' => $this->df->id(), 'fid' => $this->id(), 'type' => $this->type));
        return new $formclass($this, $actionurl);
    }

    /**
     * Is this field available for fieldgroups?
     * @return boolean
     */
    public function for_use_in_fieldgroup() {
        return $this->forfieldgroup;
    }

    /**
     */
    public function to_form() {
        return $this->field;
    }

    /**
     *
     * @return datalynxfield_renderer
     */
    public function renderer() {
        global $CFG;

        if (!$this->_renderer) {
            $rendererclass = "datalynxfield_{$this->type}_renderer";
            require_once("$CFG->dirroot/mod/datalynx/field/{$this->type}/renderer.php");
            $this->_renderer = new $rendererclass($this);
        }
        return $this->_renderer;
    }

    protected static $defaultoptions = array('manage' => false, 'visible' => false, 'edit' => false,
            'editable' => false, 'disabled' => false, 'required' => false, 'internal' => false
    );

    // CONTENT MANAGEMENT.

    /**
     * Get definition of tags/pattern
     *
     * @param $tags
     * @param $entry
     * @param array $options
     * @return array
     */
    public function get_definitions($tags, $entry, array $options) {
        return $this->renderer()->replacements($tags, $entry,
                array_merge(self::$defaultoptions, $options)); // FIXME:.
        // YOU
        // *MUST*
        // REMOVE
        // THIS
        // MERGE!
    }

    /**
     * @return bool
     */
    public static function is_internal() {
        return false;
    }

    /**
     * Update the content of a field in an entry. That happens in table datalynx_conents.
     *
     * @param stdClass $entry
     * @param array|null $values
     * @return bool|int
     * @throws dml_exception
     */
    public function update_content($entry, array $values = null) {
        global $DB;
        $fieldid = $this->field->id;
        $fieldgroup = false;

        $fieldid = $this->field->id;
        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;
        list($contents, $oldcontents) = $this->format_content($entry, $values);

        $rec = new stdClass();
        $rec->fieldid = $this->field->id;
        $rec->entryid = $entry->id;
        foreach ($contents as $key => $content) {
            $c = $key ? $key : '';
            $rec->{"content$c"} = $content;
        }

        // TODO: Bug found,
        // When we add a list of values but the first is empty, this insert is not triggered and the order is inserted wrong.

        // Insert only if no old contents and there is new contents.
        if (is_null($contentid) and !empty($contents)) {
            return $DB->insert_record('datalynx_contents', $rec);
        }

        // TODO: This needs upgrading, we don't delete the whole entry id but only the one value if other lines exist.
        // Delete if old content but not new.
        if (!is_null($contentid) and empty($contents)) {
            return $this->delete_content($entry->id);
        }

        // Update if new is different from old.
        if (!is_null($contentid)) {
            foreach ($contents as $key => $content) {
                if (!isset($oldcontents[$key]) or $content !== $oldcontents[$key]) {
                    $rec->id = $contentid; // MUST_EXIST.
                    return $DB->update_record('datalynx_contents', $rec);
                }
            }
        }
        return true;
    }

    /**
     * Delete all content associated with the field.
     *
     * @param int $entryid
     * @return bool
     * @throws dml_exception
     */
    public function delete_content($entryid = 0) {
        global $DB;

        if ($entryid) {
            $params = array('fieldid' => $this->field->id, 'entryid' => $entryid);
        } else {
            $params = array('fieldid' => $this->field->id);
        }

        $rs = $DB->get_recordset('datalynx_contents', $params);
        if ($rs->valid()) {
            $fs = get_file_storage();
            foreach ($rs as $content) {
                $fs->delete_area_files($this->df->context->id, 'mod_datalynx', 'content',
                        $content->id);
            }
        }
        $rs->close();

        return $DB->delete_records('datalynx_contents', $params);
    }

    /**
     * Returns an array of distinct content of the field (GROUP BY)
     *
     * @param int $sortdir
     * @return array
     * @throws dml_exception
     */
    public function get_distinct_content($sortdir = 0) {
        global $DB;

        if (is_null($this->_distinctvalues)) {
            $this->_distinctvalues = array();
            $fieldid = $this->field->id;
            $sortdir = $sortdir ? 'DESC' : 'ASC';
            $contentname = $this->get_sort_sql();
            $sql = "SELECT DISTINCT $contentname
            FROM {datalynx_contents} c$fieldid
            WHERE c$fieldid.fieldid = $fieldid AND $contentname IS NOT NULL
            ORDER BY $contentname $sortdir";

            if ($options = $DB->get_records_sql($sql)) {
                foreach ($options as $data) {
                    $value = isset($data->content) ? $data->content : '';
                    if ($value === '') {
                        continue;
                    }
                    $this->_distinctvalues[] = $value;
                }
            }
        }
        return $this->_distinctvalues;
    }

    /**
     * Prepares content from uploaded csv files to be saved to DB
     *
     * @param $data
     * @param $importsettings
     * @param null $csvrecord
     * @param null $entryid
     * @return bool
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        $fieldid = $this->field->id;
        $fieldname = $this->name();
        // TODO.
        // Ugly hack for internal fields.
        if ($this->is_internal()) {
            $setting = reset($importsettings);
            $csvname = $setting['name'];
        } else {
            $csvname = $importsettings[$fieldname]['name'];
        }

        if (isset($csvrecord[$csvname]) and $csvrecord[$csvname] !== '') {
            $data->{"field_{$fieldid}_{$entryid}"} = $csvrecord[$csvname];
        }

        return true;
    }

    /**
     * Retrieve the submitted form data for a specific field
     * and return as array indexed by contentname
     *
     * @param number $entryid
     * @param stdClass $data submitted form data
     * @return array with
     */
    public function get_content_from_data($entryid, $data) {
        $fieldid = $this->field->id;
        $content = array();
        $fieldgroups = [];
        foreach ($data as $key => $value) {
            if (strpos($key, 'fieldgroup_') === 0) {
                $fieldgroups[$value]  = $key;
            }
        }

        // Keeping this for backwards compatibility.
        foreach ($this->content_names() as $name) {
            // Add normal fields.
            $delim = $name ? '_' : '';
            $contentname = "field_{$fieldid}_$entryid" . $delim . $name;
            if (isset($data->$contentname)) {
                $content[$name] = $data->$contentname;
            }
            // Add fieldgroup fields.
            foreach ($fieldgroups as $fieldgroup) {
                // Search for fieldgroup.
                $contentname = "field_{$fieldid}_$entryid" . "_" . $fieldgroup . "_0" . $delim . $name;
                $i = 0;
                while (isset($data->$contentname)) {
                    $content[$fieldgroup] = true;
                    $content[$contentname] = $data->$contentname;
                    $i++;
                    $contentname = "field_{$fieldid}_$entryid" . "_" . $fieldgroup . "_" . $i . $delim . $name;
                }
            }
        }

        return $content;
    }

    /**
     * This function should be overriden in each field class which extends this base class.
     * If a field has more than one form element where user content is expected to be submitted
     * all of these elements have to be specified here
     * Example: Field of type file has these form elements: 'filemanager', 'alttext', 'delete',
     * 'editor'
     * So these values have to be returned like that: return array('filemanager', 'alttext',
     * 'delete', 'editor');
     *
     * @return array of strings
     */
    protected function content_names() {
        return array('');
    }

    /**
     * Formats content for database storage
     *
     * @param $entry stdClass object containing all the entry contents (from the database, NOT the
     *        form!)
     * @param array $values values from the entry form elements
     * @return array
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;

        $newcontent = null;
        $oldcontent = null;

        if (!empty($values)) {
            $resetted = reset($values);
            $newcontent = !empty($resetted) ? $resetted : "";
            if (!empty($newcontent) && is_array($newcontent)) {
                // When no value ist selected, then a default value is saved by Quickform. Value is removed here:.
                if (($key = array_search('_qf__force_multiselect_submission', $newcontent)) !== false) {
                    unset($newcontent[$key]);
                }
                $newcontent = implode(",", $newcontent);
            }
            $newcontent = (string) clean_param($newcontent, PARAM_NOTAGS);
        }

        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontent = $entry->{"c{$fieldid}_content"};
        }

        return array(array($newcontent), array($oldcontent));
    }

    /**
     * Checks whether the field supports 'group by' filtering option
     *
     * @return bool true if 'group by' is supported, false otherwise
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * To be overriden by classes, that extend the field base class
     * This returns all the column names of the columns used to save content of one specific field
     * in the table "datalynx_contents".
     * Values can be 'content', 'content1', 'content2', until 'content4'
     *
     * @return array of strings
     */
    public function get_content_parts() {
        return array('content');
    }

    /**
     * Get the select part of the sql query.
     *
     * @return string
     */
    public function get_select_sql() {
        if ($this->field->id > 0) {
            $arr = array();
            $arr[] = " c{$this->field->id}.id AS c{$this->field->id}_id ";
            foreach ($this->get_content_parts() as $part) {
                $arr[] = $this->get_sql_compare_text($part) . " AS c{$this->field->id}_$part";
            }
            $selectsql = implode(',', $arr);
            return " $selectsql ";
        } else {
            return '';
        }
    }

    /**
     * Get the sort part of the sql query.
     *
     * @param string $paramname
     * @param string $paramcount
     * @return array|null
     */
    public function get_sort_from_sql($paramname = 'sortie', $paramcount = '') {
        $fieldid = $this->field->id;
        if ($fieldid > 0) {
            $sql = " LEFT JOIN {datalynx_contents} c$fieldid
            ON (c$fieldid.entryid = e.id AND c$fieldid.fieldid = :$paramname$paramcount)";
            return array($sql, $fieldid);
        } else {
            return null;
        }
    }

    /**
     */
    public function get_sort_sql() {
        return $this->get_sql_compare_text();
    }

    /**
     */
    public function get_search_from_sql() {
        $fieldid = $this->field->id;
        if ($fieldid > 0) {
            return " LEFT JOIN {datalynx_contents} c$fieldid ON c$fieldid.entryid = e.id AND c$fieldid.fieldid = $fieldid ";
        } else {
            return '';
        }
    }

    /**
     *
     * @param $search
     * @return array|null $fieldsql, $fieldparams, $fromcontent
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_search_sql($search) {
        global $DB;

        list($not, $operator, $value) = $search;

        static $i = 0;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        // For all NOT criteria except NOT Empty, exclude entries.
        // Which don't meet the positive criterion.
        // Because some fields may not have content records.
        // And the respective entries may be filter out.
        // Despite meeting the criterion.
        $excludeentries = (($not and $operator !== '') or (!$not and $operator === ''));

        if ($excludeentries) {
            $varcharcontent = $DB->sql_compare_text('content');
        } else {
            $varcharcontent = $this->get_sql_compare_text();
        }

        if ($operator === '') {
            list($sql, $params) = $DB->get_in_or_equal('', SQL_PARAMS_NAMED, "df_{$fieldid}_",
                    false);
            $sql = " $varcharcontent $sql ";
        } else {
            if ($operator === '=') {
                $searchvalue = trim($value);
                list($sql, $params) = $DB->get_in_or_equal($searchvalue, SQL_PARAMS_NAMED,
                        "df_{$fieldid}_");
                $sql = " $varcharcontent $sql ";
            } else {
                if ($operator === 'IN') {
                    $searchvalue = array_map('trim', $value);
                    list($sql, $params) = $DB->get_in_or_equal($searchvalue, SQL_PARAMS_NAMED,
                            "df_{$fieldid}_");
                    $sql = " $varcharcontent $sql ";
                } else {
                    if (in_array($operator, array('LIKE', 'BETWEEN', ''))) {
                        $params = array($name => "%$value%");
                        $sql = $DB->sql_like($varcharcontent, ":$name", false);
                    } else {
                        $params = array($name => "'$value'");
                        $sql = " $varcharcontent $operator :$name ";
                    }
                }
            }
        }

        if ($excludeentries) {
            // Get entry ids for entries that meet the criterion.
            if ($eids = $this->get_entry_ids_for_content($sql, $params)) {
                // Get NOT IN sql.
                list($notinids, $params) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                        "df_{$fieldid}_", false);
                $sql = " e.id $notinids ";
                return array($sql, $params, false);
            } else {
                return null;
            }
        } else {
            return array($sql, $params, true);
        }
    }

    /**
     */
    protected function get_entry_ids_for_content($sql, $params) {
        global $DB;

        $sql = " fieldid = :fieldid AND $sql ";
        $params['fieldid'] = $this->id();
        return $DB->get_records_select_menu('datalynx_contents', $sql, $params, '', 'id,entryid');
    }

    /**
     */
    public function parse_search($formdata, $i) {
        $fieldid = $this->field->id;
        if (!empty($formdata->{"f_{$i}_$fieldid"})) {
            return $formdata->{"f_{$i}_$fieldid"};
        } else {
            return false;
        }
    }

    /**
     */
    public function format_search_value($searchparams) {
        list($not, $operator, $value) = $searchparams;
        return $not . ' ' . $operator . ' ' . $value;
    }

    /**
     */
    public function get_search_value($value) {
        return $value;
    }

    /**
     */
    protected function get_sql_compare_text($column = 'content') {
        global $DB;

        return $DB->sql_compare_text("c{$this->field->id}.$column");
    }

    /**
     * Whether this field provides join sql for fetching content
     *
     * @return bool
     */
    public function is_joined() {
        return false;
    }

    /**
     * Whether this field content resides in datalynx_contents
     *
     * @return bool
     */
    public function is_datalynx_content() {
        return true;
    }

    /**
     * Return file area of field
     *
     * @param string $suffix
     * @return string|boolean
     */
    protected function filearea($suffix = null) {
        if (!empty($suffix)) {
            return 'field-' . str_replace(' ', '_', $suffix);
        } else {
            if (!empty($this->field->name)) {
                return 'field-' . str_replace(' ', '_', $this->field->name);
            } else {
                return false;
            }
        }
    }

    /**
     * Returns operators supported by this field.
     * It contains all operators present in the previous
     * version and should therefore be overridden by a concrete
     * field class to remove unsupported operators from the list.
     *
     * @return array an array of operators
     */
    public function get_supported_search_operators() {
        return array(); // If search is not supported, offer no operators.
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return false;
    }
}

/**
 * Base class for Datalynx field types that require no content
 */
abstract class datalynxfield_no_content extends datalynxfield_base {

    public function update_content($entry, array $values = null) {
        return true;
    }

    public function delete_content($entryid = 0) {
        return true;
    }

    public function get_distinct_content($sortdir = 0) {
        return array();
    }

    public function get_select_sql() {
        return '';
    }

    public function get_sort_sql() {
        return '';
    }

    public function is_datalynx_content() {
        return false;
    }

    protected function filearea($suffix = null) {
        return false;
    }
}

/**
 * Base class for Datalynx field types that offer single choice or multiplce choices
 * from a set of options
 */
abstract class datalynxfield_option extends datalynxfield_base {

    protected $_options = array();

    /**
     * TODO: see if this can be changed or merged with function below
     *
     * @return mixed
     */
    public function get_options() {
        if (!$this->_options) {
            if (!empty($this->field->param1)) {
                $rawoptions = explode("\n", $this->field->param1);
                foreach ($rawoptions as $key => $option) {
                    $option = trim($option);
                    if ($option != '') {
                        $this->_options[$key + 1] = $option;
                    }
                }
            }
        }
        return $this->_options;
    }

    /**
     *
     * @param boolean $forceget
     * @return Ambigous <multitype:, string>
     */
    public function options_menu($forceget = false, $addnoselection = false) {
        if (!$this->_options or $forceget) {
            if (!empty($this->field->param1)) {
                if ($addnoselection) {
                    $this->_options[0] = '...';
                }
                $rawoptions = explode("\n", $this->field->param1);
                foreach ($rawoptions as $key => $option) {
                    $option = trim($option);
                    if ($option != '') {
                        $this->_options[$key + 1] = $option;
                    }
                }
            }
        }
        return $this->_options;
    }

    /**
     *
     * @param array $map
     * @return mixed
     */
    public abstract function update_options($map = array());

    /**
     * When an option from a single/multi choice is deleted / renamed or added
     * the old content will be updated to the new values of the options. If an option
     * is deleted all the selections for that specific option made in an entry will be deleted
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::set_field()
     */
    public function set_field($forminput = null) {
        $this->field = new stdClass();
        $this->field->id = !empty($forminput->id) ? $forminput->id : 0;
        $this->field->type = $this->type;
        $this->field->dataid = $this->df->id();
        $this->field->name = !empty($forminput->name) ? trim($forminput->name) : '';
        $this->field->description = !empty($forminput->description) ? trim($forminput->description) : '';
        $this->field->visible = isset($forminput->visible) ? $forminput->visible : 2;
        $this->field->edits = isset($forminput->edits) ? $forminput->edits : -1;
        $this->field->label = !empty($forminput->label) ? $forminput->label : '';

        $oldvalues = $newvalues = $this->_options;
        $renames = !empty($forminput->renameoption) ? $forminput->renameoption : array();
        $deletes = !empty($forminput->deleteoption) ? $forminput->deleteoption : array();
        $adds = preg_split("/[\|\r\n]+/",
                !empty($forminput->addoptions) ? $forminput->addoptions : '');

        // Make sure there are no renames when options are deleted. That will not work.
        $delvalues = array_values($deletes);
        if (!empty($delvalues)) {
            $renames = array();
        }

        $delkeys = array_keys($deletes);
        foreach ($delkeys as $id) {
            $addedid = array_search($oldvalues[$id], $adds);
            if ($addedid !== false) {
                unset($adds[$addedid]);
                unset($deletes[$id]);
            } else {
                unset($newvalues[$id]);
            }
        }
        $dummyentry = "0";
        while (array_search($dummyentry, $newvalues) !== false) {
            $dummyentry .= "0";
        }
        $newvalues = array_merge(array(0 => $dummyentry), $newvalues);

        $map = array(0 => 0);
        for ($i = 1; $i <= count($oldvalues); $i++) {
            $j = array_search($oldvalues[$i], $newvalues);
            if ($j !== false) {
                $map[$i] = $j;
            } else {
                $map[$i] = 0;
            }
        }

        foreach ($renames as $id => $newname) {
            if (!!(trim($newname))) {
                $newvalues[$id] = $newname;
            }
        }

        foreach ($adds as $add) {
            $add = trim($add);
            if (!empty($add)) {
                $newvalues[] = $add;
            }
        }

        if (!empty($this->_options)) {
            $this->update_options($map);
        }

        unset($newvalues[0]);
        $this->field->param1 = implode("\n", $newvalues);
        for ($i = 2; $i <= 10; $i++) {
            $param = "param$i";
            if (isset($forminput->$param)) {
                $this->field->$param = $forminput->$param;
            }
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::format_search_value()
     */
    public function format_search_value($searchparams) {
        list($not, $operator, $value) = $searchparams;
        if (is_array($value)) {
            $selected = implode(', ', $value);
            return $not . ' ' . $operator . ' ' . $selected;
        } else {
            return false;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::parse_search()
     */
    public function parse_search($formdata, $i) {
        $fieldname = "f_{$i}_{$this->field->id}";
        return optional_param_array($fieldname, false, PARAM_NOTAGS);
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}

/**
 * Base class for Datalynx field types that offer a set of options with multiple choice
 */
class datalynxfield_option_multiple extends datalynxfield_option {

    /**
     * Update the selected options in the entries. The field value of an entry saves the selected
     * line numbers in a multiselect field. When an option is deleted, a line is deleted. Example:
     * line 2 is deleted, therefore line 3 becomes line 2, line 4 becomes line 3 and so on.
     * Therefore the values of the field in the entries have to be remapped to the new line numbers of the options
     *
     * @see datalynxfield_option::update_options()
     */
    public function update_options($map = array()) {
        global $DB;
        $params = array();
        $i = 0;
        $where = 'c.fieldid = :fieldid AND (';
        foreach ($map as $old => $new) {
            $where .= $DB->sql_like('c.content', ":old{$i}") . ' OR ';
            $params["old{$i}"] = "%#{$old}#%";
            $i++;
        }
        $where = rtrim($where, "OR ") . ")";
        $selectsql = "SELECT c.id, c.content
        FROM {datalynx_contents} c
        WHERE {$where}
        ";
        $params['fieldid'] = $this->field->id;

        $oldcontents = $DB->get_records_sql_menu($selectsql, $params);
        foreach ($oldcontents as $id => $oldcontent) {
            $prepareoldcontent = str_replace('#', '', $oldcontent);
            $prepared = explode(",", $prepareoldcontent);
            $replaced = array();
            foreach ($prepared as $value) {
                if ($map[$value] !== 0) {
                    $replaced[$map[$value]] = $map[$value];
                }
            }
            $implodedcontent = implode(",", $replaced);
            $newcontent = "#" . str_replace(",", "#,#", $implodedcontent) . "#";

            $DB->set_field('datalynx_contents', 'content', $newcontent, array('id' => $id));
        }
    }

    /**
     * does not support group by filter settings
     *
     * @see datalynxfield_base::supports_group_by()
     */
    public function supports_group_by() {
        return false;
    }

    /**
     * Prepare the content of the field for database storage when an entry
     * is modified or created
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::format_content()
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $contents = array();
        $oldcontents = array();

        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        $newvalues = reset($values);
        foreach ($newvalues as $key => $value) {
            if (empty($value)) {
                unset($newvalues[$key]);
            }
        }
        // New contents.
        if (!empty($newvalues)) {
            $content = '#' . implode('#,#', $newvalues) . '#';
            $contents[] = $content;
        } else {
            $contents[] = ''; // Keep empties in database.
        }

        return array($contents, $oldcontents);
    }

    public function get_search_sql($search) {
        global $DB;

        list($not, $operator, $value) = $search;

        static $i = 0; // FIXME: might cause problems!
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        $sql = '';
        $params = [];
        $conditions = [];
        $notinidsequal = false;

        // For all NOT criteria except NOT Empty, exclude entries.
        // Which don't meet the positive criterion.
        // Because some fields may not have content records.
        // And the respective entries may be filter out.
        // Despite meeting the criterion.
        $excludeentries = (($not and $operator !== '') or (!$not and $operator === ''));

        if ($operator === 'EXACTLY' && empty($value)) {
            $operator = '';
        }

        $content = "c{$this->field->id}.content";
        $usecontent = true;
        if ($operator === 'ANY_OF') {
            foreach ($value as $key => $sel) {
                $xname = $name . $key;
                $likesel = str_replace('%', '\%', $sel);

                $conditions[] = $DB->sql_like($content, ":{$xname}");
                $params[$xname] = "%#$likesel#%";
            }
            $sql = " $not (" . implode(" OR ", $conditions) . ") ";
        } else {
            if ($operator === 'ALL_OF') {
                foreach ($value as $key => $sel) {
                    $xname = $name . $key;
                    $likesel = str_replace('%', '\%', $sel);

                    $conditions[] = $DB->sql_like($content, ":{$xname}");
                    $params[$xname] = "%#$likesel#%";
                }
                $sql = " $not (" . implode(" AND ", $conditions) . ") ";
            } else {
                if ($operator === 'EXACTLY' || $operator === '=') {
                    if ($not) {
                        $content = "content";
                        $usecontent = false;
                    } else {
                        $content = "c{$this->field->id}.content";
                        $usecontent = true;
                    }

                    $j = 0;
                    foreach (array_keys($this->options_menu()) as $key) {
                        if (in_array($key, $value)) {
                            $xname = $name . $j++;
                            $likesel = str_replace('%', '\%', $key);

                            $conditions[] = $DB->sql_like($content, ":{$xname}", true, true, false);
                            $params[$xname] = "%#$likesel#%";
                        }
                    }
                    foreach (array_keys($this->options_menu()) as $key) {
                        if (!in_array($key, $value)) {
                            $xname = $name . $j++;
                            $likesel = str_replace('%', '\%', $key);

                            $conditions[] = $DB->sql_like($content, ":{$xname}", true, true, true);
                            $params[$xname] = "%#$likesel#%";
                        }
                    }

                    if ($not) {
                        $sqlfind = " (" . implode(" AND ", $conditions) . ") ";

                        $sql = ' 1 ';
                        if ($eids = $this->get_entry_ids_for_content($sqlfind, $params)) { // There are.
                            // Non-empty.
                            // Contents.
                            list($contentids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                                    "df_{$fieldid}_x_", false);
                            $params = array_merge($params, $paramsnot);
                            $sql = " (e.id $contentids) ";
                        }
                    } else {
                        $sql = " (" . implode(" AND ", $conditions) . ") ";
                    }
                } else {
                    if ($operator === '') { // EMPTY.
                        $usecontent = false;
                        $sqlnot = $DB->sql_like("content", ":{$name}_hascontent");
                        $params["{$name}_hascontent"] = "%";

                        if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) { // There are non-empty.
                            // Contents.
                            list($contentids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                                    "df_{$fieldid}_x_", !!$not);
                            $params = array_merge($params, $paramsnot);
                            $sql = " (e.id $contentids) ";
                        } else { // There are no non-empty contents.
                            if ($not) {
                                $sql = " 0 ";
                            } else {
                                $sql = " 1 ";
                            }
                        }
                    }
                }
            }
        }

        if ($excludeentries && $operator !== '' && $operator !== 'EXACTLY') {
            $sqlnot = str_replace($content, 'content', $sql);
            $sqlnot = str_replace('NOT (', '(', $sqlnot);
            if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) {
                // Get NOT IN sql.
                list($notinids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                        "df_{$fieldid}_x_", $notinidsequal);
                $params = array_merge($params, $paramsnot);
                $sql = " ($sql OR e.id $notinids) ";
            }
        }

        return array($sql, $params, $usecontent);
    }

    public function get_supported_search_operators() {
        return array('ANY_OF' => get_string('anyof', 'datalynx'),
                'ALL_OF' => get_string('allof', 'datalynx'),
                'EXACTLY' => get_string('exactly', 'datalynx'), '' => get_string('empty', 'datalynx'));
    }
}

/**
 * Base class for Datalynx field types that offer a set of options with single choice
 */
class datalynxfield_option_single extends datalynxfield_option {

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_option::update_options()
     */
    public function update_options($map = array()) {
        global $DB;

        $params = array();
        $i = 0;
        $updatesql = "UPDATE {datalynx_contents}
                         SET content = (
                        CASE";
        foreach ($map as $old => $new) {
            $updatesql .= " WHEN content = :old{$i} THEN :new{$i} ";
            $params["old{$i}"] = $old;
            $params["new{$i}"] = $new;
            $i++;
        }
        $updatesql .= "ELSE 0 END) WHERE fieldid = :fieldid";
        $params['fieldid'] = $this->field->id;

        $DB->execute($updatesql, $params);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::format_content()
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        // Old contents.
        $oldcontents = array();
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }
        // New contents.
        $contents = array();

        $selected = null;

        // We want to store empty values as well.
        foreach ($values as $value) {
            $selected = $value;
        }

        // Add the content.
        $contents[] = $selected;

        return array($contents, $oldcontents);
    }

    /**
     * Computes which values of this field have already been chosen by the given user and
     * determines which ones have reached their limit
     *
     * @param int $userid ID of the user modifying an entry; if not specified defaults to $USER->id
     * @return array an array of disabled values
     */
    public function get_disabled_values_for_user($userid = 0) {
        global $DB, $USER;

        if ($userid == 0) {
            $userid = $USER->id;
        }

        $sql = "SELECT dc.content, COUNT(dc.id)
        FROM {datalynx_contents} dc
        INNER JOIN {datalynx_entries} de ON dc.entryid = de.id
        WHERE de.userid = :userid
        AND de.dataid = :dataid
        AND dc.fieldid = :fieldid
        GROUP BY dc.content
        HAVING COUNT(dc.id) >= :selectlimit";

        $params = array('userid' => $userid, 'dataid' => $this->df->id(),
                'fieldid' => $this->field->id, 'selectlimit' => $this->field->param5);

        $results = $DB->get_records_sql($sql, $params);

        return array_keys($results);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::supports_group_by()
     */
    public function supports_group_by() {
        return true;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql($search) {
        global $DB;

        list($not, $operator, $value) = $search;

        static $i = 0; // FIXME: might cause problems!
        $i++;
        $fieldid = $this->field->id;

        $sql = null;
        $params = [];
        $name = "df_{$fieldid}_{$i}";
        $notinidsequal = false;

        // For all NOT criteria except NOT Empty, exclude entries.
        // Which don't meet the positive criterion.
        // Because some fields may not have content records.
        // And the respective entries may be filter out.
        // Despite meeting the criterion.
        $excludeentries = (($not and $operator !== '') or (!$not and $operator === ''));

        $content = "c{$this->field->id}.content";

        $usecontent = true;
        if ($operator === 'ANY_OF' || $operator === '=') {
            list($insql, $params) = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED, "param_{$i}_");
            $sql = " $not ($content $insql) ";
        } else {
            if ($operator === '') {
                $usecontent = false;
                $sqlnot = $DB->sql_like("content", ":{$name}_hascontent");
                $params["{$name}_hascontent"] = "%";

                if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) { // There are non-empty.
                    // Contents.
                    list($contentids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                            "df_{$fieldid}_x_", !!$not);
                    $params = array_merge($params, $paramsnot);
                    $sql = " (e.id $contentids) ";
                } else { // There are no non-empty contents.
                    if ($not) {
                        $sql = " 0 ";
                    } else {
                        $sql = " 1 ";
                    }
                }
            }
        }

        if ($excludeentries && $operator !== '') {
            $sqlnot = str_replace($content, 'content', $sql);
            $sqlnot = str_replace('NOT (', '(', $sqlnot);
            if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) {
                // Get NOT IN sql.
                list($notinids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                        "df_{$fieldid}_x_", $notinidsequal);
                $params = array_merge($params, $paramsnot);
                $sql = " ($sql OR e.id $notinids) ";
            }
        }

        return array($sql, $params, $usecontent);
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::prepare_import_content()
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            $label = !empty($csvrecord[$csvname]) ? $csvrecord[$csvname] : null;

            if ($label) {
                $options = $this->options_menu();
                if ($optionkey = array_search($label, $options)) {
                    $data->{"field_{$fieldid}_{$entryid}"} = $optionkey;
                }
            }
        }
        return true;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_supported_search_operators()
     */
    public function get_supported_search_operators() {
        return array('ANY_OF' => get_string('anyof', 'datalynx'), '' => get_string('empty', 'datalynx'));
    }
}
