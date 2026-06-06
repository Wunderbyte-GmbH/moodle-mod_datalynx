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
 * Field formats list page in mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../classes/datalynx.php');
require_once("$CFG->libdir/tablelib.php");

$urlparams = new stdClass();
$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx ID.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module ID.

$deleteid = optional_param('delete', 0, PARAM_INT);

$dlx = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);
$urlparams->d = $dlx->id();
$urlparams->id = $dlx->cm->id;

require_login($dlx->data->course, false, $dlx->cm);
require_capability('mod/datalynx:managetemplates', $dlx->context);

$dlx->set_page('fieldformat/index', ['urlparams' => $urlparams]);

// Activate navigation node.
navigation_node::override_active_url(
    new moodle_url('/mod/datalynx/fieldformat/index.php', ['id' => $dlx->cm->id])
);

// Process delete action.
if ($deleteid && confirm_sesskey()) {
    try {
        \mod_datalynx\local\field_format\manager::delete_format($deleteid);
        redirect(new moodle_url('/mod/datalynx/fieldformat/index.php', ['d' => $dlx->id()]));
    } catch (\moodle_exception $e) {
        $dlx->notifications['bad'][] = $e->getMessage();
    }
}

// Print header.
$dlx->print_header(['tab' => 'fieldformats', 'urlparams' => $urlparams]);

// Display the add dropdown selector.
$supported = \mod_datalynx\local\field_format\manager::get_supported_field_types();
if (!empty($supported)) {
    $addurl = new moodle_url('/mod/datalynx/fieldformat/edit.php', ['d' => $dlx->id()]);
    $select = new single_select($addurl, 'fieldtype', $supported, null, ['' => 'choosedots'], 'addformatform');
    $select->set_label(get_string('fieldformatadd', 'datalynx') . '&nbsp;');
    echo html_writer::tag('div', $OUTPUT->render($select), ['class' => 'mdl-align', 'style' => 'margin-bottom: 20px;']);
} else {
    $attributes = ['class' => 'mdl-align', 'style' => 'margin-bottom: 20px;'];
    echo html_writer::tag('div', get_string('fieldformatnone', 'datalynx'), $attributes);
}

// Retrieve formats.
$formats = \mod_datalynx\local\field_format\manager::get_formats_for_instance($dlx->id());

if ($formats) {
    // Set up table.
    $headers = [
        'name' => get_string('name'),
        'fieldtype' => get_string('type', 'datalynx'),
        'edit' => get_string('edit'),
        'delete' => get_string('delete'),
    ];

    $table = new flexible_table('datalynxfieldformatsindex' . $dlx->id());
    $table->define_baseurl(new moodle_url('/mod/datalynx/fieldformat/index.php', ['d' => $dlx->id()]));
    $table->define_columns(array_keys($headers));
    $table->define_headers(array_values($headers));

    $table->sortable(false);
    $table->set_attribute('class', 'generaltable generalbox boxaligncenter boxwidthwide');
    $table->column_style('edit', 'text-align', 'center');
    $table->column_style('delete', 'text-align', 'center');
    $table->setup();

    $editbaseurl = '/mod/datalynx/fieldformat/edit.php';
    $linkparams = ['d' => $dlx->id(), 'sesskey' => sesskey()];

    foreach ($formats as $formatid => $format) {
        $name = html_writer::link(
            new moodle_url($editbaseurl, $linkparams + ['id' => $formatid]),
            $format->get_name()
        );

        $fieldtype = get_string('pluginname', 'datalynxfield_' . $format->get_fieldtype());

        $edit = html_writer::link(
            new moodle_url($editbaseurl, $linkparams + ['id' => $formatid]),
            $OUTPUT->pix_icon('t/edit', get_string('edit'))
        );

        $delete = html_writer::link(
            new moodle_url('/mod/datalynx/fieldformat/index.php', $linkparams + ['delete' => $formatid]),
            $OUTPUT->pix_icon('t/delete', get_string('delete'))
        );

        $table->add_data([$name, $fieldtype, $edit, $delete]);
    }

    $table->finish_output();
} else if (!empty($supported)) {
    echo $OUTPUT->notification(get_string('fieldformatnone', 'datalynx'), 'info');
}

$dlx->print_footer();
