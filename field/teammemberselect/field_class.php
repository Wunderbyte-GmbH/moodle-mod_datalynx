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
 * @package datalynxfield
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/datalynx/field/field_class.php");

class datalynxfield_teammemberselect extends datalynxfield_base {
    public $type = 'teammemberselect';

    const TEAMMEMBERSELECT_FORMAT_NEWLINE = 0;
    const TEAMMEMBERSELECT_FORMAT_SPACE = 1;
    const TEAMMEMBERSELECT_FORMAT_COMMA = 2;
    const TEAMMEMBERSELECT_FORMAT_COMMA_SPACE = 3;
    const TEAMMEMBERSELECT_FORMAT_UL = 4;

    public $teamsize;
    public $admissibleroles;
    public $minteamsize;
    public $listformat;
    public $teamfield;
    public $referencefieldid;
    public $notifyteammembers;
    public $usercanaddself;

    public $separators;
    public $rules;

    public function __construct($df = 0, $field = 0) {
        parent::__construct($df, $field);
        global $DB;
        $this->teamsize = $this->field->param1;
        $this->admissibleroles = json_decode($this->field->param2, true);
        $this->minteamsize = $this->field->param3;
        $this->listformat = $this->field->param4;
        $this->teamfield = $this->field->param5 != 0;
        $this->referencefieldid = $this->field->param5;
        $this->notifyteammembers = $this->field->param6 != 0;
        $this->usercanaddself = $this->field->param7 != 0;
        $this->separators = array(
                self::TEAMMEMBERSELECT_FORMAT_NEWLINE => get_string('listformat_newline', 'datalynx'),
                self::TEAMMEMBERSELECT_FORMAT_SPACE => get_string('listformat_space', 'datalynx'),
                self::TEAMMEMBERSELECT_FORMAT_COMMA => get_string('listformat_comma', 'datalynx'),
                self::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE => get_string('listformat_commaspace', 'datalynx'),
                self::TEAMMEMBERSELECT_FORMAT_UL => get_string('listformat_ul', 'datalynx')
        );

        $query = "SELECT r.id, r.name
                    FROM {datalynx_rules} r
                   WHERE r.dataid = :dataid
                     AND r.type LIKE :type";
        $this->rules = $DB->get_records_sql_menu($query, array('dataid' => $df->id(), 'type' => 'eventnotification'));
        $this->rules = array_merge(array(0 => '...'), $this->rules);
    }

    protected static $allusers = array();
    protected static $allowedusers = array();
    protected static $alluserslinks = array();
    protected static $alloweduserslinks = array();

    protected function init_user_menu() {
        global $DB, $COURSE;

        $context = context_course::instance($COURSE->id);
        $query = "SELECT DISTINCT CONCAT(u.id, '-', ra.roleid) AS mainid, u.*, ra.roleid
                    FROM {role_assignments} ra
              INNER JOIN {user} u ON u.id = ra.userid
                   WHERE ra.contextid = :contextid
                ORDER BY u.lastname ASC, u.firstname ASC, u.email ASC, u.username ASC";
        $results = $DB->get_records_sql($query, array('contextid' => $context->id));

        $fieldid = $this->field->id;
        self::$allusers[$fieldid] = array();
        self::$alluserslinks[$fieldid] = array();
        self::$allowedusers[$fieldid] = array();
        self::$alloweduserslinks[$fieldid] = array();

        foreach ($results as $result) {
            self::$allusers[$fieldid][$result->id] = fullname($result) . " ({$result->email})";

            $baseurl = new moodle_url('/user/view.php', array('id' => $result->id, 'course' => $COURSE->id));
            self::$alluserslinks[$fieldid][$result->id] = html_writer::link($baseurl, fullname($result));

            if (array_search($result->roleid, $this->admissibleroles) !== false) {
                self::$allowedusers[$fieldid][$result->id] = self::$allusers[$fieldid][$result->id];
                self::$alloweduserslinks[$fieldid][$result->id] = self::$alluserslinks[$fieldid][$result->id];
            }
        }
    }

    public function get_teamfield() {
        global $DB;

        $query = "SELECT *
                    FROM {datalynx_fields} df
                   WHERE df.dataid = :dataid
                     AND df.type LIKE 'teammemberselect'
                     AND df.param5 IS NOT NULL
                     AND df.param5 <> 0";

        return $DB->get_record_sql($query, array('dataid' => $this->df->id()));
    }

    /**
     * Update a field in the database
     */
    public function update_field($fromform = null) {
        global $DB, $OUTPUT;
        if (!empty($fromform)) {
            $this->set_field($fromform);
        }

        if (!$DB->update_record('datalynx_fields', $this->field)) {
            echo $OUTPUT->notification('updating of field failed!');
            return false;
        }
        return true;
    }

    public function options_menu($addnoselection = false, $makelinks = false, $excludeuser = 0, $allowall = false) {
        $fieldid = $this->field->id;
        if (!isset(self::$allusers[$fieldid])) {
            $this->init_user_menu();
        }
        $options = array();
        if ($addnoselection) {
            $options[0] = '...';
        }

        $options += $makelinks ?
                    ($allowall ? self::$alluserslinks[$fieldid] : self::$alloweduserslinks[$fieldid]) :
                    ($allowall ? self::$allusers[$fieldid] : self::$allowedusers[$fieldid]);

        if ($excludeuser && isset($options[$excludeuser])) {
            unset($options[$excludeuser]);
        }

        return $options;
    }

    /**
     *
     */
    public function get_search_sql($search) {
        global $DB, $USER;
        static $i = 0;
        list($not, $operator, $value) = $search;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";
        $params = array();

        $content = "c{$fieldid}.content";
        $paramname = "{$name}_user";

        if ($operator == 'USER') {
            global $USER;
            $like = $DB->sql_like($content, ":{$paramname}");
            $params[$paramname] = "%\"{$USER->id}\"%";
            return array(" $not $like", $params, true);
        } else if ($operator == 'OTHER_USER') {
            $like = $DB->sql_like($content, ":{$paramname}");
            $params[$paramname] = "%\"{$value}\"%";
            return array(" $not $like", $params, true);
        } else {
           return array(" ", $params);
        }
    }

    /**
     *
     */
    public function parse_search($formdata, $i) {
        global $USER;
        $fieldid = $this->field->id;
        $operator = !empty($formdata->{"searchoperator{$i}"}) ? $formdata->{"searchoperator{$i}"} : '';
        $fieldvalue = !empty($formdata->{"f_{$i}_$fieldid"}) ? $formdata->{"f_{$i}_$fieldid"} : false;
        if ($operator == 'USER') {
            return $USER->id;
        } else if ($operator == 'OTHER_USER') {
            return $fieldvalue;
        } else {
            return false;
        }
    }

    /**
     *
     */
    protected function format_content($entry, array $values = array()) {
        $fieldid = $this->field->id;
        $oldcontents = array();
        $contents = array();

        // old contents
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        // parse values
        $first = reset($values);
        $selected = !empty($first) ? $first : array();

        // new contents
        if (!empty($selected)) {
            $contents[] = json_encode($selected);
        }

        return array($contents, $oldcontents);
    }

    public function get_supported_search_operators() {
        return array(
            ''     => '&lt;' . get_string('choose') . '&gt;',
            'USER' => get_string('iamteammember', 'datalynx'),
            'OTHER_USER' => get_string('useristeammember', 'datalynx')
        );
    }

    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->id();
        $fieldname = $this->name();

        $formfieldname = "field_{$fieldid}_{$entryid}";
        if (array_key_exists("[[*$fieldname]]", $tags) and isset($formdata->$formfieldname)) {
            $numvalues = 0;
            foreach ($formdata->$formfieldname as $value) {
                if ($value != 0) {
                    $numvalues++;
                }
            }
            if ($numvalues < $this->minteamsize) {
                return array("{$formfieldname}_dropdown_grp" =>
                             get_string('minteamsize_error_form', 'datalynx', $this->minteamsize));
            }
        }
        return array();
    }

    public function supports_group_by() {
        return false;
    }
}
