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
 * @package mod
 * @subpackage datalynx
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The Datalynx has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Datalynx code corresponds to Database code,
 * certain copyrights on the Database module may obtain.
 */

require_once('../../config.php');

$urlparams = new object;
$urlparams->d = optional_param('d', 0, PARAM_INT);   // datalynx id
$urlparams->id = optional_param('id', 0, PARAM_INT);   // course module id
$urlparams->cssedit = optional_param('cssedit', 0, PARAM_BOOL);   // edit mode

if ($urlparams->cssedit) {
    require_once('mod_class.php');
    require_once $CFG->libdir.'/formslib.php';

    class mod_datalynx_css_form extends moodleform {

        function definition() {
            global $CFG, $COURSE;

            $mform = &$this->_form;

            // buttons
            //-------------------------------------------------------------------------------
            $this->add_action_buttons(true);

            // css
            //-------------------------------------------------------------------------------
            $mform->addElement('header', 'generalhdr', get_string('headercss', 'datalynx'));

            // includes
            $attributes = array('wrap' => 'virtual', 'rows' => 5, 'cols' => 60);
            $mform->addElement('textarea', 'cssincludes', get_string('cssincludes', 'datalynx'), $attributes);

            // code
            $attributes = array('wrap' => 'virtual', 'rows' => 15, 'cols' => 60);
            $mform->addElement('textarea', 'css', get_string('csscode', 'datalynx'), $attributes);

            // uploads
            $options = array(
                'subdirs' => 0,
                'maxbytes' => $COURSE->maxbytes,
                'maxfiles' => 10,
                'accepted_types' => array('*.css')
            );
            $mform->addElement('filemanager', 'cssupload', get_string('cssupload', 'datalynx'), null, $options);
            
            // buttons
            //-------------------------------------------------------------------------------
            $this->add_action_buttons(true);
        }

    }

    // Set a datalynx object
    $df = new datalynx($urlparams->d, $urlparams->id);
    require_capability('mod/datalynx:managetemplates', $df->context);

    $df->set_page('css', array('urlparams' => $urlparams));

    // activate navigation node
    navigation_node::override_active_url(new moodle_url('/mod/datalynx/css.php', array('id' => $df->cm->id, 'cssedit' => 1)));

    $mform = new mod_datalynx_css_form(new moodle_url('/mod/datalynx/css.php', array('d' => $df->id(), 'cssedit' => 1))); 

    if ($mform->is_cancelled()) {
    
    } else if ($data = $mform->get_data()){
        // update the datalynx
        $rec = new object();
        $rec->css = $data->css;
        $rec->cssincludes = $data->cssincludes;        
        $df->update($rec, get_string('csssaved', 'datalynx'));
        
        // add uploaded files
        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();
        if ($files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data->cssupload, 'sortorder', false)) {
            $filerec = new object;
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
        
        add_to_log($df->course->id, 'datalynx', 'css saved', 'css.php?id='. $df->cm->id. '&amp;d='. $df->id(), $df->id(), $df->cm->id);
    }

    $df->print_header(array('tab' => 'css', 'urlparams' => $urlparams));

    $options = array(
        'subdirs' => 0,
        'maxbytes' => $COURSE->maxbytes,
        'maxfiles' => 10,
    );
    $draftitemid = file_get_submitted_draft_itemid('cssupload');
    file_prepare_draft_area($draftitemid, $df->context->id, 'mod_datalynx', 'css', 0, $options);
    $df->data->cssupload = $draftitemid;


    $mform->set_data($df->data);
    $mform->display();
    $df->print_footer();

} else {

    defined('NO_MOODLE_COOKIES') or define('NO_MOODLE_COOKIES', true); // session not used here

    $lifetime  = 600;                                   // Seconds to cache this stylesheet
    
    $PAGE->set_url('/mod/datalynx/css.php', array('d'=>$urlparams->d));

    if ($cssdata = $DB->get_field('datalynx', 'css', array('id'=>$urlparams->d))) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
        header('Expires: ' . gmdate("D, d M Y H:i:s", time() + $lifetime) . ' GMT');
        header('Cache-control: max_age = '. $lifetime);
        header('Pragma: ');
        header('Content-type: text/css; charset=utf-8');  // Correct MIME type

        echo $cssdata;
    }
}
