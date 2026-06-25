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

    /** @var string Reserved param9 key holding the "only trigger on change" field IDs. */
    const ONCHANGE_KEY = '_onlyonchangefields';

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
        global $CFG, $SITE, $DB, $USER;

        $messagedata = new stdClass();
        $eventname = (new \ReflectionClass($event))->getShortName();
        if (strpos($eventname, 'team') !== false) {
            $messagedata->fieldname = $DB->get_field(
                'datalynx_fields',
                'name',
                ['id' => $event->get_data()['other']['fieldid']]
            );
            if (!$this->checkteam($event)) {
                return false;
                // TODO: MDL-66151 In else branch: combine added and removed members if notification sent to changed team.
            }
        }

        // Resolve the entry id this event concerns. Comment events carry the comment id in
        // objectid and the entry id in other.itemid.
        if (strpos($eventname, 'comment') !== false) {
            $entryid = (int) ($event->get_data()['other']['itemid'] ?? 0);
        } else {
            $entryid = (int) ($event->get_data()['objectid'] ?? 0);
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

        $dlx = $this->dlx;
        $viewurl = "$CFG->wwwroot/mod/datalynx/view.php?d=" . $dlx->id();

        $datalynxname = $dlx->name() ? format_string($dlx->name(), true) : 'Unspecified datalynx';
        $coursename = $dlx->course->shortname ? format_string($dlx->course->shortname, true) : 'Unspecified datalynx';

        $messagedata->siteurl = $CFG->wwwroot;

        $messagedata->objectid = $event->get_data()['objectid'];

        $notename = get_string("messageprovider:event_$eventname", 'datalynx');

        $pluginname = get_string('pluginname', 'datalynx');
        $sitename = format_string($SITE->fullname);
        $customsubject = is_scalar($this->rule->param6 ?? null) ? trim((string) $this->rule->param6) : '';
        if ($customsubject === '') {
            $subject = "$sitename -> $coursename -> $pluginname $datalynxname:  $notename";
        } else {
            $subject = format_string($customsubject);
        }
        if (strpos($eventname, 'comment') !== false) {
            $commentid = $event->get_data()['objectid'];
            $messagedata->commenttext = $DB->get_field('comments', 'content', ['id' => $commentid]);
        }
        $authorid = $DB->get_field('datalynx_entries', 'userid', ['id' => $entryid]);
        $author = $DB->get_record('user', ['id' => $authorid]);

        $userfrom = (strpos($eventname, 'event') !== false &&
                $this->sender == self::FROM_AUTHOR) ? $author : $USER;
        $messagedata->senderprofilelink = html_writer::link(
            new moodle_url('/user/profile.php', ['id' => $userfrom->id]),
            fullname($userfrom)
        );

        $messagestosend = [];
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
                $message->courseid = $dlx->course->id;
            }
            $message->userfrom = $userfrom;
            $userto = $DB->get_record('user', ['id' => $userid]);
            $message->userto = $userto;
            $messagedata->fullname = fullname($userto);

            $viewurlparams = ['eids' => $entryid];

            $roleids = $this->dlx()->get_user_datalynx_permissions($userid);
            foreach ($roleids as $roleid) {
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
                    $entrydata = $DB->get_record('datalynx_contents', ['fieldid' => $fieldid, 'entryid' => $entryid]);
                    $messagedata->messagecontent .= format_text($entrydata->content);
                }
            }

            $entryurl = new moodle_url($viewurl, $viewurlparams);
            $messagedata->viewlink = html_writer::link(
                $entryurl,
                get_string('linktoentry', 'datalynx')
            );
            $datalynxurl = new moodle_url($viewurl);
            $messagedata->datalynxlink = html_writer::link($datalynxurl, $datalynxname);

            [$message->fullmessage, $message->fullmessagehtml] = $this->build_message_body(
                $eventname,
                $entryid,
                $entryurl,
                $datalynxurl,
                $messagedata,
                $userto
            );
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
     * Field IDs configured for the "only trigger when these field values change" option.
     *
     * Stored as a JSON array under the reserved {@see self::ONCHANGE_KEY} key in param9, kept
     * separate from the numeric condition rows so it applies independently of them.
     *
     * @return array
     */
    private function get_onlyonchange_fieldids(): array {
        if (empty($this->rule->param9)) {
            return [];
        }
        $decoded = json_decode($this->rule->param9, true);
        if (!is_array($decoded) || empty($decoded[self::ONCHANGE_KEY])) {
            return [];
        }
        return array_values((array) $decoded[self::ONCHANGE_KEY]);
    }

    /**
     * Check that at least one of the given fields actually changed during this update.
     *
     * Only meaningful for entry_updated events (the caller gates on that). The changed field IDs
     * are taken from the event's other['changed_field_ids'] array.
     *
     * @param int[] $onchangeids field IDs that must have changed
     * @param \core\event\base $event
     * @return bool
     */
    private function change_constraint_satisfied(array $onchangeids, \core\event\base $event): bool {
        if (!$onchangeids) {
            return true;
        }
        $changed = $event->other['changed_field_ids'] ?? [];
        return (bool) array_intersect($onchangeids, $changed);
    }

    /**
     * Resolve the trigger conditions for this rule as a customsearch array.
     *
     * Reads the JSON-stored multi-condition customsearch from param9. Falls back to the
     * legacy single-condition param5/param10 storage (synthesizing a one-row customsearch)
     * for rules not yet migrated.
     *
     * @return array customsearch aggregated by field id, or [] when no condition is set.
     */
    private function get_trigger_conditions(): array {
        if (!empty($this->rule->param9)) {
            $decoded = json_decode($this->rule->param9, true);
            if (!is_array($decoded)) {
                return [];
            }
            // The on-change field list lives under a reserved key, not a condition row.
            unset($decoded[self::ONCHANGE_KEY]);
            return $decoded;
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
     * Check whether the given entry matches the trigger conditions.
     *
     * Reuses the filter search engine ({@see \mod_datalynx\local\filter\datalynx_filter}) so a
     * rule condition evaluates exactly like the same criterion in a saved filter — including
     * internal fields (status/approve evaluated on the entry row) and AND/OR/NOT combinations.
     *
     * @param int $entryid
     * @param array $conditions customsearch aggregated by field id
     * @return bool
     */
    private function entry_matches_conditions(int $entryid, array $conditions): bool {
        global $DB;

        if (!$entryid) {
            return false;
        }

        $dlx = $this->dlx();
        $fields = $dlx->get_fields();

        $filter = new \mod_datalynx\local\filter\datalynx_filter(
            (object) ['dataid' => $dlx->id(), 'customsearch' => $conditions]
        );
        $filter->init_filter_sql();
        [$tables, $where, $params] = $filter->get_search_sql($fields);

        $params['conddataid'] = $dlx->id();
        $params['condeid'] = $entryid;
        // The $where fragment is already of the form " AND (...)" (or empty).
        $sql = "SELECT e.id
                  FROM {datalynx_entries} e
                  JOIN {user} u ON u.id = e.userid
                       $tables
                 WHERE e.dataid = :conddataid AND e.id = :condeid $where";
        return $DB->record_exists_sql($sql, $params);
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
     * @return array{0:string,1:string}
     */
    private function build_message_body(
        string $eventname,
        int $entryid,
        moodle_url $entryurl,
        moodle_url $datalynxurl,
        stdClass $messagedata,
        stdClass $recipient
    ): array {
        $templatehtml = $this->render_email_template($entryid, $entryurl, $datalynxurl, $recipient);
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
     * @return ?string
     */
    private function render_email_template(
        int $entryid,
        moodle_url $entryurl,
        moodle_url $datalynxurl,
        stdClass $recipient
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

            $manager = new email_view_manager();
            $payload = $manager->get_entry_payload($dlx->id(), (int) $viewrecord->id, $entryid, [
                'notificationentryurl' => $entryurl->out(false),
                'notificationentrylink' => html_writer::link($entryurl, get_string('linktoentry', 'datalynx')),
                'notificationdatalynxurl' => $datalynxurl->out(false),
                'notificationdatalynxlink' => html_writer::link($datalynxurl, format_string($dlx->name(), true)),
            ]);
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
