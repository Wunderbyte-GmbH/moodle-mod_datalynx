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
defined('MOODLE_INTERNAL') or die();

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

    protected static $allusers = array();

    protected static $allowedusers = array();

    protected static $alluserslinks = array();

    protected static $alloweduserslinks = array();

    protected static $alluserids = array();

    protected static $forbiddenuserids = array();

    protected static $admissibility = ['needed' => [], 'forbidden' => []];

    /**
     * Can this field be used in fieldgroups?
     * @var boolean
     */
    protected $forfieldgroup = true;

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
            self::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE => get_string('listformat_commaspace',
                    'datalynx'),
            self::TEAMMEMBERSELECT_FORMAT_UL => get_string('listformat_ul', 'datalynx'));

        $query = "SELECT r.id, r.name
                    FROM {datalynx_rules} r
                   WHERE r.dataid = :dataid
                     AND r.type LIKE :type";
        $this->rules = $DB->get_records_sql_menu($query,
                array('dataid' => $df->id(), 'type' => 'eventnotification'));
    }

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
            // If user was already checked and was marked as forbidden, skip checking any other
            // roles they might have.
            if (in_array($result->id, self::$forbiddenuserids[$fieldid])) {
                continue;
            }

            // If this is the first time user is checked, add them to the all user list.
            if (!in_array($result->id, self::$alluserids[$fieldid])) {
                $fullname = fullname($result);
                self::$allusers[$fieldid][$result->id] = "$fullname ({$result->email})";

                $baseurl->param('id', $result->id);
                self::$alluserslinks[$fieldid][$result->id] = "<a href=\"$baseurl\">$fullname</a>";

                self::$alluserids[$fieldid][] = $result->id;
            }

            // If user has a forbidden role, remove them from admissible users (if present) and mark
            // them as forbidden.
            if (in_array($result->roleid, self::$admissibility['forbidden'])) {
                self::$forbiddenuserids[$fieldid][] = $result->id;
                unset(self::$allowedusers[$fieldid][$result->id]);
                unset(self::$alloweduserslinks[$fieldid][$result->id]);

                // Otherwise, if user has a needed role, add them to admissible users.
            } else {
                if (in_array($result->roleid, self::$admissibility['needed'])) {
                    self::$allowedusers[$fieldid][$result->id] = self::$allusers[$fieldid][$result->id];
                    self::$alloweduserslinks[$fieldid][$result->id] = self::$alluserslinks[$fieldid][$result->id];
                }
            }
        }
    }

    protected function get_admissibility_for_roles($context) {
        $allneeded = [];
        $allforbidden = [];

        $perms = [mod_datalynx\datalynx::PERMISSION_ADMIN => 'mod/datalynx:viewprivilegeadmin',
            mod_datalynx\datalynx::PERMISSION_MANAGER => 'mod/datalynx:viewprivilegemanager',
            mod_datalynx\datalynx::PERMISSION_TEACHER => 'mod/datalynx:viewprivilegeteacher',
            mod_datalynx\datalynx::PERMISSION_STUDENT => 'mod/datalynx:viewprivilegestudent',
            mod_datalynx\datalynx::PERMISSION_GUEST => 'mod/datalynx:viewprivilegeguest'];

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
     * Update a teammemberselectfield when editing an entry and notify teammembers of changes
     */
    public function update_content($entry, array $values = null) {
        $newcontentid = parent::update_content($entry, $values);

        // TODO: All this is only to notify team members. Check if we really need this here.
        global $DB;
        $fieldid = $this->field->id;

        // Read oldcontent from passed entry, not from DB query.
        $oldcontent = array();
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontent = json_decode($entry->{"c{$fieldid}_content"}, true);
        }

        $first = reset($values);
        $newcontent = !empty($first) ? $first : array();

        if (!empty($newcontent)) {
            if (isset($newcontent[0]) && $newcontent[0] == -999) {
                array_shift($newcontent); // Remove Dummy value.
            }
        }

        $field = $DB->get_record('datalynx_fields', array('id' => $this->field->id));
        $this->notify_team_members($entry, $field, $oldcontent, $newcontent);

        return $newcontentid;
    }

    /**
     *
     * {@inheritdoc}
     * @see datalynxfield_base::prepare_import_content()
     */
    public function prepare_import_content(&$data, $importsettings, $csvrecord = null, $entryid = null) {
        // Import only from csv.
        if ($csvrecord) {
            $fieldid = $this->field->id;
            $fieldname = $this->name();
            $csvname = $importsettings[$fieldname]['name'];
            // Teammembers are stored in the csv as <li><a href elements.
            $htmlcontainsuserids = !empty($csvrecord[$csvname]) ? $csvrecord[$csvname] : null;
            if ($htmlcontainsuserids) {
                $doc = new DOMDocument();
                $doc->loadHTML("<html><body>" . $htmlcontainsuserids . "</body></html>");
                $userids = array();
                // Loop through all <a href elements and add userids.
                foreach ($doc->getElementsByTagName('a') as $element) {
                    $href = $element->getAttribute('href');
                    if (($pos = strpos($href, "id=")) !== false) {
                        $userids[] = trim(substr($href, $pos + 3), " \t\n\r\0\x0B\"");
                    }
                }
                $data->{"field_{$fieldid}_{$entryid}"} = $userids;
            }
        }
        return true;
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

    public function options_menu($addnoselection = false, $makelinks = false, $excludeuser = 0,
            $allowall = false) {
        $fieldid = $this->field->id;
        if (!isset(self::$allusers[$fieldid])) {
            $this->init_user_menu();
        }

        $options = array();
        $options += array(-999 => null); // NULL to "not" show in lists.

        if ($makelinks) {
            if ($allowall) {
                $options += self::$alluserslinks[$fieldid];
            } else {
                $options += self::$alloweduserslinks[$fieldid];
            }
        } else {
            if ($allowall) {
                $options += self::$allusers[$fieldid];
            } else {
                $options += self::$allowedusers[$fieldid];
            }
        }

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
                    list($notinids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                            "df_{$fieldid}_x_", false);
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
        } else {
            // Customfilter adds ANY_OF instead of OTHER_USER.
            if ($operator === 'OTHER_USER' || $operator === 'ANY_OF') {

                $params[$name] = "%\"{$value}\"%";

                if (!!$not) {
                    $like = $DB->sql_like("content", ":{$name}", true, true);

                    if ($eids = $this->get_entry_ids_for_content($like, $params)) {
                        list($notinids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                                "df_{$fieldid}_x_", false);
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
            } else {
                if ($operator === '') {
                    // This is the "empty" operator.
                    $usecontent = false;
                    $sqlnot = $DB->sql_like("content", ":{$name}_hascontent");
                    $params["{$name}_hascontent"] = "%";

                    if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) { // There are
                                                                                      // non-empty.
                                                                                      // Contents.
                        list($contentids, $paramsnot) = $DB->get_in_or_equal($eids, SQL_PARAMS_NAMED,
                                "df_{$fieldid}_x_", !!$not);
                        $params = array_merge($params, $paramsnot);
                        $sql = " (e.id $contentids) ";
                    } else { // There are no non-empty contents.
                        if ($not) {
                            $sql = " 0 ";
                        } else {
                            $sql = " 1 ";
                        }
                    }
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
        } else {
            if ($operator == 'OTHER_USER') {
                return $fieldvalue;
            } else {
                return false;
            }
        }
    }

    protected function format_content($entry, array $values = null) {
        $fieldid = $this->field->id;
        $oldcontents = array();
        $contents = array();

        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        // Parse values.
        $first = reset($values);
        $selected = !empty($first) ? $first : array();

        if (!empty($selected)) {
            // Remove Dummy value.
            if (isset($selected[0]) && $selected[0] == -999) {
                array_shift($selected);
            }
        }

        $contents[] = json_encode($selected); // Empty values are kept.
        return array($contents, $oldcontents);
    }

    public function get_supported_search_operators() {
        return array('' => get_string('empty', 'datalynx'),
            'USER' => get_string('iamteammember', 'datalynx'),
            'OTHER_USER' => get_string('useristeammember', 'datalynx'));
    }

    public function supports_group_by() {
        return false;
    }

    /**
     * Trigger events to notify the team members when new members were
     * added to the field "teammemeberselect" in a specific entry
     *
     * @param object $entry
     * @param object $field
     * @param array $oldmembers
     * @param array $newmembers
     */
    public function notify_team_members($entry, $field, $oldmembers, $newmembers) {
        global $DB;

        $oldmembers = !empty($oldmembers) ? array_filter($oldmembers) : array();
        $newmembers = array_filter($newmembers);

        $addedmemberids = array_diff($newmembers, $oldmembers);
        $removedmemberids = array_diff($oldmembers, $newmembers);

        if (!empty($addedmemberids)) {
            list($insql, $params) = $DB->get_in_or_equal($addedmemberids);
            $addedmembers = $DB->get_records_sql("SELECT * FROM {user} WHERE id $insql", $params);
        } else {
            $addedmembers = array();
        }

        if (!empty($removedmemberids)) {
            list($insql, $params) = $DB->get_in_or_equal($removedmemberids);
            $removedmembers = $DB->get_records_sql("SELECT * FROM {user} WHERE id $insql", $params);
        } else {
            $removedmembers = array();
        }

        $other = ['dataid' => $field->dataid, 'fieldid' => $field->id, 'name' => $field->name,
            'addedmembers' => json_encode($addedmembers),
            'removedmembers' => json_encode($removedmembers)];

        if (!empty($addedmembers)) {
            $event = \mod_datalynx\event\team_updated::create(
                    array('context' => $this->df->context, 'objectid' => $entry->id,
                        'other' => $other));
            $event->trigger();
        }

        if (!empty($removedmembers)) {
            $event = \mod_datalynx\event\team_updated::create(
                    array('context' => $this->df->context, 'objectid' => $entry->id,
                        'other' => $other));
            $event->trigger();
        }
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     *
     * @return bool
     */
    public static function is_customfilterfield() {
        return true;
    }

    /**
     * Is $value a valid content or do we see an empty input?
     * @return bool
     */
    public static function is_fieldvalue_empty($value) {

        if (empty($value)) {
            return true;
        }

        // Backwards compatible, we added -999 to pass empty values.
        if (count($value) === 1 && isset($value[0]) && $value[0] == -999) {
            return true;
        }

        return false;
    }
}
