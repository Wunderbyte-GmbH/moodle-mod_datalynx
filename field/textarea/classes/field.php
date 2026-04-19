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
 * @package datalynxfield_textarea
 * @subpackage textarea
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_textarea;

use mod_datalynx\local\field\datalynxfield_base;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/filelib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Textarea field class.
 */
class field extends datalynxfield_base {
    /** @var string Field type */
    public $type = 'textarea';

    /** @var array Editor options */
    protected $editoroptions;

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * Constructor.
     *
     * @param int|object $df Datalynx ID or object
     * @param int|object $field Field ID or object
     */
    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);

        $trust = !empty($this->field->param4) ? $this->field->param4 : 0;
        $maxbytes = !empty($this->field->param5) ? $this->field->param5 : 0;
        $maxfiles = !empty($this->field->param6) ? $this->field->param6 : -1;

        $this->editoroptions = [];
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
     * Check if the field is an editor.
     *
     * @return bool
     */
    public function is_editor() {
        return !empty($this->field->param1);
    }

    /**
     * Get editor options.
     *
     * @return array
     */
    public function editor_options() {
        return $this->editoroptions;
    }

    /**
     * Update the field content.
     *
     * @param stdClass $entry
     * @param ?array $values
     * @return int
     */
    public function update_content(stdClass $entry, ?array $values = null) {
        global $DB;

        $entryid = $entry->id;
        $fieldid = $this->field->id;

        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

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

        $DB->update_record('datalynx_contents', $rec);

        // We need the contentid as return value.
        return $rec->id;
    }

    /**
     * Get the content parts of the field.
     *
     * @return array
     */
    public function get_content_parts() {
        return ['content', 'content1'];
    }

    /**
     * Prepare content for import.
     *
     * @param stdClass $data
     * @param array $importsettings
     * @param array $csvrecord
     * @param int $entryid
     * @return bool
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        $fieldid = $this->field->id;

        parent::prepare_import_content($data, $importsettings, $csvrecord, $entryid);

        // For editors reformat in editor structure.
        if ($this->is_editor()) {
            if (isset($data->{"field_{$fieldid}_{$entryid}"})) {
                $valuearr = explode('##', $data->{"field_{$fieldid}_{$entryid}"});
                $content = [];
                if ($csvrecord) {
                    $content['text'] = !empty($valuearr[0]) ? htmlspecialchars_decode($valuearr[0]) : null;
                } else {
                    $content['text'] = !empty($valuearr[0]) ? $valuearr[0] : null;
                }
                $content['format'] = !empty($valuearr[1]) ? $valuearr[1] : FORMAT_MOODLE;
                $content['trust'] = !empty($valuearr[2]) ? $valuearr[2] : $this->editoroptions['trusttext'];
                $data->{"field_{$fieldid}_{$entryid}_editor"} = $content;
                unset($data->{"field_{$fieldid}_{$entryid}"});
            }
        }
        return true;
    }

    /**
     * Get supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators() {
        return ['' => get_string('empty', 'datalynx'), '=' => get_string('equal', 'datalynx'),
                'LIKE' => get_string('contains', 'datalynx')];
    }
}
