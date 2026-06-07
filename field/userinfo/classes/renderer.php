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
 * @package datalynxfield_userinfo
 * @subpackage userinfo
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_userinfo;

use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/user/profile/lib.php");

/**
 * Renderer for the userinfo field type.
 */
class renderer extends datalynxfield_renderer {
    /**
     * Renders the field in edit mode.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param ?array $options Additional options.
     */
    public function display_edit(&$mform, $entry, ?array $options = null) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        if ($entryid == "-1") {
            global $USER;
            $userid = $USER->id;
        } else {
            $userid = $entry->userid;
        }

        $targetshortname = $field->infoshortname;
        $targettype = $field->infotype;
        $targetparam8 = $field->param8;
        $targetparam10 = $field->param10;

        if (!empty($options['option'])) {
            global $DB;
            $customfield = $DB->get_record('user_info_field', ['shortname' => $options['option']]);
            if ($customfield) {
                $targetshortname = $customfield->shortname;
                $targettype = $customfield->datatype;
                $targetparam8 = $customfield->param1;
                $targetparam10 = $customfield->param3;

                if ($targetshortname !== $field->infoshortname) {
                    $fieldname .= "_" . $targetshortname;
                }
            }
        }

        // NOTE: If none is found default is shown.
        $userprofile = profile_user_record($userid);
        $content = isset($userprofile->{$targetshortname}) ? $userprofile->{$targetshortname} : '';

        $mform->addElement(
            'html',
            '<div class="datalynx-field-wrapper" data-field-type="' . $field->type .
            '" data-field-name="' . $field->name() . '">'
        );

        switch ($targettype) {
            case 'datetime':
                $fieldtype = 'date_selector';
                if ($targetparam10) {
                    $fieldtype = 'date_time_selector';
                }
                $mform->addElement($fieldtype, $fieldname, $targetshortname);
                break;
            case 'menu':
                $dropdown = explode("\n", $targetparam8);
                $dropdown = array_combine($dropdown, $dropdown); // Keys and values are the same.
                $mform->addElement('select', $fieldname, $targetshortname, $dropdown);
                break;
            case 'checkbox':
                $mform->addElement('advcheckbox', $fieldname, $targetshortname);
                break;
            default:
                $mform->addElement('text', $fieldname);
                $mform->setType($fieldname, PARAM_TEXT);
        }
        $mform->setDefault($fieldname, $content);

        // Add required.
        if ($targetshortname === $field->infoshortname && $field->mandatory) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }

        $mform->addElement('html', '</div>');
    }

    /**
     * Replaces tags with their respective content.
     *
     * @param ?array $tags The tags to replace.
     * @param ?stdClass $entry The entry object.
     * @param ?array $options Additional options.
     * @return array
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        global $USER;
        $field = $this->field;
        $fieldname = $field->name();
        $dlxid = $field->dlx()->id();
        $replacements = [];

        if (empty($tags)) {
            return $replacements;
        }

        foreach ($tags as $tag) {
            $stripped = trim($tag, '@');
            $suffix = '';
            if ($stripped === "##author:{$fieldname}##") {
                $suffix = '';
            } else if ($stripped === "[[{$fieldname}]]") {
                $suffix = '';
            } else if (strpos($stripped, "##{$fieldname}:") === 0) {
                $suffix = substr($stripped, strlen("##{$fieldname}:"), -2);
            } else if (strpos($stripped, "[[{$fieldname}:") === 0) {
                $suffix = substr($stripped, strlen("[[{$fieldname}:"), -2);
            } else {
                continue;
            }

            // Resolve the output option.
            $option = $field->infotype;
            if ($suffix !== '') {
                $format = \mod_datalynx\local\field_format\manager::get_format_by_name($dlxid, $suffix);
                if ($format && $format->get_fieldtype() === 'userinfo') {
                    $option = $format->get_setting('option', $suffix);
                } else {
                    $option = $suffix;
                }
            }

            $manageable = ($entry->id == -1)
                || (!empty($entry->userid) && $USER->id == $entry->userid)
                || has_capability('mod/datalynx:manageentries', $field->dlx()->context);
            $editable = $field->editable;
            $isediting = !empty($options['edit']) ? $options['edit'] : false;

            if ($isediting && $manageable && $editable) {
                $replacements[$tag] = ['', [[$this, 'display_edit'], [$entry, ['option' => $option, 'tag' => $tag]]]];
                continue;
            }

            switch ($option) {
                case 'checkbox':
                    $replacements[$tag] = ['html', $this->display_checkbox($entry)];
                    break;
                case 'datetime':
                    $replacements[$tag] = ['html', $this->display_datetime($entry)];
                    break;
                case 'menu':
                case 'text':
                    $replacements[$tag] = ['html', $this->display_text($entry)];
                    break;
                case 'textarea':
                case 'richtext':
                    $replacements[$tag] = ['html', $this->display_richtext($entry)];
                    break;
                default:
                    global $DB;
                    $customfield = null;
                    if (preg_match('/^[a-zA-Z0-9_]+$/', $option)) {
                        $customfield = $DB->get_record('user_info_field', ['shortname' => $option]);
                    }
                    if ($customfield) {
                        $val = $this->get_custom_field_value($entry, $customfield->shortname);
                        $replacements[$tag] = ['html', $this->render_custom_value($val, $customfield->datatype)];
                    } else {
                        $replacements[$tag] = '';
                    }
            }
        }

        return $replacements;
    }

    /**
     * Displays a checkbox for the user info.
     *
     * @param stdClass $entry The entry object.
     * @return string
     */
    protected function display_checkbox($entry) {
        $field = $this->field;
        $fieldid = $field->id();
        $fieldname = $field->name();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        } else {
            global $USER, $DB;
            $content = $DB->get_field(
                'user_info_data',
                'data',
                ['userid' => $USER->id, 'fieldid' => $field->infoid]
            );
        }

        $params = ['disabled' => "disabled", 'type' => "checkbox", 'name' => $fieldname];
        if (intval($content) === 1) {
            $params['checked'] = 'checked';
        }
        return html_writer::empty_tag('input', $params);
    }

    /**
     * Displays a date/time for the user info.
     *
     * @param stdClass $entry The entry object.
     * @return string
     */
    protected function display_datetime($entry) {
        $field = $this->field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        } else {
            global $USER, $DB;
            $content = $DB->get_field(
                'user_info_data',
                'data',
                ['userid' => $USER->id, 'fieldid' => $field->infoid]
            );
        }

        // Check if time was specified.
        if ($param10) {
            $format = get_string('strftimedaydatetime', 'langconfig');
        } else {
            $format = get_string('strftimedate', 'langconfig');
        }

        // Check if a date has been specified.
        if (!empty($content)) {
            return userdate($content, $format);
        }

        return '';
    }

    /**
     * Displays text for the user info.
     *
     * @param stdClass $entry The entry object.
     * @return string
     */
    protected function display_text($entry) {
        $field = $this->field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        } else {
            global $USER, $DB;
            $content = $DB->get_field(
                'user_info_data',
                'data',
                ['userid' => $USER->id, 'fieldid' => $field->infoid]
            );
        }

        if (!$content) {
            return '';
        }

        $options = new stdClass();
        $options->para = false;
        $format = FORMAT_MOODLE;
        if (!$str = format_text($content, $format, $options)) {
            return '';
        }

        // Are we creating a link?
        if (!empty($this->field->param9)) {
            // Define the target.
            if (!empty($this->field->param10)) {
                $attributes = ['target' => $this->field->param10];
            } else {
                $attributes = [];
            }

            // Create the link.
            $str = html_writer::link(
                str_replace('$$', urlencode($str), $this->field->param9),
                htmlspecialchars($this->field->content),
                $attributes
            );
        }

        return $str;
    }

    /**
     * Displays rich text for the user info.
     *
     * @param stdClass $entry The entry object.
     * @return string
     */
    protected function display_richtext($entry) {
        $field = $this->field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            if ($content = $entry->{"c{$fieldid}_content"}) {
                $format = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : FORMAT_PLAIN;
                return format_text($content, $format, ['overflowdiv' => true]);
            }
        } else {
            global $USER, $DB;
            $content = $DB->get_field(
                'user_info_data',
                'data',
                ['userid' => $USER->id, 'fieldid' => $field->infoid]
            );
            $format = FORMAT_PLAIN;
            return format_text($content, $format, ['overflowdiv' => true]);
        }

        return '';
    }

    /**
     * Return the patterns this renderer supports.
     *
     * @return array
     */
    protected function patterns() {
        $fieldname = $this->field->name();
        $cat = get_string('authorinfo', 'datalynx');

        $patterns = [];
        $patterns["##author:$fieldname##"] = [true, $cat];
        $patterns["[[$fieldname]]"] = [true, $cat];

        $formats = \mod_datalynx\local\field_format\manager::get_formats_for_instance(
            $this->field->dlx()->id(),
            'userinfo'
        );

        if (!empty($formats)) {
            foreach ($formats as $format) {
                $name = $format->get_name();
                $patterns["##{$fieldname}:{$name}##"] = [true, $cat];
                $patterns["[[{$fieldname}:{$name}]]"] = [true, $cat];
            }
        }

        return $patterns;
    }

    /**
     * Retrieve the value of a custom user profile field for the entry author.
     *
     * @param \stdClass $entry The entry record.
     * @param string    $shortname The shortname of the custom profile field.
     * @return string The field value, or an empty string if not found.
     */
    protected function get_custom_field_value($entry, $shortname) {
        global $DB, $USER;
        $field = $this->field;
        $fieldid = $field->id();

        if ($shortname === $field->infoshortname && isset($entry->{"c{$fieldid}_content"})) {
            return $entry->{"c{$fieldid}_content"};
        }

        $userid = ($entry->id < 0) ? $USER->id : ($entry->userid ?? 0);
        if ($userid > 0) {
            $value = $DB->get_field_sql(
                "SELECT d.data
                   FROM {user_info_data} d
                   JOIN {user_info_field} f ON f.id = d.fieldid
                  WHERE d.userid = :userid AND f.shortname = :shortname",
                ['userid' => $userid, 'shortname' => $shortname]
            );
            if ($value !== false) {
                return $value;
            }
        }
        return '';
    }

    /**
     * Render a custom user profile field value according to its data type.
     *
     * @param mixed  $content  The raw field content.
     * @param string $datatype The profile field data type (e.g. checkbox, datetime, menu).
     * @return string The rendered HTML or plain-text value.
     */
    protected function render_custom_value($content, $datatype) {
        switch ($datatype) {
            case 'checkbox':
                $params = ['disabled' => "disabled", 'type' => "checkbox"];
                if (intval($content) === 1) {
                    $params['checked'] = 'checked';
                }
                return html_writer::empty_tag('input', $params);

            case 'datetime':
                if (!empty($content)) {
                    $format = get_string('strftimedate', 'langconfig');
                    return userdate($content, $format);
                }
                return '';

            case 'menu':
            case 'text':
                if (!$content) {
                    return '';
                }
                $options = new stdClass();
                $options->para = false;
                return format_text($content, FORMAT_MOODLE, $options);

            case 'textarea':
            case 'richtext':
                if (!$content) {
                    return '';
                }
                return format_text($content, FORMAT_PLAIN, ['overflowdiv' => true]);

            default:
                return s($content);
        }
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

        $fieldid = $this->field->id();
        $errors = [];

        $defaultfieldname = "field_{$fieldid}_{$entryid}";
        $formfieldnames = [$defaultfieldname => $this->field->infoshortname];

        global $DB;
        $dlxid = $this->field->dlx()->id();
        foreach ($tags as $tag) {
            $stripped = trim($tag, '@');
            $suffix = '';
            if ($stripped === "##author:{$this->field->name()}##") {
                $suffix = '';
            } else if ($stripped === "[[{$this->field->name()}]]") {
                $suffix = '';
            } else if (strpos($stripped, "##{$this->field->name()}:") === 0) {
                $suffix = substr($stripped, strlen("##{$this->field->name()}:"), -2);
            } else if (strpos($stripped, "[[{$this->field->name()}:") === 0) {
                $suffix = substr($stripped, strlen("[[{$this->field->name()}:"), -2);
            } else {
                continue;
            }

            $option = $this->field->infotype;
            if ($suffix !== '') {
                $format = \mod_datalynx\local\field_format\manager::get_format_by_name($dlxid, $suffix);
                if ($format && $format->get_fieldtype() === 'userinfo') {
                    $option = $format->get_setting('option', $suffix);
                } else {
                    $option = $suffix;
                }
            }

            if (preg_match('/^[a-zA-Z0-9_]+$/', $option)) {
                $customfield = $DB->get_record('user_info_field', ['shortname' => $option]);
                if ($customfield) {
                    $formfieldname = "field_{$fieldid}_{$entryid}_{$option}";
                    $formfieldnames[$formfieldname] = $option;
                }
            }
        }

        if ($entryid == -1) {
            global $USER;
            $userid = $USER->id;
        } else {
            // TODO: MDL-0000 Find a way to get rid of this database call to find original author.
            $entry = $DB->get_record('datalynx_entries', ['id' => $entryid], 'userid', MUST_EXIST);
            $userid = $entry->userid;
        }

        foreach ($formfieldnames as $formfieldname => $shortname) {
            if (!property_exists($formdata, $formfieldname)) {
                continue;
            }

            $value = $formdata->{$formfieldname};

            // Check if required.
            if ($shortname === $this->field->infoshortname && $this->field->mandatory && $value == '') {
                $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
            } else {
                // Update.
                $user = [];
                $user["id"] = $userid;
                $user["profile_field_{$shortname}"] = $value;
                profile_save_data((object) $user);
                // We don't want these infos to be stored in the datalynx content table.
                unset($formdata->{$formfieldname});
            }
        }

        return $errors;
    }
}
