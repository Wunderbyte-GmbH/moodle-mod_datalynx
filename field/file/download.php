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
 * @package datalynxfield
 * @subpackage file
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');

$cid = required_param('cid', PARAM_INT);
$context = required_param('context', PARAM_INT);
$file = required_param('file', PARAM_FILE);
// Check user is logged in.
require_login();

$count = $DB->get_field('datalynx_contents', 'content2', array('id' => $cid));
$count++;
$DB->set_field('datalynx_contents', 'content2', $count, array('id' => $cid));
redirect(new moodle_url("/pluginfile.php/$context/mod_datalynx/content/$cid/$file"));

