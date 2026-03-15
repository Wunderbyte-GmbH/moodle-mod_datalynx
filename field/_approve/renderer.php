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
 * @subpackage _approve
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield__approve_renderer extends datalynxfield_renderer {

    /**
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
                $replacements['##approve##@'] = ['html', $this->display_browse($entry)];
                $replacements['##approve##'] = ['html', $this->display_browse($entry)];
            }
        }

        return $replacements;
    }

    /**
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
     */
    protected function display_browse($entry, $params = null) {
        global $PAGE;

        $field = $this->_field;
        if ($entry && isset($entry->approved) && $entry->approved) {
            $iconclass = 'fa-regular fa-circle-xmark text-danger';
            $labelstring = get_string('unapprove', 'datalynx');
            $approval = 'disapprove';
        } else {
            $iconclass = 'fa-regular fa-circle-check text-success';
            $labelstring = get_string('approve');
            $approval = 'approve';
        }

        $icon = html_writer::tag('i', '', [
            'class' => "icon {$iconclass} fa-fw",
            'role' => 'img',
            'aria-hidden' => 'true',
        ]);
        $label = html_writer::span($labelstring, 'datalynxfield__approve-label');

        if (has_capability('mod/datalynx:approve', $field->df()->context)) {
            $PAGE->requires->js_call_amd('mod_datalynx/approve', 'init',
                    [get_string('approve'), get_string('unapprove', 'datalynx')]);

            return html_writer::link(
                    new moodle_url($entry->baseurl,
                            [$approval => $entry->id, 'sesskey' => sesskey(),
                                    'sourceview' => $this->_field->df()->get_current_view()->id()
                            ]), $icon . $label, [
                                'class' => 'datalynxfield__approve',
                                'data-action' => 'toggle-approval',
                                'title' => $labelstring,
                            ]);
        } else {
            return $icon . $label;
        }
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
