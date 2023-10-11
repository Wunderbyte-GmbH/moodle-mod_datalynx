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

    /**
     * Class constructor
     *
     * @param datalynx|int $df datalynx id or class object
     * @param stdClass|int $rule rule id or DB record
     */
    public function __construct($df = 0, $rule = 0) {
        parent::__construct($df, $rule);
        $this->sftpserver = $this->rule->param2;
        $this->stfpport = $this->rule->param3;
        $this->sftpusername = $this->rule->param4;
        $this->sftppassword = $this->rule->param5;
        $this->sftppath = $this->rule->param6;
    }

    public function trigger(\core\event\base $event) {
        global $CFG;
        require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");
        require_once("$CFG->dirroot/mod/datalynx/view/csv/view_class.php");
        require_once($CFG->libdir.'/completionlib.php');

        $did = $event->get_data()['objectid'];

        // Download Server files.
        $dl = $this->download_files($did);

        $instance = new datalynxview_csv($did);
        $df = new mod_datalynx\datalynx($did);

        // $file = $CFG->dirroot . '/mod/datalynx/testfile/1_test.csv';
        $fs = get_file_storage();

        // Scan folder
        // $folder = $CFG->dataroot . '/temp/csvimport/moddatalynx/' . $did . '/';
        // $files = scandir($folder);

        // foreach ($files as $file) {
        //     if ($file !== '.' && $file !== '..') {
        //         if (preg_match('/^(\d+)_/', $file, $matches)) {
        //             $prefix = $matches[1];
        //         }
        //     }
        // }

        // How
        // $filerecord = [
        //     'contextid'    => $df->context->id,
        //     'component'    => 'mod_datalynx',
        //     'filearea'     => 'content',
        //     'itemid'       => 204,  -> (not entryid datalynx_contents -> id)
        //     'filepath'     => '/',
        //     'filename'     => 'test.csv',
        //     'timecreated'  => time(),
        //     'timemodified' => time(),
        //     'userid' => $userid,
        // ];
        // $fs->create_file_from_pathname($filerecord, $file);

        if (file_exists($file) && is_readable($file)) {
                $filecontents = file_get_contents($file);

            if ($filecontents !== false) {
                $data = new stdClass();
                $data->eids = array();
                $df = new mod_datalynx\datalynx($did);
                $fields = $df->get_fields();
                // Get all the fields and write it into fieldsettings array. Needed for process_csv function.
                $fieldsettings = [];
                foreach ($fields as $field => $value) {
                    $fieldsettings[$field] = [$value->field->name => array('name' => $value->field->name)];
                }
                // Options array for process_csv
                $options = array(
                'settings' => $fieldsettings,
                );
                $data->ftpsyncmode = true;
                $data = $instance->process_csv($data, $filecontents, $options);
                $instance->execute_import($data);
            } else {
                // handle the case where reading the file failed.
                echo 'Error reading the file.';
            }
        } else {
            // handle the case where the file does not exist or is not readable.
            echo 'File does not exist or is not readable.';
        }

        return true;
    }

    /**
     * Download the files from the server and place it in a temporary folder.
     *
     * @param  integer $did
     *
     * @return void
     */
    private function download_files(int $did) {
        global $CFG;
        // Initialize cURL.
        $c = curl_init("sftp://$this->sftpusername:$this->sftppassword@$this->sftpserver:$this->stfpport/$this->sftppath");

        // Set cURL options for SFTP.
        curl_setopt($c, CURLOPT_RETURNTRANSFER, CURLPROTO_SFTP);

        // Get a list of files in the SFTP folder.
        $list = curl_exec($c);
        $info = curl_getinfo($c);

        // Close the cURL connection.
        curl_close($c);

        // Split the directory listing into an array of file names.
        $lines = explode("\n", trim($list));

        foreach ($lines as $line) {
            // Split each line by whitespace and extract the last field as the file name.
            $fields = preg_split('/\s+/', trim($line));
            $filename = end($fields);

            // Skip directories and any other non-file entries.
            if ($filename && strpos($filename, '.') !== 0) {
                $filenames[] = $filename;
            }
        }

        $datadir = $CFG->dataroot . '/temp/csvimport/moddatalynx/' . $did . '/';
        if (!is_dir($datadir)) {
            // Create the directory if it doesn't exist
            if (!make_temp_directory('/csvimport/moddatalynx/' .$did .'/')) {
                // Handle directory creation error (e.g., display an error message)
                throw new Exception('Error creating sapfiles directory');
            }
        }
        // Loop through the file names and download each file.
        foreach ($filenames as $filename) {
            // Initialize a new cURL handle to download the file.
            $c = curl_init("sftp://$this->sftpusername:$this->sftppassword@$this->sftpserver:$this->stfpport/$this->sftppath/$filename");

            // Set cURL options for SFTP file transfer.
            $destinationpath = $CFG->dataroot . '/temp/csvimport/moddatalynx/' . $did . '/' . $filename; // Replace with your local download path
            $fp = fopen($destinationpath, 'w');

            curl_setopt($c, CURLOPT_FILE, $fp);
            curl_setopt($c, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);

            // Execute the cURL request to download the file.
            curl_exec($c);

            // Close the cURL handle and the local file pointer.
            curl_close($c);
            fclose($fp);
            return true;
        }
    }

    /**
     * Check how to get the userid based on rule setting
     *
     * @param  string $userinfo
     *
     * @return void
     */
    // private function get_userid(string $userinfo) {
    //     switch ($this->mode) {
    //         case self::USERID:
    //             $userid  = 2; // Replace with the actual user ID
    //             break;
    //         case self::USERNAME:
    //             // $userid =
    //             break;
    //         case self::USEREMAIL:

    //             break;
    //         default:
    //             echo "Invalid mode selected.";
    //             break;
    //     }
    // }
}
