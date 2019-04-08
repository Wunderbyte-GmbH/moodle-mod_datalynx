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
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../config.php');

$d = required_param('dfid', PARAM_INT);

// Check user is logged in.
require_login();

$retviews = '';
$rettextfields = '';
if ($d) {
    if ($views = $DB->get_records_menu('datalynx_views', array('dataid' => $d), 'name', 'id,name')) {
        $viewmenu = array();
        foreach ($views as $key => $value) {
            $viewmenu[] = "$key " . strip_tags($value);
        }
        $retviews = implode(',', $viewmenu);
    }
    if ($textfields = $DB->get_records_menu('datalynx_fields', array('dataid' => $d, 'type' => 'text'), 'name', 'id,name')
    ) {
        $textfieldmenu = array();
        foreach ($textfields as $key => $value) {
            $textfieldmenu[] = "$key " . strip_tags($value);
        }
        $rettextfields = implode(',', $textfieldmenu);
    }
}
echo "$retviews#$rettextfields";
