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
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../classes/datalynx.php');
require_once('preset_form.php');

$urlparams = new stdClass();

$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.

// Presets list actions.
$urlparams->apply = optional_param('apply', 0, PARAM_INT); // Path of preset to apply.
$urlparams->torestorer = optional_param('torestorer', 1, PARAM_INT); // Apply user data to.
// Restorer.
$urlparams->map = optional_param('map', 0, PARAM_BOOL); // Map new preset fields to old fields.
$urlparams->delete = optional_param('delete', '', PARAM_SEQUENCE); // Ids of presets to delete.
$urlparams->share = optional_param('share', '', PARAM_SEQUENCE); // Ids of presets to share.
$urlparams->download = optional_param('download', '', PARAM_SEQUENCE); // Ids of presets to.
// Download in one zip.

$urlparams->confirmed = optional_param('confirmed', 0, PARAM_INT);

// Set a datalynx object.
$df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);

require_login($df->data->course, false, $df->cm);

require_capability('mod/datalynx:managetemplates', $df->context);

$df->set_page('preset/index', array('modjs' => true, 'urlparams' => $urlparams));

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/preset/index.php', array('id' => $df->cm->id)));

$pm = $df->get_preset_manager();

// DATA PROCESSING.
$pm->process_presets($urlparams);

$localpresets = $pm->get_user_presets($pm::PRESET_COURSEAREA);
$sharedpresets = $pm->get_user_presets($pm::PRESET_SITEAREA);

// Any notifications.
if (!$localpresets && !$sharedpresets) {
    $df->notifications['bad'][] = get_string('presetnoneavailable', 'datalynx'); // No presets in.
    // Datalynx.
}

// Print header.
$df->print_header(array('tab' => 'presets', 'urlparams' => $urlparams));

// Print the preset form.
$pm->print_preset_form();

// If there are presets print admin style list of them.
$pm->print_presets_list($localpresets, $sharedpresets);

$df->print_footer();
