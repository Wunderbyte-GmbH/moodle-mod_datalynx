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
 * @package datalynxfield
 * @subpackage identifier
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 *
 */
class datalynxfield_identifier_renderer extends datalynxfield_renderer {

    /**
     *
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = '';
        if ($entryid > 0 and !empty($entry->{"c{$fieldid}_content"})){
            $content = $entry->{"c{$fieldid}_content"};
        }

        // Include reference to field in entry form only when there is no content
        // so as to generate once
        if (empty($content)) {
            $fieldname = "field_{$fieldid}_{$entryid}";
            $mform->addElement('hidden', $fieldname, 1);
            $mform->setType($fieldname, PARAM_NOTAGS);
        }
    }

    /**
     *
     */
    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();

        $content = '';
        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = strtoupper($entry->{"c{$fieldid}_content"});
        }

        return $content;
    }

}
