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
        // We want to display these fields.
        $fieldgroupfields = explode(',', $this->_field->field->param1);

        // Create display for every field.
        $displ = '';

        // Loop through showdefault.
        $showdefault = $this->_field->field->param3;
        for ($x = 0; $x < $showdefault; $x++) {
            $displ .= "</td></tr><tr><td>";

            foreach ($fieldgroupfields as $fieldname) {
                $subfield = clone $this->_field; // Clone fields so we don't mess with references.
                $subfield->field = $this->get_fieldgroup_from_name($fieldname); // Attach subfield.

                // E.g. for urls we need specific properties, how to fix this for all types?
                $params = $this->fieldspecific($subfield, $params);

                // TODO: Test with all field classes..
                $rendererclass = "datalynxfield_{$subfield->field->type}_renderer";
                require_once("$CFG->dirroot/mod/datalynx/field/{$subfield->field->type}/renderer.php");
                $fieldclass = new $rendererclass($subfield);

                // Retrieve only relevant part of content and hand it over.
                // TODO: This throws errors when _fieldgroup is not set.
                if (isset($entry->{"c{$subfield->field->id}_content_fieldgroup"})) {
                    $contentarray = $entry->{"c{$subfield->field->id}_content_fieldgroup"};
                } else {
                    // If we have exactly one content, show this and leave the rest blank.
                    if (isset( $entry->{"c{$subfield->field->id}_content"} )) {
                        $contentarray = array( $entry->{"c{$subfield->field->id}_content"} );
                    } else {
                        $contentarray = array();
                    }
                }

                if (isset($contentarray[$x])) {
                    $entry->{"c{$subfield->field->id}_content"} = $contentarray[$x];
                } else {
                    $entry->{"c{$subfield->field->id}_content"} = "";
                }

                $displ .= "" . $subfield->field->name . ": "; // Needs to be automated here, no html.
                $displ .= $fieldclass->render_display_mode($entry, $params);
                $displ .= "     ";
            }
        }
        return $displ;
    }

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        global $CFG;
        // We want to display these fields.
        $fieldgroupfields = explode(',', $this->_field->field->param1);

        // Loop through showdefault.
        $showdefault = $this->_field->field->param3;
        for ($x = 0; $x < $showdefault; $x++) {

            $mform->addElement('html', '</td></tr><tr><td>'); // Fix this table thing. TODO: Get rid of this table and use css.

            foreach ($fieldgroupfields as $fieldname) {
                $subfield = clone $this->_field; // Clone fields so we don't mess with references.
                $subfield->field = $this->get_fieldgroup_from_name($fieldname); // Attach subfield.

                // TODO: Test with all field classes..
                $rendererclass = "datalynxfield_{$subfield->field->type}_renderer";
                require_once("$CFG->dirroot/mod/datalynx/field/{$subfield->field->type}/renderer.php");
                $fieldclass = new $rendererclass($subfield);

                // Retrieve only relevant part of content and hand it over.
                if ( isset ( $entry->{"c{$subfield->field->id}_content_fieldgroup"}) ) {
                    $tempcontent = $entry->{"c{$subfield->field->id}_content_fieldgroup"};
                } else {
                    // If we have exactly one content, show this and leave the rest blank.
                    if (isset( $entry->{"c{$subfield->field->id}_content"} )) {
                        $tempcontent = array( $entry->{"c{$subfield->field->id}_content"} );
                    } else {
                        $tempcontent = array();
                    }
                }
                $tempentryid = $entry->id;

                // Don't touch content if it is not a fieldgroup.
                if (isset($tempcontent[$x])) {
                    $entry->{"c{$subfield->field->id}_content"} = $tempcontent[$x];
                } else {
                    $entry->{"c{$subfield->field->id}_content"} = "";
                }

                // Add a static label.
                $mform->addElement('static', '', $subfield->field->name . ": ");
                $entry->id = $entry->id . "_" . $x; // Add iterator to fieldname.
                $fieldclass->render_edit_mode($mform, $entry, $options);

                // Restore entryid to prior state.
                $entry->id = $tempentryid;
            }
        }
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        return false; // TODO: Remove from search.
    }

    // We call validation of subfields.
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

    public static function fieldspecific($subfield, $params) {
        switch ($subfield->field->type) {
            case 'url':
                // Urls call property class and target.
                $subfield->class = $subfield->field->param3;
                $subfield->target = $subfield->field->param4;
                // Urls do use a parameter here.
                $params['link'] = true;
                break;
        }
        // Return the possible unchanged params.
        return $params;
    }
}
