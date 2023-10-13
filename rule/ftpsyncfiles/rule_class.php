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

    protected $sftpport;

    protected $sftpusername;

    protected $sftppassword;

    protected $sftppath;

    const USERID = 1;

    const USERNAME = 2;

    const USEREMAIL = 3;
    /**
     * user profile field to use to identify the user.
     * @var string
     */
    private $matchingfield;

    /**
     * Field id of the teammemberselect field the user should be assigned to.
     * @var int
     */
    private $teammemberfieldid;

    /**
     * The id of the user who should own the entry. This is different from the user matched from the file.
     * @var
     */
    private $authorid;
    /**
     * The datalynx object to use.
     * @var \mod_datalynx\datalynx
     */
    private \mod_datalynx\datalynx $dl;
    private ?file_storage $fs;
    /**
     * @var false|int
     */
    private $draftitemid;

    /**
     * Class constructor
     *
     * @param int $df datalynx id or class object
     * @param int $rule rule id or DB record
     */
    public function __construct($df = 0, $rule = 0) {
        parent::__construct($df, $rule);
        $this->sftpserver = $this->rule->param2;
        $this->sftpport = $this->rule->param3;
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
        $this->dl = new mod_datalynx\datalynx($did);

        // Download Server files.
        $this->draftitemid = file_get_submitted_draft_itemid('file'); // Assuming 'file' is the form field name.
        $this->fs = get_file_storage();
        $this->download_files((int)$did);

        $files = $this->fs->get_area_files($this->dl->context, 'mod_datalynx', 'draft', $this->draftitemid);

        if (!empty($files)) {
            foreach ($files as $file) {
                if (file_exists($file) && is_readable($file)) {
                    $filename = $file->get_filename();
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
                        $data->{"field_{$this->teammemberfieldid}_{$entryid}"} = $this->get_userid_from_filename($filename);
                        $processed = $dlentries->process_entries('update', $data->eids, $data, true);
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
     * Download the files from the server and place in draftitemid.
     *
     * @param  int $did
     * @return void
     */
    private function download_files(int $did): void {
        global $CFG;
        $server = $this->sftpserver;
        $remotedir = $this->sftppath;
        $username = $this->sftpusername;
        $password = $this->sftppassword;
        $port = $this->sftpport;
        $connection = "sftp://$username:$password@$server:$port$remotedir";
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
        curl_setopt($ch, CURLOPT_URL, $server);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $connection);

        $result = curl_exec($ch);

        if ($result === false) {
            echo 'cURL error: ' . curl_error($ch);
        } else {
            // List of remote files and directories.
            $remotelist = explode("\n", trim($result));

            foreach ($remotelist as $line) {
                $parts = preg_split('/\s+/', trim($line));
                $filename = end($parts);

                if (!empty($filename) && $filename !== '.' && $filename !== '..') {
                    $remotepath = "$this->sftppath$filename";
                    // Todo: Check filename and get all information.

                    $filehandle = curl_init();

                    // Set cURL options for file download.
                    curl_setopt($filehandle, CURLOPT_URL, $connection);
                    curl_setopt($filehandle, CURLOPT_RETURNTRANSFER, 1);

                    // Download the file.
                    $filedata = curl_exec($filehandle);

                    // Todo: get context.
                    $context = $this->dl->context;

                    if ($filedata !== false) {

                        // TODO: Store Data in Moodle.
                        $file = $this->fs->create_file_from_string(
                            [
                                'contextid' => $context->id, // Replace with the appropriate context if necessary.
                                'component' => 'mod_datalynx',
                                'filearea' => 'draft',
                                'itemid' => $this->draftitemid,
                                'filepath' => '/',
                                'filename' => $filename,
                            ],
                            $filedata
                        );

                        echo "Downloaded $filename successfully." . PHP_EOL;
                    } else {
                        echo "Failed to download $filename." . PHP_EOL;
                    }
                    curl_close($filehandle);
                }
            }
        }
        curl_close($ch);
    }

    /**
     * Find out the user the uploaded file belongs to based on the filename
     * @param string $filename
     * @return int
     */
    protected function get_userid_from_filename(string $filename): int {
        global $DB;
        switch ($this->matchingfield) {
            case 'idnumber':
                $userid = $DB->get_field('user', 'id', array('idnumber' => $filename));
                break;
            case 'email':
                $userid = $DB->get_field('user', 'id', array('email' => $filename));
                break;
            case 'id':
                $userid = $filename;
                break;
            case 'username':
                $userid = $DB->get_field('user', 'id', array('username' => $filename));
                break;
            default:
                $userid = 0;
                break;
        }
        return $userid;
    }
}
