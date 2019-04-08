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
 * @package mod-datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Definition of log events
 */
defined('MOODLE_INTERNAL') or die();

$logs = array(
        array('module' => 'datalynx', 'action' => 'view', 'mtable' => 'datalynx', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'add', 'mtable' => 'datalynx', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'update', 'mtable' => 'datalynx', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'record delete', 'mtable' => 'datalynx', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'fields add', 'mtable' => 'datalynx_fields', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'fields update', 'mtable' => 'datalynx_fields', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'views add', 'mtable' => 'datalynx_views', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'views update', 'mtable' => 'datalynx_views', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'filters add', 'mtable' => 'datalynx_filters', 'field' => 'name'),
        array('module' => 'datalynx', 'action' => 'filters update', 'mtable' => 'datalynx_filters', 'field' => 'name')
);
