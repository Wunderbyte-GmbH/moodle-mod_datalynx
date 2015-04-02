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
    public $allowunsubscription;

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
        $this->allowunsubscription = $this->field->param8 != 0;
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

    protected static $alluserids = array();
    protected static $forbiddenuserids = array();
    protected static $admissibility = ['needed' => [], 'forbidden' => []];

    protected function init_user_menu() {
        global $DB, $COURSE;

        $context = context_course::instance($COURSE->id);

        $fieldid = $this->field->id;
        self::$allusers[$fieldid] = array();
        self::$alluserslinks[$fieldid] = array();
        self::$allowedusers[$fieldid] = array();
        self::$alloweduserslinks[$fieldid] = array();

        self::$alluserids[$fieldid] = array();
        self::$forbiddenuserids[$fieldid] = array();

        self::$admissibility = $this->get_admissibility_for_roles($context);


        $query = "SELECT DISTINCT CONCAT(u.id, '-', ra.roleid) AS mainid, u.*, ra.roleid
                    FROM {role_assignments} ra
              INNER JOIN {user} u ON u.id = ra.userid
                   WHERE ra.contextid = :contextid
                ORDER BY u.lastname ASC, u.firstname ASC, u.email ASC, u.username ASC";

        $results = $DB->get_records_sql($query, array('contextid' => $context->id));

        $baseurl = new moodle_url('/user/view.php', array('course' => $COURSE->id));

        foreach ($results as $result) {
            // if user was already checked and was marked as forbidden, skip checking any other roles they might have
            if (in_array($result->id, self::$forbiddenuserids[$fieldid])) {
                continue;
            }

            // if this is the first time user is checked, add them to the all user list
            if (!in_array($result->id, self::$alluserids[$fieldid])) {
                $fullname = fullname($result);
                self::$allusers[$fieldid][$result->id] = "$fullname ({$result->email})";

                $baseurl->param('id', $result->id);
                self::$alluserslinks[$fieldid][$result->id] = "<a href=\"$baseurl\">$fullname</a>";

                self::$alluserids[$fieldid][] = $result->id;
            }

            // if user has a forbidden role, remove them from admissible users (if present) and mark them as forbidden
            if (in_array($result->roleid, self::$admissibility['forbidden'])) {
                self::$forbiddenuserids[$fieldid][] = $result->id;
                unset(self::$allowedusers[$fieldid][$result->id]);
                unset(self::$alloweduserslinks[$fieldid][$result->id]);

                // otherwise, if user has a needed role, add them to admissible users
            } else if (in_array($result->roleid, self::$admissibility['needed'])) {
                self::$allowedusers[$fieldid][$result->id] = self::$allusers[$fieldid][$result->id];
                self::$alloweduserslinks[$fieldid][$result->id] = self::$alluserslinks[$fieldid][$result->id];
            }
        }
    }

    protected function get_admissibility_for_roles($context) {
        $allneeded = [];
        $allforbidden = [];

        $perms = [datalynx::PERMISSION_ADMIN => 'mod/datalynx:viewprivilegeadmin',
            datalynx::PERMISSION_MANAGER => 'mod/datalynx:viewprivilegemanager',
            datalynx::PERMISSION_TEACHER => 'mod/datalynx:viewprivilegeteacher',
            datalynx::PERMISSION_STUDENT => 'mod/datalynx:viewprivilegestudent',
            datalynx::PERMISSION_GUEST => 'mod/datalynx:viewprivilegeguest'];

        foreach ($perms as $permissionid => $capstring) {
            if (in_array($permissionid, $this->admissibleroles)) {
                list($needed, $forbidden) = get_roles_with_cap_in_context($context, $capstring);
                $allneeded = array_merge($allneeded, $needed);
                $allforbidden = array_merge($allforbidden, $forbidden);
            }
        }
        return ['needed' => array_unique($allneeded), 'forbidden' => array_unique($allforbidden)];
    }

    public function get_teamfield() {
        global $DB;

        $query = "SELECT *
                    FROM {datalynx_fields} df
                   WHERE df.dataid = :dataid
                     AND df.type LIKE 'teammemberselect'
                     AND df.param5 IS NOT NULL
                     AND df.param5 <> '0'";

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

    public function get_search_sql($search) {
        global $DB;
        static $i = 0;
        list($not, $operator, $value) = $search;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        $sql = "1";
        $params = array();
        $usecontent = false;

        $content = "c{$fieldid}.content";
        if ($operator === 'USER') {
            global $USER;
            $params[$name] = "%\"{$USER->id}\"%";

            if (!!$not) {
                $like = $DB->sql_like("content", ":{$name}", true, true);

                if ($eids = $this->get_entry_ids_for_content($like, $params)) {
                    list($notinids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, "df_{$fieldid}_x_", false);
                    $params = array_merge($params, $paramsnot);
                    $sql = " (e.id $notinids)";
                } else {
                    $sql = " 0 ";
                }

                $usecontent = false;
            } else {
                $sql = $DB->sql_like("c{$fieldid}.content", ":{$name}", true, true);
                $usecontent = true;
            }
        } else if ($operator === 'OTHER_USER') {
            $params[$name] = "%\"{$value}\"%";

            if (!!$not) {
                $like = $DB->sql_like("content", ":{$name}", true, true);

                if ($eids = $this->get_entry_ids_for_content($like, $params)) {
                    list($notinids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, "df_{$fieldid}_x_", false);
                    $params = array_merge($params, $paramsnot);
                    $sql = " (e.id $notinids) ";
                } else {
                    $sql = " 0 ";
                }

                $usecontent = false;
            } else {
                $sql = $DB->sql_like("c{$fieldid}.content", ":{$name}", true, true);
                $usecontent = true;
            }
        } else if ($operator === '') {
            $usecontent = false;
            $sqlnot = $DB->sql_like("content", ":{$name}_hascontent");
            $params["{$name}_hascontent"] = "%";

            if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) { // there are non-empty contents
                list($contentids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED, "df_{$fieldid}_x_", !!$not);
                $params = array_merge($params, $paramsnot);
                $sql = " (e.id $contentids) ";
            } else { // there are no non-empty contents
                if ($not) {
                    $sql = " 0 ";
                } else {
                    $sql = " 1 ";
                }
            }
        }

        return array($sql, $params, $usecontent);
    }

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

        if (!empty($selected)) {
            foreach ($selected as $userid) {
                if ($userid != "0") {
                    $contents[] = json_encode($selected);
                    break;
                }
            }
        }

        return array($contents, $oldcontents);
    }

    public function get_supported_search_operators() {
        return array(
            ''     => get_string('empty', 'datalynx'),
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
