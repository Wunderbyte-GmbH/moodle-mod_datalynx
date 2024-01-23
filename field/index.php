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
 * @package datalynxfield
 * @copyright Ivan Šakić, Thomas Niedermaier, David Bogner
 * @copyright based on the work by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once('../../../config.php');
require_once('../classes/datalynx.php');
require_once("$CFG->libdir/tablelib.php");

$urlparams = new stdClass();

$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.
$urlparams->fid = optional_param('fid', 0, PARAM_INT); // Update field id.

// Fields list actions.
$urlparams->new = optional_param('new', 0, PARAM_ALPHA); // Type of the new field.
$urlparams->delete = optional_param('delete', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Fields to delete.
$urlparams->duplicate = optional_param('duplicate', 0, PARAM_SEQUENCE); // Ids (comma delimited) of.
// Fields to duplicate.
$urlparams->visible = optional_param('visible', 0, PARAM_INT); // Id of field to hide/(show to.
// Owner)/show to all.
$urlparams->editable = optional_param('editable', 0, PARAM_INT); // Id of field to set editing.
$urlparams->convert = optional_param('convert', 0, PARAM_INT); // Id of field to be converted.

$urlparams->confirmed = optional_param('confirmed', 0, PARAM_INT);

// Set a datalynx object.
$df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);

require_login($df->data->course, false, $df->cm);

require_capability('mod/datalynx:managetemplates', $df->context);

$df->set_page('field/index', array('modjs' => true, 'urlparams' => $urlparams));

// Activate navigation node.
navigation_node::override_active_url(
        new moodle_url('/mod/datalynx/field/index.php', array('id' => $df->cm->id)));

// DATA PROCESSING.
// Duplicate requested fields.
if ($urlparams->duplicate && confirm_sesskey()) {
    $df->process_fields('duplicate', $urlparams->duplicate, $urlparams->confirmed);
    // Delete requested fields.
} else {
    if ($urlparams->delete && confirm_sesskey()) {
        $df->process_fields('delete', $urlparams->delete, $urlparams->confirmed);
        // Set field visibility.
    } else {
        if ($urlparams->visible && confirm_sesskey()) {
            $df->process_fields('visible', $urlparams->visible, true); // Confirmed by default.
            // Set field editability.
        } else {
            if ($urlparams->editable && confirm_sesskey()) {
                $df->process_fields('editable', $urlparams->editable, true); // Confirmed by default.
            } else {
                if ($urlparams->convert && confirm_sesskey()) {
                    $df->process_fields('convert', $urlparams->convert, true); // Confirmed by default.
                }
            }
        }
    }
}

// Any notifications.
$fields = $df->get_fields(null, false, true,
        flexible_table::get_sort_for_table('datalynxfieldsindex' . $df->id()));
if (!$fields) {
    $df->notifications['bad'][] = get_string('fieldnoneindatalynx', 'datalynx'); // Nothing in.
    // Datalynx.
}

// Print header.
$df->print_header(array('tab' => 'fields', 'urlparams' => $urlparams));

// Display the field form jump list.
$directories = get_list_of_plugins('mod/datalynx/field/');
$menufield = array();

foreach ($directories as $directory) {
    if ($directory[0] != '_' && strpos($directory, 'entry') !== 0) {
        // Get name from language files.
        $menufield[$directory] = get_string('pluginname', "datalynxfield_$directory");
    }
}
// Sort in alphabetical order.
asort($menufield);

$popupurl = new moodle_url('/mod/datalynx/field/field_edit.php',
        array('d' => $df->id(), 'sesskey' => sesskey()));
$fieldselect = new single_select($popupurl, 'type', $menufield, null, array('' => 'choosedots'), 'fieldform');
$fieldselect->set_label(get_string('fieldadd', 'datalynx') . '&nbsp;');
$br = html_writer::empty_tag('br');
echo html_writer::tag('div', $br . $OUTPUT->render($fieldselect) . $br,
        array('class' => 'fieldadd mdl-align'));

// If there are user fields print admin style list of them.
if ($fields) {

    $editbaseurl = '/mod/datalynx/field/field_edit.php';
    $actionbaseurl = '/mod/datalynx/field/index.php';
    $linkparams = array('d' => $df->id(), 'sesskey' => sesskey());

    $stredit = get_string('edit');
    $strduplicate = get_string('duplicate');
    $strdelete = get_string('delete');
    $strhide = get_string('hide');
    $strshow = get_string('show');
    $strlock = get_string('lock', 'datalynx');
    $strunlock = get_string('unlock', 'datalynx');
    $strconvert = get_string('convert', 'datalynx');

    // The default value of the type attr of a button is submit, so set it to button so that.
    // It doesn't submit the form.
    $selectallnone = html_writer::checkbox(null, null, false, null,
            array('onclick' => 'select_allnone(\'field\'&#44;this.checked)'));
    $multiactionurl = new moodle_url($actionbaseurl, $linkparams);
    $multidelete = html_writer::tag('button',
            $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'datalynx')),
            array('type' => 'button', 'name' => 'multidelete',
                    'onclick' => 'bulk_action(\'field\'&#44; \'' . $multiactionurl->out(false) .
                            '\'&#44; \'delete\')'));
    $multiduplicate = html_writer::tag('button',
            $OUTPUT->pix_icon('t/copy', get_string('multiduplicate', 'datalynx')),
            array('type' => 'button', 'name' => 'multiduplicate',
                    'onclick' => 'bulk_action(\'field\'&#44; \'' . $multiactionurl->out(false) .
                            '\'&#44; \'duplicate\')'));

    // Table headers.
    $headers = array('name' => get_string('name'), 'type' => get_string('type', 'datalynx'),
            'description' => get_string('description'), 'visible' => get_string('visible'),
            'edits' => get_string('fieldeditable', 'datalynx'), 'edit' => $stredit,
            'convert' => get_string('convert', 'datalynx'), 'duplicate' => $multiduplicate,
            'delete' => $multidelete, 'selectallnone' => $selectallnone
    );

    $table = new flexible_table('datalynxfieldsindex' . $df->id());
    $table->define_baseurl(new moodle_url('/mod/datalynx/field/index.php', array('d' => $df->id())));
    $table->define_columns(array_keys($headers));
    $table->define_headers(array_values($headers));

    // Column sorting.
    $table->sortable(true);
    $table->no_sorting('description');
    $table->no_sorting('edit');
    $table->no_sorting('duplicate');
    $table->no_sorting('delete');
    $table->no_sorting('selectallnone');
    $table->no_sorting('convert');

    // Column styles.
    $table->set_attribute('class', 'generaltable generalbox boxaligncenter boxwidthwide');
    $table->column_style('visible', 'text-align', 'center');
    $table->column_style('edits', 'text-align', 'center');
    $table->column_style('edit', 'text-align', 'center');
    $table->column_style('duplicate', 'text-align', 'center');
    $table->column_style('delete', 'text-align', 'center');

    $table->setup();

    foreach ($fields as $fieldid => $field) {
        // Skip internal fields.
        if ($field::is_internal()) {
            continue;
        }

        $fieldname = html_writer::link(
                new moodle_url($editbaseurl, $linkparams + array('fid' => $fieldid)), $field->name());
        $fieldedit = html_writer::link(
                new moodle_url($editbaseurl, $linkparams + array('fid' => $fieldid)),
                $OUTPUT->pix_icon('t/edit', $stredit));
        $fieldduplicate = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('duplicate' => $fieldid)),
                $OUTPUT->pix_icon('t/copy', $strduplicate));
        $fielddelete = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('delete' => $fieldid)),
                $OUTPUT->pix_icon('t/delete', $strdelete));
        $fieldselector = html_writer::checkbox("fieldselector", $fieldid, false);

        $fieldtype = $field->image() . '&nbsp;' . $field->typename();
        $fielddescription = shorten_text($field->field->description, 30);

        // Visible.
        if ($visible = $field->field->visible) {
            $visibleicon = $OUTPUT->pix_icon('t/hide', $strhide);
            $visibleicon = ($visible == 1 ? "($visibleicon)" : $visibleicon);
        } else {
            $visibleicon = $OUTPUT->pix_icon('t/show', $strshow);
        }
        $fieldvisible = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('visible' => $fieldid)), $visibleicon);

        // Editable.
        if ($editable = $field->field->edits) {
            $editableicon = $OUTPUT->pix_icon('t/lock', $strlock);
        } else {
            $editableicon = $OUTPUT->pix_icon('t/unlock', $strunlock);
        }
        $fieldeditable = html_writer::link(
                new moodle_url($actionbaseurl, $linkparams + array('editable' => $fieldid)), $editableicon);
        // Convert textarea to editor field.
        if ($field->type == "textarea") {
            $converticon = $OUTPUT->pix_icon('t/right', get_string('converttoeditor', 'datalynx'));
            $convert = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('convert' => $fieldid)), $converticon);
        } else {
            $convert = '';
        }

        $table->add_data(
                array($fieldname, $fieldtype, $fielddescription, $fieldvisible, $fieldeditable,
                        $fieldedit, $convert, $fieldduplicate, $fielddelete, $fieldselector
                ));
    }

    $table->finish_output();
}

$df->print_footer();
