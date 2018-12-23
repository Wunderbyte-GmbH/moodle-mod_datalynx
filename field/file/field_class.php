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
 * @subpackage file
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

/**
 */
class datalynxfield_file extends datalynxfield_base {

    public $type = 'file';

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var boolean
     */
    protected $forfieldgroup = false;

    // Content - file manager.
    // Content1 - alt name.
    // Content2 - download counter.

    /**
     */
    protected function content_names() {
        return array('filemanager', 'alttext', 'delete', 'editor');
    }

    /**
     */
    public function update_content($entry, array $values = null) {
        global $DB, $USER;

        $entryid = $entry->id;
        $fieldid = $this->field->id;

        // This sets variables by resetting every part from the array key.
        $filemanager = $alttext = $delete = $editor = null;
        if (!empty($values)) {
            foreach ($values as $name => $value) {
                if (!empty($name) and !empty($value)) {
                    ${$name} = $value; // Sets $filemanager etc.
                }
            }
        }

        // Update file content.
        if ($editor) {
            return $this->save_changes_to_file($entry, $values);
        }

        // Contentid to locate in table.
        $contentid = isset($entry->{"c{$this->field->id}_id"}) ? $entry->{"c{$this->field->id}_id"} : null;

        $usercontext = context_user::instance($USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $filemanager);

        if (count($files) > 1) {
            // There are files to upload so add/update content record.
            $rec = new stdClass();
            $rec->fieldid = $fieldid;
            $rec->entryid = $entryid;
            $rec->content = 1; // We just store a 1 to show there is something, look for files.
            $rec->content1 = $alttext;

            if (!empty($contentid)) {
                $rec->id = $contentid;
                $DB->update_record('datalynx_contents', $rec);
            } else {
                $contentid = $DB->insert_record('datalynx_contents', $rec);
            }

            // Now save files.
            $options = array('subdirs' => 0, 'maxbytes' => $this->field->param1,
                    'maxfiles' => $this->field->param2, 'accepted_types' => $this->field->param3
            );

            // TODO: This is not precise enough, we only store contents and these are linked to the entry.
            // We need a way to determine a specific line or remove field file and picture from fieldgroups for now.
            $contextid = $this->df->context->id;
            file_save_draft_area_files($filemanager, $contextid, 'mod_datalynx', 'content',
                    $contentid, $options);

            $this->update_content_files($contentid);

        } else {
            // User cleared files from the field.
            if (!empty($contentid)) {
                // TODO: Fix this, don't delete whole entry only contentid we see.
                $this->delete_content($entryid);
            }
        }
        return true;
    }

    /**
     */
    protected function format_content($entry, array $values = null) {
        return array(null, null, null);
    }

    /**
     */
    public function get_content_parts() {
        return array('content', 'content1', 'content2');
    }

    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Files can not be importet.
        return false;
    }

    /**
     */
    protected function update_content_files($contentid, $params = null) {
        return true;
    }

    /**
     */
    protected function save_changes_to_file($entry, array $values = null) {
        $fieldid = $this->field->id;
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entry->id}";

        $contentid = isset($entry->{"c{$this->field->id}_id"}) ? $entry->{"c{$this->field->id}_id"} : null;

        $options = array('context' => $this->df->context);
        $data = (object) $values;
        $data = file_postupdate_standard_editor((object) $values, $fieldname, $options,
                $this->df->context, 'mod_datalynx', 'content', $contentid);

        // Get the file content.
        $fs = get_file_storage();
        $file = reset(
                $fs->get_area_files($this->df->context->id, 'mod_datalynx', 'content', $contentid,
                        'sortorder', false));
        $filecontent = $file->get_content();

        // Find content position (between body tags).
        $tmpbodypos = stripos($filecontent, '<body');
        $openbodypos = strpos($filecontent, '>', $tmpbodypos) + 1;
        $sublength = strripos($filecontent, '</body>') - $openbodypos;

        // Replace body content with new content.
        $filecontent = substr_replace($filecontent, $data->$fieldname, $openbodypos, $sublength);

        // Prepare new file record.
        $rec = new stdClass();
        $rec->contextid = $this->df->context->id;
        $rec->component = 'mod_datalynx';
        $rec->filearea = 'content';
        $rec->itemid = $contentid;
        $rec->filename = $file->get_filename();
        $rec->filepath = '/';
        $rec->timecreated = $file->get_timecreated();
        $rec->userid = $file->get_userid();
        $rec->source = $file->get_source();
        $rec->author = $file->get_author();
        $rec->license = $file->get_license();

        // Delete old file.
        $fs->delete_area_files($this->df->context->id, 'mod_datalynx', 'content', $contentid);

        // Create a new file from string.
        $fs->create_file_from_string($rec, $filecontent);
        return true;
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }
}
