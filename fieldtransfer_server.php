<?php
// This file is part of Moodle - http://moodle.org/.
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @package mod
 * @subpackage dataform
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('fieldtransfer_lib.php');

$request = required_param('request', PARAM_RAW);
$plaintextmessage = fieldtransfer_strip_encryption($request);
$xmlrpcrequest = fieldtransfer_strip_signature($plaintextmessage);

$method = '';
$params = xmlrpc_decode_request($xmlrpcrequest, $method);
$response = fieldtransfer_get_user_info($params[1], $params[2]);

$responsetext = xmlrpc_encode($response);
$signedresponse = mnet_sign_message($responsetext);
$remotecertificate = $DB->get_field('mnet_host', 'public_key', array('wwwroot' => $params[0]));
$encryptedresponse = mnet_encrypt_message($signedresponse, $remotecertificate);

echo $encryptedresponse;
die;

/**
 * @param array $usernames
 * @param array $fieldnames
 * @return array
 */
function fieldtransfer_get_user_info(array $usernames, array $fieldnames) {
    global $DB;
    list($insql, $params) = $DB->get_in_or_equal($usernames, SQL_PARAMS_NAMED);
    $query = "SELECT u.username, u.id
                FROM {user} u
               WHERE u.username $insql";
    $usernames = $DB->get_records_sql_menu($query, $params);

    $data = array();
    list($insql, $params) = $DB->get_in_or_equal($fieldnames, SQL_PARAMS_NAMED);
    foreach ($usernames as $username => $userid) {
        $query = "SELECT f.shortname, d.data
                    FROM {user_info_field} f
              INNER JOIN {user_info_data} d ON f.id = d.fieldid
                   WHERE d.userid = :userid
                     AND f.shortname $insql";
        $userinfo = $DB->get_records_sql_menu($query, $params + array('userid' => $userid));
        $data[$username] = $userinfo;
    }

    return $data;
}
