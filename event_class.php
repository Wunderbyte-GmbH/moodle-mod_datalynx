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
 * @package dataform
 * @copyright  2014 Ivan Šakić
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class dataform_event_handler {

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
            require_once($CFG->dirroot . '/mod/dataform/db/events.php');
            self::$handlers = $handlers;
        }

        if ($menu) {
            $eventmenu = array();
            foreach(self::$handlers as $id => $event) {
                $eventmenu[$id] = get_string($id, 'dataform');
            }
            return $eventmenu;
        } else {
            return self::$handlers;
        }
    }

    /**
     * Returns an instance of a rule manager based on given data
     * @param stdClass $data must contain a dataform object in $data->df
     * @return dataform_rule_manager
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
        self::trigger_rules($data, 'dataform_entryadded');
    }

    public static function handle_entryupdated(stdClass $data) {
        self::trigger_rules($data, 'dataform_entryupdated');
    }

    public static function handle_entrydeleted(stdClass $data) {
        self::trigger_rules($data, 'dataform_entrydeleted');
    }

    public static function handle_entryapproved(stdClass $data) {
        self::trigger_rules($data, 'dataform_entryapproved');
    }

    public static function handle_entrydisapproved(stdClass $data) {
        self::trigger_rules($data, 'dataform_entrydisapproved');
    }

    public static function handle_commentadded(stdClass $data) {
        self::trigger_rules($data, 'dataform_commentadded');
    }

    public static function handle_ratingadded(stdClass $data) {
        self::trigger_rules($data, 'dataform_ratingadded');
    }

    public static function handle_ratingupdated(stdClass $data) {
        self::trigger_rules($data, 'dataform_ratingupdated');
    }

    public static function handle_memberadded(stdClass $data) {
        self::trigger_rules($data, 'dataform_memberadded');
        self::notify_team_members($data, 'memberadded');
    }

    public static function handle_memberremoved(stdClass $data) {
        self::trigger_rules($data, 'dataform_memberremoved');
        self::notify_team_members($data, 'memberremoved');
    }

    private static function notify_team_members(stdClass $data, $event) {
        global $CFG, $SITE, $USER;

        $df = $data->df;
        $data->event = $event;

        $data->dataforms = get_string('modulenameplural', 'dataform');
        $data->dataform = get_string('modulename', 'dataform');
        $data->activity = format_string($df->name(), true);
        $data->url = "$CFG->wwwroot/mod/dataform/view.php?d=" . $df->id();

        // Prepare message
        $strdataform = get_string('pluginname', 'dataform');
        $sitename = format_string($SITE->fullname);
        $data->siteurl = $CFG->wwwroot;
        $data->coursename = !empty($data->coursename) ? $data->coursename : 'Unspecified course';
        $data->dataformname = !empty($data->dataformname) ? $data->dataformname : 'Unspecified dataform';
        $data->dataformbaselink = html_writer::link($data->url, $data->dataformname);
        $data->dataformlink = html_writer::link($data->view->get_baseurl(), $data->dataformname);
        $entryurl = new moodle_url($data->view->get_baseurl());
        $data->viewlink = html_writer::link($entryurl, get_string('linktoentry', 'dataform'));
        $data->entryid = implode(array_keys($data->items), ',');

        $notename = get_string("messageprovider:dataform_$event", 'dataform');
        $subject = "$sitename -> $data->coursename -> $strdataform $data->dataformname:  $notename";

        // prepare message object
        $message = new stdClass();
        $message->siteshortname   = format_string($SITE->shortname);
        $message->component       = 'mod_dataform';
        $message->name            = "dataform_$event";
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
            $notedetails = get_string("message_$event", 'dataform', $data);
            $contenthtml = text_to_html($notedetails, false, false, true);
            $content = html_to_text($notedetails);
            $message->fullmessage = $content;
            $message->fullmessagehtml = $contenthtml;
            message_send($message);
        }
    }
}
