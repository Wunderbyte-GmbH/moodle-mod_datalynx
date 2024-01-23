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
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @copyright 2016 onwards edulabs.org
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once("../../config.php");
require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");
require_once("$CFG->dirroot/mod/datalynx/lib.php");

$id = required_param('id', PARAM_INT); // Course id.
$course = $DB->get_record('course', array('id' => $id));
if (!$course) {
    throw new moodle_exception('invalidcourseid');
}

$context = context_course::instance($course->id);
require_course_login($course);

$event = \mod_datalynx\event\course_module_instance_list_viewed::create(array('context' => $context));
$event->trigger();

$modulename = get_string('modulename', 'datalynx');
$modulenameplural = get_string('modulenameplural', 'datalynx');

$PAGE->set_url('/mod/datalynx/index.php', array('id' => $id));
$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add($modulename, new moodle_url('/mod/datalynx/index.php', array('id' => $course->id)));
$PAGE->set_title($modulename);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
$datalynxs = get_all_instances_in_course("datalynx", $course);
if (!$datalynxs) {
    notice(get_string('thereareno', 'moodle', $modulenameplural),
            new moodle_url('/course/view.php', array('id', $course->id)));
}

$modinfo = get_fast_modinfo($id);
$sections = $modinfo->get_section_info_all();

$table = new html_table();
$table->attributes['align'] = 'center';
$table->attributes['cellpadding'] = '2';
$table->head = array();
$table->align = array();

// Section.
$table->head[] = get_string('sectionname', 'format_' . $course->format);
$table->align[] = 'center';

// Name.
$table->head[] = get_string('name');
$table->align[] = 'left';

// Description.
$table->head[] = get_string('description');
$table->align[] = 'left';

// Number of entries.
$table->head[] = get_string('entries', 'datalynx');
$table->align[] = 'center';

// Number of pending entries.
$table->head[] = get_string('entriespending', 'datalynx');
$table->align[] = 'center';

// Rss.
$rss = (!empty($CFG->enablerssfeeds) && !empty($CFG->datalynx_enablerssfeeds));
if ($rss) {
    require_once($CFG->libdir . "/rsslib.php");
    $table->head[] = 'RSS';
    $table->align[] = 'center';
}

// Actions.
if ($showeditbuttons = $PAGE->user_allowed_editing()) {
    $table->head[] = '';
    $table->align[] = 'center';
    $editingurl = new moodle_url('/course/mod.php', array('sesskey' => sesskey()));
}

$options = new stdClass();
$options->noclean = true;
$currentsection = null;
$stredit = get_string('edit');
$strdelete = get_string('delete');

foreach ($datalynxs as $datalynx) {
    $tablerow = array();

    $df = new mod_datalynx\datalynx($datalynx);

    if (!has_capability('mod/datalynx:viewindex', $df->context)) {
        continue;
    }

    // Section.
    if ($datalynx->section !== $currentsection) {
        if ($currentsection !== null) {
            $table->data[] = 'hr';
        }
        $currentsection = $datalynx->section;
        $tablerow[] = get_section_name($course, $sections[$datalynx->section]);
    } else {
        $tablerow[] = '';
    }

    // Name (linked; dim if not visible).
    $linkparams = !$datalynx->visible ? array('class' => 'dimmed') : null;
    $linkedname = html_writer::link(
            new moodle_url('/mod/datalynx/view.php', array('id' => $datalynx->coursemodule)),
            format_string($datalynx->name, true), $linkparams);
    $tablerow[] = $linkedname;

    // Description.
    $tablerow[] = format_text($datalynx->intro, $datalynx->introformat, $options);

    // Number of entries.
    $tablerow[] = $df->get_entriescount(mod_datalynx\datalynx::COUNT_ALL);

    // Number of pending entries.
    $tablerow[] = $df->get_entriescount(mod_datalynx\datalynx::COUNT_UNAPPROVED);

    // Rss.
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
        $buttons['delete'] = html_writer::link($editingurl,
                $OUTPUT->pix_icon('t/delete', $strdelete));
        $editingurl->remove_params('delete');

        $tablerow[] = implode('&nbsp;&nbsp;&nbsp;', $buttons);
    }

    $table->data[] = $tablerow;
}

echo html_writer::empty_tag('br');
echo html_writer::tag('div', html_writer::table($table), array('class' => 'no-overflow'));
echo $OUTPUT->footer();
