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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.
 
/**
 * @package datalynx_rule
 * @subpackage eventnotification
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->dirroot/mod/datalynx/rule/rule_class.php");

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

    /**
     * Class constructor
     *
     * @param var $df       datalynx id or class object
     * @param var $rule    rule id or DB record
     */
    public function __construct($df = 0, $rule = 0) {
        parent::__construct($df, $rule);

        $this->sender = $this->rule->param2;
        $this->recipient = unserialize($this->rule->param3);
    }

    public function trigger(\core\event\base $event) {
        global $CFG, $SITE, $DB;

        $data = new stdClass();

        $df = new datalynx($event->other['dataid']);
        $eventname = (new \ReflectionClass($event))->getShortName();

        $data->datalynxs = get_string('modulenameplural', 'datalynx');
        $data->datalynx = get_string('modulename', 'datalynx');
        $data->activity = format_string($df->name(), true);
        $data->url = "$CFG->wwwroot/mod/datalynx/view.php?d=" . $df->id();

        // Prepare message
        $strdatalynx = get_string('pluginname', 'datalynx');
        $sitename = format_string($SITE->fullname);
        $data->siteurl = $CFG->wwwroot;
        $data->coursename = !empty($data->coursename) ? $data->coursename : 'Unspecified course';
        $data->datalynxname = !empty($data->datalynxname) ? $data->datalynxname : 'Unspecified datalynx';
        if (is_array($data->items)) {
            $data->entryid = implode(array_keys($data->items), ',');
        } else if (is_object($data->items) && isset($data->items->itemid)) {
            $data->entryid = $data->items->itemid;
            $data->items = array($data->entryid => $DB->get_record('datalynx_entries', array('id' => $data->entryid)));
        } else {
            $data->entryid = array();
        }


        if ($df->data->singleview) {
            $entryurl = new moodle_url($data->url, array('view' => $df->data->singleview, 'eids' => $data->entryid));
        } else if ($df->data->defaultview) {
            $entryurl = new moodle_url($data->url, array('view' => $df->data->defaultview, 'eids' => $data->entryid));
        } else {
            $entryurl = new moodle_url($data->url);
        }
        $notename = get_string("messageprovider:$eventname", 'datalynx');
        $subject = "$sitename -> $data->coursename -> $strdatalynx $data->datalynxname:  $notename";

        // prepare message object
        $message = new stdClass();
        $message->siteshortname   = format_string($SITE->shortname);
        $message->component       = 'mod_datalynx';
        $message->name            = get_string("event_$eventname", 'datalynx');
        $message->context         = $data->context;
        $message->subject         = $subject;
        $message->fullmessageformat = $data->notificationformat;
        $message->smallmessage    = '';
        $message->notification = 1;

        foreach ($data->items as $entry) {
            $data->viewlink = html_writer::link($entryurl, get_string('linktoentry', 'datalynx'));
            $message->userfrom = $data->userfrom = $this->get_sender_for_entry($entry);
            $data->senderprofilelink = html_writer::link(new moodle_url('/user/profile.php', array('id' => $data->userfrom->id)), fullname($data->userfrom));
            foreach ($this->get_recipients_for_entry($entry) as $userid) {
                $user = $DB->get_record('user', array('id' => $userid));
                $message->userto = $user;
                $data->fullname = fullname($user);
                $notedetails = get_string("message_$eventname", 'datalynx', $data);
                $contenthtml = text_to_html($notedetails, false, false, true);
                $content = html_to_text($notedetails);
                $message->fullmessage = $content;
                $message->fullmessagehtml = $contenthtml;
                message_send($message);
            }
        }
        return true;
    }

    private function get_sender_for_entry($entry) {
        global $DB, $USER;
        if ($this->sender == self::FROM_AUTHOR) {
            return $DB->get_record('user', array('id' => $entry->userid));
        } else {
            return $USER;
        }
    }

    private function get_recipients_for_entry($entry) {
        static $recipientsbyrole = null;
        $recipientids = array();
        if (isset($this->recipient['author'])) {
            $recipientids[] = $entry->userid;
        }
        if (isset($this->recipient['roles'])) {
            if (!isset($recipientsbyrole)) {
                $recipientsbyrole = $this->get_roles_used_in_context($this->df->context, $this->recipient['roles']);
            }
            $recipientids += $recipientsbyrole;
        }
        $recipientids = array_unique($recipientids);
        return $recipientids;
    }

    public function is_triggered_by($eventname) {
        $eventname = explode('\\', trim($eventname, '\\'))[2];
        return array_search($eventname, unserialize($this->rule->param1)) !== false;
    }

    /**
     * Gets the list of roles assigned to this context and up (parents)
     *
     * @param context $context
     * @param array $roleids
     * @return array
     */
    private function get_roles_used_in_context(context $context, array $roleids) {
        global $DB;

        list($contextlist, $params1) = $DB->get_in_or_equal($context->get_parent_context_ids(true), SQL_PARAMS_NAMED);
        list($rolelist, $params2) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                 WHERE ra.roleid $rolelist
                   AND ra.contextid $contextlist";

        return $DB->get_fieldset_sql($sql, $params1 + $params2);
    }

}

