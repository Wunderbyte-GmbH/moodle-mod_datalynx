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
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");


/**
 * Renderer class for teammemberselect datalynx field
 */
class datalynxfield_teammemberselect_renderer extends datalynxfield_renderer {

    public function render_display_mode(stdClass $entry, array $options): string {
        global $USER, $PAGE;

        // Variable $field datalynxfield_teammemberselect.
        $field = $this->_field;
        $fieldid = $field->id();
        $str = '';

        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = json_decode($entry->{"c{$fieldid}_content"}, true);
            $selected = $selected ? $selected : [];

            $str = $this->get_user_list((int) $field->df()->course->id, $selected);

            switch ($field->listformat) {
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_NEWLINE:
                    $str = implode('<br>', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_SPACE:
                    $str = implode(' ', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_COMMA:
                    $str = implode(',', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE:
                    $str = implode(', ', $str);
                    break;
                case datalynxfield_teammemberselect::TEAMMEMBERSELECT_FORMAT_UL:
                default:
                    if (count($str) > 0) {
                        $str = '<ul class="team-member-list"><li>' . implode('</li><li>', $str) . '</li></ul>';
                    } else {
                        $str = '';
                    }
                    break;
            }
        }

        $subscribeenabled = isset($options['subscribe']);
        $selected = isset($entry->{"c{$fieldid}_content"}) ? json_decode(
                $entry->{"c{$fieldid}_content"}, true) : [];
        $selected = $selected ? $selected : []; // TODO: Seems obsolete.
        $teamfull = $field->teamsize < count($selected);
        $hasadmissiblerole = array_intersect(
                $field->df()->get_user_datalynx_permissions($USER->id), $field->admissibleroles);
        $userismember = in_array($USER->id, $selected);
        $canunsubscribe = $this->_field->allowunsubscription;

        if ($subscribeenabled && $hasadmissiblerole && (!$teamfull || $userismember) &&
                (!$userismember || $canunsubscribe)) {

            $str .= html_writer::link(
                    new moodle_url('/mod/datalynx/view.php',
                            array('d' => $field->df()->id(), 'fieldid' => $fieldid,
                                'entryid' => $entry->id,
                                'userid' => $USER->id,
                                'action' => $userismember ? 'unsubscribe' : 'subscribe',
                                )),
                    get_string($userismember ? 'unsubscribe' : 'subscribe', 'datalynx'),
                    array(
                        'class' => 'datalynxfield_subscribe' . ($userismember ? ' subscribed' : '')));

            $userurl = new moodle_url('/user/view.php',
                    array('course' => $field->df()->course->id, 'id' => $USER->id));

            // Load JS.
            $PAGE->requires->js_call_amd('mod_datalynx/teammemberselect', 'init',
                    array($field->df()->id(), $fieldid, $userurl->out(false), fullname($USER), $canunsubscribe));
        }

        return $str;
    }

    private static $userlist = [];

    /**
     * @param int $courseid
     * @param array $userids
     * @return array
     */
    private function get_user_list(int $courseid, array $userids): array {
        global $DB;

        $list = [];
        $notpresent = [];
        foreach ($userids as $userid) {
            if (!$userid) {
                continue;
            } else {
                if (isset(self::$userlist[$userid])) {
                    $list[] = self::$userlist[$userid];
                } else {
                    $notpresent[] = $userid;
                }
            }
        }

        if (!empty($notpresent)) {
            $baseurl = new moodle_url('/user/view.php', array('course' => $courseid));
            list($insql, $params) = $DB->get_in_or_equal($notpresent);
            $sql = "SELECT * FROM {user} WHERE id $insql";
            $users = $DB->get_records_sql($sql, $params);
            foreach ($users as $user) {
                $baseurl->param('id', $user->id);
                $fullname = fullname($user);
                $item = "<a href=\"$baseurl\">$fullname</a>";
                self::$userlist[$user->id] = $item;
                $list[] = $item;
            }
        }

        return $list;
    }

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options = null) {
        global $USER, $PAGE;

        // Variable $field datalynxfield_teammemberselect.
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $classname = "teammemberselect_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);

        // If we edit an existing entry that is not required we need a workaround.
        $newentry = optional_param('new', null, PARAM_INT) === null ? 1 : 0;
        if (!$newentry && !$required) {
            $PAGE->requires->js_amd_inline("
            require(['jquery'], function($) {
                $('option[value=\"-999\"]').removeAttr('selected');
            });");
        }

        // We create a hidden field to force sending. Needs to be done via directly inserting
        // html.
        if (!$required) {
            $mform->addElement('html',
                    '<input type="hidden" name="' . $fieldname . '[-1]" value="-999">');
        }
        $selected = !empty($entry->{"c{$fieldid}_content"}) ? json_decode(
                $entry->{"c{$fieldid}_content"}, true) : array();
        $authorid = isset($entry->userid) ? $entry->userid : $USER->id;
        $menu = $field->options_menu(true, false, $field->usercanaddself ? 0 : $authorid);

        $mform->addElement('autocomplete', $fieldname, null, $menu,
                array('class' => "datalynxfield_teammemberselect $classname", 'multiple' => true,
                    'noselectionstring' => "Gerade keine Auswahl."));
        $mform->setType($fieldname, PARAM_INT);
        $mform->setDefault($fieldname, $selected); // Not value after validation fails.

        if ($required) {
            $mform->addRule($fieldname, get_string('fieldrequired', 'datalynx'), 'required', null, 'client');
        }
    }

    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $field = $this->_field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";
        $menu = array(-1 => '') + $field->options_menu();
        $options = array('multiple' => true);

        $elements = array();
        $elements[] = $mform->createElement('autocomplete', $fieldname, null, $menu, $options);
        $mform->setType($fieldname, PARAM_INT);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator{$i}", 'eq', '');
        $mform->disabledIf($fieldname, "searchoperator{$i}", 'eq', 'USER');

        return array($elements, null);
    }

    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:subscribe]]"] = array(true);

        return $patterns;
    }

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        // In case we see a fieldgroup validate inputs only if line was visible to the user.
        if (!is_numeric($entryid)) {
            $linenumber = explode("_", $entryid)[3];
            $lastvisible = "fieldgroup_" . explode("_", $entryid)[2] . "_lastvisible";
            $lastvisibleline = $formdata->$lastvisible;
            if ($linenumber >= $lastvisibleline) {
                return array();
            }
        }

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required()) {
                $userfound = false;
                if (isset($formdata->$formfieldname)) {
                    foreach ($formdata->$formfieldname as $userid) {
                        if ($userid != 0) {
                            $userfound = true;
                            break;
                        }
                    }
                }
                if (!$userfound) {
                    $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
                }
            }
            if (isset($formdata->$formfieldname)) {
                // Get rid of Dummy value -999 to correct calculations.
                if (isset($formdata->{$formfieldname}[0]) && $formdata->{$formfieldname}[0] == -999) {
                    array_shift($formdata->{$formfieldname});
                }

                // Limit chosen users to max teamsize and ensure min teamsize users are chosen!
                $teamsize = count($formdata->$formfieldname);
                if ($teamsize > $this->_field->teamsize) {
                    $errors[$formfieldname] = get_string('maxteamsizeerrorform', 'datalynx',
                            $this->_field->teamsize);
                }
                if ($teamsize < $this->_field->minteamsize) {
                    $errors[$formfieldname] = get_string('minteamsizeerrorform', 'datalynx',
                            $this->_field->minteamsize);
                }
            }
        }
        return $errors;
    }
}
