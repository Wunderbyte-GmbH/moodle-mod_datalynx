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
 * @subpackage _approve
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG;
require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 * Approve field renderer class for datalynx.
 */
class datalynxfield__approve_renderer extends datalynxfield_renderer {
    /**
     * Get replacements for tags.
     *
     * @param array|null $tags
     * @param stdClass|null $entry
     * @param array|null $options
     * @return array
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $df = $this->_field->df();

        $canapprove = has_capability('mod/datalynx:approve', $df->context);
        $edit = !empty($options['edit']) ? $options['edit'] && $canapprove : false;
        $replacements = [];
        // Just one tag, empty until we check df settings.
        $replacements['##approve##'] = '';

        if ($df->data->approval) {
            if (!$entry || $edit) {
                $replacements['##approve##'] = ['', [[$this, 'display_edit'], [$entry]]];

                // Existing entry to browse.
            } else {
                // Ensure the link is rendered for the common [[approve]] tag.
                $replacements['##approve##'] = ['html', $this->display_browse($entry)];
                $replacements['##approve##@'] = ['html', $this->display_browse($entry)];
            }
        }

        return $replacements;
    }

    /**
     * Render search mode.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();

        $options = [0 => ucfirst(get_string('approvednot', 'datalynx')),
                1 => ucfirst(get_string('approved', 'datalynx'))];
        $select = &$mform->createElement('select', "f_{$i}_$fieldid", null, $options);
        $select->setSelected($value);
        // Disable the 'not' and 'operator' fields.
        $mform->disabledIf("searchnot$i", "f_{$i}_$fieldid", 'neq', 2);
        $mform->disabledIf("searchoperator$i", "f_{$i}_$fieldid", 'neq', 2);

        return [[$select], null];
    }

    /**
     * Display edit mode.
     *
     * @param HTML_QuickForm $mform
     * @param stdClass $entry
     * @param array|null $options
     */
    public function display_edit(&$mform, $entry, array $options = null) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        if ($entryid > 0) {
            $checked = $entry->approved;
        } else {
            $checked = 0;
        }

        $fieldname = "field_{$fieldid}_{$entryid}";
        $mform->addElement('advcheckbox', $fieldname, null, null, null, [0, 1]);
        $mform->setDefault($fieldname, $checked);
    }

    /**
     * Display browse mode.
     *
     * @param stdClass $entry
     * @param array|null $params
     * @return string
     */
    protected function display_browse($entry, $params = null) {
        $field = $this->_field;
        if ($entry && isset($entry->approved) && $entry->approved) {
            $iconclass = 'fa-regular fa-circle-check text-success   ';
            $icon = "<i class='$iconclass' title='" . get_string('approved', 'datalynx') . "'></i>";
        } else {
            $iconclass = 'fa-regular fa-circle text-muted';
            $icon = "<i class='$iconclass' title='" . get_string('approvednot', 'datalynx') . "'></i>";
        }

        $canapprove = has_capability('mod/datalynx:approve', $field->df()->context);
        if ($canapprove && $entry) {
            $url = new moodle_url($this->page->url, ['approve' => $entry->id, 'sesskey' => sesskey()]);
            return html_writer::link($url, $icon);
        }
        return $icon;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $cat = get_string('actions', 'datalynx');

        $patterns = [];
        $patterns["##approve##"] = [true, $cat];

        return $patterns;
    }
}
