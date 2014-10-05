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
$urlparams->vedit        = optional_param('vedit', 0, PARAM_INT);       // view id to edit
$urlparams->returnurl  = optional_param('returnurl', '', PARAM_URL);

// Set a datalynx object
$df = new datalynx($urlparams->d);

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

// for cancelled
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
        $view->add($data);
        $log = get_string('viewsadded','datalynx');
    // update view
    } else {
        $view->update($data);
        $log = get_string('viewsupdated','datalynx');
    }
    
    $df->notifications['good'][] = $log;
    //FIXME: add_to_log($df->course->id, 'datalynx', $log, 'view/index.php?d='. $df->id(). '&amp;vedit=', $view->id(), $df->cm->id);

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
$mform->display();

$df->print_footer();
