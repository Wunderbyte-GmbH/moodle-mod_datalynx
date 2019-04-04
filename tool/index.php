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
 * @package datalynxtool
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../classes/datalynx.php');

$urlparams = new stdClass();

$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.

// Views list actions.
$urlparams->run = optional_param('run', '', PARAM_PLUGIN); // Tool plugin to run.

$urlparams->confirmed = optional_param('confirmed', 0, PARAM_INT);

// Set a datalynx object.
$df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);

require_login($df->data->course, false, $df->cm);

require_capability('mod/datalynx:managetemplates', $df->context);

$df->set_page('tool/index', array('modjs' => true, 'urlparams' => $urlparams));

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/tool/index.php', array('id' => $df->cm->id)));

// DATA PROCESSING.
if ($urlparams->run and confirm_sesskey()) { // Run selected tool.
    $tooldir = "$CFG->dirroot/mod/datalynx/tool/$urlparams->run";
    $toolclass = "datalynxtool_$urlparams->run";
    if (file_exists($tooldir)) {
        require_once("$tooldir/lib.php");
        if ($result = $toolclass::run($df)) {
            list($goodbad, $message) = $result;
        } else {
            $goodbad = 'bad';
            $message = '';
        }
        $df->notifications[$goodbad][] = $message;
    }
}

// Get the list of tools.
$directories = get_list_of_plugins('mod/datalynx/tool/');
$tools = array();
foreach ($directories as $directory) {
    $tools[$directory] = (object) array(
            'name' => get_string('pluginname', "datalynxtool_$directory"),
            'description' => get_string('pluginname_help', "datalynxtool_$directory")
    );
}
ksort($tools); // Sort in alphabetical order.

// Any notifications?
if (!$tools) {
    $df->notifications['bad'][] = get_string('toolnoneindatalynx', 'datalynx'); // Nothing in.
    // Database.
}

// Print header.
$df->print_header(array('tab' => 'tools', 'urlparams' => $urlparams));

// If there are tools print admin style list of them.
if ($tools) {
    $actionbaseurl = '/mod/datalynx/tool/index.php';
    $linkparams = array('d' => $df->id(), 'sesskey' => sesskey());

    // Table headings.
    $strname = get_string('name');
    $strdesc = get_string('description');
    $strrun = get_string('toolrun', 'datalynx');

    $table = new html_table();
    $table->head = array($strname, $strdesc, $strrun);
    $table->align = array('left', 'left', 'center');
    $table->wrap = array(false, false, false);
    $table->attributes['align'] = 'center';

    foreach ($tools as $dir => $tool) {

        $runlink = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('run' => $dir)),
                $OUTPUT->pix_icon('t/collapsed', $strrun));

        $table->data[] = array($tool->name, $tool->description, $runlink);
    }
    echo html_writer::table($table);
}

$df->print_footer();

