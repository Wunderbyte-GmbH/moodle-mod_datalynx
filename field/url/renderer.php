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
 * @subpackage url
 * @copyright 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield_url_renderer extends datalynxfield_renderer {

    /**
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;
        $url = isset($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : null;
        $alt = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : null;
        /*
        // TODO: Replace filepicker with normal text input fields.
        $mform->addElement('text', 'name', "test", "");
        $mform->setType('name', PARAM_URL);
        */
        $url = empty($url) ? 'http://' : $url;
        $usepicker = empty($field->field->param1) ? false : true;
        $displaylinktextfield = empty($field->field->param5) ? false : true;
        $options = array('title' => s($field->field->description), 'size' => 64);

        $group = array();
        $group[] = $mform->createElement('url', "{$fieldname}_url", null, $options,
                array('usefilepicker' => $usepicker));
        $mform->setType("{$fieldname}_url", PARAM_URL);
        $mform->setDefault("{$fieldname}_url", s($url));

        // Add alt name if not forcing name.
        if (empty($field->field->param2) && $displaylinktextfield) {
            $group[] = $mform->createElement('static', '', '',
                    get_string('linktext', 'datalynxfield_url'));
            $group[] = $mform->createElement('text', "{$fieldname}_alt",
                    get_string('linktext', 'datalynxfield_url'));
            $mform->setType("{$fieldname}_alt", PARAM_TEXT);
            $mform->setDefault("{$fieldname}_alt", s($alt));
        }

        $mform->addGroup($group, "{$fieldname}_grp", null, null, false);
    }

    /**
     */
    public function render_display_mode(stdClass $entry, array $params) {
        global $CFG;

        $field = $this->_field;
        $fieldid = $field->id();
        $types = array_intersect(['link', 'image', 'imageflex', 'media'
        ], array_keys($params));
        $type = isset($types[0]) ? $types[0] : '';

        // TODO: In fieldgroups this fails, why aren't these read from field->param3?
        $attributes = array('class' => $field->class, 'target' => $field->target);

        if (isset($entry->{"c{$fieldid}_content"})) {
            $url = $entry->{"c{$fieldid}_content"};
            if (empty($url) or ($url == 'http://')) {
                return '';
            }

            // Simple url text.
            if (empty($type)) {
                return $url;
            }

            // Param2 forces the text to something.
            if ($field->field->param2) {
                $linktext = s($field->field->param2);
            } else {
                $linktext = empty($entry->{"c{$fieldid}_content1"}) ? $url : $entry->{"c{$fieldid}_content1"};
            }

            // Linking.
            if ($type == 'link') {
                return html_writer::link($url, $linktext, $attributes);
            }

            // Image.
            if ($type == 'image') {
                return html_writer::empty_tag('img', array('src' => $url));
            }

            // Image flexible.
            if ($type == 'imageflex') {
                return html_writer::empty_tag('img', array('src' => $url, 'style' => 'width:100%'));
            }

            // Media.
            if ($type == 'media') {
                require_once("$CFG->dirroot/filter/mediaplugin/filter.php");
                $mpfilter = new filter_mediaplugin($field->df()->context, array());
                return $mpfilter->filter(html_writer::link($url, '', $attributes));
            }
        }

        return '';
    }

    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = array();
        $arr[] = &$mform->createElement('text', $fieldname, null, array('size' => '32'));
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');

        return array($arr, null);
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:link]]"] = array(false);
        $patterns["[[$fieldname:image]]"] = array(false);
        $patterns["[[$fieldname:imageflex]]"] = array(false);
        $patterns["[[$fieldname:media]]"] = array(false);

        return $patterns;
    }

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}_url";
        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() &&
                    (!isset($formdata->$formfieldname) || $formdata->$formfieldname === 'http://')
            ) {
                $errors["field_{$fieldid}_{$entryid}_grp"] = get_string('fieldrequired', 'datalynx');
            }
            // Validate that the input is a URL.
            $isurl = filter_var($formdata->$formfieldname, FILTER_VALIDATE_URL);
            $isdefault = $formdata->$formfieldname === 'http://';
            $isempty = $formdata->$formfieldname === '';
            if ($isurl or $isdefault or $isempty) {
                continue;
            } else {
                $errors["field_{$fieldid}_{$entryid}_grp"] = "Please enter a valid URL."; // TODO: Multilang.
            }
        }

        return $errors;
    }
}
