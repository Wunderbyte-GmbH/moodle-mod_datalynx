<?php
/**
 * Created by PhpStorm.
 * User: isakic
 * Date: 10/22/14
 * Time: 12:00 PM
 */

define('AJAX_SCRIPT', true);

require_once('../../../config.php');

$behaviorid = required_param('behaviorid', PARAM_INT);
$permissionid = optional_param('permissionid', 0, PARAM_INT);
$forproperty = required_param('forproperty', PARAM_ALPHA);

require_sesskey();

$toggle = "ERROR";
if ($for == "required") {
    $required = $DB->get_field('datalynx_behaviors', $forproperty, array('id' => $behaviorid));
    if ($required) {
        $required = 0;
        $toggle = "OFF";
    } else {
        $required = 1;
        $toggle = "ON";
    }

    $DB->set_field('datalynx_behaviors', $forproperty, $required, array('id' => $behaviorid));
} else {
    $permissions = unserialize($DB->get_field('datalynx_behaviors', $forproperty, array('id' => $behaviorid)));
    if (!in_array($permissionid, $permissions)) {
        $permissions[] = $permissionid;
        $toggle = "ON";
    } else {
        if(($key = array_search($permissionid, $permissions)) !== false) {
            unset($permissions[$key]);
        }
        $toggle = "OFF";
    }
    $DB->set_field('datalynx_behaviors', $forproperty, serialize($permissions), array('id' => $behaviorid));
}

echo json_encode($toggle);
die;
