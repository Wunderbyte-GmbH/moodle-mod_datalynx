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
 * @subpackage picture
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/file/field_class.php");

class datalynxfield_picture extends datalynxfield_file {

    public $type = 'picture';

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var boolean
     */
    protected $forfieldgroup = true;

    /**
     */
    public function update_content($entry, array $values = null) {
        global $DB;
        parent::update_content($entry, $values);

        // We can read the contentid from entry and update the right files.
        $contentid = isset($entry->{"c{$this->field->id}_id"}) ? $entry->{"c{$this->field->id}_id"} : null;

        if (!empty($contentid)) {
            $this->update_content_files($contentid, array('updatethumb' => true, 'updatefile' => false));
        }
    }

    /**
     */
    public function update_field($fromform = null) {
        global $DB, $OUTPUT;

        // Get the old field data so that we can check whether the thumbnail dimensions have changed.
        $oldfield = $this->field;
        if (!parent::update_field($fromform)) {
            echo $OUTPUT->notification('updating of new field failed!');
            return false;
        }
        // Have the dimensions changed?
        $updatefile = $oldfield->param7 != $this->field->param7 or
        $oldfield->param8 != $this->field->param8;
        $updatethumb = $oldfield->param9 != $this->field->param9 or
        $oldfield->param10 != $this->field->param10;
        if ($oldfield && ($updatefile || $updatethumb)) {
            // Check through all existing records and update the thumbnail.
            if ($contents = $DB->get_records('datalynx_contents',
                    array('fieldid' => $this->field->id))
            ) {
                if (count($contents) > 20) {
                    echo $OUTPUT->notification(
                            get_string('resizingimages', 'datalynxfield_picture'), 'notifysuccess');
                    echo "\n\n";
                    // To make sure that ob_flush() has the desired effect.
                    ob_flush();
                }
                foreach ($contents as $content) {
                    @set_time_limit(300);
                    // Might be slow!
                    $this->update_content_files($content->id,
                            array('updatefile' => $updatefile, 'updatethumb' => $updatethumb));
                }
            }
        }
        return true;
    }

    /**
     * Delete a field completely
     */
    public function delete_field() {
        global $DB;

        if (!empty($this->field->id)) {
            foreach (array('content', 'thumb'
            ) as $filearea) {
                $fs = get_file_storage();
                $fs->delete_area_files($this->df->context->id, 'mod_datalynx', $filearea);
            }
            $this->delete_content();
            $DB->delete_records('datalynx_fields', array('id' => $this->field->id));
        }
        return true;
    }

    /**
     * (Re)generate pic and thumbnail images according to the dimensions specified in the field
     * settings.
     */
    protected function update_content_files($contentid, $params = null) {
        $updatefile = isset($params['updatefile']) ? $params['updatefile'] : true;
        $updatethumb = isset($params['updatethumb']) ? $params['updatethumb'] : true;

        $fs = get_file_storage();
        if (!$files = $fs->get_area_files($this->df->context->id, 'mod_datalynx', 'content',
                $contentid)
        ) {
            return;
        }

        // Update dimensions and regenerate thumbs.
        foreach ($files as $file) {

            // Catch in case we see a directory in this list.
            if ($file->is_directory()) {
                continue;
            }

            if ($file->is_valid_image()) {
                // Original first.
                if ($updatefile) {
                    $maxwidth = !empty($this->field->param7) ? $this->field->param7 : '';
                    $maxheight = !empty($this->field->param8) ? $this->field->param8 : '';

                    // If either width or height try to (re)generate.
                    if ($maxwidth or $maxheight) {
                        // This may fail for various reasons.
                        try {
                            global $DB;
                            $record = $DB->get_record('files', array('id' => $file->get_id()));
                            $fs->convert_image($record, $record->id, $maxwidth, $maxheight, true);
                        } catch (Exception $e) {
                            return false;
                        }
                    }
                }

                // Thumbnail next.
                if ($updatethumb) {
                    $thumbwidth = !empty($this->field->param9) ? $this->field->param9 : '';
                    $thumbheight = !empty($this->field->param10) ? $this->field->param10 : '';
                    $thumbname = 'thumb_' . $file->get_filename();

                    if ($thumbfile = $fs->get_file($this->df->context->id, 'mod_datalynx', 'thumb', $contentid, '/', $thumbname)) {
                        $thumbfile->delete();
                    }

                    // If either width or height try to (re)generate, otherwise delete what exists.
                    if ($thumbwidth or $thumbheight) {

                        $filerecord = array('contextid' => $this->df->context->id,
                                'component' => 'mod_datalynx', 'filearea' => 'thumb',
                                'itemid' => $contentid, 'filepath' => '/', 'filename' => $thumbname,
                                'userid' => $file->get_userid()
                        );

                        try {
                            $fs->convert_image($filerecord, $file, $thumbwidth, $thumbheight, true);
                        } catch (Exception $e) {
                            return false;
                        }
                    }
                }
            }
        }
        return true;
    }
}
