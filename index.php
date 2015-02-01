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
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * The Datalynx has been developed as an enhanced counterpart
 * of Moodle's Database activity module (1.9.11+ (20110323)).
 * To the extent that Datalynx code corresponds to Database code,
 * certain copyrights on the Database module may obtain
 */

require_once("../../config.php");
require_once("$CFG->dirroot/mod/datalynx/mod_class.php");
require_once("$CFG->dirroot/mod/datalynx/lib.php");

$id             = required_param('id', PARAM_INT);   // course
//$add            = optional_param('add', '', PARAM_ALPHA);
//$update         = optional_param('update', 0, PARAM_INT);
//$duplicate      = optional_param('duplicate', 0, PARAM_INT);
//$hide           = optional_param('hide', 0, PARAM_INT);
//$show           = optional_param('show', 0, PARAM_INT);
//$movetosection  = optional_param('movetosection', 0, PARAM_INT);
//$delete         = optional_param('delete', 0, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $id))) {
    throw new moodle_exception('invalidcourseid');
}

$context = context_course::instance($course->id);
require_course_login($course);

$event = \mod_datalynx\event\course_module_instance_list_viewed::create(array('context' => $context));
$event->trigger();

$modulename = get_string('modulename','datalynx');
$modulenameplural  = get_string('modulenameplural','datalynx');

$PAGE->set_url('/mod/datalynx/index.php', array('id' => $id));
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add($modulename, new moodle_url('/mod/datalynx/index.php', array('id'=>$course->id)));
$PAGE->set_title($modulename);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

if (!$datalynxs = get_all_instances_in_course("datalynx", $course)) {
    notice(get_string('thereareno', 'moodle',$modulenameplural) , new moodle_url('/course/view.php', array('id', $course->id)));
}

$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $sections = get_all_sections($course->id);
}

$table = new html_table();
$table->attributes['align'] = 'center';
$table->attributes['cellpadding'] = '2';
$table->head  = array ();
$table->align = array ();

// section
if ($usesections) {
    $table->head[] = get_string('sectionname', 'format_'.$course->format);
    $table->align[] = 'center';
}

// name
$table->head[] = get_string('name');
$table->align[] = 'left';

// description
$table->head[] = get_string('description');
$table->align[] = 'left';

// number of entries
$table->head[] = get_string('entries', 'datalynx');
$table->align[] = 'center';

// number of pending entries
$table->head[] = get_string('entriespending', 'datalynx');
$table->align[] = 'center';

// rss
$rss = (!empty($CFG->enablerssfeeds) and !empty($CFG->datalynx_enablerssfeeds));
if ($rss) {
    require_once($CFG->libdir."/rsslib.php");
    $table->head[] = 'RSS';
    $table->align[] = 'center';
}

// actions
if ($showeditbuttons = $PAGE->user_allowed_editing()) {
    $table->head[] = '';
    $table->align[] = 'center';
    $editingurl = new moodle_url('/course/mod.php',
                                array('sesskey' => sesskey()));
}

$options = new object;
$options->noclean = true;
$currentsection = null;
$stredit = get_string('edit');
$strdelete = get_string('delete');

foreach ($datalynxs as $datalynx) {
    $tablerow = array();
    
    $df = new datalynx($datalynx);

    if (!has_capability('mod/datalynx:viewindex', $df->context)) {
        continue;
    }

    // section
    if ($usesections) {
        if ($datalynx->section !== $currentsection) {
            if ($currentsection !== null) {
                $table->data[] = 'hr';
            }
            $currentsection = $datalynx->section;
            $tablerow[] = get_section_name($course, $sections[$datalynx->section]);
        } else {
            $tablerow[] = '';
        }
    }

    // name (linked; dim if not visible)
    $linkparams = !$datalynx->visible ? array('class' => 'dimmed') : null;
    $linkedname = html_writer::link(new moodle_url('/mod/datalynx/view.php', array('id' => $datalynx->coursemodule)),
                                format_string($datalynx->name, true),
                                $linkparams);
    $tablerow[] = $linkedname;

    // description
    $tablerow[] = format_text($datalynx->intro, $datalynx->introformat, $options);

    // number of entries
    $tablerow[] = $df->get_entriescount(datalynx::COUNT_ALL);
    
    // number of pending entries
    $tablerow[] = $df->get_entriescount(datalynx::COUNT_LEFT);
    
    // rss
    if ($rss) {
        if ($datalynx->rssarticles > 0) {
            $tablerow[] = rss_get_link($course->id, $USER->id, 'datalynx', $datalynx->id, 'RSS');
        } else {
            $tablerow[] = '';
        }
    }

    if ($showeditbuttons) {
        $buttons = array();
        $editingurl->param('update', $datalynx->coursemodule);
        $buttons['edit'] = html_writer::link($editingurl, $OUTPUT->pix_icon('t/edit', $stredit));
        $editingurl->remove_params('update');
        
        $editingurl->param('delete', $datalynx->coursemodule);
        $buttons['delete'] = html_writer::link($editingurl, $OUTPUT->pix_icon('t/delete', $strdelete));
        $editingurl->remove_params('delete');

        $tablerow[] = implode('&nbsp;&nbsp;&nbsp;', $buttons);
    }
    
    $table->data[] = $tablerow;
}

echo html_writer::empty_tag('br');
echo html_writer::tag('div', html_writer::table($table), array('class'=>'no-overflow'));
echo $OUTPUT->footer();
