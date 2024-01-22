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
 * @package mod_datalynx
 * @copyright 2015 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(dirname(__FILE__)) . '/classes/datalynx.php');

/**
 * Class that manages and triggers rules
 */
class datalynx_rule_manager {

    /**
     * @var datalynx|\mod_datalynx\datalynx
     */
    protected $_df;

    /**
     * @var array
     */
    protected $_customrules;

    /**
     * @var array
     */
    private static $observers = null;

    /**
     * Loads data about available events from events.php and lang file
     *
     * @param int $dataid instance id, only necessary if per-team-field-change events are needed
     * @return array
     * @throws coding_exception
     */
    public static function get_event_data($dataid = 0) {
        if (!self::$observers) {
            require_once('../db/events.php');
            self::$observers = $observers;
        }

        $eventmenu = array();
        foreach (self::$observers as $observer) {
            if ($observer['callback'] == 'datalynx_rule_manager::trigger_rules') {
                // Eventname is formed as follows: mod_datalynx\event\<name>, trimming backspace.
                // Chars just in case.
                $eventname = explode('\\', trim($observer['eventname'], '\\'))[2];
                if ($eventname !== 'team_updated') {
                    $eventmenu[$eventname] = get_string("event_$eventname", 'mod_datalynx');
                } else {
                    foreach (self::get_team_fields_menu($dataid) as $id => $teamfieldname) {
                        $eventmenu["$eventname:$id"] = get_string("event_$eventname", 'mod_datalynx') .
                                ": $teamfieldname";
                    }
                }
            }
        }
        return $eventmenu;
    }

    private static function get_team_fields_menu($dataid) {
        global $DB;
        $params = array('dataid' => $dataid, 'type' => 'teammemberselect');
        return $DB->get_records_menu('datalynx_fields', $params, '', 'id, name');
    }

    /**
     * constructor
     *
     * @param $datalynx datalynx
     */
    public function __construct(mod_datalynx\datalynx $datalynx) {
        $this->_df = $datalynx;
        $this->_customrules = array();
    }

    /**
     * Observer of events that can trigger datalynx rules.
     * Triggers all rules associated with the event.
     *
     * @param \core\event\base $event event data
     */
    public static function trigger_rules(\core\event\base $event) {
        global $DB;
        $cmid = $event->get_data()['contextinstanceid'];
        $dataid = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
        $rulemanager = new datalynx_rule_manager(new mod_datalynx\datalynx($dataid));
        $rules = $rulemanager->get_rules_for_event($event->eventname);
        foreach ($rules as $rule) {
            $rule->trigger($event);
        }
    }

    /**
     * given a rule id return the rule object from get_rules
     * Initializes get_rules if necessary
     *
     * @param int $ruleid
     * @param bool $forceget
     * @return datalynx_rule_base|null
     */
    public function get_rule_from_id($ruleid, $forceget = false) {
        $rules = $this->get_rules(null, false, $forceget);

        if (empty($rules[$ruleid])) {
            return null;
        } else {
            return $rules[$ruleid];
        }
    }

    /**
     * given a rule type returns the rule object from get_rules
     * Initializes get_rules if necessary
     *
     * @return datalynx_rule_base[]
     */
    public function get_rules_by_plugintype($type, $menu = false) {
        $typerules = array();
        foreach ($this->get_rules() as $ruleid => $rule) {
            if ($rule->get_type() === $type) {
                if ($menu) {
                    $typerules[$ruleid] = $rule->get_name();
                } else {
                    $typerules[$ruleid] = $rule;
                }
            }
        }
        return $typerules;
    }

    /**
     *
     * @param string $eventname
     * @param bool $enabledonly
     * @return datalynx_rule_base[]
     */
    public function get_rules_for_event($eventname, $enabledonly = true) {
        $rules = array();
        foreach ($this->get_rules() as $ruleid => $rule) {
            if ($rule->is_triggered_by($eventname) && (!$enabledonly || $rule->is_enabled())) {
                $rules[$ruleid] = $rule;
            }
        }
        return $rules;
    }

    /**
     * given a rule name returns the rule object from get_rules
     */
    public function get_rule_by_name($name) {
        foreach ($this->get_rules() as $rule) {
            if ($rule->get_name() === $name) {
                return $rule;
            }
        }
        return false;
    }

    /**
     * returns a subclass rule object given a record of the rule
     * used to invoke plugin methods
     * input: $param $rule record from db, or rule type
     */
    public function get_rule($key) {
        global $CFG;

        if ($key) {
            if (is_object($key)) {
                $type = $key->type;
            } else {
                $type = $key;
                $key = 0;
            }
            require_once($type . '/rule_class.php');
            $ruleclass = 'datalynx_rule_' . $type;
            $rule = new $ruleclass($this->_df, $key);
            return $rule;
        } else {
            return false;
        }
    }

    /**
     *
     * @param null $exclude
     * @param bool $menu
     * @param bool $forceget
     * @return datalynx_rule_base[] array of all rules
     */
    public function get_rules($exclude = null, $menu = false, $forceget = false) {
        global $DB;

        if (!$this->_customrules || $forceget) {
            $this->_customrules = array();
            // Collate user rules.
            if ($rules = $DB->get_records('datalynx_rules', array('dataid' => $this->_df->id()))) {
                foreach ($rules as $ruleid => $rule) {
                    $this->_customrules[$ruleid] = $this->get_rule($rule);
                }
            }
        }

        $rules = $this->_customrules;
        if (empty($exclude) && !$menu) {
            return $rules;
        } else {
            $retrules = array();
            foreach ($rules as $ruleid => $rule) {
                if (!empty($exclude) && in_array($ruleid, $exclude)) {
                    continue;
                }
                if ($menu) {
                    $retrules[$ruleid] = $rule->get_name();
                } else {
                    $retrules[$ruleid] = $rule;
                }
            }
            return $retrules;
        }
    }

    /**
     */
    public function process_rules($action, $rids, $confirmed = false) {
        global $OUTPUT, $DB;

        $df = $this->_df;

        if (!has_capability('mod/datalynx:managetemplates', $df->context)) {
            // TODO throw exception.
            return false;
        }

        $dfrules = $this->get_rules();
        $rules = array();
        // Collate the rules for processing.
        if ($ruleids = explode(',', $rids)) {
            foreach ($ruleids as $ruleid) {
                if ($ruleid > 0 && isset($dfrules[$ruleid])) {
                    $rules[$ruleid] = $dfrules[$ruleid];
                }
            }
        }

        $processedrids = array();
        $strnotify = '';

        if (empty($rules) && $action != 'add') {
            $df->notifications['bad'][] = get_string("rulenoneforaction", 'datalynx');
            return false;
        } else {
            if (!$confirmed) {
                // Print header.
                $df->print_header('rules');

                // Print a confirmation page.
                echo $OUTPUT->confirm(get_string("rulesconfirm$action", 'datalynx', count($rules)),
                        new moodle_url('/mod/datalynx/rule/index.php',
                                array('d' => $df->id(), $action => implode(',', array_keys($rules)),
                                        'sesskey' => sesskey(), 'confirmed' => 1)),
                        new moodle_url('/mod/datalynx/rule/index.php', array('d' => $df->id())));

                $df->print_footer();
                exit();
            } else {
                // Go ahead and perform the requested action.
                switch ($action) {
                    case 'add': // TODO add new.
                        if ($forminput = data_submitted()) {
                            // Check for arrays and convert to a comma-delimited string.
                            $df->convert_arrays_to_strings($forminput);

                            // Create a rule object to collect and store the data safely.
                            $rule = $this->get_rule($forminput->type);
                            $ruleid = $rule->insert_rule($forminput);

                            $other = array('dataid' => $this->_df->id());
                            $event = \mod_datalynx\event\rule_created::create(
                                    array('context' => $this->_df->context, 'objectid' => $ruleid,
                                            'other' => $other));
                            $event->trigger();
                        }
                        $strnotify = 'rulesadded';
                        break;

                    case 'update': // Update existing.
                        if ($forminput = data_submitted()) {
                            // Check for arrays and convert to a comma-delimited string.
                            $df->convert_arrays_to_strings($forminput);

                            // Create a rule object to collect and store the data safely.
                            $rule = reset($rules);
                            $oldrulename = $rule->rule->name;
                            $rule->update_rule($forminput);

                            $other = array('dataid' => $this->_df->id());
                            $event = \mod_datalynx\event\rule_updated::create(
                                    array('context' => $this->_df->context,
                                            'objectid' => $rule->rule->id, 'other' => $other));
                            $event->trigger();
                        }
                        $strnotify = 'rulesupdated';
                        break;

                    case 'enabled':
                        foreach ($rules as $rid => $rule) {
                            // Disable = 0; enable = 1.
                            $enabled = ($rule->rule->enabled ? 0 : 1);
                            $DB->set_field('datalynx_rules', 'enabled', $enabled,
                                    array('id' => $rid));

                            $processedrids[] = $rid;

                            $other = array('dataid' => $this->_df->id());
                            $event = \mod_datalynx\event\rule_updated::create(
                                    array('context' => $this->_df->context, 'objectid' => $rid,
                                            'other' => $other));
                            $event->trigger();
                        }

                        $strnotify = '';
                        break;

                    case 'duplicate':
                        foreach ($rules as $rule) {
                            // Set new name.
                            while ($df->name_exists('rules', $rule->get_name())) {
                                $rule->rule->name .= '_1';
                            }
                            $ruleid = $DB->insert_record('datalynx_rules', $rule->rule);
                            $processedrids[] = $ruleid;

                            $other = array('dataid' => $this->_df->id());
                            $event = \mod_datalynx\event\rule_created::create(
                                    array('context' => $this->_df->context, 'objectid' => $ruleid,
                                            'other' => $other));
                            $event->trigger();
                        }
                        $strnotify = 'rulesadded';
                        break;

                    case 'delete':
                        foreach ($rules as $rule) {
                            $rule->delete_rule();
                            $processedrids[] = $rule->rule->id;

                            $other = array('dataid' => $this->_df->id());
                            $event = \mod_datalynx\event\rule_deleted::create(
                                    array('context' => $this->_df->context,
                                            'objectid' => $rule->rule->id, 'other' => $other));
                            $event->trigger();
                        }
                        $strnotify = 'rulesdeleted';
                        break;

                    default:
                        break;
                }

                if ($strnotify) {
                    $rulesprocessed = $processedrids ? count($processedrids) : 'No';
                    $df->notifications['good'][] = get_string($strnotify, 'datalynx',
                            $rulesprocessed);
                }
                if (!empty($processedrids)) {
                    $this->get_rules(null, false, true);
                }

                return $processedrids;
            }
        }
    }

    /**
     */
    public function print_rule_list() {
        global $OUTPUT;

        $df = $this->_df;

        $editbaseurl = '/mod/datalynx/rule/rule_edit.php';
        $actionbaseurl = '/mod/datalynx/rule/index.php';
        $linkparams = array('d' => $df->id(), 'sesskey' => sesskey());

        // Table headings.
        $strname = get_string('name');
        $strtype = get_string('type', 'datalynx');
        $strdescription = get_string('description');
        $stredit = get_string('edit');
        $strduplicate = get_string('duplicate');
        $strdelete = get_string('delete');
        $strenabled = get_string('enabled', 'datalynx');
        $strhide = get_string('hide');
        $strshow = get_string('show');

        // The default value of the type attr of a button is submit, so set it to button so that it doesn't submit the form.
        $selectallnone = html_writer::checkbox(null, null, false, null,
                array('onclick' => 'select_allnone(\'rule\'&#44;this.checked)'));
        $multiactionurl = new moodle_url($actionbaseurl, $linkparams);
        $multidelete = html_writer::tag('button',
                $OUTPUT->pix_icon('t/delete', get_string('multidelete', 'datalynx')),
                array('type' => 'button', 'name' => 'multidelete',
                        'onclick' => 'bulk_action(\'rule\'&#44; \'' . $multiactionurl->out(false) .
                                '\'&#44; \'delete\')'
                ));
        $multiduplicate = html_writer::tag('button',
                $OUTPUT->pix_icon('t/copy', get_string('multiduplicate', 'datalynx')),
                array('type' => 'button', 'name' => 'multiduplicate',
                        'onclick' => 'bulk_action(\'rule\'&#44; \'' . $multiactionurl->out(false) .
                                '\'&#44; \'duplicate\')'
                ));

        $table = new html_table();
        $table->head = array($strname, $strtype, $strdescription, $strenabled, $stredit,
                $multiduplicate, $multidelete, $selectallnone);
        $table->align = array('left', 'left', 'left', 'center', 'center', 'center', 'center', 'center');
        $table->wrap = array(false, false, false, false, false, false, false, false);
        $table->attributes['align'] = 'center';

        $rules = $this->get_rules();
        foreach ($rules as $ruleid => $rule) {
            // Skip predefined rules.
            if ($ruleid < 0) {
                continue;
            }

            $rulename = html_writer::link(
                    new moodle_url($editbaseurl, $linkparams + array('rid' => $ruleid)), $rule->get_name());
            $ruleedit = html_writer::link(
                    new moodle_url($editbaseurl, $linkparams + array('rid' => $ruleid)),
                    $OUTPUT->pix_icon('t/edit', $stredit));
            $ruleduplicate = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('duplicate' => $ruleid)),
                    $OUTPUT->pix_icon('t/copy', $strduplicate));
            $ruledelete = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('delete' => $ruleid)),
                    $OUTPUT->pix_icon('t/delete', $strdelete));
            $ruleselector = html_writer::checkbox("ruleselector", $ruleid, false);

            $ruletype = $rule->typename();
            $ruledescription = shorten_text($rule->rule->description, 30);

            // Enabled.
            if ($enabled = $rule->rule->enabled) {
                $enabledicon = $OUTPUT->pix_icon('t/hide', $strhide);
            } else {
                $enabledicon = $OUTPUT->pix_icon('t/show', $strshow);
            }
            $ruleenabled = html_writer::link(
                    new moodle_url($actionbaseurl, $linkparams + array('enabled' => $ruleid)), $enabledicon);

            $table->data[] = array($rulename, $ruletype, $ruledescription, $ruleenabled, $ruleedit,
                    $ruleduplicate, $ruledelete, $ruleselector
            );
        }

        echo html_writer::tag('div', html_writer::table($table), array('class' => 'ruleslist'));
    }

    /**
     */
    public function print_add_rule() {
        global $OUTPUT;

        // Display the rule form jump list.
        $directories = get_list_of_plugins('mod/datalynx/rule/');
        $rulemenu = array();

        foreach ($directories as $directory) {
            if ($directory[0] != '_') {
                // Get name from language files.
                $rulemenu[$directory] = get_string('pluginname', "datalynxrule_$directory");
            }
        }
        // Sort in alphabetical order.
        asort($rulemenu);

        $popupurl = new moodle_url('/mod/datalynx/rule/rule_edit.php',
                array('d' => $this->_df->id(), 'sesskey' => sesskey()));
        $ruleselect = new single_select($popupurl, 'type', $rulemenu, null, array('' => 'choosedots'), 'ruleform');
        $ruleselect->set_label(get_string('ruleadd', 'datalynx') . '&nbsp;');
        $br = html_writer::empty_tag('br');
        echo html_writer::tag('div', $br . $OUTPUT->render($ruleselect) . $br,
                array('class' => 'ruleadd mdl-align'));
    }

    private static function notify_team_members(stdClass $data, $event) {
        global $CFG, $SITE, $USER, $DB;

        $df = $data->df;
        $data->event = $event;

        $data->datalynxs = get_string('modulenameplural', 'datalynx');
        $data->datalynx = get_string('modulename', 'datalynx');
        $data->activity = format_string($df->name(), true);
        $data->url = "$CFG->wwwroot/mod/datalynx/view.php?d=" . $df->id();

        // Prepare message.
        $strdatalynx = get_string('pluginname', 'datalynx');
        $sitename = format_string($SITE->fullname);
        $data->siteurl = $CFG->wwwroot;
        $data->coursename = !empty($data->coursename) ? $data->coursename : 'Unspecified course';
        $data->datalynxname = !empty($data->datalynxname) ? $data->datalynxname : 'Unspecified datalynx';
        $data->entryid = implode(',', array_keys($data->items));

        if ($df->data->singleview) {
            $entryurl = new moodle_url($data->url,
                    array('view' => $df->data->singleview, 'eids' => $data->entryid));
        } else {
            if ($df->data->defaultview) {
                $entryurl = new moodle_url($data->url,
                        array('view' => $df->data->defaultview, 'eids' => $data->entryid));
            } else {
                $entryurl = new moodle_url($data->url);
            }
        }
        $data->viewlink = html_writer::link($entryurl, get_string('linktoentry', 'datalynx'));

        $notename = get_string("messageprovider:datalynx_$event", 'datalynx');
        $subject = "$sitename -> $data->coursename -> $strdatalynx $data->datalynxname:  $notename";

        $data->senderprofilelink = html_writer::link(
                new moodle_url('/user/profile.php', array('id' => $data->userfrom->id)), fullname($data->userfrom));
        $messagestosend = array();
        foreach ($data->users as $user) {
            // Prepare message object.
            $message = new \core\message\message();
            $message->component = 'mod_datalynx';
            $message->name = "datalynx_$event";
            $message->subject = $subject;
            $message->fullmessageformat = $data->notificationformat;
            $message->smallmessage = '';
            $message->notification = 1;
            $message->userfrom = $data->userfrom = $USER;
            if ($CFG->branch > 31) {
                $message->courseid = $df->course->id;
            }
            $userto = $DB->get_record('user', array('id' => $user->id));
            $message->userto = $userto;
            $data->fullname = fullname($userto);
            $notedetails = get_string("message_$event", 'datalynx', $data);
            $contenthtml = text_to_html($notedetails, false, false, true);
            $content = html_to_text($notedetails);
            $message->fullmessage = $content;
            $message->fullmessagehtml = $contenthtml;
            $messagestosend[] = $message;
        }
        if ($messagestosend) {
            $adhocktask = new \mod_datalynx\task\sendmessage_task();
            $adhocktask->set_custom_data_as_string(serialize(base64_encode($messagestosend)));
            $adhocktask->set_component('mod_datalynx');
            \core\task\manager::queue_adhoc_task($adhocktask);
        }
    }
}
