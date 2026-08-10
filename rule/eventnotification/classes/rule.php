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

namespace datalynxrule_eventnotification;

use context;
use html_writer;
use mod_datalynx\datalynx;
use mod_datalynx\local\rule\base;
use mod_datalynx\local\rule\match_compiler;
use mod_datalynx\local\rule\match_ledger;
use mod_datalynx\local\view\manager\email_view_manager;
use mod_datalynx\output\email_view_browser;
use moodle_url;
use stdClass;

/**
 * Event notification rule
 *
 * @package    datalynxrule_eventnotification
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule extends base {
    /** @var int From author */
    const FROM_AUTHOR = 0;

    /** @var int From current user */
    const FROM_CURRENT_USER = 1;

    /** @var int To author */
    const TO_AUTHOR = 1;

    /** @var int To user */
    const TO_USER = 2;

    /** @var int To roles */
    const TO_ROLES = 4;

    /** @var int To admin */
    const TO_ADMIN = 8;

    /** @var int To email */
    const TO_EMAIL = 16;

    /** @var string Dedupe scope value meaning "this rule alone". */
    const SCOPE_RULE = 'rule';

    /** @var int Counterparts one sweep will announce at most. */
    const DEFAULT_MAX_MATCHES = 200;

    /** @var string Rule type */
    public $type = 'eventnotification';

    /** @var int Sender */
    protected $sender;

    /**
     * @var array
     */
    protected array $recipient = [];

    /**
     * @var array
     */
    protected array $targetviews = [];

    /**
     * @var int Selected email template view id.
     */
    protected int $emailtemplateviewid = 0;

    /**
     * Class constructor
     *
     * @param datalynx|int $dlx datalynx id or class object
     * @param stdClass|int $rule rule id or DB record
     */
    public function __construct($dlx = 0, $rule = 0) {
        parent::__construct($dlx, $rule);

        $this->sender = $this->rule->param2;
        $this->recipient = $this->decode_array($this->rule->param3 ?? null);
        $this->targetviews = $this->decode_array($this->rule->param4 ?? null);
        $this->emailtemplateviewid = !empty($this->rule->param8) ? (int) $this->rule->param8 : 0;
    }

    /**
     * JSON-decode a rule param value that is expected to be an array.
     *
     * @param mixed $value
     * @return array
     */
    private function decode_array($value): array {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check team
     *
     * @param \core\event\base $event
     * @return bool
     */
    private function checkteam(\core\event\base $event) {
        $teamid = $event->get_data()['other']['fieldid'];
        $param1 = $this->rule->param1 ?? null;
        $triggers = empty($param1) ? [] : (json_decode($param1, true) ?? []);
        foreach ($triggers as $trigger) {
            if (strpos($trigger, "$teamid") !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Trigger rule
     *
     * @param \core\event\base $event
     * @return bool
     */
    public function trigger(\core\event\base $event) {
        global $DB;

        $eventname = (new \ReflectionClass($event))->getShortName();
        $teamfieldid = 0;
        if (strpos($eventname, 'team') !== false) {
            $teamfieldid = (int) ($event->get_data()['other']['fieldid'] ?? 0);
            if (!$this->checkteam($event)) {
                return false;
                // TODO: MDL-66151 In else branch: combine added and removed members if notification sent to changed team.
            }
        }

        // Resolve the entry id this event concerns (see base::resolve_entryid()).
        $entryid = $this->resolve_entryid($event);

        $matchconfig = $this->get_match_config();
        if ($matchconfig && $event instanceof \mod_datalynx\event\entry_deleted) {
            // A deleted entry has no counterparts left, and entry ids are reused.
            $this->ledger($matchconfig)->forget_entry($entryid);
            return true;
        }

        // Only trigger when the entry satisfies the configured trigger conditions.
        $conditions = $this->get_trigger_conditions();
        if ($conditions && !$this->entry_matches_conditions($entryid, $conditions)) {
            return false;
        }
        // When "only on change" fields are configured, require at least one of them to have
        // actually changed its value during this update. This filter only applies to the
        // entry_updated event (the only event carrying a changed-field list); other events the
        // rule may also listen to are unaffected. It is independent of the trigger conditions
        // above, so it also applies when no other condition is set.
        $onchangeids = $this->get_onlyonchange_fieldids();
        if (
            $onchangeids && $event instanceof \mod_datalynx\event\entry_updated
            && !$this->change_constraint_satisfied($onchangeids, $event)
        ) {
            return false;
        }

        $context = [
            'eventname' => $eventname,
            'entryid' => $entryid,
            'objectid' => (int) ($event->get_data()['objectid'] ?? 0),
            'teamfieldid' => $teamfieldid,
        ];

        // Searching for counterparts costs one query per criterion, and one more per candidate
        // when a route has to be tested in the direction the corridor query cannot express, so it
        // does not belong in the request that saved the entry. Everything the event carries and
        // the sweep still needs is handed over in the task payload.
        if ($matchconfig) {
            $context['userfromid'] = (int) $this->resolve_userfrom($entryid)->id;
            $task = new \datalynxrule_eventnotification\task\find_matches();
            $task->set_component('datalynxrule_eventnotification');
            $task->set_custom_data([
                'ruleid' => (int) $this->get_id(),
                'dataid' => (int) $this->dlx->id(),
                'context' => $context,
            ]);
            \core\task\manager::queue_adhoc_task($task, true);
            return true;
        }

        return $this->notify($context, $this->get_recipients($this->author_id($entryid), $entryid), $entryid);
    }

    /**
     * Send the notifications for a rule whose matching criteria have been evaluated.
     *
     * Called from the ad-hoc task, where the event object is long gone and only the values
     * collected in {@see self::trigger()} are available.
     *
     * @param array $context eventname, entryid, objectid, teamfieldid and userfromid
     * @return bool
     */
    public function run_matching(array $context): bool {
        global $DB;

        $entryid = (int) $context['entryid'];
        $matchconfig = $this->get_match_config();
        if (!$matchconfig || !$DB->record_exists('datalynx_entries', ['id' => $entryid])) {
            return false;
        }

        $compiler = new match_compiler($this->dlx);
        $compiled = $compiler->compile($matchconfig['criteria'], $entryid);
        if ($compiled['impossible']) {
            // The triggering entry holds no value a criterion needs, so nothing can match it.
            // Saying so is the point: falling through would match every entry instead.
            return false;
        }

        $matches = $this->find_matching_entries($entryid, $compiled['customsearch'], [
            'excludeownauthor' => true,
            'requireapproved' => !empty($matchconfig['requireapproved']),
            'limit' => (int) ($matchconfig['maxmatches'] ?? self::DEFAULT_MAX_MATCHES),
        ]);
        foreach ($compiled['postfilters'] as $postfilter) {
            $matches = $compiler->apply_postfilter($matches, $postfilter);
        }

        $ledger = !empty($matchconfig['dedupe']) ? $this->ledger($matchconfig) : null;
        $authorid = $this->author_id($entryid);

        $messages = [];
        $announced = false;
        foreach ($matches as $matchid) {
            if ($ledger && !$ledger->remember($entryid, $matchid)) {
                // Already announced; the pair is not news until it stops matching and matches again.
                continue;
            }
            $announced = true;
            if (!empty($this->recipient['matchauthors'])) {
                // Tell the counterpart's author about this entry, pointing back at their own.
                $matchauthorid = $this->author_id($matchid);
                if ($matchauthorid) {
                    $messages[] = $this->build_message($context, $entryid, $matchauthorid, $matchid);
                }
            }
            if (!empty($this->recipient['subjectauthorpermatch']) && $authorid) {
                // Tell this entry's author about the counterpart, linking to that entry.
                $messages[] = $this->build_message($context, $matchid, $authorid, $entryid);
            }
        }

        // The plain recipients hear about the entry itself, but only when the search found
        // something worth telling them about.
        if ($announced) {
            foreach ($this->get_recipients($authorid, $entryid) as $userid) {
                $messages[] = $this->build_message($context, $entryid, (int) $userid);
            }
        }

        if ($ledger && !empty($matchconfig['forgetstale'])) {
            $ledger->forget_missing($entryid, $matches);
        }

        return $this->queue(array_filter($messages));
    }

    /**
     * Build and queue one message per recipient, all about the same entry.
     *
     * @param array $context see {@see self::run_matching()}
     * @param int[] $recipients user ids
     * @param int $entryid the entry the messages are about
     * @return bool
     */
    private function notify(array $context, array $recipients, int $entryid): bool {
        $messages = [];
        foreach ($recipients as $userid) {
            $messages[] = $this->build_message($context, $entryid, (int) $userid);
        }

        return $this->queue(array_filter($messages));
    }

    /**
     * Hand a batch of messages to the ad-hoc sender.
     *
     * @param array $messages
     * @return bool
     */
    private function queue(array $messages): bool {
        if (!$messages) {
            return true;
        }

        $adhocktask = new \mod_datalynx\task\sendmessage_task();
        $adhocktask->set_custom_data_as_string(base64_encode(serialize(array_values($messages))));
        $adhocktask->set_component('mod_datalynx');
        \core\task\manager::queue_adhoc_task($adhocktask);

        return true;
    }

    /**
     * Build one notification.
     *
     * @param array $context see {@see self::run_matching()}
     * @param int $entryid the entry the message is about - its content, its link and its template
     * @param int $recipientuserid
     * @param int $otherentryid the counterpart entry when the notification is about a pair
     * @return \core\message\message|null null when the recipient cannot be messaged
     */
    private function build_message(
        array $context,
        int $entryid,
        int $recipientuserid,
        int $otherentryid = 0
    ): ?\core\message\message {
        global $CFG, $SITE, $DB;

        $userto = $DB->get_record('user', ['id' => $recipientuserid]);
        if (!$userto || $userto->deleted || $userto->suspended) {
            return null;
        }

        $dlx = $this->dlx;
        $eventname = $context['eventname'];
        $viewurl = "$CFG->wwwroot/mod/datalynx/view.php?d=" . $dlx->id();
        $datalynxname = $dlx->name() ? format_string($dlx->name(), true) : 'Unspecified datalynx';
        $coursename = $dlx->course->shortname ? format_string($dlx->course->shortname, true) : 'Unspecified datalynx';

        $messagedata = new stdClass();
        $messagedata->siteurl = $CFG->wwwroot;
        $messagedata->objectid = $context['objectid'];
        if (!empty($context['teamfieldid'])) {
            $messagedata->fieldname = $DB->get_field('datalynx_fields', 'name', ['id' => $context['teamfieldid']]);
        }
        if (strpos($eventname, 'comment') !== false) {
            $messagedata->commenttext = $DB->get_field('comments', 'content', ['id' => $context['objectid']]);
        }

        $pluginname = get_string('pluginname', 'datalynx');
        $sitename = format_string($SITE->fullname);
        $notename = get_string("messageprovider:event_$eventname", 'datalynx');
        $customsubject = is_scalar($this->rule->param6 ?? null) ? trim((string) $this->rule->param6) : '';
        $subject = $customsubject === ''
            ? "$sitename -> $coursename -> $pluginname $datalynxname:  $notename"
            : format_string($customsubject);

        $userfrom = isset($context['userfromid'])
            ? $DB->get_record('user', ['id' => $context['userfromid']])
            : $this->resolve_userfrom($entryid);
        $messagedata->senderprofilelink = html_writer::link(
            new moodle_url('/user/profile.php', ['id' => $userfrom->id]),
            fullname($userfrom)
        );
        $messagedata->fullname = fullname($userto);

        $message = new \core\message\message();
        $message->component = 'mod_datalynx';
        $message->name = "event_$eventname";
        $message->subject = $subject;
        $message->fullmessageformat = 1;
        $message->smallmessage = '';
        $message->notification = 1;
        if ($CFG->branch > 31) {
            $message->courseid = $dlx->course->id;
        }
        $message->userfrom = $userfrom;
        $message->userto = $userto;

        $viewurlparams = ['eids' => $entryid];
        foreach ($this->dlx()->get_user_datalynx_permissions($recipientuserid) as $roleid) {
            if (isset($this->targetviews[$roleid])) {
                $viewurlparams['view'] = $this->targetviews[$roleid];
                break;
            }
        }
        if (!isset($viewurlparams['view'])) {
            if ($dlx->data->singleview) {
                $viewurlparams['view'] = $dlx->data->singleview;
            } else {
                if ($dlx->data->defaultview) {
                    $viewurlparams['view'] = $dlx->data->defaultview;
                }
            }
        }

        // Include defined field contents in the message.
        $messagedfields = !empty($this->rule->param7) ? json_decode($this->rule->param7) : null;
        $messagedata->messagecontent = '';
        if (!empty($messagedfields)) {
            foreach ($messagedfields as $fieldid) {
                $entrydata = $DB->get_record(
                    'datalynx_contents',
                    ['fieldid' => $fieldid, 'entryid' => $entryid],
                    '*',
                    IGNORE_MISSING
                );
                if ($entrydata) {
                    $messagedata->messagecontent .= format_text($entrydata->content);
                }
            }
        }

        $entryurl = new moodle_url($viewurl, $viewurlparams);
        $messagedata->viewlink = html_writer::link($entryurl, get_string('linktoentry', 'datalynx'));
        $datalynxurl = new moodle_url($viewurl);
        $messagedata->datalynxlink = html_writer::link($datalynxurl, $datalynxname);

        [$message->fullmessage, $message->fullmessagehtml] = $this->build_message_body(
            $eventname,
            $entryid,
            $entryurl,
            $datalynxurl,
            $messagedata,
            $userto,
            $otherentryid
        );

        return $message;
    }

    /**
     * Who the message is "from".
     *
     * The entry author when the rule is set to FROM_AUTHOR, falling back to the current user when
     * no author can be resolved (a non entry-related event, for instance).
     *
     * @param int $entryid
     * @return stdClass
     */
    private function resolve_userfrom(int $entryid): stdClass {
        global $DB, $USER;

        if ($this->sender != self::FROM_AUTHOR) {
            return $USER;
        }
        $author = $DB->get_record('user', ['id' => $this->author_id($entryid)]);

        return $author ?: $USER;
    }

    /**
     * The author of an entry.
     *
     * @param int $entryid
     * @return int user id, or 0 when the entry is gone
     */
    private function author_id(int $entryid): int {
        global $DB;

        return (int) $DB->get_field('datalynx_entries', 'userid', ['id' => $entryid], IGNORE_MISSING);
    }

    /**
     * The counterpart matching configuration of this rule.
     *
     * @return array empty when the rule does not look for counterparts
     */
    protected function get_match_config(): array {
        if (!empty($this->rule->param5)) {
            // A rule still holding a legacy single condition has never been saved under the
            // current form, so it cannot carry matching criteria either.
            return [];
        }
        $decoded = json_decode((string) ($this->rule->param9 ?? ''), true);
        $config = is_array($decoded) ? ($decoded[self::MATCH_KEY] ?? []) : [];

        return (is_array($config) && !empty($config['criteria'])) ? $config : [];
    }

    /**
     * The ledger of announced pairs for this rule.
     *
     * @param array $matchconfig
     * @return match_ledger
     */
    private function ledger(array $matchconfig): match_ledger {
        $scope = trim((string) ($matchconfig['dedupe'] ?? ''));
        // Scoping to the rule is the default; a shared token lets two rules that match the two
        // sides of one relationship announce each pair once between them.
        if ($scope === '' || $scope === self::SCOPE_RULE) {
            $scope = 'rule:' . $this->get_id();
        }

        return new match_ledger($this->dlx->id(), $scope);
    }

    /**
     * Resolve the trigger conditions for this rule as a customsearch array.
     *
     * Uses the shared param9 customsearch ({@see base::get_trigger_conditions()}) and, for rules
     * not yet migrated, falls back to the legacy single-condition param5/param10 storage
     * (synthesizing a one-row customsearch).
     *
     * @return array customsearch aggregated by field id, or [] when no condition is set.
     */
    protected function get_trigger_conditions(): array {
        $conditions = parent::get_trigger_conditions();
        if ($conditions) {
            return $conditions;
        }

        // Legacy fallback: build a one-condition customsearch from param5/param10.
        if (!empty($this->rule->param5)) {
            $fieldid = $this->rule->param5;
            $field = $this->dlx()->get_field_from_id($fieldid);
            if (!$field) {
                return [];
            }
            $value = $this->rule->param10;
            $decoded = json_decode((string) $value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else if ($field instanceof \mod_datalynx\local\field\datalynxfield_option_multiple) {
                $value = explode(',', (string) $value);
            }
            $operator = $this->legacy_condition_operator($field);
            return [$fieldid => ['AND' => [['', $operator, $value]]]];
        }

        return [];
    }

    /**
     * Operator that reproduces the legacy (pre-param9) exact-match condition behaviour for a field.
     *
     * The old trigger compared the field value for exact (set) equality, so multi-option fields map
     * to EXACTLY and everything else to '=' (falling back to the first real operator the field offers).
     *
     * @param \mod_datalynx\local\field\datalynxfield_base $field
     * @return string
     */
    private function legacy_condition_operator($field): string {
        if ($field instanceof \mod_datalynx\local\field\datalynxfield_option_multiple) {
            return 'EXACTLY';
        }
        $operators = $field->get_supported_search_operators();
        if (array_key_exists('=', $operators)) {
            return '=';
        }
        foreach (array_keys($operators) as $op) {
            if ($op !== '') {
                return $op;
            }
        }
        return '';
    }

    /**
     * Build the notification message body using either the selected email view or the legacy text.
     *
     * @param string $eventname
     * @param int $entryid
     * @param moodle_url $entryurl
     * @param moodle_url $datalynxurl
     * @param stdClass $messagedata
     * @param stdClass $recipient
     * @param int $otherentryid the counterpart entry when the notification is about a pair
     * @return array{0:string,1:string}
     */
    private function build_message_body(
        string $eventname,
        int $entryid,
        moodle_url $entryurl,
        moodle_url $datalynxurl,
        stdClass $messagedata,
        stdClass $recipient,
        int $otherentryid = 0
    ): array {
        $templatehtml = $this->render_email_template($entryid, $entryurl, $datalynxurl, $recipient, $otherentryid);
        if ($templatehtml !== null) {
            return [html_to_text($templatehtml), $templatehtml];
        }

        $messagetext = get_string("message_$eventname", 'datalynx', $messagedata);
        return [html_to_text($messagetext), text_to_html($messagetext, false, false, true)];
    }

    /**
     * Render the selected email template view for a specific entry.
     *
     * @param int $entryid
     * @param moodle_url $entryurl
     * @param moodle_url $datalynxurl
     * @param stdClass $recipient
     * @param int $otherentryid the counterpart entry when the notification is about a pair
     * @return ?string
     */
    private function render_email_template(
        int $entryid,
        moodle_url $entryurl,
        moodle_url $datalynxurl,
        stdClass $recipient,
        int $otherentryid = 0
    ): ?string {
        global $OUTPUT, $USER;

        if (empty($this->emailtemplateviewid)) {
            return null;
        }

        $originaluser = $USER;
        \core\session\manager::set_user($recipient);

        try {
            $dlx = new datalynx($this->dlx()->id());
            $viewrecord = $dlx->get_all_views()[$this->emailtemplateviewid] ?? null;
            if (!$viewrecord || $viewrecord->type !== datalynx::INTERNAL_VIEW_EMAIL) {
                return null;
            }

            $options = [
                'notificationentryurl' => $entryurl->out(false),
                'notificationentrylink' => html_writer::link($entryurl, get_string('linktoentry', 'datalynx')),
                'notificationdatalynxurl' => $datalynxurl->out(false),
                'notificationdatalynxlink' => html_writer::link($datalynxurl, format_string($dlx->name(), true)),
            ];
            if ($otherentryid) {
                // A notification about a pair renders one of the two entries; these let the
                // template point at the other one.
                $otherurl = new moodle_url($entryurl, ['eids' => $otherentryid]);
                $options['notificationotherentryurl'] = $otherurl->out(false);
                $options['notificationotherentrylink'] = html_writer::link(
                    $otherurl,
                    get_string('linktoentry', 'datalynx')
                );
            }

            $manager = new email_view_manager();
            $payload = $manager->get_entry_payload($dlx->id(), (int) $viewrecord->id, $entryid, $options);
            if (!$payload['hascontent']) {
                return null;
            }

            $renderable = new email_view_browser($payload);
            return $OUTPUT->render_from_template(
                'mod_datalynx/email_view_browser',
                $renderable->export_for_template($OUTPUT)
            );
        } finally {
            \core\session\manager::set_user($originaluser);
        }
    }

    /**
     * Get IDs of recipient users as defined by this rule
     *
     * @param int $authorid user ID of the entry author, if the rule is entry-related
     * @param int $entryid ID of the entry (if applicable)
     * @return array array of user IDs
     */
    private function get_recipients($authorid = 0, $entryid = 0) {
        $recipientids = [];
        if (isset($this->recipient['author']) && $authorid) {
            $recipientids[] = $authorid;
        }
        if (isset($this->recipient['roles'])) {
            $recipientids = array_merge(
                $recipientids,
                $this->get_recipients_by_permission(
                    $this->dlx->context,
                    $this->recipient['roles']
                )
            );
        }
        if (isset($this->recipient['teams'])) {
            $recipientids = array_merge(
                $recipientids,
                $this->get_team_recipients($this->recipient['teams'], $entryid)
            );
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
     * @param array $permissions
     * @return array IDs of recipient users
     */
    protected function get_recipients_by_permission(context $context, $permissions) {
        global $DB;

        $allneeded = [];
        $allforbidden = [];

        $perms = [datalynx::PERMISSION_ADMIN => 'mod/datalynx:viewprivilegeadmin',
                datalynx::PERMISSION_MANAGER => 'mod/datalynx:viewprivilegemanager',
                datalynx::PERMISSION_TEACHER => 'mod/datalynx:viewprivilegeteacher',
                datalynx::PERMISSION_STUDENT => 'mod/datalynx:viewprivilegestudent',
                datalynx::PERMISSION_GUEST => 'mod/datalynx:viewprivilegeguest',
        ];

        foreach ($perms as $permissionid => $capstring) {
            if (in_array($permissionid, $permissions)) {
                [$needed, $forbidden] = get_roles_with_cap_in_context($context, $capstring);
                $allneeded = array_merge($allneeded, $needed);
                $allforbidden = array_merge($allforbidden, $forbidden);
            }
        }

        [$contextlist, $params1] = $DB->get_in_or_equal(
            $context->get_parent_context_ids(true),
            SQL_PARAMS_NAMED
        );

        $users = [];
        if (!empty($allneeded)) {
            // Loop through the actual Moodle role IDs found by get_roles_with_cap_in_context.
            foreach ($allneeded as $roleid) {
                // Get role users from both explicit role assignments and course enrolments.
                $roleusers = get_role_users($roleid, $context, true, 'u.id', 'u.id ASC');
                if ($roleusers) {
                    $users = array_merge($users, array_keys($roleusers));
                }
            }
            $users = array_unique($users);
        }

        $forbiddenusers = [];
        if (!empty($allforbidden)) {
            foreach ($allforbidden as $roleid) {
                $roleusers = get_role_users($roleid, $context, true, 'u.id', 'u.id ASC');
                if ($roleusers) {
                    $forbiddenusers = array_merge($forbiddenusers, array_keys($roleusers));
                }
            }
            $forbiddenusers = array_unique($forbiddenusers);
        }

        return array_diff($users, $forbiddenusers);
    }

    /**
     * Compiles an array of IDs of users that should receive this notification based on team fields
     *
     * @param array $teams
     * @param int $entryid
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_team_recipients($teams, $entryid = 0) {
        global $DB;
        $ids = [];
        if (empty($teams)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($teams, SQL_PARAMS_NAMED);
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
        $params = array_merge($params, ['dataid' => $this->dlx->id()]);
        $contents = $DB->get_fieldset_sql($sql, $params);
        foreach ($contents as $content) {
            $ids = array_merge($ids, json_decode($content, true) ?? []);
        }
        return array_unique($ids);
    }
}
