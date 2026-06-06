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
 * Field format index page — lists all formats for a datalynx instance.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once("$CFG->libdir/tablelib.php");

use mod_datalynx\datalynx;
use mod_datalynx\local\field_format\manager as format_manager;

$urlparams = new stdClass();
$urlparams->d  = optional_param('d', 0, PARAM_INT);
$urlparams->id = optional_param('id', 0, PARAM_INT);

$datalynx = new datalynx($urlparams->d, $urlparams->id);
$urlparams->d  = $datalynx->id();
$urlparams->id = $datalynx->cm->id;

require_login($datalynx->data->course, false, $datalynx->cm);
require_capability('mod/datalynx:managetemplates', $datalynx->context);

$datalynx->set_page('fieldformat/index', ['urlparams' => $urlparams]);

// Print header.
$datalynx->print_header(['tab' => 'formats', 'urlparams' => $urlparams]);

echo html_writer::empty_tag('br');
echo html_writer::start_tag('div', ['class' => 'fieldadd mdl-align']);

$addbaseurl = new moodle_url(
    '/mod/datalynx/fieldformat/edit.php',
    ['d' => $datalynx->id(), 'sesskey' => sesskey(), 'id' => 0]
);

// Allow choosing a field type to add.
$fieldtypes = [];
foreach (['entryauthor', 'entrytime', 'entrygroup'] as $type) {
    $fieldtypes[$type] = $type;
}

echo html_writer::tag(
    'p',
    get_string('fieldformat_tagusage_help', 'datalynx'),
    ['class' => 'alert alert-info']
);

echo html_writer::end_tag('div');
echo html_writer::empty_tag('br');

$editbaseurl = '/mod/datalynx/fieldformat/edit.php';
$linkparams  = ['d' => $datalynx->id(), 'sesskey' => sesskey()];

// Table headers.
$headers = [
    'name'      => get_string('name'),
    'fieldtype' => get_string('type', 'datalynx'),
    'settings'  => get_string('fieldformat_settings', 'datalynx'),
    'edit'      => get_string('edit'),
    'delete'    => get_string('delete'),
];

$table = new flexible_table('datalynxfieldformatsindex' . $datalynx->id());
$table->define_baseurl(
    new moodle_url('/mod/datalynx/fieldformat/index.php', ['d' => $datalynx->id()])
);
$table->define_columns(array_keys($headers));
$table->define_headers(array_values($headers));
$table->setup();

$formats = format_manager::get_formats_for_instance($datalynx->id());
foreach ($formats as $format) {
    $editurl = new moodle_url(
        $editbaseurl,
        array_merge($linkparams, ['id' => $format->get_id()])
    );
    $deleteurl = new moodle_url(
        $editbaseurl,
        array_merge($linkparams, ['id' => $format->get_id(), 'action' => 'delete'])
    );

    // Summarise settings.
    $settingssummary = implode(', ', array_map(
        fn($k, $v) => s($k) . ': ' . s($v),
        array_keys($format->get_settings()),
        array_values($format->get_settings())
    ));

    $table->add_data([
        s($format->get_name()),
        s($format->get_fieldtype()),
        $settingssummary,
        html_writer::link($editurl, get_string('edit')),
        html_writer::link(
            $deleteurl,
            get_string('delete'),
            ['onclick' => 'return confirm(' . json_encode(
                get_string('fieldformat_confirmdelete', 'datalynx', $format->get_name())
            ) . ');']
        ),
    ]);
}
$table->print_html();

echo html_writer::start_tag('div', ['class' => 'fieldadd mdl-align mt-3']);
echo get_string('fieldformat_add', 'datalynx') . ': ';
foreach (['entryauthor', 'entrytime', 'entrygroup'] as $type) {
    echo html_writer::link(
        new moodle_url($editbaseurl, array_merge($linkparams, ['id' => 0, 'fieldtype' => $type])),
        $type,
        ['class' => 'btn btn-secondary btn-sm mr-1']
    );
}
echo html_writer::end_tag('div');

$datalynx->print_footer();
