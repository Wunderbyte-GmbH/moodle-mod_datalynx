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
 * @package datalynxfield
 * @subpackage fieldgroup
 * @copyright 2018 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . "/../renderer.php");

/**
 * Class datalynxfield_fieldgroup_renderer Renderer for fieldgroup field type
 */
class datalynxfield_fieldgroup_renderer extends datalynxfield_renderer {

    public function render_display_mode(stdClass $entry, array $params) {
        global $CFG;
        // We want to display fields text1 and text2... Get this from field definition first.
        $fieldgroupfields = 'text1,text2';

        // Create display for every field.
        $displ = '';
        $array = explode(',', $fieldgroupfields);
        foreach($array as $field){
            $this->_field->field = $this->get_fieldgroup_from_name($field); // Attach subfield.
            $field = $this->_field;

            // TODO: Test with all field classes..
            $rendererclass = "datalynxfield_{$this->_field->field->type}_renderer";
            require_once("$CFG->dirroot/mod/datalynx/field/{$this->_field->field->type}/renderer.php");
            $fieldclass = new $rendererclass($field);

            $displ .= "<br>".$this->_field->field->name.": ";
            $displ .= $fieldclass->render_display_mode($entry, $params);
            $displ .= "<br>";
        }

        return $displ;

    }

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        global $CFG;

        $fieldgroupfields = 'text1,text2';
        $array = explode(',', $fieldgroupfields);
        foreach($array as $field)
        {
            $this->_field->field = $this->get_fieldgroup_from_name($field); // Attach subfield.
            $field = $this->_field;

            // TODO: Test with all field classes..
            $rendererclass = "datalynxfield_{$this->_field->field->type}_renderer";
            require_once("$CFG->dirroot/mod/datalynx/field/{$this->_field->field->type}/renderer.php");
            $fieldclass = new $rendererclass($field);

            // Add a static label.
            $mform->addElement('static', '', $this->_field->field->name . ": ");
            $fieldclass->render_edit_mode($mform, $entry, $options);

        }
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        return false; // TODO: Remove from search.
    }

    // TODO: What to validate?
    public function validate($entryid, $tags, $formdata) {
        return array();
    }

    /**
     * Fieldgroups should be shown in own group.
     */
    protected function patterns() {
        $cat = 'Fieldgroups'; // TODO: Multilang.
        $fieldname = $this->_field->name();

        $patterns = array();
        $patterns["[[$fieldname]]"] = array(true, $cat);

        return $patterns;
    }

    public static function get_fieldgroup_from_name($name) {
        global $DB;
        $record = $DB->get_record('datalynx_fields', array('name' => $name));
        return $record;
    }
}
