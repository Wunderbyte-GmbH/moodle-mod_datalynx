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
 * @package datalynxtool
 * @subpackage downloadfiles
 * @copyright 2017 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

use core\notification;

require_once("$CFG->dirroot/mod/datalynx/entries_class.php");
require_once("$CFG->dirroot/mod/datalynx/field/entryauthor/field_class.php");

class datalynxtool_downloadfiles {

    /**
     * Retrieves all files of all entries adds them to zip and sends them to download
     *
     * @param datalynx $dl
     */
    public static function run(mod_datalynx\datalynx $dl) {
        global $DB, $CFG;

        // Create filter in order to get all files of all entries of a dl instance.
        $fields = $dl->get_fields_by_type('file');
        $filterdata = new stdClass();
        $filterdata->dataid = $dl->id();
        $filterdata->contentfields = array_keys($fields);
        $filter = new datalynx_filter($filterdata);
        $em = new datalynx_entries($dl, $filter);
        $em->set_content();
        $files = $em->get_embedded_files($filterdata->contentfields);
        $fileinfo = $em->get_contentinfo($filterdata->contentfields);

        // Construct the zip file name.
        $filename = clean_filename($dl->course->shortname . '-' . $dl->name() . '-' . '.zip');

        $filesforzipping = array();
        if (!empty($files)) {
            foreach ($files as $zipfilepath => $file) {
                $foldername = $fileinfo[$file->get_itemid()]->lastname . "_" . $fileinfo[$file->get_itemid()]->firstname . "/";
                $pathfilename = $foldername . $file->get_filename();
                $pathfilename = clean_param($pathfilename, PARAM_PATH);
                $filesforzipping[$pathfilename] = $file;
            }
        }

        if (count($filesforzipping) == 0) {
            $result = notification::warning(get_string('entrynoneforaction', 'mod_datalynx'));
        } else if ($zipfile = self::pack_files($filesforzipping)) {
            // Send file and delete after sending.
            send_temp_file($zipfile, $filename);
            // We will not get here - send_temp_file calls exit.
        }
    }

    /**
     * Generate zip file from array of given files.
     *
     * @param array $filesforzipping - array of files to pass into archive_to_pathname.
     *                                 This array is indexed by the final file name and each
     *                                 element in the array is an instance of a stored_file object.
     * @return string path of temp file - note this returned file does
     *         not have a .zip extension - it is a temp file.
     */
    protected static function pack_files($filesforzipping) {
        global $CFG;
        // Create path for new zip file.
        $tempzip = tempnam($CFG->tempdir . '/', 'datalynx_');
        // Zip files.
        $zipper = new zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            return $tempzip;
        }
        return false;
    }
}
