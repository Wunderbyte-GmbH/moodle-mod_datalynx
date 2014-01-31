<?php

// This file is part of Moodle - http://moodle.org/
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
 * Event handler class file.
 *
 * @package mod
 * @package datalynx
 * @copyright  2014 Ivan Šakić
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class datalynx_event_handler {

    private static $handlers = null;

    /**
     * Loads data about available events from events.php and lang file
     * @param bool $menu true if the result should be an array($eventname => $eventlabel)
     * @return array
     */
    public static function get_event_data($menu = false) {
        global $CFG;
        if (!self::$handlers) {
            $handlers = array();
            require_once($CFG->dirroot . '/mod/datalynx/db/events.php');
            self::$handlers = $handlers;
        }

        if ($menu) {
            $eventmenu = array();
            foreach(self::$handlers as $id => $event) {
                $eventmenu[$id] = get_string($id, 'datalynx');
            }
            return $eventmenu;
        } else {
            return self::$handlers;
        }
    }

    /**
     * Returns an instance of a rule manager based on given data
     * @param stdClass $data must contain a datalynx object in $data->df
     * @return datalynx_rule_manager
     */
    private static function get_rule_manager(stdClass $data) {
        if (!isset($data->df) || !is_object($data->df)) {
            return false;
        } else {
            return $data->df->get_rule_manager();
        }
    }

    /**
     * Trigger all rules associated with the event
     * @param stdClass $data event data
     * @param $event triggered event name
     */
    private static function trigger_rules(stdClass $data, $event) {
        $rulemanager = self::get_rule_manager($data);
        $rules = $rulemanager->get_rules_for_event('eventnotification', $event);
        foreach ($rules as $rule) {
            $rule->trigger($data);
        }
    }

    public static function handle_entryadded(stdClass $data) {
        self::trigger_rules($data, 'datalynx_entryadded');
    }

    public static function handle_entryupdated(stdClass $data) {
        self::trigger_rules($data, 'datalynx_entryupdated');
    }

    public static function handle_entrydeleted(stdClass $data) {
        self::trigger_rules($data, 'datalynx_entrydeleted');
    }

    public static function handle_entryapproved(stdClass $data) {
        self::trigger_rules($data, 'datalynx_entryapproved');
    }

    public static function handle_entrydisapproved(stdClass $data) {
        self::trigger_rules($data, 'datalynx_entrydisapproved');
    }

    public static function handle_commentadded(stdClass $data) {
        self::trigger_rules($data, 'datalynx_commentadded');
    }

    public static function handle_ratingadded(stdClass $data) {
        self::trigger_rules($data, 'datalynx_ratingadded');
    }

    public static function handle_ratingupdated(stdClass $data) {
        self::trigger_rules($data, 'datalynx_ratingupdated');
    }

    public static function handle_memberadded(stdClass $data) {
        self::trigger_rules($data, 'datalynx_memberadded');
        self::notify_team_members($data, 'memberadded');
    }

    public static function handle_memberremoved(stdClass $data) {
        self::trigger_rules($data, 'datalynx_memberremoved');
        self::notify_team_members($data, 'memberremoved');
    }

    private static function notify_team_members(stdClass $data, $event) {
        global $CFG, $SITE, $USER;

        $df = $data->df;
        $data->event = $event;

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
        $data->entryid = implode(array_keys($data->items), ',');

        if ($df->data->singleview) {
            $entryurl = new moodle_url($data->url, array('view' => $df->data->singleview, 'eids' => $data->entryid));
        } else if ($df->data->defaultview) {
            $entryurl = new moodle_url($data->url, array('view' => $df->data->defaultview, 'eids' => $data->entryid));
        } else {
            $entryurl = new moodle_url($data->url);
        }
        $data->viewlink = html_writer::link($entryurl, get_string('linktoentry', 'datalynx'));

        $notename = get_string("messageprovider:datalynx_$event", 'datalynx');
        $subject = "$sitename -> $data->coursename -> $strdatalynx $data->datalynxname:  $notename";

        // prepare message object
        $message = new stdClass();
        $message->siteshortname   = format_string($SITE->shortname);
        $message->component       = 'mod_datalynx';
        $message->name            = "datalynx_$event";
        $message->context         = $data->context;
        $message->subject         = $subject;
        $message->fullmessageformat = $data->notificationformat;
        $message->smallmessage    = '';
        $message->notification = 1;
        $message->userfrom = $data->userfrom = $USER;
        $data->senderprofilelink = html_writer::link(new moodle_url('/user/profile.php', array('id' => $data->userfrom->id)), fullname($data->userfrom));
        foreach ($data->users as $user) {
            $message->userto = $user;
            $data->fullname = fullname($user);
            $notedetails = get_string("message_$event", 'datalynx', $data);
            $contenthtml = text_to_html($notedetails, false, false, true);
            $content = html_to_text($notedetails);
            $message->fullmessage = $content;
            $message->fullmessagehtml = $contenthtml;
            message_send($message);
        }
    }
}
