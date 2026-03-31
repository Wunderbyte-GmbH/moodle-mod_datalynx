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
 * @package datalynxfield_identifier
 * @subpackage identifier
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 * Renderer for the identifier field type.
 *
 * @package datalynxfield_identifier
 */
class datalynxfield_identifier_renderer extends datalynxfield_renderer {
    /**
     * Renders the field in edit mode.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param array $options Additional options.
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = '';
        if ($entryid > 0 && !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        }

        // Include reference to field in entry form only when there is no content.
        // So as to generate once.
        if (empty($content)) {
            $fieldname = "field_{$fieldid}_{$entryid}";
            $mform->addElement('hidden', $fieldname, 1);
            $mform->setType($fieldname, PARAM_NOTAGS);
        }
    }

    /**
     * Renders the field in display mode.
     *
     * @param stdClass $entry The entry object.
     * @param array $options Additional options.
     * @return string
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();

        $content = '';
        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = strtoupper($entry->{"c{$fieldid}_content"});
        }

        return $content;
    }

    /**
     * Renders the field in search mode.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param int $i The index of the search field.
     * @param string $value The current search value.
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $fieldid = $this->field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = [];
        $arr[] = &$mform->createElement('text', $fieldname, null, ['size' => '32']);
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');

        return [$arr, null];
    }
}
