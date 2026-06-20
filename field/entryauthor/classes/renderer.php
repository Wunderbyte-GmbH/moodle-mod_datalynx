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
 * @package datalynxfield_entryauthor
 * @subpackage entryauthor
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @copyright 2013 onwards David Bogner, Michael Pollak
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_entryauthor;

use coding_exception;
use core_user\fields;
use dml_exception;
use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Renderer for the entryauthor field type.
 */
class renderer extends datalynxfield_renderer {
    /**
     * Return replacements for all ##author:something## patterns.
     *
     * @param ?array $tags
     * @param null $entry
     * @param ?array $options
     * @return array
     * @throws coding_exception
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        $field = $this->field;
        $edit = !empty($options['edit']) ? $options['edit'] : false;
        $dlxid = $field->dlx()->id();
        $replacements = [];

        // Dedicated inline user-profile-field editor / display (replaces the legacy userinfo field).
        if ($field->get('internalname') === 'profileeditor') {
            foreach ((array) $tags as $tag) {
                $editable = !empty($field->get('editable'));
                if ($edit && $editable && $this->profile_edit_allowed($entry)) {
                    $replacements[$tag] = ['', [[$this, 'display_edit_profile'], [$entry]]];
                } else {
                    $replacements[$tag] = ['html', $this->display_profile_value($entry)];
                }
            }
            return $replacements;
        }

        foreach ($tags as $tag) {
            $stripped = trim($tag, '@');
            // Plain ##author## tag — picture + linked fullname (matches fullnamewithpicturelink).
            if ($stripped === '##author##') {
                $replacements[$tag] = ['html', $this->display_namewithpicture($entry)];
                continue;
            }
            if (strpos($stripped, '##author:') === 0) {
                $suffix = substr($stripped, strlen('##author:'), -2);
            } else {
                continue;
            }

            // Check if format exists.
            $format = \mod_datalynx\local\field_format\manager::get_format_by_name($dlxid, $suffix);
            if ($format && $format->get_fieldtype() === 'entryauthor') {
                $displayfield = $format->get_setting('option', $suffix);
            } else {
                $displayfield = $suffix;
            }

            // Edit tag case.
            if ($displayfield === 'edit') {
                if ($edit && has_capability('mod/datalynx:manageentries', $field->dlx()->context)) {
                    $replacements[$tag] = ['', [[$this, 'display_edit'], [$entry]]];
                } else {
                    $replacements[$tag] = ['html', $this->display_name($entry)];
                }
                continue;
            }

            // Picture large case.
            if ($displayfield === 'picturelarge') {
                $replacements[$tag] = ['html', $this->display_picture($entry, true)];
                continue;
            }

            // Call display method if it exists.
            $method = "display_{$displayfield}";
            if (method_exists($this, $method)) {
                $replacements[$tag] = ['html', $this->$method($entry)];
            } else {
                // Fallback to user record field or custom user profile field.
                global $DB, $USER;
                $userid = ($entry->id < 0) ? $USER->id : ($entry->userid ?? 0);
                if ($userid > 0) {
                    $user = $DB->get_record('user', ['id' => $userid]);
                    if ($user) {
                        if (isset($user->{$displayfield})) {
                            $replacements[$tag] = ['html', s($user->{$displayfield})];
                            continue;
                        }
                        // Custom user profile field check.
                        $customfieldid = $DB->get_field('user_info_field', 'id', ['shortname' => $displayfield]);
                        if ($customfieldid) {
                            $customdata = $DB->get_field(
                                'user_info_data',
                                'data',
                                ['userid' => $userid, 'fieldid' => $customfieldid]
                            );
                            $replacements[$tag] = ['html', s($customdata !== false ? $customdata : '')];
                            continue;
                        }
                    }
                }
                $replacements[$tag] = '';
            }
        }

        return $replacements;
    }

    /**
     * Display a list of users to choose as entry author.
     * This allows to specify not the editing $USER but another user
     * as author of an entry.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param ?array $options
     * @throws coding_exception
     */
    public function display_edit(&$mform, $entry, ?array $options = null) {
        global $USER;
        if ($entry->id < 0) { // New entry.
            $entry->firstname = $USER->firstname;
            $entry->lastname = $USER->lastname;
            $entry->email = $USER->email;
            $entry->userid = $USER->id;
            $entry->institution = $USER->institution;
            $entry->department = $USER->department;
        }
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";
        $selected = $entry->userid;
        static $usersmenu = null;
        if (is_null($usersmenu)) {
            $users = get_users_by_capability(
                $field->dlx->context,
                'mod/datalynx:writeentry',
                'u.*',
                'u.lastname ASC'
            );
            // Add a supervisor's id.
            if (!in_array($entry->userid, array_keys($users))) {
                $user = new stdClass();
                $user->id = $entry->userid;
                $user->firstname = $entry->firstname;
                $user->lastname = $entry->lastname;
                $user->email = $entry->email;
                $user->institution = $entry->institution;
                $user->department = $entry->department;
                $users[$entry->userid] = $user;
            }
        }
        $usermenu = [];
        foreach ($users as $userid => $user) {
            $usermenu[$userid] = $user->lastname . ' ' . $user->firstname . ' (' . $user->email . ')';
        }
        $mform->addElement('html', '<div class="datalynx-no-fitem-wrapper">');
        $mform->addElement('select', $fieldname, null, $usermenu);
        $mform->setDefault($fieldname, $selected);
        $mform->addElement('html', '</div>');
    }

    /**
     * Display name.
     *
     * @param stdClass $entry The entry object.
     * @return string
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function display_name($entry) {
        global $USER, $DB;

        if ($entry->id < 0) { // New entry.
            $entry->firstname = $USER->firstname;
            $entry->lastname = $USER->lastname;
            $entry->userid = $USER->id;
        }
        $user = $DB->get_record('user', ['id' => $entry->userid]);
        $dlx = $this->field->dlx();
        return html_writer::link(
            new moodle_url(
                '/user/view.php',
                ['id' => $entry->userid, 'course' => $dlx->course->id]
            ),
            fullname($user)
        );
    }

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Display user picture + fullname linked to profile (matches reportbuilder fullnamewithpicturelink).
     *
     * @param stdClass $entry The entry object.
     * @return string HTML: avatar image + fullname, both wrapped in a profile link.
     */
    public function display_namewithpicture($entry) {
        global $OUTPUT, $USER, $DB;

        if ($entry->id < 0) { // New entry.
            $user = $USER;
        } else {
            $user = $DB->get_record('user', ['id' => $entry->userid]);
        }
        if (!$user) {
            return '';
        }
        $dlx = $this->field->dlx();
        $profileurl = new moodle_url('/user/view.php', ['id' => $user->id, 'course' => $dlx->course->id]);
        $picture = $OUTPUT->user_picture($user, ['link' => false, 'alttext' => false, 'courseid' => $dlx->course->id]);
        return html_writer::link($profileurl, $picture . fullname($user));
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Display firstname.
     *
     * @param stdClass $entry The entry object.
     * @return string firstname
     */
    public function display_firstname($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->firstname;
        } else {
            return $entry->firstname;
        }
    }

    /**
     * Display lastname.
     *
     * @param stdClass $entry The entry object.
     * @return string lastname
     */
    public function display_lastname($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->lastname;
        } else {
            return $entry->lastname;
        }
    }

    /**
     * Display username.
     *
     * @param stdClass $entry The entry object.
     * @return string username
     */
    public function display_username($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->username;
        } else {
            return $entry->username;
        }
    }

    /**
     * Display user id.
     *
     * @param stdClass $entry The entry object.
     * @return integer id
     */
    public function display_id($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->id;
        } else {
            return $entry->userid;
        }
    }

    /**
     * Display user idnumber (not user id!).
     *
     * @param stdClass $entry The entry object.
     * @return integer idnumber
     */
    public function display_idnumber($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->idnumber;
        } else {
            return $entry->idnumber;
        }
    }

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Display user picture.
     *
     * @param stdClass $entry The entry object.
     * @param bool $large
     * @return mixed
     */
    public function display_picture($entry, $large = false) {
        global $OUTPUT, $USER;

        if ($entry->id < 0) { // New entry.
            $user = $USER;
        } else {
            $user = new stdClass();
            $picturefields = fields::get_picture_fields();
            foreach ($picturefields as $userfield) {
                if (isset($entry->{$userfield})) {
                    $user->{$userfield} = $entry->{$userfield};
                } else {
                    $user->{$userfield} = "";
                }
            }
        }

        $pictureparams = ['courseid' => $this->field->dlx()->course->id];
        if ($large) {
            $pictureparams['size'] = 100;
        }
        return $OUTPUT->user_picture($user, $pictureparams);
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Display email.
     *
     * @param stdClass $entry The entry object.
     * @return string email
     */
    public function display_email($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->email;
        } else {
            return $entry->email;
        }
    }

    /**
     * Display the institution of user profile.
     *
     * @param stdClass $entry The entry object.
     * @return mixed
     */
    public function display_institution($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->institution;
        } else {
            return $entry->institution;
        }
    }

    /**
     * Display the department of user profile.
     *
     * @param stdClass $entry The entry object.
     * @return mixed
     */
    public function display_department($entry) {
        global $USER;

        if ($entry->id < 0) { // New entry.
            return $USER->department;
        } else {
            return $entry->department;
        }
    }

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Display all badges a user has earned in an entry view.
     *
     * @param stdClass $entry The entry object.
     * @return string
     */
    public function display_badges($entry) {
        global $USER, $PAGE;

        if ($entry->id < 0) { // New entry.
            $userid = $USER->id;
        } else {
            $userid = $entry->userid;
        }

        $output = $PAGE->get_renderer('core', 'badges');

        if ($badges = badges_get_user_badges($userid)) {
            return $output->print_badges_list($badges, $userid, true);
        }
        return '';
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Resolve the user whose profile is targeted by this profile-editor field for a given entry.
     *
     * @param stdClass $entry The entry object.
     * @return int The target user id (0 if unknown).
     */
    protected function get_target_userid($entry): int {
        global $USER;
        if (empty($entry) || (isset($entry->id) && $entry->id < 0)) {
            return (int) $USER->id;
        }
        return (int) ($entry->userid ?? 0);
    }

    /**
     * Whether the current user may edit the profile field of the entry author.
     *
     * @param stdClass $entry The entry object.
     * @return bool
     */
    protected function profile_edit_allowed($entry): bool {
        return $this->profile_edit_allowed_for_user($this->get_target_userid($entry));
    }

    /**
     * Whether the current user may edit the given user's profile, on a site level.
     *
     * @param int $userid The target (entry author) user id.
     * @return bool
     */
    protected function profile_edit_allowed_for_user($userid): bool {
        global $USER;
        if (empty($userid)) {
            return false;
        }
        if ((int) $userid === (int) $USER->id) {
            return has_capability('moodle/user:editownprofile', \context_system::instance());
        }
        return has_capability('moodle/user:editprofile', \context_user::instance($userid));
    }

    /**
     * Render the read-only value of the targeted user profile field for the entry author.
     *
     * @param stdClass $entry The entry object.
     * @return string
     */
    protected function display_profile_value($entry): string {
        global $CFG;
        require_once("$CFG->dirroot/user/profile/lib.php");

        $field = $this->field;
        $userid = $this->get_target_userid($entry);
        if (empty($userid)) {
            return '';
        }
        $userprofile = profile_user_record($userid);
        $shortname = $field->get('infoshortname');
        $content = ($shortname && isset($userprofile->{$shortname})) ? $userprofile->{$shortname} : '';
        if ($content === '' || $content === null) {
            return '';
        }

        switch ($field->get('infotype')) {
            case 'checkbox':
                $params = ['disabled' => 'disabled', 'type' => 'checkbox', 'name' => $field->name()];
                if (intval($content) === 1) {
                    $params['checked'] = 'checked';
                }
                return html_writer::empty_tag('input', $params);
            case 'datetime':
                $format = $field->get('param10')
                    ? get_string('strftimedaydatetime', 'langconfig')
                    : get_string('strftimedate', 'langconfig');
                return userdate($content, $format);
            case 'textarea':
                return format_text($content, FORMAT_HTML, ['overflowdiv' => true]);
            default:
                $options = new stdClass();
                $options->para = false;
                return format_text($content, FORMAT_MOODLE, $options);
        }
    }

    /**
     * Render the inline edit widget for the targeted user profile field.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param ?array $options Additional options.
     */
    public function display_edit_profile(&$mform, $entry, ?array $options = null) {
        global $CFG;
        require_once("$CFG->dirroot/user/profile/lib.php");

        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";

        $userid = $this->get_target_userid($entry);
        $userprofile = $userid ? profile_user_record($userid) : null;
        $shortname = $field->get('infoshortname');
        $content = ($userprofile && $shortname && isset($userprofile->{$shortname}))
            ? $userprofile->{$shortname} : '';

        $mform->addElement(
            'html',
            '<div class="datalynx-field-wrapper" data-field-type="entryauthor" data-field-name="' .
            s($field->name()) . '">'
        );

        switch ($field->get('infotype')) {
            case 'datetime':
                $fieldtype = $field->get('param10') ? 'date_time_selector' : 'date_selector';
                $mform->addElement($fieldtype, $fieldname, $shortname);
                break;
            case 'menu':
                $dropdown = explode("\n", (string) $field->get('param8'));
                $dropdown = array_combine($dropdown, $dropdown);
                $mform->addElement('select', $fieldname, $shortname, $dropdown);
                break;
            case 'checkbox':
                $mform->addElement('advcheckbox', $fieldname, $shortname);
                break;
            default:
                $mform->addElement('text', $fieldname, $shortname);
                $mform->setType($fieldname, PARAM_TEXT);
        }
        $mform->setDefault($fieldname, $content);

        if (!empty($field->get('mandatory'))) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }

        $mform->addElement('html', '</div>');
    }

    /**
     * Validate and persist an inline profile-field edit. The value is saved to the entry author's
     * user profile field (NOT to datalynx content), then removed from the form data.
     *
     * @param int|string $entryid The entry id (or composite fieldgroup id).
     * @param array $tags The tags handled by this field.
     * @param stdClass $formdata The submitted form data.
     * @return array Validation errors keyed by form element name.
     */
    public function validate($entryid, $tags, $formdata) {
        global $USER, $DB, $CFG;

        $field = $this->field;
        $errors = [];

        // Only the dedicated inline profile editor persists anything here.
        if ($field->get('internalname') !== 'profileeditor' || empty($field->get('editable'))) {
            return $errors;
        }

        $formfieldname = "field_{$field->id()}_{$entryid}";
        if (!property_exists($formdata, $formfieldname)) {
            return $errors;
        }
        $value = $formdata->{$formfieldname};

        // Resolve the entry author whose profile will be written.
        if (!is_numeric($entryid) || (int) $entryid < 0) {
            $userid = (int) $USER->id;
        } else {
            $entry = $DB->get_record('datalynx_entries', ['id' => $entryid], 'userid', IGNORE_MISSING);
            $userid = $entry ? (int) $entry->userid : 0;
        }

        // Enforce the site-level capability to edit this user's profile.
        if (!$userid || !$this->profile_edit_allowed_for_user($userid)) {
            unset($formdata->{$formfieldname});
            return $errors;
        }

        if (!empty($field->get('mandatory')) && ($value === '' || $value === null)) {
            $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
            return $errors;
        }

        require_once("$CFG->dirroot/user/profile/lib.php");
        $user = ['id' => $userid, "profile_field_{$field->get('infoshortname')}" => $value];
        profile_save_data((object) $user);

        // The value lives in the user profile, never in datalynx content.
        unset($formdata->{$formfieldname});

        return $errors;
    }

    /**
     * Array of patterns this field supports.
     *
     * @return array
     * @throws coding_exception
     */
    protected function patterns() {
        $fieldinternalname = $this->field->get('internalname');
        $cat = get_string('authorinfo', 'datalynx');
        $patterns = [];

        // A dedicated profile-editor pseudo-field exclusively owns its own format tag.
        if ($fieldinternalname === 'profileeditor') {
            return ["##author:{$this->field->name()}##" => [true, $cat]];
        }

        // Entryauthor tag ##author## is always available as the plain default (full name, no format applied).
        if ($fieldinternalname === 'name') {
            $patterns['##author##'] = [true, $cat];
        }

        $formats = \mod_datalynx\local\field_format\manager::get_formats_for_instance(
            $this->field->dlx()->id(),
            'entryauthor'
        );
        if (empty($formats)) {
            $patterns["##author:{$fieldinternalname}##"] = [true, $cat];
            if ($fieldinternalname === 'picture') {
                $patterns["##author:picturelarge##"] = [true, $cat];
            }
            return $patterns;
        }

        foreach ($formats as $format) {
            // Custom profile-field formats are owned by their dedicated profile-editor pseudo-field
            // (see field::get_field_objects), so the built-in author fields must not claim them too.
            if (method_exists($format, 'is_profile_editor') && $format->is_profile_editor()) {
                continue;
            }
            $name = $format->get_name();
            $option = $format->get_setting('option', $name);
            $ispictureformat = ($option === 'picture' || $option === 'picturelarge');
            $ispicturefield = ($fieldinternalname === 'picture');

            if ($ispictureformat) {
                if ($ispicturefield) {
                    $patterns["##author:{$name}##"] = [true, $cat];
                }
            } else {
                $exactfields = array_filter($this->field->dlx()->get_fields(), function ($f) use ($option) {
                    return $f->type === 'entryauthor' && $f->get('internalname') === $option;
                });
                if (!empty($exactfields)) {
                    if ($fieldinternalname === $option) {
                        $patterns["##author:{$name}##"] = [true, $cat];
                    }
                } else {
                    if ($fieldinternalname === 'name') {
                        $patterns["##author:{$name}##"] = [true, $cat];
                    }
                }
            }
        }

        return $patterns;
    }
}
