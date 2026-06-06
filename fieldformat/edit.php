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
 * Field format edit page in mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once('../classes/datalynx.php');

$d = required_param('d', PARAM_INT); // Datalynx ID.
$id = optional_param('id', 0, PARAM_INT); // Format record ID.
$fieldtype = optional_param('fieldtype', '', PARAM_ALPHANUM); // Field type.

$dlx = new mod_datalynx\datalynx($d);

require_login($dlx->data->course, false, $dlx->cm);
require_capability('mod/datalynx:managetemplates', $dlx->context);

$format = null;
if ($id) {
    $format = \mod_datalynx\local\field_format\manager::get_format_by_id($id);
    if (!$format) {
        throw new moodle_exception('invalidformat', 'mod_datalynx');
    }
    $fieldtype = $format->get_fieldtype();
} else {
    if (empty($fieldtype)) {
        throw new moodle_exception('missingfieldtype', 'mod_datalynx');
    }
}

// Dynamically check and load the form class.
$formclass = "\\datalynxfield_{$fieldtype}\\form\\field_format_form";
if (!class_exists($formclass)) {
    throw new moodle_exception('errorformclassnotfound', 'mod_datalynx', '', $formclass);
}

$actionurl = new moodle_url('/mod/datalynx/fieldformat/edit.php', ['d' => $dlx->id(), 'id' => $id, 'fieldtype' => $fieldtype]);
$mform = new $formclass($actionurl);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/datalynx/fieldformat/index.php', ['d' => $dlx->id()]));
} else if ($data = $mform->get_data()) {
    // Save data.
    $record = new stdClass();
    if ($id) {
        $record->id = $id;
    }
    $record->dataid = $data->d;
    $record->name = $data->name;
    $record->fieldtype = $data->fieldtype;

    // Serialize specific settings.
    $settings = [];
    foreach ($data as $key => $value) {
        if (!in_array($key, ['id', 'd', 'fieldtype', 'name', 'submitbutton', 'sesskey'])) {
            $settings[$key] = $value;
        }
    }
    $record->settings = json_encode($settings);

    \mod_datalynx\local\field_format\manager::save_format($record);

    redirect(new moodle_url('/mod/datalynx/fieldformat/index.php', ['d' => $dlx->id()]));
}

$dlx->set_page('fieldformat/edit', ['urlparams' => ['d' => $dlx->id(), 'id' => $id, 'fieldtype' => $fieldtype]]);

// Activate navigation node.
navigation_node::override_active_url(
    new moodle_url('/mod/datalynx/fieldformat/index.php', ['id' => $dlx->cm->id])
);

// Print header.
$dlx->print_header(['tab' => 'fieldformats', 'urlparams' => ['d' => $dlx->id()]]);

// Heading.
$typename = get_string('pluginname', 'datalynxfield_' . $fieldtype);
if ($id) {
    $heading = get_string('fieldformatname', 'mod_datalynx') . ': ' . format_string($format->get_name());
} else {
    $heading = get_string('fieldformatadd', 'mod_datalynx') . ' (' . $typename . ')';
}
echo html_writer::tag('h2', $heading, ['class' => 'mdl-align']);

// Set form data.
if ($id) {
    $formdata = $format->get_record();
    $settings = $format->get_settings();
    foreach ($settings as $key => $val) {
        $formdata->$key = $val;
    }
    $formdata->d = $dlx->id();
    $mform->set_data($formdata);
} else {
    $formdata = new stdClass();
    $formdata->d = $dlx->id();
    $formdata->id = 0;
    $formdata->fieldtype = $fieldtype;
    $mform->set_data($formdata);
}

$mform->display();

$dlx->print_footer();
