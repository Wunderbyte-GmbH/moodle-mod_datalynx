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
require_once('../../../config.php');
require_once('../classes/datalynx.php');

$urlparams = new stdClass();

$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.
$urlparams->fid = optional_param('fid', 0, PARAM_INT); // Update filter id.

// Filters list actions.
$urlparams->new = optional_param('new', 0, PARAM_INT); // New filter.
$urlparams->default = optional_param('default', 0, PARAM_INT); // Id of filter to default.
$urlparams->visible = optional_param('visible', 0, PARAM_SEQUENCE); // Filter ids (comma.
// Delimited) to hide/show.
$urlparams->fedit = optional_param('fedit', 0, PARAM_INT); // Filter id to edit.
$urlparams->delete = optional_param('delete', 0, PARAM_SEQUENCE); // Filter ids (comma delim) to.
// Delete.
$urlparams->duplicate = optional_param('duplicate', 0, PARAM_SEQUENCE); // Filter ids (comma delim).
// To duplicate.

$urlparams->confirmed = optional_param('confirmed', 0, PARAM_INT);

// Filter actions.
$urlparams->update = optional_param('update', 0, PARAM_INT); // Update filter.
$urlparams->cancel = optional_param('cancel', 0, PARAM_BOOL);

// Set a datalynx object.
$df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);
require_capability('mod/datalynx:managetemplates', $df->context);

$df->set_page('filter/index', array('modjs' => true, 'urlparams' => $urlparams));

require_login($df->data->course, false, $df->cm);

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/filter/index.php', array('id' => $df->cm->id)));

$fm = $df->get_filter_manager();

// DATA PROCESSING.
if ($urlparams->update and confirm_sesskey()) { // Add/update a new filter.
    $fm->process_filters('update', $urlparams->fid, true);
} else {
    if ($urlparams->duplicate and confirm_sesskey()) { // Duplicate any requested filters.
        $fm->process_filters('duplicate', $urlparams->duplicate, $urlparams->confirmed);
    } else {
        if ($urlparams->delete and confirm_sesskey()) { // Delete any requested filters.
            $fm->process_filters('delete', $urlparams->delete, $urlparams->confirmed);
        } else {
            if ($urlparams->visible and confirm_sesskey()) { // Set filter's visibility.
                $fm->process_filters('visible', $urlparams->visible, true); // Confirmed by default.
            } else {
                if ($urlparams->default and confirm_sesskey()) { // Set filter to default.
                    if ($urlparams->default == -1) {
                        $df->set_default_filter(); // Reset.
                    } else {
                        $df->set_default_filter($urlparams->default);
                    }
                }
            }
        }
    }
}

// Edit a new filter.
if ($urlparams->new and confirm_sesskey()) {
    $filter = $fm->get_filter_from_id($fm::BLANK_FILTER);
    $filterform = $fm->get_filter_form($filter);
    $fm->display_filter_form($filterform, $filter, $urlparams);

    // Or edit existing filter.
} else {
    if ($urlparams->fedit and confirm_sesskey()) {
        $filter = $fm->get_filter_from_id($urlparams->fedit);
        $filterform = $fm->get_filter_form($filter);
        $fm->display_filter_form($filterform, $filter, $urlparams);

        // Or display the filters list.
    } else {
        // Any notifications?
        if (!$filters = $fm->get_filters(null, false, true)) {
            $df->notifications['bad'][] = get_string('filtersnoneindatalynx', 'datalynx'); // Nothing in.
            // Datalynx.
        }

        // Print header.
        $df->print_header(array('tab' => 'filters', 'urlparams' => $urlparams));

        // Print the filter add link.
        $fm->print_add_filter();

        // If there are filters print admin style list of them.
        if ($filters) {
            $fm->print_filter_list();
        }
    }
}

$df->print_footer();
