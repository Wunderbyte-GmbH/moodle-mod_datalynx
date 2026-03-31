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
 * @package datalynxfield_editor
 * @subpackage editor
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

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
        $field = $this->field;
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
        $data = file_prepare_standard_editor(
            $data,
            $fieldname,
            $field->editor_options(),
            $field->df()->context,
            'mod_datalynx',
            'content',
            $contentid
        );
        $mform->addElement('editor', "{$fieldname}_editor", null, null, $field->editor_options());
        $mform->setDefault("{$fieldname}_editor", $data->{"{$fieldname}_editor"});
        if ($required) {
            $mform->addRule("{$fieldname}_editor", null, 'required', null, 'client');
        }
    }

    /**
     * Render the editor field in display mode, rewriting file URLs.
     *
     * @param stdClass $entry The entry object.
     * @param array $options Rendering options including optional 'excerpt' key.
     * @return string The rendered HTML output.
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();
        $excerpt = in_array('excerpt', array_keys($options)) ? true : false;
        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        if (isset($entry->{"c{$fieldid}_content"})) {
            $text = $entry->{"c{$fieldid}_content"};
            $format = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : FORMAT_HTML;
            $options = new stdClass();
            $options->para = false;
            $text = file_rewrite_pluginfile_urls(
                $text,
                'pluginfile.php',
                $field->df()->context->id,
                'mod_datalynx',
                'content',
                $contentid
            );
            $str = format_text($text, $format, $options);
            if ($excerpt) {
                $str = strip_tags($str, '<p><i><b><strong>');
                $str = substr($str, 0, 500);
                $str = str_replace("&nbsp;", ' ', $str);
                $str = trim($str);
            }
            return $str;
        } else {
            return '';
        }
    }

    /**
     * Render the editor field in search mode.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param int $i The filter index.
     * @param string $value The current search value.
     * @return array Array of elements.
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

    /**
     * Returns the list of patterns supported by this field.
     *
     * @return array field patterns
     */
    protected function patterns() {
        $fieldname = $this->field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = [true];
        $patterns["[[$fieldname:excerpt]]"] = [true];
        return $patterns;
    }

    /**
     * Validate the editor field.
     *
     * @param int $entryid The entry ID.
     * @param array $tags The tags to validate.
     * @param stdClass $formdata The submitted form data.
     * @return array Array of error messages.
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        $errors = [];
        foreach ($tags as $tag) {
            [, $behavior, ] = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() && isset($formdata->$formfieldname)) {
                if (!clean_param($formdata->$formfieldname, PARAM_NOTAGS)) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                }
            }
        }

        return $errors;
    }
}
