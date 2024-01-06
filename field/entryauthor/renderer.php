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
 * @subpackage entryauthor
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @copyright 2013 onwards David Bogner, Michael Pollak
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_user\fields;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield_entryauthor_renderer extends datalynxfield_renderer {

    /**
     * Return replacements for all ##author:something## patterns.
     *
     * @param array|null $tags
     * @param null $entry
     * @param array|null $options
     * @return array
     * @throws coding_exception
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->get('internalname');
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        // No edit mode.
        $replacements = array();

        // Edit author name.
        if ($fieldname == 'name') {
            // Two tags are possible.
            foreach ($tags as $tag) {
                if (trim($tag, '@') == "##author:edit##" && $edit &&
                        has_capability('mod/datalynx:manageentries', $field->df()->context)
                ) {
                    $replacements[$tag] = array('',
                            array(array($this, 'display_edit'), array($entry)));
                } else {
                    $replacements[$tag] = array('html', $this->{"display_$fieldname"}($entry));
                }
            }

            // If not picture there is only one possible tag so no check.
        } else {
            if ($fieldname != 'picture') {
                $replacements["##author:{$fieldname}##@"] = array('html', $this->{"display_$fieldname"}($entry));
                $replacements["##author:{$fieldname}##"] = array('html', $this->{"display_$fieldname"}($entry));

                // For picture switch on $tags.
            } else {
                foreach ($tags as $tag) {
                    if (trim($tag, '@') == "##author:picturelarge##") {
                        $replacements[$tag] = array('html', $this->{"display_$fieldname"}($entry, true));
                    } else {
                        $replacements[$tag] = array('html', $this->{"display_$fieldname"}($entry));
                    }
                }
            }
        }

        return $replacements;
    }

    /**
     * Display a list of users to choose as entry author.
     * This allows to specify not the editing $USER but another user
     * as author of an entry.
     *
     * @param $mform
     * @param $entry
     * @param array|null $options
     * @throws coding_exception
     */
    public function display_edit(&$mform, $entry, array $options = null) {
        global $USER;
        if ($entry->id < 0) { // New entry.
            $entry->firstname = $USER->firstname;
            $entry->lastname = $USER->lastname;
            $entry->email = $USER->email;
            $entry->userid = $USER->id;
            $entry->institution = $USER->institution;
        }
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_{$entryid}";
        $selected = $entry->userid;
        static $usersmenu = null;
        if (is_null($usersmenu)) {
            $users = get_users_by_capability($field->df->context, 'mod/datalynx:writeentry', 'u.*',
                    'u.lastname ASC');
            // Add a supervisor's id.
            if (!in_array($entry->userid, array_keys($users))) {
                $user = new stdClass();
                $user->id = $entry->userid;
                $user->firstname = $entry->firstname;
                $user->lastname = $entry->lastname;
                $user->email = $entry->email;
                $user->institution = $entry->institution;
                $users[$entry->userid] = $user;
            }
        }
        $usermenu = array();
        foreach ($users as $userid => $user) {
            $usermenu[$userid] = $user->lastname . ' ' . $user->firstname . ' (' . $user->email . ')';
        }
        $mform->addElement('select', $fieldname, null, $usermenu);
        $mform->setDefault($fieldname, $selected);
    }

    /**
     * Display name.
     *
     * @param $entry
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
        $user = $DB->get_record('user', array('id' => $entry->userid));
        $df = $this->_field->df();
        return html_writer::link(
                new moodle_url('/user/view.php',
                        array('id' => $entry->userid, 'course' => $df->course->id)), fullname($user));
    }

    /**
     * Display firstname.
     *
     * @param $entry
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
     * @param $entry
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
     * @param $entry
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
     * @param $entry
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
     * @param $entry
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

    /**
     * Display user picture.
     *
     * @param $entry
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

        $pictureparams = array('courseid' => $this->_field->df()->course->id);
        if ($large) {
            $pictureparams['size'] = 100;
        }
        return $OUTPUT->user_picture($user, $pictureparams);
    }

    /**
     * Display email.
     *
     * @param $entry
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
     * @param $entry
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
     * Display all badges a user has earned in an entry view.
     *
     * @param $entry
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

    /**
     * Array of patterns this field supports.
     *
     * @return array
     * @throws coding_exception
     */
    protected function patterns() {
        $fieldinternalname = $this->_field->get('internalname');
        $cat = get_string('authorinfo', 'datalynx');

        $patterns = array();
        $patterns["##author:{$fieldinternalname}##"] = array(true, $cat);
        // For user name add edit tag.
        if ($fieldinternalname == 'name') {
            $patterns["##author:edit##"] = array(true, $cat);
        }
        // For user picture add the large picture.
        if ($fieldinternalname == 'picture') {
            $patterns["##author:picturelarge##"] = array(true, $cat);
        }

        return $patterns;
    }
}
