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
 * @package datalynx_filterform
 * @copyright 2014 Ivan Šakić
 * @license http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
defined('MOODLE_INTERNAL') or die();
require_once('../../../config.php');
require_once('../mod_class.php');
require_once("$CFG->libdir/tablelib.php");

$urlparams = new stdClass();
$urlparams->d = optional_param('d', 0, PARAM_INT);

$datalynx = new datalynx($urlparams->d);
$urlparams->id = $datalynx->cm->id;

require_login($datalynx->data->course, false, $datalynx->cm);

require_capability('mod/datalynx:managetemplates', $datalynx->context);

$datalynx->set_page('filterform/index', array('urlparams' => $urlparams));

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/filterform/index.php', array('id' => $datalynx->cm->id)));

// TODO: print notifications.

// Print header.
$datalynx->print_header(array('tab' => 'filterforms', 'urlparams' => $urlparams));

echo html_writer::empty_tag('br');
echo html_writer::start_tag('div', array('class' => 'fieldadd mdl-align'));
echo html_writer::link(
        new moodle_url('/mod/datalynx/filterform/filterform_edit.php',
                array('d' => $datalynx->id(), 'sesskey' => sesskey(), 'id' => 0)),
        get_string('filterformadd', 'datalynx'));
echo html_writer::end_tag('div');
echo html_writer::empty_tag('br');

$editbaseurl = '/mod/datalynx/filterform/filterform_edit.php';
$linkparams = array('d' => $datalynx->id(), 'sesskey' => sesskey());

// Table headers.
$headers = array('name' => get_string('name'), 'description' => get_string('description'),
        'fields' => get_string('fields', 'datalynx'), 'edit' => get_string('edit'),
        'duplicate' => get_string('duplicate'), 'delete' => get_string('delete')
);

$table = new flexible_table('datalynxfilterformindex' . $datalynx->id());
$table->define_baseurl(
        new moodle_url('/mod/datalynx/filterform/index.php', array('d' => $datalynx->id())));
$table->define_columns(array_keys($headers));
$table->define_headers(array_values($headers));

// Column sorting.
$table->sortable(false);

// Column styles.
$table->set_attribute('class',
        'generaltable generalbox boxaligncenter boxwidthwide datalynx-filterforms');
$table->set_attribute('data-sesskey', sesskey());
$table->column_style('edit', 'text-align', 'center');
$table->column_style('duplicate', 'text-align', 'center');
$table->column_style('delete', 'text-align', 'center');

$table->setup();

$filterforms = $DB->get_records('datalynx_filter_forms', array('dataid' => $datalynx->id()));

foreach ($filterforms as $filterformid => $filterform) {

    $filterformname = html_writer::link(
            new moodle_url($editbaseurl, $linkparams + array('id' => $filterformid)), $filterform->name);
    $filterformdescription = shorten_text($filterform->description, 30);
    $filterformfields = "[[FIELD LIST GOES HERE]]";
    $filterformedit = html_writer::link(
            new moodle_url($editbaseurl, $linkparams + array('id' => $filterformid)),
            $OUTPUT->pix_icon('t/edit', get_string('edit')));
    $filterformduplicate = html_writer::link(
            new moodle_url($editbaseurl,
                    $linkparams + array('action' => 'duplicate', 'id' => $filterformid)),
            $OUTPUT->pix_icon('t/copy', get_string('duplicate')));
    $filterformdelete = html_writer::link(
            new moodle_url($editbaseurl,
                    $linkparams + array('action' => 'delete', 'id' => $filterformid)),
            $OUTPUT->pix_icon('t/delete', get_string('delete')));

    $table->add_data(
            array($filterformname, $filterformdescription, $filterformfields, $filterformedit,
                    $filterformduplicate, $filterformdelete));
}

$table->finish_output();

$datalynx->print_footer();