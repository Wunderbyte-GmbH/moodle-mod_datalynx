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
 * @package datalynxview
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once("$CFG->dirroot/mod/datalynx/mod_class.php");

$urlparams = new object();
$urlparams->d          = required_param('d', PARAM_INT);    // datalynx ID

$urlparams->type       = optional_param('type', '', PARAM_ALPHA);   // type of a view to edit
$urlparams->vedit      = optional_param('vedit', 0, PARAM_INT);       // view id to edit
$urlparams->returnurl  = optional_param('returnurl', '', PARAM_URL);

// Set a datalynx object
$df = new datalynx($urlparams->d);

global $DB;
$options = array();
$options['behaviors'] = $DB->get_records_select_menu('datalynx_behaviors', 'dataid = :dataid', array('dataid' => $urlparams->d), 'value ASC', 'name AS value, name AS label');
$options['behaviors'][''] = get_string('defaultbehavior', 'datalynx');
$fields = $DB->get_fieldset_select('datalynx_fields', 'name', 'dataid = :dataid', array('dataid' => $urlparams->d));
$options['renderers'] = array();
$commonrenderers = $DB->get_records_select_menu('datalynx_renderers', 'dataid = :dataid', array('dataid' => $urlparams->d), 'value ASC', 'name AS value, name AS label');
foreach ($fields as $field) {
    $options['renderers'][$field] = $commonrenderers; // TODO: add field-specific renderers here
    $options['renderers'][$field][''] = get_string('defaultrenderer', 'datalynx');
}
$options['types'] = $DB->get_records_select_menu('datalynx_fields', 'dataid = :dataid', array('dataid' => $urlparams->d), 'name ASC', 'name, type');

$module = array(
    'name'=>'mod_datalynx',
    'fullpath'=>'/mod/datalynx/datalynx.js',
    'requires' => array('moodle-core-notification-dialogue'));

$PAGE->requires->js_init_call('M.mod_datalynx.tag_manager.init', $options, true, $module);
$PAGE->requires->string_for_js('behavior', 'datalynx');
$PAGE->requires->string_for_js('renderer', 'datalynx');
$PAGE->requires->string_for_js('fieldname', 'datalynx');
$PAGE->requires->string_for_js('fieldtype', 'datalynx');
$PAGE->requires->string_for_js('tagproperties', 'datalynx');
$PAGE->requires->string_for_js('deletetag', 'datalynx');
$PAGE->requires->string_for_js('action', 'datalynx');
$PAGE->requires->string_for_js('field', 'datalynx');

$df->set_page('view/view_edit', array('modjs' => true, 'urlparams' => $urlparams));

require_sesskey();
require_capability('mod/datalynx:managetemplates', $df->context);

if ($urlparams->vedit) {
    $view = $df->get_view_from_id($urlparams->vedit);
    if ($default = optional_param('resetdefault',0 ,PARAM_INT)) {
        $view->generate_default_view();
    }    
} else if ($urlparams->type) {
    $view = $df->get_view($urlparams->type);
    $view->generate_default_view();
}

$mform = $view->get_form();

// form cancelled
if ($mform->is_cancelled()){
        if ($urlparams->returnurl) {
            redirect($urlparams->returnurl);
        } else {
            redirect(new moodle_url('/mod/datalynx/view/index.php', array('d' => $urlparams->d)));
        }

// no submit buttons: reset to default 
} else if ($mform->no_submit_button_pressed()) {
    // reset view to default
    // TODO is this the best way?
    $resettodefault = optional_param('resetdefaultbutton', '', PARAM_ALPHA);
    if ($resettodefault) {
        $urlparams->resetdefault = 1;
        redirect(new moodle_url('/mod/datalynx/view/view_edit.php', ((array) $urlparams) + array('sesskey' => sesskey())));
        
    }

// process validated    
} else if ($data = $mform->get_data()) {
    // add new view
    if (!$view->id()) {
        $vid = $view->add($data);

        $other = array('dataid' => $df->id());
        $event = \mod_datalynx\event\view_created::create(array('context' => $df->context, 'objectid' => $vid, 'other' => $other));
        $event->trigger();
    // update view
    } else {
        $view->update($data);

        $other = array('dataid' => $df->id());
        $event = \mod_datalynx\event\view_updated::create(array('context' => $df->context, 'objectid' => $view->id(), 'other' => $other));
        $event->trigger();
    }
    
    //$df->notifications['good'][] = $log; //FIXME: what is this for

    if (!isset($data->submitreturnbutton)) {
        // TODO: set default view       

        if ($urlparams->returnurl) {
            redirect($urlparams->returnurl);
        } else {
            redirect(new moodle_url('/mod/datalynx/view/index.php', array('d' => $urlparams->d)));
        }
    }

    // Save and continue so refresh the form
    $mform = $view->get_form();       
}

// activate navigation node
navigation_node::override_active_url(new moodle_url('/mod/datalynx/view/index.php', array('id' => $df->cm->id)));

// print header
$df->print_header(array('tab' => 'views', 'nonotifications' => true, 'urlparams' => $urlparams));

$formheading = $view->id() ? get_string('viewedit', 'datalynx', $view->name()) : get_string('viewnew', 'datalynx', $view->typename());
echo html_writer::tag('h2', format_string($formheading), array('class' => 'mdl-align'));

// display form
$mform->set_data($view->to_form());

$texteditors = $CFG->texteditors;
$CFG->texteditors = 'atto,textarea';
$mform->display();
$CFG->texteditors = $texteditors;

$df->print_footer();

