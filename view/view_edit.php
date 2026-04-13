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
 * Datalynx view edit page.
 *
 * @package    mod_datalynx
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");

$urlparams = new stdClass();
$urlparams->d = required_param('d', PARAM_INT); // Datalynx ID.

$urlparams->type = optional_param('type', '', PARAM_ALPHA); // Type of a view to edit.
$urlparams->vedit = optional_param('vedit', 0, PARAM_INT); // View id to edit.
$urlparams->returnurl = optional_param('returnurl', '', PARAM_URL);

// Set a datalynx object.
$dl = new mod_datalynx\datalynx($urlparams->d);

require_login($dl->data->course, false, $dl->cm);

global $DB;
$options = [];
$options['behaviors'] = $DB->get_records_select_menu(
    'datalynx_behaviors',
    'dataid = :dataid',
    ['dataid' => $urlparams->d],
    'value ASC',
    'name AS value, name AS label'
);
$options['behaviors'][''] = get_string('defaultbehavior', 'datalynx');
$fields = $DB->get_fieldset_select(
    'datalynx_fields',
    'name',
    'dataid = :dataid',
    ['dataid' => $urlparams->d]
);
$options['renderers'] = [];
$commonrenderers = $DB->get_records_select_menu(
    'datalynx_renderers',
    'dataid = :dataid',
    ['dataid' => $urlparams->d],
    'value ASC',
    'name AS value, name AS label'
);
foreach ($fields as $field) {
    // TODO: MDL-66151 add field-specific renderers here.
    $options['renderers'][$field] = $commonrenderers;
    $options['renderers'][$field][''] = get_string('defaultrenderer', 'datalynx');
}
$options['types'] = $DB->get_records_select_menu(
    'datalynx_fields',
    'dataid = :dataid',
    ['dataid' => $urlparams->d],
    'name ASC',
    'name, type'
);
$options['datalynxid'] = $urlparams->d;
$options['views'] = [];
$options['referenceeditors'] = ['id_esection_editor', 'id_eparam2_editor'];

$PAGE->requires->js_call_amd('mod_datalynx/patterndialogue', 'init');

$dl->set_page('view/view_edit', ['modjs' => true, 'urlparams' => $urlparams]);

require_sesskey();
require_capability('mod/datalynx:managetemplates', $dl->context);

$viewrecords = $DB->get_records('datalynx_views', ['dataid' => $urlparams->d], 'name ASC', 'id, name');
foreach ($viewrecords as $viewrecord) {
    $options['views'][] = [
        'id' => (int) $viewrecord->id,
        'name' => $viewrecord->name,
    ];
}

if ($urlparams->vedit) {
    $views = $dl->get_views_editable_by_user('');
    if (!empty($views) && array_key_exists($urlparams->vedit, $views)) {
        $view = $views[$urlparams->vedit];
        if ($default = optional_param('resetdefault', 0, PARAM_INT)) {
            $view->generate_default_view();
        }
    } else {
        throw new moodle_exception('The requested view does not exist or you do not have permission to edit it.');
    }
} else {
    if ($urlparams->type) {
        try {
            $view = $dl->get_view($urlparams->type);
        } catch (\coding_exception $e) {
            throw new moodle_exception('The requested view type does not exist.');
        }
        $view->generate_default_view();
    }
}

$mform = $view->get_form();

// Form cancelled.
if ($mform->is_cancelled()) {
    if ($urlparams->returnurl) {
        redirect($urlparams->returnurl);
    } else {
        redirect(new moodle_url('/mod/datalynx/view/index.php', ['d' => $urlparams->d]));
    }

    // No submit buttons: reset to default.
} else {
    if ($mform->no_submit_button_pressed()) {
        // Reset view to default.
        // TODO MDL-66151 is this the best way?
        $resettodefault = optional_param('resetdefaultbutton', '', PARAM_ALPHA);
        if ($resettodefault) {
            $urlparams->resetdefault = 1;
            redirect(
                new moodle_url(
                    '/mod/datalynx/view/view_edit.php',
                    ((array) $urlparams) + ['sesskey' => sesskey()]
                )
            );
        }

        // Process validated.
    } else {
        if ($data = $mform->get_data()) {
            // Add new view.
            if (!$view->id()) {
                $vid = $view->add($data);

                $other = ['dataid' => $dl->id()];
                $event = \mod_datalynx\event\view_created::create(
                    ['context' => $dl->context, 'objectid' => $vid, 'other' => $other]
                );
                $event->trigger();
                // Update view.
            } else {
                $view->update($data);

                $other = ['dataid' => $dl->id()];
                $event = \mod_datalynx\event\view_updated::create(
                    ['context' => $dl->context, 'objectid' => $view->id(), 'other' => $other]
                );
                $event->trigger();
            }

            if (!isset($data->submitreturnbutton)) {
                // TODO: MDL-66151 set default view.

                if ($urlparams->returnurl) {
                    redirect($urlparams->returnurl);
                } else {
                    redirect(new moodle_url('/mod/datalynx/view/index.php', ['d' => $urlparams->d]));
                }
            }

            // Save and continue so refresh the form.
            $mform = $view->get_form();
        }
    }
}

// Activate navigation node.
navigation_node::override_active_url(
    new moodle_url('/mod/datalynx/view/index.php', ['id' => $dl->cm->id])
);

// Print header.
$dl->print_header(['tab' => 'views', 'nonotifications' => true, 'urlparams' => $urlparams]);

$formheading = $view->id() ? get_string('viewedit', 'datalynx', $view->name()) : get_string(
    'viewnew',
    'datalynx',
    $view->typename()
);
$output = html_writer::tag('h2', format_string($formheading), ['class' => 'mdl-align']);

// Output options data for patterndialogue.js (avoids js_call_amd argument size limit).
echo html_writer::tag('script', json_encode($options), [
        'type' => 'application/json',
        'id' => 'mod_datalynx-patterndialogue-options',
]);

// Display form.
$mform->set_data($view->to_form());

// ToDo: Ugly hack for forcing tiny as the only editor available even if user chose another editor.
$texteditors = $CFG->texteditors;
$CFG->texteditors = 'tiny';
$mform->display();
$CFG->texteditors = $texteditors;

$dl->print_footer();
