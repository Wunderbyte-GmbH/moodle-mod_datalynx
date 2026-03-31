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
 * @package datalynxfield_entrygroup
 * @subpackage entrygroup
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 * Renderer for the entry group field type.
 */
class datalynxfield_entrygroup_renderer extends datalynxfield_renderer {
    /**
     * Get replacements for the given tags.
     *
     * @param array $tags
     * @param stdClass $entry
     * @param array $options
     * @return array
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $field = $this->field;
        $fieldname = $field->get('internalname');
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        // Set the group object.
        $group = new stdClass();
        if ($entry->id < 0) { // New record.
            $entry->groupid = $field->df()->currentgroup;
            $group->id = $entry->groupid;
            $group->name = null;
            $group->picture = null;
        } else {
            $group->id = $entry->groupid;
            $group->name = $entry->groupname;
            $group->picture = $entry->grouppic;
        }

        $replacements = [];

        foreach ($tags as $tag) {
            $replacements[$tag] = '';
            switch (trim($tag, '@')) {
                case '##group:id##':
                    if (!empty($group->id)) {
                        $replacements[$tag] = ['html', $group->id];
                    }
                    break;

                case '##group:name##':
                    $replacements[$tag] = ['html', $group->name];
                    break;

                case '##group:picture##':
                    $replacements[$tag] = ['html',
                            print_group_picture($group, $field->df()->course->id, false, true),
                    ];
                    break;

                case '##group:picturelarge##':
                    $replacements[$tag] = ['html',
                            print_group_picture($group, $field->df()->course->id, true, true),
                    ];
                    break;

                case '##group:edit##':
                    if (
                        $edit && has_capability(
                            'mod/datalynx:manageentries',
                            $field->df()->context
                        )
                    ) {
                        $replacements[$tag] = ['',
                                [[$this, 'display_edit'], [$entry]],
                        ];
                    } else {
                        $replacements[$tag] = ['html', $group->name];
                    }
                    break;
            }
        }

        return $replacements;
    }

    /**
     * Display the group selector for editing.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function display_edit(&$mform, $entry, array $options = null) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $selected = $entry->groupid;
        static $groupsmenu = null;
        if (is_null($groupsmenu)) {
            $groupsmenu = [0 => get_string('choosedots')];
            if ($groups = groups_get_activity_allowed_groups($field->df()->cm)) {
                foreach ($groups as $groupid => $group) {
                    $groupsmenu[$groupid] = $group->name;
                }
            }
        }

        $mform->addElement('select', $fieldname, null, $groupsmenu);
        $mform->setDefault($fieldname, $selected);
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $cat = get_string('groupinfo', 'datalynx');

        $patterns = [];
        $patterns['##group:id##'] = [true, $cat];
        $patterns['##group:name##'] = [true, $cat];
        $patterns['##group:picture##'] = [true, $cat];
        $patterns['##group:picturelarge##'] = [false, $cat];
        $patterns['##group:edit##'] = [true, $cat];

        return $patterns;
    }
}
