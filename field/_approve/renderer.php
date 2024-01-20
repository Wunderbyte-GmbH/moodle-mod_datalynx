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
        $replacements = array();
        // Just one tag, empty until we check df settings.
        $replacements['##approve##'] = '';

        if ($df->data->approval) {
            if (!$entry || $edit) {
                $replacements['##approve##'] = array('', array(array($this, 'display_edit'), array($entry)));

                // Existing entry to browse.
            } else {
                $replacements['##approve##@'] = array('html', $this->display_browse($entry));
                $replacements['##approve##'] = array('html', $this->display_browse($entry));
            }
        }

        return $replacements;
    }

    /**
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();

        $options = array(0 => ucfirst(get_string('approvednot', 'datalynx')),
                1 => ucfirst(get_string('approved', 'datalynx')));
        $select = &$mform->createElement('select', "f_{$i}_$fieldid", null, $options);
        $select->setSelected($value);
        // Disable the 'not' and 'operator' fields.
        $mform->disabledIf("searchnot$i", "f_{$i}_$fieldid", 'neq', 2);
        $mform->disabledIf("searchoperator$i", "f_{$i}_$fieldid", 'neq', 2);

        return array(array($select), null);
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
        $mform->addElement('advcheckbox', $fieldname, null, null, null, array(0, 1));
        $mform->setDefault($fieldname, $checked);
    }

    /**
     */
    protected function display_browse($entry, $params = null) {
        global $OUTPUT, $CFG, $PAGE;

        $field = $this->_field;
        if ($entry && isset($entry->approved) && $entry->approved) {
            $approved = 'approved';
            $approval = 'disapprove';
            $approvedimagesrc = 'i/completion-auto-pass';
        } else {
            $approved = 'disapproved';
            $approval = 'approve';
            $approvedimagesrc = 'i/completion-auto-n';
        }
        $strapproved = get_string($approved, 'datalynx');
        if ($CFG->branch >= 33) {
            $approvedimage = html_writer::empty_tag('img',
                    array('src' => $OUTPUT->image_url($approvedimagesrc),
                        'class' => "iconsmall" . (isset($entry->approved) && $entry->approved ? ' approved' : ''),
                        'alt' => $strapproved, 'title' => $strapproved));
        } else {
            $approvedimage = html_writer::empty_tag('img',
                    array('src' => $OUTPUT->pix_url($approvedimagesrc),
                        'class' => "iconsmall" . (isset($entry->approved) && $entry->approved ? ' approved' : ''),
                        'alt' => $strapproved, 'title' => $strapproved));
        }

        if (has_capability('mod/datalynx:approve', $field->df()->context)) {
            if ($CFG->branch >= 33) {
                $PAGE->requires->js_call_amd('mod_datalynx/approve', 'init',
                        array($OUTPUT->image_url('i/completion-auto-pass')->__toString(),
                            $OUTPUT->image_url('i/completion-auto-n')->__toString()));
            } else {
                $$PAGE->requires->js_call_amd('mod_datalynx/approve', 'init',
                        array($OUTPUT->pix_url('i/completion-auto-pass')->__toString(),
                            $OUTPUT->pix_url('i/completion-auto-n')->__toString()));
            }

            return html_writer::link(
                    new moodle_url($entry->baseurl,
                            array($approval => $entry->id, 'sesskey' => sesskey(),
                                    'sourceview' => $this->_field->df()->get_current_view()->id()
                            )), $approvedimage, array('class' => 'datalynxfield__approve'));
        } else {
            return $approvedimage;
        }
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $cat = get_string('actions', 'datalynx');

        $patterns = array();
        $patterns["##approve##"] = array(true, $cat);

        return $patterns;
    }
}
