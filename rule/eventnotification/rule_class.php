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
 * @package datalynx_rule
 * @subpackage eventnotification
 * @copyright 2015 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . "/../rule_class.php");

class datalynx_rule_eventnotification extends datalynx_rule_base {

    const FROM_AUTHOR = 0;

    const FROM_CURRENT_USER = 1;

    const TO_AUTHOR = 1;

    const TO_USER = 2;

    const TO_ROLES = 4;

    const TO_ADMIN = 8;

    const TO_EMAIL = 16;

    public $type = 'eventnotification';

    protected $sender;

    protected $recipient;

    protected $targetviews;

    /**
     * Class constructor
     *
     * @param datalynx|int $df datalynx id or class object
     * @param stdClass|int $rule rule id or DB record
     */
    public function __construct($df = 0, $rule = 0) {
        parent::__construct($df, $rule);

        $this->sender = $this->rule->param2;
        $this->recipient = unserialize($this->rule->param3);
        $this->targetviews = unserialize($this->rule->param4);
    }

    /**
     *
     * @param \core\event\base $event
     * @return bool
     */
    private function checkteam(\core\event\base $event) {
        $teamid = $event->get_data()['other']['fieldid'];
        $triggers = unserialize($this->rule->param1);
        foreach ($triggers as $trigger) {
            if (strpos($trigger, "$teamid") !== false) {
                return true;
            }
        }
        return false;
    }

    public function trigger(\core\event\base $event) {
        global $CFG, $SITE, $DB, $USER;

        $messagedata = new stdClass();
        $eventname = (new \ReflectionClass($event))->getShortName();
        if (strpos($eventname, 'team') !== false) {
            $messagedata->fieldname = $DB->get_field('datalynx_fields', 'name',
                    array('id' => $event->get_data()['other']['fieldid']));
            if (!$this->checkteam($event)) {
                return false;
                // TODO: In else branch: combine added and removed members if notification sent to changed team.
            }
        }

        // Check if we only trigger on specific checkbox.
        if ($this->rule->param5) {

            // If so, test for conditions and stop sending if not met.
            $entryid = $entryid = $event->get_data()['objectid'];
            $fieldid = $this->rule->param5;
            $content = $DB->get_record('datalynx_contents', array('fieldid' => $fieldid, 'entryid' => $entryid));

            if (!$content) {
                return false;
            }
            // We assume the checkbox has only one option.
            if ($content->content != '#1#') {
                return false;
            }

        }

        $df = $this->df;
        $viewurl = "$CFG->wwwroot/mod/datalynx/view.php?d=" . $df->id();

        $datalynxname = $df->name() ? format_string($df->name(), true) : 'Unspecified datalynx';
        $coursename = $df->course->shortname ? format_string($df->course->shortname, true) : 'Unspecified datalynx';

        $messagedata->siteurl = $CFG->wwwroot;

        $messagedata->objectid = $event->get_data()['objectid'];

        $notename = get_string("messageprovider:event_$eventname", 'datalynx');

        $pluginname = get_string('pluginname', 'datalynx');
        $sitename = format_string($SITE->fullname);
        if (empty(trim($this->rule->param6))) {
            $subject = "$sitename -> $coursename -> $pluginname $datalynxname:  $notename";
        } else {
            $subject = format_string(trim($this->rule->param6));
        }
        if (strpos($eventname, 'comment') !== false) {
            $entryid = $event->get_data()['other']['itemid'];
            $commentid = $event->get_data()['objectid'];
            $messagedata->commenttext = $DB->get_field('comments', 'content', array('id' => $commentid));
        } else {
            $entryid = $event->get_data()['objectid'];
        }
        $authorid = $DB->get_field('datalynx_entries', 'userid', array('id' => $entryid));
        $author = $DB->get_record('user', array('id' => $authorid));

        $userfrom = (strpos($eventname, 'event') !== false &&
                $this->sender == self::FROM_AUTHOR) ? $author : $USER;
        $messagedata->senderprofilelink = html_writer::link(
                new moodle_url('/user/profile.php', array('id' => $userfrom->id)),
                fullname($userfrom));

        $messagestosend = array();
        foreach ($this->get_recipients($author->id, $entryid) as $userid) {
            // Prepare message object.
            $message = new \core\message\message();
            $message->component = 'mod_datalynx';
            $message->name = "event_$eventname";
            $message->subject = $subject;
            $message->fullmessageformat = 1;
            $message->smallmessage = '';
            $message->notification = 1;
            if ($CFG->branch > 31) {
                $message->courseid = $df->course->id;
            }
            $message->userfrom = $userfrom;
            $userto = $DB->get_record('user', array('id' => $userid));
            $message->userto = $userto;
            $messagedata->fullname = fullname($userto);

            $viewurlparams = ['eids' => $entryid];

            $roleids = $this->df()->get_user_datalynx_permissions($userid);
            foreach ($roleids as $roleid) {
                if (isset($this->targetviews[$roleid])) {
                    $viewurlparams['view'] = $this->targetviews[$roleid];
                    break;
                }
            }
            if (!isset($viewurlparams['view'])) {
                if ($df->data->singleview) {
                    $viewurlparams['view'] = $df->data->singleview;
                } else {
                    if ($df->data->defaultview) {
                        $viewurlparams['view'] = $df->data->defaultview;
                    }
                }
            }

            $entryurl = new moodle_url($viewurl, $viewurlparams);
            $messagedata->viewlink = html_writer::link($entryurl,
                    get_string('linktoentry', 'datalynx'));
            $messagedata->datalynxlink = html_writer::link(new moodle_url($viewurl), $datalynxname);

            $messagetext = get_string("message_$eventname", 'datalynx', $messagedata);
            $message->fullmessage = html_to_text($messagetext);
            $message->fullmessagehtml = text_to_html($messagetext, false, false, true);
            $messagestosend[] = $message;
        }
        if ($messagestosend) {
            $adhocktask = new \mod_datalynx\task\sendmessage_task();
            $adhocktask->set_custom_data_as_string(base64_encode(serialize($messagestosend)));
            $adhocktask->set_component('mod_datalynx');
            \core\task\manager::queue_adhoc_task($adhocktask);
        }
        return true;
    }

    /**
     * Get IDs of recipient users as defined by this rule
     *
     * @param int $authorid user ID of the entry author, if the rule is entry-related
     * @param int $entryid ID of the entry (if applicable)
     * @return array array of user IDs
     */
    private function get_recipients($authorid = 0, $entryid = 0) {
        $recipientids = array();
        if (isset($this->recipient['author']) && $authorid) {
            $recipientids[] = $authorid;
        }
        if (isset($this->recipient['roles'])) {
            $recipientids = array_merge($recipientids,
                    $this->get_recipients_by_permission($this->df->context,
                            $this->recipient['roles']));
        }
        if (isset($this->recipient['teams'])) {
            $recipientids = array_merge($recipientids,
                    $this->get_team_recipients($this->recipient['teams'], $entryid));
        }

        if (isset($this->recipient['specificuserid'])) {
            $recipientids[] = $this->recipient['specificuserid'];
        }

        return array_diff(array_unique($recipientids), [0]);
    }

    /**
     * Retrieves IDs of users that possess given permissions within the context.
     *
     * @param context $context
     * @param $permissions
     * @return array IDs of recipient users
     */
    protected function get_recipients_by_permission(context $context, $permissions) {
        global $DB;

        $allneeded = [];
        $allforbidden = [];

        $perms = [mod_datalynx\datalynx::PERMISSION_ADMIN => 'mod/datalynx:viewprivilegeadmin',
                mod_datalynx\datalynx::PERMISSION_MANAGER => 'mod/datalynx:viewprivilegemanager',
                mod_datalynx\datalynx::PERMISSION_TEACHER => 'mod/datalynx:viewprivilegeteacher',
                mod_datalynx\datalynx::PERMISSION_STUDENT => 'mod/datalynx:viewprivilegestudent',
                mod_datalynx\datalynx::PERMISSION_GUEST => 'mod/datalynx:viewprivilegeguest'
        ];

        foreach ($perms as $permissionid => $capstring) {
            if (in_array($permissionid, $permissions)) {
                list($needed, $forbidden) = get_roles_with_cap_in_context($context, $capstring);
                $allneeded = array_merge($allneeded, $needed);
                $allforbidden = array_merge($allforbidden, $forbidden);
            }
        }

        list($contextlist, $params1) = $DB->get_in_or_equal($context->get_parent_context_ids(true),
                SQL_PARAMS_NAMED);

        if ($allneeded) {
            list($insqlneeded, $params2) = $DB->get_in_or_equal($allneeded, SQL_PARAMS_NAMED);
            $sqlneeded = "SELECT DISTINCT ra.userid
                                 FROM {role_assignments} ra
                                WHERE ra.roleid $insqlneeded
                                  AND ra.contextid $contextlist";

            $users = $DB->get_fieldset_sql($sqlneeded, $params1 + $params2);
        } else {
            $users = [];
        }

        if ($allforbidden) {
            list($insqlforbidden, $params3) = $DB->get_in_or_equal($allforbidden, SQL_PARAMS_NAMED);
            $sqlforbidden = "SELECT DISTINCT ra.userid
                                    FROM {role_assignments} ra
                                   WHERE ra.roleid $insqlforbidden
                                     AND ra.contextid $contextlist";
            $forbiddenusers = $DB->get_fieldset_sql($sqlforbidden, $params1 + $params3);
        } else {
            $forbiddenusers = [];
        }

        return array_diff($users, $forbiddenusers);
    }

    /**
     * Compiles an array of IDs of users that should receive this notification based on team fields
     *
     * @param $teams
     * @param $entryid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_team_recipients($teams, $entryid = 0) {
        global $DB;
        $ids = array();
        if (empty($teams)) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($teams, SQL_PARAMS_NAMED);
        if ($entryid) {
            $entryidsql = "dc.entryid = :entryid";
            $params['entryid'] = $entryid;
        } else {
            $entryidsql = "1";
        }
        $sql = "SELECT dc.content
                  FROM {datalynx_contents} dc
            INNER JOIN {datalynx_fields} df ON dc.fieldid = df.id
                 WHERE dataid = :dataid
                   AND $entryidsql
                   AND df.id $insql";
        $params = array_merge($params, ['dataid' => $this->df->id()]);
        $contents = $DB->get_fieldset_sql($sql, $params);
        foreach ($contents as $content) {
            $ids = array_merge($ids, json_decode($content, true));
        }
        return array_unique($ids);
    }
}
