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
namespace datalynxfield_entrygroup;

use mod_datalynx\local\field\datalynxfield_renderer;
use mod_datalynx\local\field_format\manager as format_manager;
use MoodleQuickForm;
use stdClass;

/**
 * Renderer for the entry group field type.
 */
class renderer extends datalynxfield_renderer {
    /**
     * Get replacements for the given tags.
     *
     * Resolves the display_field from the DB format record's settings, falling back to
     * using the suffix directly as the display field name (legacy compatibility).
     *
     * @param ?array $tags
     * @param stdClass $entry
     * @param ?array $options
     * @return array
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        $field = $this->field;
        $edit  = !empty($options['edit']) ? $options['edit'] : false;
        $dlxid = $field->df()->id();

        // Set the group object.
        $group = new stdClass();
        if ($entry->id < 0) { // New record.
            $entry->groupid = $field->dlx()->currentgroup;
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
            $stripped = trim($tag, '@');

            // Extract suffix from ##group:suffix##.
            $suffix = substr($stripped, strlen('##group:'), -2);

            // Resolve display_field from DB format settings, fall back to suffix.
            $format = format_manager::get_format_by_name($dlxid, $suffix);
            if ($format && $format->get_fieldtype() === 'entrygroup') {
                $displayfield = $format->get_setting('display_field') ?: $suffix;
            } else {
                $displayfield = $suffix;
            }

            switch ($displayfield) {
                case 'id':
                    if (!empty($group->id)) {
                        $replacements[$tag] = ['html', $group->id];
                    }
                    break;

                case 'name':
                    $replacements[$tag] = ['html', $group->name];
                    break;

                case 'picture':
                    $replacements[$tag] = ['html',
                            print_group_picture($group, $field->dlx()->course->id, false, true),
                    ];
                    break;

                case 'picturelarge':
                    $replacements[$tag] = ['html',
                            print_group_picture($group, $field->dlx()->course->id, true, true),
                    ];
                    break;

                case 'edit':
                    if (
                        $edit && has_capability(
                            'mod/datalynx:manageentries',
                            $field->dlx()->context
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
     * @param ?array $options
     */
    public function display_edit(&$mform, $entry, ?array $options = null) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $selected = $entry->groupid;
        static $groupsmenu = null;
        if (is_null($groupsmenu)) {
            $groupsmenu = [0 => get_string('choosedots')];
            if ($groups = groups_get_activity_allowed_groups($field->dlx()->cm)) {
                foreach ($groups as $groupid => $group) {
                    $groupsmenu[$groupid] = $group->name;
                }
            }
        }

        $mform->addElement('select', $fieldname, null, $groupsmenu);
        $mform->setDefault($fieldname, $selected);
    }

    /**
     * Array of patterns this field supports.
     *
     * Patterns are dynamically generated from DB format records for the 'entrygroup' type.
     *
     * @return array
     */
    protected function patterns() {
        $cat = get_string('groupinfo', 'datalynx');
        $dlxid = $this->field->df()->id();

        $patterns = [];

        $formats = format_manager::get_formats_for_instance($dlxid, 'entrygroup');
        foreach ($formats as $format) {
            $patterns["##group:{$format->get_name()}##"] = [true, $cat];
        }

        return $patterns;
    }
}
