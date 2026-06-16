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
 * Main entry point for the fieldgroup field configuration.
 *
 * @package    datalynxfield_fieldgroup
 * @subpackage fieldgroup
 * @copyright  2018 michael pollak <moodle@michaelpollak.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');
require_once('../../classes/datalynx.php');
require_once("$CFG->libdir/tablelib.php");

$urlparams = new stdClass();
$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.

$dlx = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);
$urlparams->d = $dlx->id();
$urlparams->id = $dlx->cm->id;

require_login($dlx->data->course, false, $dlx->cm);

require_capability('mod/datalynx:managetemplates', $dlx->context);

$dlx->set_page('fieldgroups/index', ['urlparams' => $urlparams]);

// Activate navigation node.
navigation_node::override_active_url(
    new moodle_url('/mod/datalynx/flield/fieldgroup/index.php', ['id' => $dlx->cm->id])
);

// Print header.
$dlx->print_header(['tab' => 'fieldgroups', 'urlparams' => $urlparams]);

echo html_writer::empty_tag('br');
echo html_writer::start_tag('div', ['class' => 'fieldadd mdl-align']);
echo html_writer::link(new moodle_url(
    '/mod/datalynx/field/field_edit.php',
    ['d' => $dlx->id(), 'sesskey' => sesskey(), 'type' => 'fieldgroup']
), get_string('fieldgroupsadd', 'datalynx'));
echo html_writer::end_tag('div');
echo html_writer::empty_tag('br');

$editbaseurl = '/mod/datalynx/field/field_edit.php';
$deletebaseurl = '/mod/datalynx/field/index.php'; // Deletelink is via index.
$linkparams = ['d' => $dlx->id(), 'sesskey' => sesskey()];

// Table headers.
$headers = ['name' => get_string('name'), 'description' => get_string('description'),
        'fieldgroupfields' => get_string('fieldgroupfields', 'datalynx'),
        'required' => get_string('required'),
        'edit' => get_string('edit'), 'duplicate' => get_string('duplicate'),
        'delete' => get_string('delete')];

$table = new flexible_table('datalynxbehaviorsindex' . $dlx->id());
$table->define_baseurl(
    new moodle_url('/mod/datalynx/field/fieldgroup/index.php', ['d' => $dlx->id()])
);
$table->define_columns(array_keys($headers));
$table->define_headers(array_values($headers));

// Column sorting.
$table->sortable(false);

// Column styles.
$table->set_attribute('class', 'generaltable generalbox boxaligncenter boxwidthwide datalynx-behaviors');
$table->set_attribute('data-sesskey', sesskey());
$table->column_style('fieldgroupfields', 'text-align', 'center');
$table->column_style('required', 'text-align', 'center');
$table->column_style('edit', 'text-align', 'center');
$table->column_style('duplicate', 'text-align', 'center');
$table->column_style('delete', 'text-align', 'center');

$table->setup();

$fieldgroups = $DB->get_records('datalynx_fields', ['dataid' => $dlx->id(), 'type' => 'fieldgroup']);

// Create table entries from fieldgroups.
foreach ($fieldgroups as $fieldgroupid => $fieldgroup) {
    $fieldname = html_writer::link(
        new moodle_url($editbaseurl, $linkparams + ['fid' => $fieldgroupid]),
        $fieldgroup->name
    );
    $fielddescription = shorten_text($fieldgroup->description, 30);

    $fieldgroupfields = $fieldgroup->param1; // What fields are in the group.
    $fieldrequired = $fieldgroup->param4; // We show how many lines are required in the overview.

    // Include the fieldgroup name in the icon alt text, title and aria-label so the actions are
    // distinguishable for screen readers and addressable in Behat (e.g. I follow "Delete mygroup").
    $editlabel = get_string('edit') . ' ' . $fieldgroup->name;
    $duplicatelabel = get_string('duplicate') . ' ' . $fieldgroup->name;
    $deletelabel = get_string('delete') . ' ' . $fieldgroup->name;

    // NOTE: We need fid NOT id here. Editing is done via field_edit.php, while duplication and
    // deletion are processed by field/index.php (via process_fields), which expects fid sequences.
    $fieldedit = html_writer::link(
        new moodle_url($editbaseurl, $linkparams + ['fid' => $fieldgroupid]),
        $OUTPUT->pix_icon('t/edit', $editlabel),
        ['title' => $editlabel, 'aria-label' => $editlabel]
    );
    $fieldduplicate = html_writer::link(
        new moodle_url(
            $deletebaseurl,
            $linkparams + ['duplicate' => $fieldgroupid]
        ),
        $OUTPUT->pix_icon('t/copy', $duplicatelabel),
        ['title' => $duplicatelabel, 'aria-label' => $duplicatelabel]
    );
    $fielddelete = html_writer::link(
        new moodle_url(
            $deletebaseurl,
            $linkparams + ['delete' => $fieldgroupid]
        ),
        $OUTPUT->pix_icon('t/delete', $deletelabel),
        ['title' => $deletelabel, 'aria-label' => $deletelabel]
    );

    $table->add_data([$fieldname, $fielddescription, $fieldgroupfields, $fieldrequired,
            $fieldedit, $fieldduplicate, $fielddelete]);
}

// Print table.
$table->finish_output();

$dlx->print_footer();
