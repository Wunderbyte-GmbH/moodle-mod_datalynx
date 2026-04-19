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
 * @package datalynxfield_coursegroup
 * @subpackage coursegroup
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_coursegroup;

use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;
use html_writer;



/**
 * Renderer class for the coursegroup field.
 *
 * @package    datalynxfield_coursegroup
 */
class renderer extends datalynxfield_renderer {
    /**
     * Returns replacement values for coursegroup tags.
     *
     * @param array|null $tags Array of tag strings to replace.
     * @param stdClass|null $entry The current entry object.
     * @param array|null $options Rendering options.
     * @return array Replacements keyed by tag.
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        $field = $this->field;
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        $replacements = array_fill_keys($tags, '');

        foreach ($tags as $tag) {
            if ($edit) {
                $replacements[$tag] = ['', [[$this, 'display_edit'], [$entry]]];
                break;
            } else {
                $parts = explode(':', trim($tag, '[]'));
                if (!empty($parts[1])) {
                    $type = $parts[1];
                } else {
                    $type = '';
                }
                $replacements[$tag] = ['html', $this->display_browse($entry, $type)];
            }
        }

        return $replacements;
    }

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Displays the edit form elements for the coursegroup field.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The current entry object.
     * @param array|null $options Rendering options.
     */
    public function display_edit(&$mform, $entry, ?array $options = null) {
        global $CFG, $DB, $PAGE;

        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        if ($field->course) {
            $courseid = $field->course;
        } else {
            $courseid = $entryid > 0 ? $entry->{"c{$fieldid}_content"} : '';
        }

        if ($field->group) {
            $groupid = $field->group;
        } else {
            $groupid = !empty($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : 0;
        }

        $fieldname = "field_{$fieldid}_{$entryid}";
        // Group course.
        $courses = get_courses("all", "c.sortorder ASC", "c.id,c.fullname");
        $coursemenu = [0 => get_string('choosedots')];
        foreach ($courses as $cid => $course) {
            $coursemenu[$cid] = $course->fullname;
        }
        if ($field->course) {
            $cname = get_string('coursenotfound', 'datalynxfield_coursegroup', $courseid);
            if (!empty($coursemenu[$courseid])) {
                $cname = $coursemenu[$courseid];
            }
            $mform->addElement('html', html_writer::tag('h3', $cname));
        } else {
            $mform->addElement('select', "{$fieldname}_course", null, $coursemenu);
            $mform->setDefault("{$fieldname}_course", $courseid);

            // Ajax.
            $options = ['coursefield' => "{$fieldname}_course",
                    'groupfield' => "{$fieldname}_group",
                    'acturl' => "$CFG->wwwroot/mod/datalynx/field/coursegroup/loadgroups.php",
            ];

            // Add JQuery.
            $PAGE->requires->js_call_amd(
                'mod_datalynx/coursegroup',
                'init',
                [$options]
            );
        }

        // Group id.
        if ($field->group) {
            if ($group = $DB->get_record('groups', ['id' => $groupid], 'name')) {
                $groupname = $group->name;
            } else {
                $groupname = get_string('groupnotfound', 'datalynxfield_coursegroup', $groupid);
            }
            $mform->addElement('html', html_writer::tag('h3', $groupname));
        } else {
            $groupmenu = ['' => get_string('choosedots')];
            if ($courseid) {
                $groups = $DB->get_records_menu('groups', ['courseid' => $courseid], 'name', 'id,name');
                foreach ($groups as $gid => $groupname) {
                    $groupmenu[$gid] = $groupname;
                }
            }
            $mform->addElement('select', "{$fieldname}_group", null, $groupmenu);
            $mform->setDefault("{$fieldname}_group", $groupid);
            $mform->setType("{$fieldname}_group", PARAM_INT);
            $mform->disabledIf("{$fieldname}_group", "{$fieldname}_course", 'eq', '');

            $mform->addElement('text', "{$fieldname}_groupid", null, ['class' => 'hide']);
            $mform->setType("{$fieldname}_groupid", PARAM_TEXT);
            $mform->setDefault("{$fieldname}_groupid", $groupid);
        }
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Displays the browse view for the coursegroup field.
     *
     * @param stdClass $entry The current entry object.
     * @param string|null $type The display type (course, group, courseid, groupid, or empty).
     * @return string The rendered output.
     */
    protected function display_browse($entry, $type = null) {
        global $DB;

        $field = $this->field;
        $fieldid = $field->id();

        $courseid = 0;
        if (!empty($field->course)) {
            $courseid = (int) $field->course;
        } else {
            if (!empty($entry->{"c{$fieldid}_content"})) {
                $courseid = (int) $entry->{"c{$fieldid}_content"};
            } else {
                return '';
            }
        }

        $groupid = 0;
        if (!empty($field->group)) {
            $groupid = (int) $field->group;
        } else {
            if (!empty($entry->{"c{$fieldid}_content1"})) {
                $groupid = (int) $entry->{"c{$fieldid}_content1"};
            }
        }

        switch ($type) {
            case 'course':
                // Return the course name.
                if ($coursename = $DB->get_field('course', 'fullname', ['id' => $courseid])) {
                    return $coursename;
                }
                break;
            case 'group':
                // Return the group name.
                if (
                    $groupid &&
                        $groupname = $DB->get_field('groups', 'name', ['id' => $groupid])
                ) {
                    return $groupname;
                }
                break;

            case 'courseid':
                return $courseid;
                break;

            case 'groupid':
                return $groupid;
                break;

            case '':
                return "$courseid $groupid";
                break;
        }
        return '';
    }

    /**
     * Value is an array of (member,courseid,groupid) only one should be set
     *
     * @param MoodleQuickForm $mform The form object.
     * @param int $i The filter index.
     * @param string $value The current search value.
     * @return array Array of form elements and labels.
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $fieldid = $this->field->id();

        if (is_array($value)) {
            [$member, $course, $group] = $value;
        } else {
            $member = $course = $group = 0;
        }

        $elements = [];
        // Select yes/no for member.
        $elements[] = &$mform->createElement('selectyesno', "f_{$i}_{$fieldid}_member");
        // Number field for course id.
        $elements[] = &$mform->createElement('text', "f_{$i}_{$fieldid}_course", null, ['size' => 32]);
        // Number field for group id.
        $elements[] = &$mform->createElement('text', "f_{$i}_{$fieldid}_group", null, ['size' => 32]);
        $mform->setDefault("f_{$i}_{$fieldid}_member", $member);
        $mform->setDefault("f_{$i}_{$fieldid}_course", $course);
        $mform->setDefault("f_{$i}_{$fieldid}_group", $group);
        $mform->disabledIf("coursegroupelements$i", "searchoperator$i", 'eq', '');

        return [$elements,
                [get_string('member', 'datalynxfield_coursegroup'),
                        '<br />' . get_string('course') . ' ', '<br />' . get_string('group') . ' ',
                ]];
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = [true];
        $patterns["[[$fieldname:course]]"] = [true];
        $patterns["[[$fieldname:group]]"] = [true];
        $patterns["[[$fieldname:courseid]]"] = [false];
        $patterns["[[$fieldname:groupid]]"] = [false];

        return $patterns;
    }
}
