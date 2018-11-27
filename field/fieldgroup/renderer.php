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

    /**
     * Fields that are included in the fieldgroup. Fieldid as key.
     * @var array
     */
    protected $subfields = array();

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_display_mode()
     */
    public function render_display_mode(stdClass $entry, array $params) {
        // We want to display these fields.
        $fieldgroupfields = $this->get_subfields();

        // Create display for every field.
        $displ = '';

        // Loop through showdefault.
        $showdefault = $this->_field->field->param3;
        for ($line = 0; $line < $showdefault; $line++) {
            $displ .= "</td></tr><tr><td>"; // TODO: How to get this in a template? Close current template and start new.

            foreach ($fieldgroupfields as $fieldid => $subfield) {
                $this->renderer_split_content($entry, $fieldid, $line);
                $displ .= "" . $subfield->field->name . ": "; // Needs to be automated here, no html.
                $displ .= $subfield->renderer()->render_display_mode($entry, $params);
                $displ .= "     ";
            }
        }
        return $displ;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_edit_mode()
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        // We want to display these fields.
        $fieldgroupfields = $this->get_subfields();

        // Loop through showdefault.
        $showdefault = $this->_field->field->param3;
        for ($line = 0; $line < $showdefault; $line++) {

            $mform->addElement('html', '</td></tr><tr><td>'); // Fix this table thing. TODO: Get rid of this table and use css.

            foreach ($fieldgroupfields as $fieldid => $subfield) {
                $this->renderer_split_content($entry, $fieldid, $line);

                // Add a static label.
                $mform->addElement('static', '', $subfield->field->name . ": ");
                $tempentryid = $entry->id;
                $entry->id = $entry->id . "_" . $line; // Add iterator to fieldname.
                $subfield->renderer()->render_edit_mode($mform, $entry, $options);

                // Restore entryid to prior state.
                $entry->id = $tempentryid;
            }
        }
    }

    /**
     * Get fields of the fielgroup as an array of fieldobjects where key = fieldid.
     *
     * @return array
     */
    public function get_subfields() {
        if (empty($this->subfields)) {
            // We want to display these fields.
            $fieldids = $this->_field->fieldids;
            foreach ($fieldids as $fieldid) {
                $field = $this->_field->df->get_field_from_id($fieldid);
                if ($field->for_use_in_fieldgroup()) {
                    $this->subfields[$fieldid] = $field;
                }
            }
        }
        return $this->subfields;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::render_search_mode()
     */
    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        return false; // TODO: Remove from search.
    }

    /**
     *  We call validation of subfields
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::validate()
     */
    public function validate($entryid, $tags, $formdata) {
        return array();
    }

    /**
     * Fieldgroups should be shown in own group.
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::patterns()
     */
    protected function patterns() {
        $cat = 'Fieldgroups'; // TODO: Multilang.
        $fieldname = $this->_field->name();

        $patterns = array();
        $patterns["[[$fieldname]]"] = array(true, $cat);

        return $patterns;
    }

    /**
     * We split the multiple contents for every line and pass only one content at a time to the subfields renderer.
     *
     * @param object $entry
     * @param number $subfieldid
     * @param number $line defines what line we want to pass here.
     */
    public static function renderer_split_content($entry, $subfieldid, $line) {
        // Retrieve only relevant part of content and hand it over.

        // Loop through all possible contents. content, content1, ... 4
        for ($i = 0; $i <= 4; $i++) {
            if ($i == 0) {
                $contentid = ''; // Content1 is actually content.
            } else {
                $contentid = $i;
            }

            if ( isset ( $entry->{"c{$subfieldid}_content{$contentid}_fieldgroup"}) ) {
                $tempcontent = $entry->{"c{$subfieldid}_content{$contentid}_fieldgroup"};
            } else {
                // If we have exactly one content, show this and leave the rest blank.
                if (isset( $entry->{"c{$subfieldid}_content{$contentid}"} )) {
                    $tempcontent = array( $entry->{"c{$subfieldid}_content{$contentid}"} );
                } else {
                    $tempcontent = array();
                }
            }

            // Don't touch content if it is not a fieldgroup.
            if (isset($tempcontent[$line])) {
                $entry->{"c{$subfieldid}_content{$contentid}"} = $tempcontent[$line];
            } else {
                $entry->{"c{$subfieldid}_content{$contentid}"} = "";
            }
        }
    }

}
