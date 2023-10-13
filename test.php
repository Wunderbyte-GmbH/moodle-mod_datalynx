<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Test file for catquiz
 * @package    local_catquiz
 * @copyright  2023 Wunderbyte GmbH
 * @author     Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\dataformat\base;
use local_catquiz\data\catquiz_base;
use local_catquiz\event\catscale_updated;
use local_catquiz\subscription;

require_once('../../config.php');

global $USER;

$context = \context_system::instance();

$PAGE->set_context($context);
require_login();

$PAGE->set_url(new moodle_url('/local/catquiz/test.php', []));

$title = "Test cases";
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

$sftpserver = 'dedi458.your-server.de/';
$stfpport = '22';
$sftppath = '/';
$sftpusername = 'wunder_1';
$sftppassword = 'Gcb2BWeBtLpWVZt4';
$username = $sftpusername;
$password = $sftppassword;

$server = $sftpserver;

$remotedir = $sftppath;
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

            if ($filedata !== false) {

                // TODO: Store Data in Moodle.

                $fs = get_file_storage();
                $draftitemid = file_get_submitted_draft_itemid('file'); // Assuming 'file' is the form field name.
                $file = $fs->create_file_from_string(
                    [
                        'contextid' => $context->id, // Replace with the appropriate context if necessary.
                        'component' => 'datalynx',
                        'filearea' => 'draft',
                        'itemid' => $draftitemid,
                        'filepath' => '/',
                        'filename' => $file,
                    ],
                    $filedata
                );

                echo "Downloaded successfully." . PHP_EOL;
            } else {
                echo "Failed to download ." . PHP_EOL;
            }

            curl_close($filehandle);
        }
    }
}

echo $OUTPUT->footer();
