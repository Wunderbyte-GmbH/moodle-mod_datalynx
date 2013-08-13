<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package dataformfield
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/dataform/field/field_class.php");

class dataformfield_teammemberselect extends dataformfield_base {
    public $type = 'teammemberselect';

    const TEAMMEMBERSELECT_FORMAT_NEWLINE = 0;
    const TEAMMEMBERSELECT_FORMAT_SPACE = 1;
    const TEAMMEMBERSELECT_FORMAT_COMMA = 2;
    const TEAMMEMBERSELECT_FORMAT_COMMA_SPACE = 3;
    const TEAMMEMBERSELECT_FORMAT_UL = 4;

    public $teamsize;
    public $admissibleroles;
    public $notifyteam;
    public $listformat;

    public $separators;
    public $rules;

    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        global $DB;
        $this->teamsize = $this->field->param1;
        $this->admissibleroles = json_decode($this->field->param2, true);
        $this->notifyteam = $this->field->param3;
        $this->listformat = $this->field->param4;
        $this->separators = array(
                self::TEAMMEMBERSELECT_FORMAT_NEWLINE => get_string('listformat_newline', 'dataform'),
                self::TEAMMEMBERSELECT_FORMAT_SPACE => get_string('listformat_space', 'dataform'),
                self::TEAMMEMBERSELECT_FORMAT_COMMA => get_string('listformat_comma', 'dataform'),
                self::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE => get_string('listformat_commaspace', 'dataform'),
                self::TEAMMEMBERSELECT_FORMAT_UL => get_string('listformat_ul', 'dataform')
        );

        $query = "SELECT r.id, r.name
                    FROM {dataform_rules} r
                   WHERE r.dataid = :dataid
                     AND r.type LIKE :type";
        $this->rules = $DB->get_records_sql_menu($query, array('dataid' => $df->id(), 'type' => 'eventnotification'));
        $this->rules = array_merge(array(0 => '...'), $this->rules);
    }

    public function options_menu($addnoselection = false, $makelinks = false) {
        global $DB, $USER, $COURSE;

        list($insql, $params) = $DB->get_in_or_equal($this->admissibleroles, SQL_PARAMS_NAMED);
        $params['courseid'] = $COURSE->id;
        $params['userid'] = $USER->id;
        $query = "SELECT u.id, u.username, u.firstname, u.lastname, u.email
                    FROM {course} c
                    JOIN {context} ct ON c.id = ct.instanceid
                    JOIN {role_assignments} ra ON ct.id = ra.contextid
                    JOIN {user} u ON u.id = ra.userid
                   WHERE c.id = :courseid
                     AND ra.roleid $insql
                     AND u.id <> :userid
                ORDER BY u.lastname ASC, u.firstname ASC, u.email ASC";
        $results = $DB->get_records_sql($query, $params);

        $options = array();
        if ($addnoselection) {
            $options[0] = '...';
        }
        foreach ($results as $result) {
            if ($makelinks) {
                $baseurl = new moodle_url('/user/view.php', array('id' => $result->id, 'course' => $params['courseid']));
                $options[$result->id] = html_writer::link($baseurl, fullname($result));
            } else {
                $options[$result->id] = fullname($result) . " ({$result->email})";
            }
        }

        return $options;
    }

    /**
     *
     */
    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = array();
        $contents = array();

        // old contents
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        // parse values
        $selected = !empty(reset($values)) ? reset($values) : array();

        // new contents
        if (!empty($selected)) {
            $contents[] = json_encode($selected);
        }

        return array($contents, $oldcontents);
    }
}
