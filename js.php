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
 * @copyright 2013 onwards Ivan Sakic, David Bogner, Thomas Niedermaier
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
require_once('../../config.php');
require_once('classes/datalynx.php');

$urlparams = new stdClass();
$urlparams->d = optional_param('d', 0, PARAM_INT); // Datalynx id.
$urlparams->id = optional_param('id', 0, PARAM_INT); // Course module id.
$urlparams->jsedit = optional_param('jsedit', 0, PARAM_BOOL); // Edit mode.

$df = new mod_datalynx\datalynx($urlparams->d, $urlparams->id);

require_login($df->data->course, false, $df->cm);

if ($urlparams->jsedit) {
    require_once($CFG->libdir . '/formslib.php');

    class mod_datalynx_js_form extends moodleform {

        public function definition() {
            global $CFG, $COURSE;

            $mform = &$this->_form;

            // Buttons.
            $this->add_action_buttons(true);

            // Js.
            $mform->addElement('header', 'generalhdr', get_string('headerjs', 'datalynx'));

            // Includes.
            $attributes = array('wrap' => 'soft', 'rows' => 5, 'cols' => 60
            );
            $mform->addElement('textarea', 'jsincludes', get_string('jsincludes', 'datalynx'),
                    $attributes);

            // Code.
            $attributes = array('wrap' => 'soft', 'rows' => 15, 'cols' => 60
            );
            $mform->addElement('textarea', 'js', get_string('jscode', 'datalynx'), $attributes);

            // Uploads.
            $options = array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 10,
                    'accepted_types' => array('*.js'
                    )
            );
            $mform->addElement('filemanager', 'jsupload', get_string('jsupload', 'datalynx'), null,
                    $options);

            // Buttons.
            $this->add_action_buttons(true);
        }
    }

    require_capability('mod/datalynx:managetemplates', $df->context);

    $df->set_page('js', array('urlparams' => $urlparams
    ));

    // Activate navigation node.
    navigation_node::override_active_url(
            new moodle_url('/mod/datalynx/js.php', array('id' => $df->cm->id, 'jsedit' => 1
            )));

            $mform = new mod_datalynx_js_form(
            new moodle_url('/mod/datalynx/js.php', array('d' => $df->id(), 'jsedit' => 1
            )));

    if (!$mform->is_cancelled()) {
        if ($data = $mform->get_data()) {
            $rec = new stdClass();
            $rec->js = $data->js;
            $rec->jsincludes = $data->jsincludes;
            $df->update($rec, get_string('jssaved', 'datalynx'));

            // Add uploaded files.
            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->jsupload,
            'sortorder', false)
            ) {
                $filerec = new stdClass();
                $filerec->contextid = $df->context->id;
                $filerec->component = 'mod_datalynx';
                $filerec->filearea = 'js';
                $filerec->filepath = '/';

                foreach ($files as $file) {
                            $filerec->filename = $file->get_filename();
                            $fs->create_file_from_storedfile($filerec, $file);
                }
                $fs->delete_area_files($usercontext->id, 'user', 'draft', $data->jsupload);
            }

            $event = \mod_datalynx\event\js_saved::create(
            array('context' => $df->context, 'objectid' => $df->id()
            ));
            $event->trigger();
        }
    }

            $df->print_header(array('tab' => 'js', 'urlparams' => $urlparams
            ));

            $options = array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes, 'maxfiles' => 10
            );
            $draftitemid = file_get_submitted_draft_itemid('jsupload');
            file_prepare_draft_area($draftitemid, $df->context->id, 'mod_datalynx', 'js', 0, $options);
            $df->data->jsupload = $draftitemid;

            $mform->set_data($df->data);
            $mform->display();
            $df->print_footer();
} else {

    defined('NO_MOODLE_COOKIES') || define('NO_MOODLE_COOKIES', true); // Session not used here.

    $lifetime = 0; // Seconds to cache this stylesheet.

    $PAGE->set_url('/mod/datalynx/js.php', array('d' => $urlparams->d
    ));

    if ($jsdata = $DB->get_field('datalynx', 'js', array('id' => $urlparams->d
    ))
    ) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $lifetime) . ' GMT');
        header('Cache-control: max_age = ' . $lifetime);
        header('Pragma: ');
        header('Content-type: text/javascript'); // Correct MIME type.

        echo $jsdata;
    }
}
