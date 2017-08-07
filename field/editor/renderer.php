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
 * @subpackage editor
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . "/../renderer.php");

/**
 * Class datalynxfield_editor_renderer Renderer for editor field type
 */
class datalynxfield_editor_renderer extends datalynxfield_renderer {

    /**
     * render the editor form for adding content to the editor field
     * TODO: improve editor rendering for including images from repositories
     *
     * @see datalynxfield_renderer::render_edit_mode()
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        // Editor.
        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        $data = new stdClass();
        $data->$fieldname = isset($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : '';
        $data->{"{$fieldname}trust"} = true;
        $required = !empty($options['required']);
        // Format.
        $data->{"{$fieldname}format"} = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : FORMAT_HTML;
        $data = file_prepare_standard_editor($data, $fieldname, $field->editor_options(),
                $field->df()->context, 'mod_datalynx', 'content', $contentid);
        $mform->addElement('editor', "{$fieldname}_editor", null, null, $field->editor_options());
        $mform->setDefault("{$fieldname}_editor", $data->{"{$fieldname}_editor"});
        if ($required) {
            $mform->addRule("{$fieldname}_editor", null, 'required', null, 'client');
        }
    }

    public function render_display_mode(stdClass $entry, array $params) {
        $field = $this->_field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $text = $entry->{"c{$fieldid}_content"};
            $format = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : FORMAT_HTML;

            $options = new stdClass();
            $options->para = false;
            $str = format_text($text, $format, $options);
            return $str;
        } else {
            return '';
        }
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

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() and isset($formdata->$formfieldname)) {
                if (!clean_param($formdata->$formfieldname, PARAM_NOTAGS)) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
