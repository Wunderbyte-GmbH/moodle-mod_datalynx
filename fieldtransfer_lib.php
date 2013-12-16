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

require_once($CFG->dirroot . '/mnet/environment.php');
require_once($CFG->dirroot . '/mnet/lib.php');

function fieldtransfer_strip_encryption($request) {
    $crypt_parser = new mnet_encxml_parser();
    $crypt_parser->parse($request);
    $mnet = get_mnet_environment();

    if (!$crypt_parser->payload_encrypted) {
        return $request;
    }

    // This key is symmetric, and is itself encrypted. Can be decrypted using our private key
    $key  = array_pop($crypt_parser->cipher);
    // This data is symmetrically encrypted, can be decrypted using the above key
    $data = array_pop($crypt_parser->cipher);

    $crypt_parser->free_resource();
    $payload          = '';

    $isOpen = openssl_open(base64_decode($data), $payload, base64_decode($key), $mnet->get_private_key());
    if ($isOpen) {
        return $payload;
    }

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
        $isOpen      = openssl_open(base64_decode($data), $payload, base64_decode($key), $keyresource);
        if ($isOpen) {
            return $payload;
        }
    }

    return false;
}

function fieldtransfer_strip_signature($plaintextmessage) {
    global $DB;

    $sig_parser = new mnet_encxml_parser();
    $sig_parser->parse($plaintextmessage);

    if ($sig_parser->signature == '') {
        return $plaintextmessage;
    }

    $payload = base64_decode($sig_parser->data_object);
    $signature = base64_decode($sig_parser->signature);
    $certificate = $DB->get_field('mnet_host', 'public_key', array('wwwroot' => $sig_parser->remote_wwwroot));

    // If we don't have any certificate for the host, don't try to check the signature
    // Just return the parsed request
    if ($certificate == false) {
        return $payload;
    }

    // Does the signature match the data and the public cert?
    $signature_verified = openssl_verify($payload, $signature, $certificate);
    $sig_parser->free_resource();

    if ($signature_verified) {
        return $payload;
    } else {
        return false;
    }
}