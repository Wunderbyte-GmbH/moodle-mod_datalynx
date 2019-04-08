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
 * @package datalynxfield
 * @subpackage entrygroup
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield_entrygroup_renderer extends datalynxfield_renderer {

    /**
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->get('internalname');
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        // Set the group object.
        $group = new stdClass();
        if ($entry->id < 0) { // New record.
            $entry->groupid = $field->df()->currentgroup;
            $group->id = $entry->groupid;
            $group->name = null;
            $group->hidepicture = null;
            $group->picture = null;
        } else {
            $group->id = $entry->groupid;
            $group->name = $entry->groupname;
            $group->hidepicture = $entry->grouphidepic;
            $group->picture = $entry->grouppic;
        }

        $replacements = array();

        foreach ($tags as $tag) {
            $replacements[$tag] = '';
            switch (trim($tag, '@')) {
                case '##group:id##':
                    if (!empty($group->id)) {
                        $replacements[$tag] = array('html', $group->id);
                    }
                    break;

                case '##group:name##':
                    $replacements[$tag] = array('html', $group->name);
                    break;

                case '##group:picture##':
                    $replacements[$tag] = array('html',
                            print_group_picture($group, $field->df()->course->id, false, true)
                    );
                    break;

                case '##group:picturelarge##':
                    $replacements[$tag] = array('html',
                            print_group_picture($group, $field->df()->course->id, true, true)
                    );
                    break;

                case '##group:edit##':
                    if ($edit and has_capability('mod/datalynx:manageentries',
                                    $field->df()->context)
                    ) {
                        $replacements[$tag] = array('',
                                array(array($this, 'display_edit'), array($entry))
                        );
                    } else {
                        $replacements[$tag] = array('html', $group->name);
                    }
                    break;
            }
        }

        return $replacements;
    }

    /**
     */
    public function display_edit(&$mform, $entry, array $options = null) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $selected = $entry->groupid;
        static $groupsmenu = null;
        if (is_null($groupsmenu)) {
            $groupsmenu = array(0 => get_string('choosedots'));
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

        $patterns = array();
        $patterns['##group:id##'] = array(true, $cat);
        $patterns['##group:name##'] = array(true, $cat);
        $patterns['##group:picture##'] = array(true, $cat);
        $patterns['##group:picturelarge##'] = array(false, $cat);
        $patterns['##group:edit##'] = array(true, $cat);

        return $patterns;
    }
}
