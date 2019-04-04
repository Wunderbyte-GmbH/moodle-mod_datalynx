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
 * @subpackage textarea
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once($CFG->dirroot . '/mod/datalynx/field/field_class.php');
require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/repository/lib.php');

class datalynxfield_textarea extends datalynxfield_base {

    public $type = 'textarea';

    protected $editoroptions;

    /**
     * Can this field be used in fieldgroups?
     * @var boolean
     */
    protected $forfieldgroup = true;

    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);

        $trust = !empty($this->field->param4) ? $this->field->param4 : 0;
        $maxbytes = !empty($this->field->param5) ? $this->field->param5 : 0;
        $maxfiles = !empty($this->field->param6) ? $this->field->param6 : -1;

        $this->editoroptions = array();
        $this->editoroptions['context'] = $this->df->context;
        $this->editoroptions['trusttext'] = $trust;
        $this->editoroptions['maxbytes'] = $maxbytes;
        $this->editoroptions['maxfiles'] = $maxfiles;
        $this->editoroptions['subdirs'] = false;
        $this->editoroptions['changeformat'] = 0;
        $this->editoroptions['forcehttps'] = false;
        $this->editoroptions['noclean'] = false;
    }

    /**
     */
    public function is_editor() {
        return !empty($this->field->param1);
    }

    /**
     */
    public function editor_options() {
        return $this->editoroptions;
    }

    /**
     */
    public function update_content($entry, array $values = null) {
        global $DB;

        $entryid = $entry->id;
        $fieldid = $this->field->id;

        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        if (empty($values)) {
            return true;
        }

        $rec = new stdClass();
        $rec->fieldid = $fieldid;
        $rec->entryid = $entryid;

        if (!$rec->id = $contentid) {
            $rec->id = $DB->insert_record('datalynx_contents', $rec);
        }

        $value = reset($values);
        if (is_array($value)) {
            // Import: One value as array of text,format,trust, so take the text.
            $value = reset($value);
        }

        $value = str_replace("<br />", "\n", $value); // Reset carriage returns, bug#887.
        $rec->content = clean_param($value, PARAM_NOTAGS); // Replaced PARAM_RAW.

        return $DB->update_record('datalynx_contents', $rec);
    }

    /**
     */
    public function get_content_parts() {
        return array('content', 'content1');
    }

    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        $fieldid = $this->field->id;

        parent::prepare_import_content($data, $importsettings, $csvrecord, $entryid);

        // For editors reformat in editor structure.
        if ($this->is_editor()) {
            if (isset($data->{"field_{$fieldid}_{$entryid}"})) {
                $valuearr = explode('##', $data->{"field_{$fieldid}_{$entryid}"});
                $content = array();
                $content['text'] = !empty($valuearr[0]) ? $valuearr[0] : null;
                $content['format'] = !empty($valuearr[1]) ? $valuearr[1] : FORMAT_MOODLE;
                $content['trust'] = !empty($valuearr[2]) ? $valuearr[2] : $this->editoroptions['trusttext'];
                $data->{"field_{$fieldid}_{$entryid}_editor"} = $content;
                unset($data->{"field_{$fieldid}_{$entryid}"});
            }
        }
        return true;
    }

    public function get_supported_search_operators() {
        return array('' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx'));
    }
}
