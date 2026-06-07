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

        foreach ($tags as $tag) {
            $stripped = trim($tag, '@');
            if (strpos($stripped, '##author:') === 0) {
                $suffix = substr($stripped, strlen('##author:'), -2);
            } else {
                continue;
            }

            // Check if format exists.
            $format = \mod_datalynx\local\field_format\manager::get_format_by_name($dlxid, $suffix);
            if ($format && $format->get_fieldtype() === 'entryauthor') {
                $displayfield = $format->get_name();
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
                // Fallback to user record field.
                global $DB, $USER;
                $userid = ($entry->id < 0) ? $USER->id : ($entry->userid ?? 0);
                if ($userid > 0) {
                    $user = $DB->get_record('user', ['id' => $userid]);
                    if ($user && isset($user->{$displayfield})) {
                        $replacements[$tag] = ['html', s($user->{$displayfield})];
                        continue;
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
        $mform->addElement('select', $fieldname, null, $usermenu);
        $mform->setDefault($fieldname, $selected);
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
     * Array of patterns this field supports.
     *
     * @return array
     * @throws coding_exception
     */
    protected function patterns() {
        $fieldinternalname = $this->field->get('internalname');
        $cat = get_string('authorinfo', 'datalynx');
        $patterns = [];

        $formats = \mod_datalynx\local\field_format\manager::get_formats_for_instance(
            $this->field->dlx()->id(),
            'entryauthor'
        );
        if (empty($formats)) {
            $patterns["##author:{$fieldinternalname}##"] = [true, $cat];
            if ($fieldinternalname === 'name') {
                $patterns["##author:edit##"] = [true, $cat];
            }
            if ($fieldinternalname === 'picture') {
                $patterns["##author:picturelarge##"] = [true, $cat];
            }
            return $patterns;
        }

        foreach ($formats as $format) {
            $name = $format->get_name();
            $ispictureformat = ($name === 'picture' || $name === 'picturelarge');
            $ispicturefield = ($fieldinternalname === 'picture');

            if ($ispictureformat) {
                if ($ispicturefield) {
                    $patterns["##author:{$name}##"] = [true, $cat];
                }
            } else {
                $exactfields = array_filter($this->field->dlx()->get_fields(), function ($f) use ($name) {
                    return $f->type === 'entryauthor' && $f->get('internalname') === $name;
                });
                if (!empty($exactfields)) {
                    if ($fieldinternalname === $name) {
                        $patterns["##author:{$name}##"] = [true, $cat];
                        if ($name === 'name') {
                            $patterns["##author:edit##"] = [true, $cat];
                        }
                    }
                } else {
                    if ($fieldinternalname === 'name') {
                        $patterns["##author:{$name}##"] = [true, $cat];
                        if ($name === 'name') {
                            $patterns["##author:edit##"] = [true, $cat];
                        }
                    }
                }
            }
        }

        return $patterns;
    }
}
