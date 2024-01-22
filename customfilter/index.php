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
 * @copyright 2018 Thomas Niedermaier
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once('../classes/datalynx.php');

$urlparams = new stdClass();

$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.
$urlparams->fid = optional_param('fid', 0, PARAM_INT); // Update filter id.
$urlparams->new = optional_param('new', 0, PARAM_INT); // New filter.
$urlparams->default = optional_param('default', 0, PARAM_INT); // Id of filter to default.
$urlparams->visible = optional_param('visible', 0, PARAM_SEQUENCE); // Filter ids (comma delimited)
                                                                    // to hide/show.
$urlparams->fedit = optional_param('fedit', 0, PARAM_INT); // Filter id to edit.
$urlparams->delete = optional_param('delete', 0, PARAM_SEQUENCE); // Filter ids (comma delim) to
                                                                  // delete.
$urlparams->duplicate = optional_param('duplicate', 0, PARAM_SEQUENCE); // Filter ids (comma delim)
                                                                        // to duplicate.
$urlparams->confirmed = optional_param('confirmed', 0, PARAM_INT);

$urlparams->update = optional_param('update', 0, PARAM_INT); // Update filter.
$urlparams->cancel = optional_param('cancel', 0, PARAM_BOOL);

$dl = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);
require_capability('mod/datalynx:managetemplates', $dl->context);

$dl->set_page('customfilter/index', array('modjs' => true, 'urlparams' => $urlparams));
require_login($dl->data->course, false, $dl->cm);

navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/customfilter/index.php', array('id' => $dl->cm->id)));

$fm = $dl->get_customfilter_manager();

// DATA PROCESSING.
// ADD, UPDATE a new filter.
if ($urlparams->update && confirm_sesskey()) {
    $fm->process_filters('update', $urlparams->fid, true);
} else if ($urlparams->duplicate && confirm_sesskey()) { // DUPLICATE any requested filters.
    $fm->process_filters('duplicate', $urlparams->duplicate, $urlparams->confirmed);
} else if ($urlparams->delete && confirm_sesskey()) { // DELETE any requested filters.
    $fm->process_filters('delete', $urlparams->delete, $urlparams->confirmed);
} else if ($urlparams->visible && confirm_sesskey()) { // Set filter's VISIBILITY.
    $fm->process_filters('visible', $urlparams->visible, true);
}

// Edit a new filter.
if ($urlparams->new && confirm_sesskey()) {
    $filter = $fm->get_filter_from_id($fm::BLANK_FILTER);
    $filterform = $fm->get_customfilter_backend_form($filter);
    $fm->display_filter_form($filterform, $filter, $urlparams);
} else if ($urlparams->fedit && confirm_sesskey()) { // Or edit existing filter.
    $filter = $fm->get_filter_from_id($urlparams->fid);
    $filterform = $fm->get_customfilter_backend_form($filter);
    $fm->display_filter_form($filterform, $filter, $urlparams);
} else { // Or display the filters list.
    if (!$filters = $fm->get_filters(null, false, true)) { // Any notifications?
        $dl->notifications['bad'][] = get_string('customfiltersnoneindatalynx', 'datalynx');
    }
    // Print header.
    $dl->print_header(array('tab' => 'customfilters', 'urlparams' => $urlparams));
    // Print the filter add link.
    $fm->print_add_filter();
    // If there are filters print admin style list of them.
    if ($filters) {
        $fm->print_filter_list();
    }
}

$dl->print_footer();
