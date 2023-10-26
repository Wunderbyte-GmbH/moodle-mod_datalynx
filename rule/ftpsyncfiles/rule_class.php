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
    private array $sftpsetting;
    private ?int $filefieldid;
    private array $files;
    private $regex;

    /**
     * Class constructor
     *
     * @param int $df datalynx id or class object
     * @param int $rule rule id or DB record
     */
    public function __construct($df = 0, $rule = 0) {
        parent::__construct($df, $rule);
        if (isset($this->rule->param2)) {
            $this->sftpsetting = unserialize($this->rule->param2);
            $this->sftpserver = $this->sftpsetting['sftpserver'];
            $this->sftpport = $this->sftpsetting['sftpport'];
            $this->sftpusername = $this->sftpsetting['sftpusername'];
            $this->sftppassword = $this->sftpsetting['sftppassword'];
            $this->sftppath = $this->sftpsetting['sftppath'];
        }
        $this->matchingfield = $this->rule->param7;
        $this->teammemberfieldid = $this->rule->param8;
        $this->authorid = $this->rule->param9;
        $this->filefieldid = $this->rule->param3;
        $this->regex = $this->rule->param6;
    }

    /**
     * Based on a triggered event we start downloading files.
     * @param \core\event\base $event
     */
    public function trigger(\core\event\base $event) {

        global $CFG, $USER;
        require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");
        require_once("$CFG->dirroot/mod/datalynx/field/entryauthor/field_class.php");
        require_once("$CFG->dirroot/mod/datalynx/entries_class.php");
        require_once("$CFG->dirroot/mod/datalynx/view/csv/view_class.php");
        require_once($CFG->libdir.'/filelib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $did = $event->get_data()['objectid'];
        $this->dl = new mod_datalynx\datalynx($did);

        $this->fs = get_file_storage();
        // Download files to $this->files array indexed by draftitemid.
        $this->download_files((int)$did);
        $context = context_user::instance($USER->id);

        if (!empty($this->files)) {
            foreach ($this->files as $draftitemid => $file) {
                $data = new stdClass();
                $data->eids = [];
                $fieldid = datalynxfield_entryauthor::_USERID;
                $filename = $file->get_filename();
                $entryid = -1;
                $data->eids[$entryid] = $entryid;
                // TODO: If filename is not userid get userid here.
                $data->{"field_{$fieldid}_{$entryid}"} = $this->authorid;
                $data->{"field_{$this->filefieldid}_{$entryid}_filemanager"} = $draftitemid;
                $data->{"field_{$this->filefieldid}_{$entryid}_alttext"} = "PDF";
                $dlentries = new datalynx_entries($this->dl);
                // Set teammember from filename.
                $userid = $this->get_userid_from_filename($filename);
                $data->{"field_{$this->teammemberfieldid}_{$entryid}"} = ["$userid"];
                $processed = $dlentries->process_entries('update', $data->eids, $data, true);
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
        global $USER;
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
        curl_setopt($ch, CURLOPT_DIRLISTONLY, 1);
        curl_setopt($ch, CURLOPT_URL, $connection);

        $result = curl_exec($ch);

        if ($result === false) {
            echo 'cURL error: ' . curl_error($ch);
        } else {
            // List of remote files and directories.
            $remotelist = explode("\n", trim($result));
            foreach ($remotelist as $line) {
                $filename = trim($line);
                if (!empty($filename) && $filename !== '.' && $filename !== '..') {
                    $remotepath = "$connection/$filename";
                    // Todo: Check filename and get all information.
                    $filehandle = curl_init();

                    // Set cURL options for file download.
                    curl_setopt($filehandle, CURLOPT_URL, $remotepath);
                    curl_setopt($filehandle, CURLOPT_RETURNTRANSFER, 1);

                    // Download the file.
                    $filedata = curl_exec($filehandle);
                    $context = context_user::instance($USER->id);

                    if ($filedata !== false) {
                        // TODO: Store Data in Moodle.
                        $draftitemid = file_get_unused_draft_itemid();
                        file_prepare_draft_area($draftitemid, $context->id, 'user', 'content', null);
                        $this->files[$draftitemid] = $this->fs->create_file_from_string(
                            [
                                'contextid' => $context->id, // Replace with the appropriate context if necessary.
                                'component' => 'user',
                                'filearea' => 'draft',
                                'itemid' => $draftitemid,
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
            foreach ($remotelist as $line) {
                $filename = trim($line);
                if (!empty($filename) && $filename !== '.' && $filename !== '..') {
                    // Delete the file from the remote server using cURL.
                    curl_setopt($ch, CURLOPT_QUOTE, ["rm " . escapeshellarg($filename)]);
                    $deleteresult = curl_exec($ch);
                    if ($deleteresult === false) {
                        curl_setopt($ch, CURLOPT_QUOTE, ["rm " . $filename]);
                        $deleteresult = curl_exec($ch);
                        if ($deleteresult === false) {
                            curl_setopt($ch, CURLOPT_QUOTE, ["rm \"$filename\""]);
                            $deleteresult = curl_exec($ch);
                            if ($deleteresult === false) {
                                echo "Failed to delete $filename on the remote server." . PHP_EOL;
                                var_dump(curl_errno($ch),curl_error($ch));
                            } else {
                                echo "Deleted $filename successfully." . PHP_EOL;
                            }
                        } else {
                            echo "Deleted $filename successfully." . PHP_EOL;
                        }
                    } else {
                        echo "Deleted $filename successfully." . PHP_EOL;
                    }
                }
            }
        }
        curl_close($ch);
    }

    /**
     * Find out the user the uploaded file belongs to based on the filename.
     * Regex defaults to ^idnumber_
     *
     * @param string $filename
     * @return int
     */
    protected function get_userid_from_filename(string $filename): int {
        global $DB;
        // Extract user identifier from filename using a pattern.
        if (empty($this->regex)) {
            $this->regex = "/^(\d+)_/";
        }
        if (preg_match($this->regex, $filename, $matches)) {
            $identifier = $matches[1];
        } else {
            return 0;
        }

        switch ($this->matchingfield) {
            case 'idnumber':
                $userid = $DB->get_field('user', 'id', array('idnumber' => $identifier));
                break;
            case 'email':
                $userid = $DB->get_field('user', 'id', array('email' => $identifier));
                break;
            case 'id':
                $userid = $identifier;
                break;
            case 'username':
                $userid = $DB->get_field('user', 'id', array('username' => $identifier));
                break;
            default:
                $userid = 0;
                break;
        }
        return (int) $userid;
    }
}
