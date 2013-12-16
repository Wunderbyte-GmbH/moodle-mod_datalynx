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
require_once($CFG->dirroot . '/lib/filelib.php');
require_once('fieldtransfer_lib.php');

function fieldtransfer_request_user_info($url, $usernames, $fieldnames) {
    global $CFG, $DB;

    $mnetenvironment = get_mnet_environment();
    $requesttext = xmlrpc_encode_request('mod/dataform/fieldtransfer_server.php/fieldtransfer_get_user_info',
        array($CFG->wwwroot, $usernames, $fieldnames),
        array("encoding" => "utf-8", "escaping" => "markup"));
    $signedrequest = mnet_sign_message($requesttext);
    $remotecertificate = $DB->get_field('mnet_host', 'public_key', array('wwwroot' => $url));
    $encryptedrequest = mnet_encrypt_message($signedrequest, $remotecertificate);
    $curl = new curl();
    $rawresponse = $curl->post("{$url}/mod/dataform/fieldtransfer_server.php", array('request' => $encryptedrequest));

    if ($rawresponse == false) {
        return false;
    }

    $rawresponse = trim($rawresponse);

    $crypt_parser = new mnet_encxml_parser();
    $crypt_parser->parse($rawresponse);

    if (!$crypt_parser->payload_encrypted) {
        $crypt_parser->free_resource();
        return false;
    }

    $key  = array_pop($crypt_parser->cipher);
    $data = array_pop($crypt_parser->cipher);

    $crypt_parser->free_resource();

    // Initialize payload var
    $decryptedenvelope = '';

    $isopen = openssl_open(base64_decode($data), $decryptedenvelope, base64_decode($key), $mnetenvironment->get_private_key());

    if (!$isopen) {
        // Decryption failed... let's try our archived keys
        $openssl_history = get_config('mnet', 'openssl_history');
        if(empty($openssl_history)) {
            $openssl_history = array();
            set_config('openssl_history', serialize($openssl_history), 'mnet');
        } else {
            $openssl_history = unserialize($openssl_history);
        }
        foreach($openssl_history as $keyset) {
            $keyresource = openssl_pkey_get_private($keyset['keypair_PEM']);
            $isopen      = openssl_open(base64_decode($data), $decryptedenvelope, base64_decode($key), $keyresource);
            if ($isopen) {
                break;
            }
        }
    }

    if (!$isopen) {
        return false;
    }

    if (strpos(substr($decryptedenvelope, 0, 100), '<signedMessage>')) {
        $sig_parser = new mnet_encxml_parser();
        $sig_parser->parse($decryptedenvelope);
    } else {
        return false;
    }

    $xmlrpcresponse = base64_decode($sig_parser->data_object);
    $response       = xmlrpc_decode($xmlrpcresponse);

    return $response;
}

