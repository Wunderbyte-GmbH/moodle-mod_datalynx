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
 * @package     dataform
 * @subpackage  statistics
 * @copyright   2013 Ivan Šakić
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../mod_class.php');
require_once('statistics_class.php');

$urlparams = new stdClass();
$urlparams->d   = optional_param('d', 0, PARAM_INT);             // dataform id
$urlparams->id  = optional_param('id', 0, PARAM_INT);            // course module id

// Set a dataform object
$df = new dataform($urlparams->d, $urlparams->id, true);
require_capability('mod/dataform:viewstatistics', $df->context);

$df->set_page('statistics/index', array('modjs' => true, 'urlparams' => $urlparams));

// activate navigation node
navigation_node::override_active_url(new moodle_url('/mod/dataform/statistics/index.php', array('id' => $df->cm->id)));

// Print header
$df->print_header(array('tab' => 'statistics', 'urlparams' => $urlparams));

$stats = new dataform_statistics_class($df);

$mform = $stats->get_form();
if ($data = $mform->get_data()) {
    $data->from_old = $data->from;
    $data->to_old = $data->to;
    $data->mode_old = $data->mode;
    $data->show_old = isset($data->show) ? $data->show : array();
    $mform->set_data($data);
} else if($mform->is_submitted()) {
    $data = null;
} else {
    $data = new stdClass();
    $data->from = 0;
    $data->to = time();
    $data->mode = dataform_statistics_class::MODE_ALL_TIME;
    $data->show = array(1 => 1, 2 => 1, 4 => 1, 8 => 1);
    $mform->set_data($data);
}

$mform->display();

$stats->print_statistics($data);

$df->print_footer();

die;
