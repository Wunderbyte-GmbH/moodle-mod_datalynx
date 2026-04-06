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
 * @package datalynxfield_youtube
 * @subpackage youtube
 * @copyright 2021 michael pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_youtube;

use mod_datalynx\local\field\datalynxfield_renderer;
use stdClass;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();
/**
 * Class datalynxfield_youtube_renderer Renderer for text field type
 */
class renderer extends datalynxfield_renderer {
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
        $fieldname = "field_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);

        $content = '';
        if ($entryid > 0 && !empty($entry->{"c{$fieldid}_content"})) {
            $content = "https://www.youtube.com/watch?v=" . $entry->{"c{$fieldid}_content"};
        }
        $fieldattr = [];
        $fieldattr['size'] = 45;

        $mform->addElement('text', $fieldname, null, $fieldattr);
        $mform->addHelpButton($fieldname, 'youtubeurl', 'datalynxfield_youtube');
        // TODO: MDL-0000 Add help string.
        $mform->setType($fieldname, PARAM_TEXT);

        $mform->setDefault($fieldname, $content);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
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
        $content = $entry->{"c{$fieldid}_content"};

        if (isset($content)) {
            $width = $field->field->param1;
            $height = $field->field->param2;
            $allow = "encrypted-media";
            $src = "https://www.youtube-nocookie.com/embed/" . $content;
            $str = "<iframe width='$width' height='$height' src='$src' ";
            $str .= "frameborder='0' allow='$allow' allowfullscreen></iframe>";
            return $str;
        }
        return '';
    }

    /**
     * Validates the field.
     *
     * @param int $entryid The entry ID.
     * @param array $tags The tags to validate.
     * @param stdClass $formdata The form data.
     * @return array
     */
    public function validate($entryid, $tags, $formdata) {
        global $DB;

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
            // Check for valid url.
            $isurl = filter_var($formdata->$formfieldname, FILTER_VALIDATE_URL);
            if (!$isurl && $formdata->$formfieldname != '') {
                $errors[$formfieldname] = get_string('invalidurl', 'datalynx');
            }
        }

        return $errors;
    }
}
