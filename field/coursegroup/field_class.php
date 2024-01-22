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
 * @subpackage coursegroup
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

/**
 *
 */
class datalynxfield_coursegroup extends datalynxfield_base {
    public $type = 'coursegroup';

    public $course;

    public $group;

    protected $_comparetext;

    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);

        $this->course = $this->field->param1;
        $this->group = $this->field->param2;
        $this->_comparetext = 'content1';
    }

    /**
     */
    protected function content_names() {
        return array('course', 'group', 'groupid');
    }

    /**
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = array();
        $contents = array();

        // New contents.
        $course = 0;
        $group = 0;
        $groupid = 0;
        if (!empty($values)) {
            foreach ($values as $name => $value) {
                if ($name) { // Update from form.
                    if (!empty($value)) {
                        ${$name} = $value;
                    }
                } else { // Update from import.
                    if (strpos($value, '##') !== false) {
                        $value = explode('##', $value);
                        $course = clean_param($value[0], PARAM_INT);
                        $group = clean_param($value[1], PARAM_INT);
                    } else {
                        $course = clean_param($value, PARAM_INT);
                    }
                    // There should be only one from import, so break.
                    break;
                }
            }
        }
        $group = $groupid;

        // Old contents.

        if (!$this->course) {
            if (!empty($course) || !empty($entry->{"c$fieldid" . '_content'})) {
                $contents[] = $course;
                $contents[] = $group;
                $oldcontents[] = isset($entry->{"c$fieldid" . '_content'}) ? $entry->{"c$fieldid" .
                '_content'} : null;
                $oldcontents[] = isset($entry->{"c$fieldid" . '_content1'}) ? $entry->{"c$fieldid" .
                '_content1'} : null;
            }
        } else {
            if (!$this->group && (!empty($group) || !empty($entry->{"c$fieldid" . '_content1'}))) {
                $contents[] = null;
                $contents[] = $group;
                $oldcontents[] = null;
                $oldcontents[] = isset($entry->{"c$fieldid" . '_content1'}) ? $entry->{"c$fieldid" .
                '_content1'} : null;
            }
        }

        return array($contents, $oldcontents);
    }

    /**
     */
    public function parse_search($formdata, $i) {
        $coursegroup = array(0, 0, 0);
        $search = 'f_' . $i . '_' . $this->field->id;

        if (!empty($formdata->{$search . '_member'})) {
            $coursegroup[0] = $formdata->{$search . '_member'};
        } else {
            if (!empty($formdata->{$search . '_course'})) {
                $coursegroup[1] = $formdata->{$search . '_course'};
            } else {
                if (!empty($formdata->{$search . '_group'})) {
                    $coursegroup[2] = $formdata->{$search . '_group'};
                } else {
                    return false;
                }
            }
        }

        return $coursegroup;
    }

    /**
     */
    public function get_content_parts() {
        return array('content', 'content1');
    }

    /**
     * Sort only by course
     */
    public function get_sort_sql() {
        return "c{$this->field->id}.content";
    }

    /**
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     */
    public function get_search_sql(array $search): array {
        global $DB, $CFG, $USER, $PAGE;

        list($not, $operator, $value) = $search;

        if (is_array($value)) {
            list($member, $course, $group) = $value;
        } else {
            $member = $course = $group = 0;
        }

        // For course and group use the parent get_search_sql.
        if ($course) {
            $this->_comparetext = 'content';
            return parent::get_search_sql(array($not, $operator, $course));
        } else {
            if ($group) {
                $this->_comparetext = 'content1';
                return parent::get_search_sql(array($not, $operator, $group));
            }
        }

        // So we need to filter by membership.
        require_once("$CFG->dirroot/user/lib.php");
        require_once("$CFG->libdir/enrollib.php");

        static $i = 0;
        $i++;
        $fieldid = $this->field->id;

        $varcharcontent = "c{$fieldid}.content1";

        // Set user id to filter on, from url if user profile page.
        $path = $PAGE->url->get_path();
        $isprofilepage = (strpos($path, '/user/view.php') !== false ||
                strpos($path, '/user/profile.php') !== false);
        if (!$isprofilepage || !$userid = optional_param('id', 0, PARAM_INT)) {
            $userid = $USER->id;
        }
        // Get user's groups.
        if (!$usergroups = $DB->get_records_menu('groups_members', array('userid' => $userid),
                'groupid', 'id,groupid')
        ) {
            // Not a member in any group so search for "groupid" -1 to retrieve no entries.
            $usergroups = array(-1);
        }
        list($ingroups, $groupids) = $DB->get_in_or_equal($usergroups, SQL_PARAMS_NAMED,
                "df_{$fieldid}_");
        return array(" $varcharcontent $ingroups ", $groupids, true);
    }

    /**
     */
    protected function get_sql_compare_text($column = 'content') {
        global $DB;

        $comparetext = $this->_comparetext;
        return $DB->sql_compare_text("c{$this->field->id}.$comparetext");
    }

    /**
     */
    public function format_search_value($searchparams) {
        list($not, $operator, $value) = $searchparams;
        list($member, $course, $group) = $value;
        if ($member) {
            return get_string('member', 'datalynxfield_coursegroup');
        }
        if ($course) {
            return get_string('course') . ' ' . $not . ' ' . $operator . ' ' . $course;
        }
        if ($group) {
            return get_string('group') . ' ' . $not . ' ' . $operator . ' ' . $group;
        }
    }

    /**
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            list($course, $group) = !empty($csvrecord[$csvname]) ? explode(' ',
                    $csvrecord[$csvname]) : array(0, 0);

            if ($course && $group) {
                $data->{"field_{$fieldid}_{$entryid}_course"} = $course;
                $data->{"field_{$fieldid}_{$entryid}_groupid"} = $group;
            }
        }

        return true;
    }
}

