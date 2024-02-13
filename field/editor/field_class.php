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
 * @subpackage editor
 * @copyright 2015 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/datalynx/field/field_class.php');
require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/repository/lib.php');

class datalynxfield_editor extends datalynxfield_base {

    public $type = 'editor';

    protected $editoroptions;

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var boolean
     */
    protected $forfieldgroup = true;

    /**
     *
     * @param number $df
     * @param number $field
     */
    public function __construct($df = 0, $field = 0) {
        global $COURSE, $PAGE, $CFG;
        parent::__construct($df, $field);

        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);

        // TODO: provide options for the editor field to configure in the field settings.

        $this->editoroptions = array();
        $this->editoroptions['context'] = $this->df->context;
        $this->editoroptions['maxfiles'] = EDITOR_UNLIMITED_FILES;
        $this->editoroptions['trusttext'] = true;
        $this->editoroptions['maxbytes'] = $maxbytes;
        $this->editoroptions['subdirs'] = false;
        $this->editoroptions['changeformat'] = 0;
        $this->editoroptions['forcehttps'] = false;
        $this->editoroptions['noclean'] = false;
        $this->editoroptions['return_types'] = FILE_INTERNAL | FILE_EXTERNAL;
    }

    /**
     * (non-PHPdoc)
     *
     * @see datalynxfield_base::content_names()
     */
    protected function content_names() {
        return array('editor');
    }

    /**
     */
    public function is_editor() {
        return true;
    }

    /**
     */
    public function editor_options() {
        return $this->editoroptions;
    }

    /**
     * write the content of the editor field and editor format to the database
     *
     * @see datalynxfield_base::update_content()
     */
    public function update_content(stdClass $entry, array $values = null) {
        global $DB;
        $entryid = $entry->id;
        $fieldid = $this->field->id;

        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        $rec = new stdClass();
        $rec->fieldid = $fieldid;
        $rec->entryid = $entryid;
        $rec->id = $contentid;
        if (!$rec->id) {
            $rec->id = $DB->insert_record('datalynx_contents', $rec);
        }
        // The editor's content is an array, so reset is used in order to access the data in the.
        // Array.
        $value = reset($values);
        $data = new stdClass();
        $data->text = $value['text'];
        $data->format = $value['format'];
        $data->content_editor = $value;
        $data = file_postupdate_standard_editor($data, 'content', $this->editoroptions,
                $this->df->context, 'mod_datalynx', 'content', $rec->id);
        $rec->content = $data->content;
        $rec->content1 = $data->contentformat;

        $DB->update_record('datalynx_contents', $rec);

        // We need the contentid as return value.
        return $rec->id;
    }

    /**
     * Returns 'content': the html content of the editor and 'content1': the format of the content
     *
     * @see datalynxfield_base::get_content_parts()
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
                if ($csvrecord) {
                    $content['text'] = !empty($valuearr[0]) ? htmlspecialchars_decode($valuearr[0]) : null;
                } else {
                    $content['text'] = !empty($valuearr[0]) ? $valuearr[0] : null;
                }
                $content['format'] = !empty($valuearr[1]) ? $valuearr[1] : FORMAT_HTML;
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

    /**
     * Is $value a valid content or do we see an empty input?
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {
        if ($value['text'] == '') {
            return true;
        }
        return false;
    }
}
