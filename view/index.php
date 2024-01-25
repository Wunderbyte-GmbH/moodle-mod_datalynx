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
require_once("$CFG->libdir/tablelib.php");

$urlparams = new stdClass();

$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.
$urlparams->vedit = optional_param('vedit', 0, PARAM_INT); // View id to edit.

// Views list actions.
$urlparams->default = optional_param('default', 0, PARAM_INT); // Id of view to default.
$urlparams->singleedit = optional_param('singleedit', 0, PARAM_INT); // Id of view to single edit.
$urlparams->singlemore = optional_param('singlemore', 0, PARAM_INT); // Id of view to single more.
$urlparams->visible = optional_param('visible', 0, PARAM_INT); // Id of view to hide/(show)/show.
$urlparams->reset = optional_param('reset', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Views to delete.
$urlparams->delete = optional_param('delete', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Views to delete.
$urlparams->duplicate = optional_param('duplicate', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Views to duplicate.
$urlparams->setfilter = optional_param('setfilter', 0, PARAM_INT); // Id of view to filter.

$urlparams->confirmed = optional_param('confirmed', 0, PARAM_INT);

// Set a datalynx object.
$dl = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);

require_login($dl->data->course, false, $dl->cm);

require_capability('mod/datalynx:managetemplates', $dl->context);

$dl->set_page('view/index', array('modjs' => true, 'urlparams' => $urlparams));

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/view/index.php', array('id' => $dl->cm->id)));

// DATA PROCESSING.
if ($urlparams->duplicate && confirm_sesskey()) { // Duplicate any requested views.
    $dl->process_views('duplicate', $urlparams->duplicate, $urlparams->confirmed);
} else {
    if ($urlparams->reset && confirm_sesskey()) { // Reset to default any requested views.
        $patterncache = cache::make('mod_datalynx', 'patterns');
        $patterncache->delete($urlparams->vedit, true);
        $dl->process_views('reset', $urlparams->reset, true);
    } else {
        if ($urlparams->delete && confirm_sesskey()) { // Delete any requested views.
            $dl->process_views('delete', $urlparams->delete, $urlparams->confirmed);
        } else {
            if ($urlparams->visible && confirm_sesskey()) { // Set view's visibility.
                $dl->process_views('visible', $urlparams->visible, true); // Confirmed by default.
            } else {
                if ($urlparams->default && confirm_sesskey()) { // Set view to default.
                    $dl->process_views('default', $urlparams->default, true); // Confirmed by default.
                } else {
                    if ($urlparams->singleedit && confirm_sesskey()) { // Set view to single edit.
                        if ($urlparams->singleedit == -1) {
                            $dl->set_single_edit_view(); // Reset.
                        } else {
                            $dl->set_single_edit_view($urlparams->singleedit);
                        }
                    } else {
                        if ($urlparams->singlemore && confirm_sesskey()) { // Set view to single more.
                            if ($urlparams->singlemore == -1) {
                                $dl->set_single_more_view(); // Reset.
                            } else {
                                $dl->set_single_more_view($urlparams->singlemore);
                            }
                        } else {
                            if ($urlparams->setfilter && confirm_sesskey()) { // Re/set view filter.
                                $dl->process_views('filter', $urlparams->setfilter, true); // Confirmed by default.
                            }
                        }
                    }
                }
            }
        }
    }
}

// Any notifications?
$dl->notifications['bad']['defaultview'] = '';
$views = $dl->get_views([], true,
        flexible_table::get_sort_for_table('datalynxviewsindex' . $dl->id()));
if (!$views) {
    $dl->notifications['bad']['getstartedviews'] = get_string('viewnoneindatalynx', 'datalynx'); // Nothing.
    // In.
    // Database.
} else {
    if (empty($dl->data->defaultview)) {
        $dl->notifications['bad']['defaultview'] = get_string('viewnodefault', 'datalynx', '');
    }
}

// Print header.
$dl->print_header(array('tab' => 'views', 'urlparams' => $urlparams));

// Display the view form jump list.
$directories = get_list_of_plugins('mod/datalynx/view/');
$menuview = array();

foreach ($directories as $directory) {
    if ($directory[0] != '_') {
        $menuview[$directory] = get_string('pluginname', "datalynxview_$directory"); // Get from.
        // Language.
        // Files.
    }
}
asort($menuview); // Sort in alphabetical order.

$br = html_writer::empty_tag('br');
$popupurl = $CFG->wwwroot . '/mod/datalynx/view/view_edit.php?d=' . $dl->id() . '&amp;sesskey=' . sesskey();
$viewselect = new single_select(new moodle_url($popupurl), 'type', $menuview, null,
        array('' => 'choosedots'), 'viewform');
$viewselect->set_label(get_string('viewadd', 'datalynx') . '&nbsp;');
echo html_writer::tag('div', $br . $OUTPUT->render($viewselect) . $br,
        array('class' => 'fieldadd mdl-align'));

// If there are views print admin style list of them.
if ($views) {

    $viewbaseurl = '/mod/datalynx/view.php';
    $editbaseurl = '/mod/datalynx/view/view_edit.php';
    $actionbaseurl = '/mod/datalynx/view/index.php';
    $linkparams = array('d' => $dl->id(), 'sesskey' => sesskey());

    // Table headings.
    $strdefault = get_string('defaultview', 'datalynx');
    $strsingleedit = get_string('singleedit', 'datalynx');
    $strsinglemore = get_string('singlemore', 'datalynx');
    $strfilter = get_string('filter', 'datalynx');
    $stredit = get_string('edit');
    $strdelete = get_string('delete');
    $strduplicate = get_string('duplicate');
    $strchoose = get_string('choose');

    $selectallnone = html_writer::checkbox(null, null, false, null,
            array('onclick' => 'select_allnone(\'view\'&#44;this.checked)'));
    $multiactionurl = new moodle_url($actionbaseurl, $linkparams);
    $multidelete = html_writer::tag('button', $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'datalynx')),
            array('name' => 'multidelete',
                    'onclick' => 'bulk_action(\'view\'&#44; \'' . $multiactionurl->out(false) . '\'&#44; \'delete\')'
            ));
    $multiduplicate = html_writer::tag('button',
            $OUTPUT->pix_icon('t/copy', get_string('multiduplicate', 'datalynx')),
            array('type' => 'button', 'name' => 'multiduplicate',
                    'onclick' => 'bulk_action(\'view\'&#44; \'' . $multiactionurl->out(false) . '\'&#44; \'duplicate\')'
            ));

    $strhide = get_string('hide');
    $strshow = get_string('show');
    $strreset = get_string('reset');

    $filtersmenu = $dl->get_filter_manager()->get_filters(null, true);

    // Table headers.
    $headers = array('name' => get_string('name'), 'type' => get_string('type', 'datalynx'),
            'description' => get_string('description'), 'visible' => get_string('visible'),
            'default' => $strdefault, 'singleedit' => $strsingleedit, 'singlemore' => $strsinglemore,
            'filter' => $strfilter, 'edit' => $stredit, 'reset' => $strreset,
            'duplicate' => $multiduplicate, 'delete' => $multidelete, 'selectallnone' => $selectallnone
    );

    $table = new flexible_table('datalynxviewsindex' . $dl->id());
    $table->define_baseurl(new moodle_url('/mod/datalynx/view/index.php', array('d' => $dl->id())));
    $table->define_columns(array_keys($headers));
    $table->define_headers(array_values($headers));

    // Column sorting.
    $table->sortable(true);
    $table->no_sorting('description');
    $table->no_sorting('default');
    $table->no_sorting('singleedit');
    $table->no_sorting('singlemore');
    $table->no_sorting('filter');
    $table->no_sorting('edit');
    $table->no_sorting('duplicate');
    $table->no_sorting('reset');
    $table->no_sorting('delete');
    $table->no_sorting('selectallnone');

    // Styles.
    $table->set_attribute('class', 'generaltable generalbox boxaligncenter boxwidthwide');
    $table->column_style('visible', 'text-align', 'center');
    $table->column_style('edit', 'text-align', 'center');
    $table->column_style('duplicate', 'text-align', 'center');
    $table->column_style('reset', 'text-align', 'center');
    $table->column_style('delete', 'text-align', 'center');

    $table->setup();

    foreach ($views as $viewid => $view) {

        $viewname = html_writer::link(
                new moodle_url($viewbaseurl, array('d' => $dl->id(), 'view' => $viewid)), $view->name());
        $viewtype = $view->typename();
        $viewdescription = shorten_text($view->view->description, 30);
        $viewedit = html_writer::link(
                new moodle_url($editbaseurl, $linkparams + array('vedit' => $viewid)),
                $OUTPUT->pix_icon('t/edit', $stredit));
        $viewduplicate = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('duplicate' => $viewid)),
                $OUTPUT->pix_icon('t/copy', $strduplicate));
        $viewreset = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('reset' => $viewid)),
                $OUTPUT->pix_icon('t/reload', $strreset));
        $viewdelete = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('delete' => $viewid)),
                $OUTPUT->pix_icon('t/delete', $strdelete));
        $viewselector = html_writer::checkbox("viewselector", $viewid, false);

        $viewvisible = '';
        for ($i = 1; $i < 16; $i = $i << 1) {
            $viewvisible .= html_writer::checkbox("visible[{$i}]", 1, ($i & $view->view->visible),
                    '', array('disabled' => '', 'title' => get_string("visible_{$i}", 'datalynx')));
        }

        // Default view.
        if ($viewid == $dl->data->defaultview) {
            $defaultview = $OUTPUT->pix_icon('t/approve', get_string('isdefault', 'mod_datalynx'));
        } else {
            $defaultview = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('default' => $viewid)),
                    $OUTPUT->pix_icon('t/switch_whole', get_string('setdefault', 'mod_datalynx')));
        }

        // Single edit view.
        if ($viewid == $dl->data->singleedit) {
            $singleedit = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('singleedit' => -1)),
                    $OUTPUT->pix_icon('t/approve', get_string('isedit', 'mod_datalynx')));
        } else {
            $singleedit = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('singleedit' => $viewid)),
                    $OUTPUT->pix_icon('t/switch_whole', get_string('setedit', 'mod_datalynx')));
        }

        // Single more view.
        if ($viewid == $dl->data->singleview) {
            $singlemore = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('singlemore' => -1)),
                    $OUTPUT->pix_icon('t/approve', get_string('ismore', 'mod_datalynx')));
        } else {
            $singlemore = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('singlemore' => $viewid)),
                    $OUTPUT->pix_icon('t/switch_whole', get_string('setmore', 'mod_datalynx')));
        }

        // TODO view filter.
        if (!empty($filtersmenu)) {
            $viewfilterid = $view->view->filter;
            if ($viewfilterid && !in_array($viewfilterid, array_keys($filtersmenu))) {
                $viewfilter = html_writer::link(
                        new moodle_url($actionbaseurl,
                                $linkparams + array('setfilter' => $viewid, 'fid' => -1)),
                        $OUTPUT->pix_icon('i/risk_xss', $strreset));
            } else {
                $blankfilteroption = array(-1 => get_string('blankfilter', 'datalynx'));
                if ($dl->data->defaultfilter) {
                    $defaultfilter = $dl->get_filter_manager()->get_filter_from_id($dl->data->defaultfilter);
                    $defaultfiltername = $defaultfilter->name;
                    $defaultfilteroption = array(
                            0 => get_string('defaultfilterlabel', 'datalynx', $defaultfiltername)
                    );
                } else {
                    $defaultfilteroption = array(
                            0 => get_string('defaultfilterlabel', 'datalynx', get_string('blankfilter', 'datalynx'))
                    );
                }

                if ($viewfilterid) {
                    $selected = $viewfilterid;
                } else {
                    $selected = 0;
                }

                $options = $blankfilteroption + $defaultfilteroption + $filtersmenu;

                $selecturl = new moodle_url($actionbaseurl,
                        $linkparams + array('setfilter' => $viewid));
                $viewselect = new single_select($selecturl, 'fid', $options, $selected, array());

                $viewfilter = $OUTPUT->render($viewselect);
            }
        } else {
            $viewfilter = get_string('filtersnonedefined', 'datalynx');
        }

        $table->add_data(
                array($viewname, $viewtype, $viewdescription, $viewvisible, $defaultview,
                        $singleedit, $singlemore, $viewfilter, $viewedit, $viewreset, $viewduplicate,
                        $viewdelete, $viewselector));
    }

    $table->finish_output();
}

$dl->print_footer();

