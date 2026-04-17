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
 * @package datalynxfield_file
 * @subpackage file
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_file;

use mod_datalynx\local\field\datalynxfield_base;
use stdClass;
use context_user;

defined('MOODLE_INTERNAL') || die();


require_once("$CFG->dirroot/lib/resourcelib.php");

/**
 * Datalynx file field class.
 */
class field extends datalynxfield_base {
    /** @var string Plugin type. */
    public $type = 'file';

    /**
     * Can this field be used in fieldgroups? Override if yes.
     * @var bool
     */
    protected $forfieldgroup = true;

    // Content - file manager.
    // Content1 - alt name.
    // Content2 - download counter.

    /**
     *  Get alt text, delete, etc.
     * @return string[]
     */
    protected function content_names(): array {
        return ['filemanager', 'alttext', 'delete', 'editor'];
    }

    /**
     * Update content for the field.
     *
     * @param stdClass $entry The entry object.
     * @param array|null $values The values to update.
     * @return int|bool The content ID or false.
     */
    public function update_content(stdClass $entry, array $values = null) {
        global $DB, $USER;

        $entryid = $entry->id;
        $fieldid = $this->field->id;

        // This sets variables by resetting every part from the array key.
        $filemanager = $alttext = $editor = null;
        if (!empty($values)) {
            foreach ($values as $name => $value) {
                if (!empty($name) && !empty($value)) {
                    ${$name} = $value; // Sets $filemanager etc.
                }
            }
        }

        // Update file content.
        if ($editor) {
            return $this->save_changes_to_file($entry, $values);
        }

        // Contentid to locate in table.
        $contentid = $entry->{"c{$this->field->id}_id"} ?? null;

        $usercontext = context_user::instance($USER->id);

        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $filemanager);

        // Even if we don't store any file info we need to have a contentid to show empty lines.
        $rec = new stdClass();
        $rec->fieldid = $fieldid;
        $rec->entryid = $entryid;
        $rec->content1 = $alttext;

        // Hack for update field for ftpsync.
        if (count($files) > 1 || $filemanager == 111111) {
            $rec->content = 1; // We just store a 1 to show there is something, look for files.
        } else {
            $rec->content = 0; // In case there is no file, add a 0.
        }

        if (!empty($contentid)) {
            $rec->id = $contentid;
            $DB->update_record('datalynx_contents', $rec);
        } else {
            $contentid = $DB->insert_record('datalynx_contents', $rec);
        }

        if (count($files) > 1) {
            // Now save files if we see any.
            $options = ['subdirs' => 0, 'maxbytes' => $this->field->param1,
                    'maxfiles' => $this->field->param2, 'accepted_types' => $this->field->param3,
            ];

            $contextid = $this->df->context->id;
            file_save_draft_area_files(
                $filemanager,
                $contextid,
                'mod_datalynx',
                'content',
                $contentid,
                $options
            );

            $this->update_content_files($contentid);
        }
        return $contentid;
    }

    /**
     * Format content for display.
     *
     * @param stdClass $entry The entry object.
     * @param array|null $values The values to use.
     * @return array The formatted content.
     */
    protected function format_content($entry, array $values = null): array {
        return [null, null, null];
    }

    /**
     * Get the names of the content parts.
     *
     * @return array The names of the content parts.
     */
    public function get_content_parts(): array {
        return ['content', 'content1', 'content2'];
    }

    /**
     * Prepare content for import.
     *
     * @param stdClass $data The data object.
     * @param array $importsettings The import settings.
     * @param array|null $csvrecord The CSV record.
     * @param int|null $entryid The entry ID.
     * @return bool True if successful, false otherwise.
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null): bool {
        global $USER;

        // Check if not a csv import.
        if (!$csvrecord) {
            return false;
        }

        $fieldid = $this->field->id;
        $fieldname = $this->name();
        $csvname = $importsettings[$fieldname]['name'];
        $fileurls = explode(',', $csvrecord[$csvname]);

        // Prepare the draftarea where to put all files.
        $draftitemid = file_get_submitted_draft_itemid("field_{$fieldid}_{$entryid}_filemanager");
        // For draftareas we use usercontextid for some reason, this is consistent with the ajax call.
        $contextid = context_user::instance($USER->id)->id;
        file_prepare_draft_area($draftitemid, $contextid, 'mod_datalynx', 'content', null);
        foreach ($fileurls as $fileurl) {
            $filesprocessed = 0;
            // Check if this is an url.
            if (!$this->validate_url($fileurl)) {
                continue;
            }

            // Check if we can see the file.
            $headers = get_headers($fileurl);
            if (strpos($headers[0], 'OK') === false) {
                continue;
            }
            $filesprocessed++;

            // Download this file in the temp folder.
            $filename = basename($fileurl);
            $filerecord = [
                'contextid' => $contextid,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => urldecode($filename),
            ];
            $fs = get_file_storage();
            $fs->create_file_from_url($filerecord, $fileurl, null, true);
        }

        // If no files, then return false.
        if ($filesprocessed == 0 && $data->ftpsyncmode == 0) {
            return false;
        }

        if ($data->ftpsyncmode) {
            $data->{"field_{$fieldid}_{$entryid}"} = 1;
            $draftitemid = 111111;
        }

        // Tell the update script what itemid to look for.
        $data->{"field_{$fieldid}_{$entryid}_filemanager"} = $draftitemid;

        // Finally we can return true.
        return true;
    }

    /**
     * As FILTER_VALIDATE_URL validates only URLs containing ASCII chars
     * this function is needed to validate URLs containing special chars
     * like ü or ä.
     *
     * @param string $url
     * @return bool
     */
    protected function validate_url($url): bool {
        $path = parse_url($url, PHP_URL_PATH);
        $encodedpath = array_map('urlencode', explode('/', $path));
        $url = str_replace($path, implode('/', $encodedpath), $url);

        return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
    }

    /**
     * Update content files.
     *
     * @param int $contentid The content ID.
     * @param array|null $params Additional parameters.
     * @return bool True if successful.
     */
    protected function update_content_files($contentid, $params = null): bool {
        return true;
    }

    /**
     * Save changes to file.
     *
     * @param stdClass $entry The entry object.
     * @param array|null $values The values to save.
     * @return bool True if successful.
     * @throws file_exception
     * @throws stored_file_creation_exception
     */
    protected function save_changes_to_file($entry, array $values = null): bool {
        $fieldid = $this->field->id;
        $fieldname = "field_{$fieldid}_{$entry->id}";

        $contentid = $entry->{"c{$this->field->id}_id"} ?? null;

        $options = ['context' => $this->df->context];
        $data = (object) $values;
        $data = file_postupdate_standard_editor(
            (object) $values,
            $fieldname,
            $options,
            $this->df->context,
            'mod_datalynx',
            'content',
            $contentid
        );

        // Get the file content.
        $fs = get_file_storage();
        $array = $fs->get_area_files(
            $this->df->context->id,
            'mod_datalynx',
            'content',
            $contentid,
            'sortorder',
            false
        );
        $file = reset($array);
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
     *
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     *
     * @param array $search Search parameters array with [not, operator, value].
     * @return array SQL fragments array.
     */
    public function get_search_sql(array $search): array {
        // We keep the not and compare with exactly 1 in the content column.
        [$not, $operator, $value] = $search;
        $value = '1';
        $operator = '=';
        return parent::get_search_sql([$not, $operator, $value]);
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     * @return bool
     */
    public static function is_customfilterfield(): bool {
        return true;
    }

    /**
     * Is $value a valid content or do we see an empty input?
     *
     * @param mixed $value
     * @return bool
     */
    public static function is_fieldvalue_empty($value): bool {
        // TODO: MDL-00000 We see a draftarea id, need to determine if files are linked to it.
        global $DB;
        $filesizes = $DB->get_records_menu(
            'files',
            ['itemid' => $value],
            'filesize DESC',
            "id, filesize",
            '',
            1
        );

        // If the biggest file in the draftarea has a positive filesize, it is not empty.
        if (!reset($filesizes)) {
            return true;
        }
        return false;
    }
}
