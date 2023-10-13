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
 * @package datalynx_rule
 * @subpackage ftpsyncfiles
 * @copyright 2015 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . "/../rule_class.php");

class datalynx_rule_ftpsyncfiles extends datalynx_rule_base {

    public $type = 'ftpsyncfiles';

    protected $sftpserver;

    protected $stfpport;

    protected $sftpusername;

    protected $sftppassword;

    protected $sftppath;

    const USERID = 1;

    const USERNAME = 2;

    const USEREMAIL = 3;
    private $matchingfield;
    private $teammemberfieldid;
    private $authorid;
    private \mod_datalynx\datalynx $dl;

    /**
     * Class constructor
     *
     * @param int $df datalynx id or class object
     * @param int $rule rule id or DB record
     */
    public function __construct($df = 0, $rule = 0) {
        parent::__construct($df, $rule);
        $this->sftpserver = $this->rule->param2;
        $this->stfpport = $this->rule->param3;
        $this->sftpusername = $this->rule->param4;
        $this->sftppassword = $this->rule->param5;
        $this->sftppath = $this->rule->param6;
        $this->matchingfield = $this->rule->param7;
        $this->teammemberfieldid = $this->rule->param8;
        $this->authorid = $this->rule->param9;
    }

    /**
     * Based on a triggered event we start downloading files.
     * @param \core\event\base $event
     */
    public function trigger(\core\event\base $event) {
        global $CFG;
        require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");
        require_once("$CFG->dirroot/mod/datalynx/view/csv/view_class.php");
        require_once($CFG->libdir.'/completionlib.php');

        $did = $event->get_data()['objectid'];

        // Download Server files.
        $filenames = $this->download_files((int)$did);

        $this->dl = new mod_datalynx\datalynx($did);
        $fs = get_file_storage();

        // Scan folder
        // $folder = $CFG->dataroot . '/temp/csvimport/moddatalynx/' . $did . '/';
        // $files = scandir($folder);

        // foreach ($files as $file) {
        // if ($file !== '.' && $file !== '..') {
        // if (preg_match('/^(\d+)_/', $file, $matches)) {
        // $prefix = $matches[1];
        // }
        // }
        // }

        // How
        // $filerecord = [
        // 'contextid'    => $df->context->id,
        // 'component'    => 'mod_datalynx',
        // 'filearea'     => 'content',
        // 'itemid'       => 204,  -> (not entryid datalynx_contents -> id)
        // 'filepath'     => '/',
        // 'filename'     => 'test.csv',
        // 'timecreated'  => time(),
        // 'timemodified' => time(),
        // 'userid' => $userid,
        // ];
        // $fs->create_file_from_pathname($filerecord, $file);

        if (!empty($filenames)) {
            foreach ($filenames as $filename) {
                if (file_exists($file) && is_readable($file)) {
                    $filecontents = file_get_contents($file);
                    if ($filecontents !== false) {
                        $data = new stdClass();
                        $data->eids = [];

                        $fieldid = datalynxfield_entryauthor::_USERID;
                        $entryid = -1;
                        $data->eids[$entryid] = $entryid;
                        // TODO: If filename is not userid get userid here.
                        // Entry author is specified in the rule settings:
                        $data->{"field_{$fieldid}_{$entryid}"} = $this->authorid;
                        $dlentries = new datalynx_entries($this->dl);

                        // Set teammember from filename.
                        $data->{"field_{$this->teammemberfieldid}_{$entryid}"} = $filename;
                        $processed = $dlentries->process_entries('update', $data->eids, $data, true);

                        // Get all the fields and write it into fieldsettings array.
                        $fieldsettings = [];
                        foreach ($fields as $field => $value) {
                            $fieldsettings[$field] = [$value->field->name => array('name' => $value->field->name)];
                        }

                    } else {
                        // handle the case where reading the file failed.
                        echo 'Error reading the file.';
                    }
                } else {
                    // handle the case where the file does not exist or is not readable.
                    echo 'File does not exist or is not readable.';
                }
            }
        }
        return true;
    }

    /**
     * Download the files from the server and place it in a temporary folder.
     *
     * @param  integer $did
     * @return array
     */
    private function download_files(int $did): array {
        global $CFG;
        $server = "sftp://" . $this->sftpserver . ":" . $this->stfpport . "/" . $this->sftppath;
        $username = $this->sftpusername;
        $password = $this->sftppassword;

        // Local directory to save downloaded files
        $localdir = $CFG->dataroot . '/temp/' . $did . '/';

        $remotedir = '/';
        $localdir = '';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "sftp://$username:$password@$server$remotedir");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);

        if ($result === false) {
            echo 'cURL error: ' . curl_error($ch);
        } else {
            // List of remote files and directories.
            $remotelist = explode("\n", trim($result));

            foreach ($remotelist as $line) {
                $parts = preg_split('/\s+/', trim($line));
                $file = end($parts);

                if (!empty($file) && $file !== '.' && $file !== '..') {
                    $remotepath = "$remotedir$file";
                    $localpath = $localdir . $file;


                    // Todo: Check filename and get all information.

                    $filehandle = curl_init();

                    // Set cURL options for file download.
                    curl_setopt($filehandle, CURLOPT_URL, "sftp://$username:$password@$server$remotepath");
                    curl_setopt($filehandle, CURLOPT_RETURNTRANSFER, 1);

                    // Download the file.
                    $filedata = curl_exec($filehandle);

                    // Todo: get context.
                    $context = context_module::instance(1);

                    if ($filedata !== false) {

                        // TODO: Store Data in Moodle.

                        $fs = get_file_storage();
                        $draftitemid = file_get_submitted_draft_itemid('file'); // Assuming 'file' is the form field name.
                        $file = $fs->create_file_from_string(
                            [
                                'contextid' => $context->id, // Replace with the appropriate context if necessary.
                                'component' => 'user',
                                'filearea' => 'draft',
                                'itemid' => $draftitemid,
                                'filepath' => '/',
                                'filename' => $file,
                            ],
                            $filedata
                        );

                        echo "Downloaded $file successfully." . PHP_EOL;
                    } else {
                        echo "Failed to download $file." . PHP_EOL;
                    }

                    curl_close($filehandle);
                }
            }
        }

        curl_close($ch);

        if (!is_dir($localdir)) {
            // Create the directory if it doesn't exist
            if (!mkdir($localdir)) {
                // Handle directory creation error (e.g., display an error message)
                throw new Exception('Error creating directory');
            }
        }
        $filenames = scandir($localdir);
        if ($filenames) {
            return $filenames;
        } else {
            return [];
        }
    }
}
