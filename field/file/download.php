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
 * @package dataformfield
 * @subpackage file
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');

$cid = required_param('cid', PARAM_INT);
$context = required_param('context', PARAM_INT);
$file = required_param('file', PARAM_FILE);
// Check user is logged in
require_login();

$count = $DB->get_field('dataform_contents', 'content2', array('id' => $cid));
$count++;
$DB->set_field('dataform_contents', 'content2', $count, array('id' => $cid));
redirect(new moodle_url("/pluginfile.php/$context/mod_dataform/content/$cid/$file"));

