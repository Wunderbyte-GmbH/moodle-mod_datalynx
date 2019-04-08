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
 * @copyright 2013 onwards Ivan Šakić, Thomas Niedermaier
 * @copyright based on the work by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once('../../config.php');

$urlparams = new stdClass();
$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.
$urlparams->cssedit = optional_param('cssedit', 0, PARAM_BOOL); // Edit mode.

$cm = get_coursemodule_from_instance('datalynx', $urlparams->d, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);

if ($urlparams->cssedit) {
    require_once('classes/datalynx.php');
    require_once($CFG->libdir . '/formslib.php');

    class mod_datalynx_css_form extends moodleform {

        public function definition() {
            global $COURSE;

            $mform = &$this->_form;

            // Buttons.
            $this->add_action_buttons(true);

            // Css.
            $mform->addElement('header', 'generalhdr', get_string('headercss', 'datalynx'));

            // Includes.
            $attributes = array('wrap' => 'virtual', 'rows' => 5, 'cols' => 60);
            $mform->addElement('textarea', 'cssincludes', get_string('cssincludes', 'datalynx'), $attributes);

            // Code.
            $attributes = array('wrap' => 'virtual', 'rows' => 15, 'cols' => 60);
            $mform->addElement('textarea', 'css', get_string('csscode', 'datalynx'), $attributes);

            // Uploads.
            $options = array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 10,
                    'accepted_types' => array('*.css')
            );
            $mform->addElement('filemanager', 'cssupload', get_string('cssupload', 'datalynx'),
                    null, $options);

            // Buttons.
            $this->add_action_buttons(true);
        }
    }

    // Set a datalynx object.
    $df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);
    require_capability('mod/datalynx:managetemplates', $df->context);

    $df->set_page('css', array('urlparams' => $urlparams));

    // Activate navigation node.
    navigation_node::override_active_url(
            new moodle_url('/mod/datalynx/css.php', array('id' => $df->cm->id, 'cssedit' => 1)));

    $mform = new mod_datalynx_css_form(
            new moodle_url('/mod/datalynx/css.php', array('d' => $df->id(), 'cssedit' => 1)));

    if (!$mform->is_cancelled()) {
        if ($data = $mform->get_data()) {
            // Update the datalynx.
            $rec = new stdClass();
            $rec->css = $data->css;
            $rec->cssincludes = $data->cssincludes;
            $df->update($rec, get_string('csssaved', 'datalynx'));

            // Add uploaded files.
            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->cssupload,
                    'sortorder', false)
            ) {
                $filerec = new stdClass();
                $filerec->contextid = $df->context->id;
                $filerec->component = 'mod_datalynx';
                $filerec->filearea = 'css';
                $filerec->filepath = '/';

                foreach ($files as $file) {
                    $filerec->filename = $file->get_filename();
                    $fs->create_file_from_storedfile($filerec, $file);
                }
                $fs->delete_area_files($usercontext->id, 'user', 'draft', $data->cssupload);
            }

            $event = \mod_datalynx\event\css_saved::create(
                    array('context' => $df->context, 'objectid' => $df->id()));
            $event->trigger();
        }
    }

    $df->print_header(array('tab' => 'css', 'urlparams' => $urlparams));

    $options = array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 10);
    $draftitemid = file_get_submitted_draft_itemid('cssupload');
    file_prepare_draft_area($draftitemid, $df->context->id, 'mod_datalynx', 'css', 0, $options);
    $df->data->cssupload = $draftitemid;

    $mform->set_data($df->data);
    $mform->display();
    $df->print_footer();
} else {

    defined('NO_MOODLE_COOKIES') or define('NO_MOODLE_COOKIES', true); // Session not used here.

    $lifetime = 600; // Seconds to cache this stylesheet.

    $PAGE->set_url('/mod/datalynx/css.php', array('d' => $urlparams->d));

    if ($cssdata = $DB->get_field('datalynx', 'css', array('id' => $urlparams->d
    ))
    ) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $lifetime) . ' GMT');
        header('Cache-control: max_age = ' . $lifetime);
        header('Pragma: ');
        header('Content-type: text/css; charset=utf-8'); // Correct MIME type.

        echo $cssdata;
    }
}
