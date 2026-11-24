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
 * @package datalynxfield_teammemberselect
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_teammemberselect;

use context_course;
use DOMDocument;
use mod_datalynx\local\field\datalynxfield_base;
use moodle_url;
use stdClass;

/**
 * Team member select field class.
 */
class field extends datalynxfield_base {
    /**
     * The ids of the chosen users.
     *
     * @param array $options
     * @return array
     */
    public function get_input_contract(array $options = []): array {
        return ['' => ['list' => PARAM_INT]];
    }

    /**
     * Empty unless a user is chosen; the form posts -999 for an empty selection.
     *
     * @param string $name
     * @param stdClass $data
     * @return bool
     */
    public function is_submitted_value_empty(string $name, stdClass $data): bool {
        return static::is_fieldvalue_empty($data->$name ?? []);
    }

    /** @var string Field type */
    public $type = 'teammemberselect';

    /** @var int Newline format constant */
    const TEAMMEMBERSELECT_FORMAT_NEWLINE = 0;

    /** @var int Space format constant */
    const TEAMMEMBERSELECT_FORMAT_SPACE = 1;

    /** @var int Comma format constant */
    const TEAMMEMBERSELECT_FORMAT_COMMA = 2;

    /** @var int Comma space format constant */
    const TEAMMEMBERSELECT_FORMAT_COMMA_SPACE = 3;

    /** @var int UL format constant */
    const TEAMMEMBERSELECT_FORMAT_UL = 4;

    /** @var int Maximum team size */
    public $teamsize;

    /** @var array Admissible roles */
    public $admissibleroles;

    /** @var int Minimum team size */
    public $minteamsize;

    /** @var int List format */
    public $listformat;

    /** @var bool Use team field */
    public $teamfield;

    /** @var int Reference field ID */
    public $referencefieldid;

    /** @var bool Notify team members */
    public $notifyteammembers;

    /** @var bool User can add self */
    public $usercanaddself;

    /** @var bool|null Whether a fieldgroup lists this field, see is_fieldgroup_subfield() */
    private ?bool $infieldgroup = null;

    /** @var bool Allow unsubscription */
    public $allowunsubscription;

    /** @var array Separator options */
    public $separators;

    /** @var array Notification rules */
    public $rules;

    /** @var array Cached all users */
    protected static $allusers = [];

    /** @var array Cached allowed users */
    protected static $allowedusers = [];

    /** @var array Cached all users links */
    protected static $alluserslinks = [];

    /** @var array Cached allowed users links */
    protected static $alloweduserslinks = [];

    /** @var array Cached all user IDs */
    protected static $alluserids = [];

    /** @var array Cached forbidden user IDs */
    protected static $forbiddenuserids = [];

    /** @var array Admissibility settings */
    protected static $admissibility = ['needed' => [], 'forbidden' => []];

    /**
     * Can this field be used in fieldgroups?
     * @var bool
     */
    protected $forfieldgroup = true;

    /**
     * Constructor.
     *
     * @param int|object $dlx Datalynx ID or object
     * @param int|object $field Field ID or object
     */
    public function __construct($dlx = 0, $field = 0) {
        parent::__construct($dlx, $field);
        if (empty($this->field->param2)) {
            $this->admissibleroles = [];
        } else {
            $this->admissibleroles = json_decode($this->field->param2, true);
        }
        $this->teamsize = $this->field->param1;
        $this->minteamsize = $this->field->param3;
        $this->listformat = $this->field->param4;
        $this->teamfield = !empty($this->field->param5);
        $this->referencefieldid = $this->field->param5;
        $this->notifyteammembers = !empty($this->field->param6);
        $this->usercanaddself = !empty($this->field->param7);
        $this->allowunsubscription = !empty($this->field->param8);
        $this->separators = [
            self::TEAMMEMBERSELECT_FORMAT_NEWLINE => get_string('listformatnewline', 'datalynx'),
            self::TEAMMEMBERSELECT_FORMAT_SPACE => get_string('listformatspace', 'datalynx'),
            self::TEAMMEMBERSELECT_FORMAT_COMMA => get_string('listformatcomma', 'datalynx'),
            self::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE => get_string(
                'listformatcommaspace',
                'datalynx'
            ),
            self::TEAMMEMBERSELECT_FORMAT_UL => get_string('listformatul', 'datalynx'),
        ];

        $this->rules = [];
        foreach (\mod_datalynx\local\definition_cache::get_records($dlx->id(), 'rules') as $rule) {
            if ($rule->type === 'eventnotification') {
                $this->rules[$rule->id] = $rule->name;
            }
        }
    }

    /**
     * Initialize user menu data.
     */
    protected function init_user_menu(): void {
        global $DB, $COURSE;

        $context = context_course::instance($COURSE->id);

        $fieldid = $this->field->id;
        self::$allusers[$fieldid] = [];
        self::$alluserslinks[$fieldid] = [];
        self::$allowedusers[$fieldid] = [];
        self::$alloweduserslinks[$fieldid] = [];

        self::$alluserids[$fieldid] = [];
        self::$forbiddenuserids[$fieldid] = [];

        self::$admissibility = $this->get_admissibility_for_roles($context);

        $query = "SELECT DISTINCT CONCAT(u.id, '-', ra.roleid) AS mainid, u.*, ra.roleid
                    FROM {role_assignments} ra
              INNER JOIN {user} u ON u.id = ra.userid
                   WHERE ra.contextid = :contextid
                ORDER BY u.lastname ASC, u.firstname ASC, u.email ASC, u.username ASC";

        $results = $DB->get_records_sql($query, ['contextid' => $context->id]);

        $baseurl = new moodle_url('/user/view.php', ['course' => $COURSE->id]);

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

    /**
     * Get admissibility for roles in context.
     *
     * @param \context $context
     * @return array
     */
    protected function get_admissibility_for_roles($context): array {
        $allneeded = [];
        $allforbidden = [];

        $perms = [\mod_datalynx\datalynx::PERMISSION_ADMIN => 'mod/datalynx:viewprivilegeadmin',
            \mod_datalynx\datalynx::PERMISSION_MANAGER => 'mod/datalynx:viewprivilegemanager',
            \mod_datalynx\datalynx::PERMISSION_TEACHER => 'mod/datalynx:viewprivilegeteacher',
            \mod_datalynx\datalynx::PERMISSION_STUDENT => 'mod/datalynx:viewprivilegestudent',
            \mod_datalynx\datalynx::PERMISSION_GUEST => 'mod/datalynx:viewprivilegeguest'];

        foreach ($perms as $permissionid => $capstring) {
            if (in_array($permissionid, $this->admissibleroles)) {
                [$needed, $forbidden] = get_roles_with_cap_in_context($context, $capstring);
                $allneeded = array_merge($allneeded, $needed);
                $allforbidden = array_merge($allforbidden, $forbidden);
            }
        }
        return ['needed' => array_unique($allneeded), 'forbidden' => array_unique($allforbidden)];
    }

    /**
     * Get team field record.
     *
     * @return stdClass|false
     */
    public function get_teamfield(): stdClass|false {
        global $DB;

        $query = "SELECT *
                    FROM {datalynx_fields} df
                   WHERE df.dataid = :dataid
                     AND df.type LIKE 'teammemberselect'
                     AND df.param5 IS NOT NULL
                     AND df.param5 <> '0'";

        return $DB->get_record_sql($query, ['dataid' => $this->dlx->id()]);
    }

    /**
     * Update a teammemberselectfield when editing an entry and notify teammembers of changes
     *
     * @param stdClass $entry The entry object.
     * @param ?array $values Field values.
     * @return int|string|bool New content ID.
     */
    public function update_content(stdClass $entry, ?array $values = null): int|string|bool {
        // Members the form no longer offers cannot be kept by the form: they are removed, which
        // the edit form warns about. Saving confirms who was removed.
        $storedcontent = $entry->{"c{$this->field->id}_content"} ?? null;
        $stored = $storedcontent ? (json_decode($storedcontent, true) ?: []) : [];
        $notoffered = $this->get_members_not_offered($entry, (array) $stored);

        $newcontentid = parent::update_content($entry, $values);

        // Not submitted at all, the team stays as it is; otherwise it holds what was submitted.
        $submitted = $values ? reset($values) : null;
        $kept = $submitted === null ? array_keys($notoffered) : array_map('intval', (array) $submitted);
        $removed = array_diff_key($notoffered, array_flip($kept));
        $interactive = !(defined('WS_SERVER') && WS_SERVER) && !(defined('AJAX_SCRIPT') && AJAX_SCRIPT);
        if ($removed && $interactive) {
            \core\notification::warning(get_string('teammembersremoved', 'datalynxfield_teammemberselect', (object) [
                'field' => format_string($this->name()),
                'users' => implode(', ', $removed),
            ]));
        }

        // TODO: MDL-0000 All this is only to notify team members. Check if we really need this here.
        global $DB;
        $fieldid = $this->field->id;

        // Read oldcontent from passed entry, not from DB query.
        $oldcontent = [];
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontent = json_decode($entry->{"c{$fieldid}_content"}, true);
        }

        $first = reset($values);
        $newcontent = !empty($first) ? $first : [];

        if (!empty($newcontent)) {
            if (isset($newcontent[0]) && $newcontent[0] == -999) {
                array_shift($newcontent); // Remove Dummy value.
            }
        }

        // Not submitted (e.g. shown disabled): the team is kept, nobody joined or left.
        // Inside a fieldgroup the field is a list of people, not the entry's team: no notifications.
        if ($values && !$this->is_fieldgroup_subfield()) {
            $field = $DB->get_record('datalynx_fields', ['id' => $this->field->id]);
            $this->notify_team_members($entry, $field, $oldcontent, $newcontent);
        }

        return $newcontentid;
    }

    /**
     * Whether a fieldgroup of the instance lists this field as a subfield.
     *
     * Inside a fieldgroup the field is a plain list of people, one per line: the team features
     * that act on the entry as a whole (edit rights and visibility for members, self
     * subscription, the team field, member notifications) do not apply.
     *
     * @return bool
     */
    public function is_fieldgroup_subfield(): bool {
        if ($this->infieldgroup === null) {
            $this->infieldgroup = false;
            $fieldgroups = array_filter(
                \mod_datalynx\local\definition_cache::get_records($this->dlx->id(), 'fields'),
                fn(stdClass $field): bool => $field->type === 'fieldgroup'
            );
            foreach (array_column($fieldgroups, 'param1') as $param1) {
                $entries = json_decode((string) $param1, true);
                foreach (\datalynxfield_fieldgroup\field::normalise_subfields(is_array($entries) ? $entries : []) as $entry) {
                    if ((int) explode(':', $entry, 2)[0] === (int) $this->id()) {
                        return $this->infieldgroup = true;
                    }
                }
            }
        }
        return $this->infieldgroup;
    }

    /**
     * The users the edit form offers for an entry's team.
     *
     * @param stdClass $entry
     * @return int[]
     */
    public function get_offered_userids(stdClass $entry): array {
        global $USER;
        $authorid = $entry->userid ?? $USER->id;
        $menu = $this->options_menu(false, false, $this->usercanaddself ? 0 : $authorid);
        return array_values(array_filter(array_map('intval', array_keys($menu)), fn(int $id): bool => $id > 0));
    }

    /**
     * The members of a team whom the edit form does not offer, e.g. because they are no longer
     * enrolled. The form cannot keep them, so saving the entry removes them from the team.
     *
     * @param stdClass $entry
     * @param array $memberids the stored team
     * @return string[] user id => full name
     */
    public function get_members_not_offered(stdClass $entry, array $memberids): array {
        global $DB;

        $memberids = array_filter(array_map('intval', $memberids), fn(int $id): bool => $id > 0);
        $missing = array_values(array_diff($memberids, $this->get_offered_userids($entry)));
        if (!$missing) {
            return [];
        }
        $users = $DB->get_records_list('user', 'id', $missing, '', 'id, ' . implode(', ', \core_user\fields::get_name_fields()));
        $names = [];
        foreach ($missing as $userid) {
            $names[$userid] = isset($users[$userid]) ? fullname($users[$userid]) : "#{$userid}";
        }
        return $names;
    }

    /**
     *
     * {@inheritdoc}
     * @see datalynxfield_base::prepare_import_content()
     * @param mixed $data Data object to populate.
     * @param array $importsettings Import settings.
     * @param ?array $csvrecord CSV record data.
     * @param ?int $entryid Entry ID.
     * @return bool True on success.
     */
    public function prepare_import_content(&$data, $importsettings, ?array $csvrecord = null, ?int $entryid = null): bool {
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
                $userids = [];
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
     * Get options menu for selection.
     *
     * @param bool $addnoselection
     * @param bool $makelinks
     * @param int $excludeuser
     * @param bool $allowall
     * @return array
     */
    public function options_menu(
        $addnoselection = false,
        $makelinks = false,
        $excludeuser = 0,
        $allowall = false
    ): array {
        $fieldid = $this->field->id;
        if (!isset(self::$allusers[$fieldid])) {
            $this->init_user_menu();
        }

        $options = [];
        $options += [-999 => null]; // NULL to "not" show in lists.

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

    /**
     * No members: the member list of an empty selection is stored as [].
     *
     * @return string[]
     */
    public static function get_empty_contents(): array {
        return ['', '[]'];
    }

    /**
     * {@inheritDoc}
     * @see datalynxfield_base::get_search_sql()
     * @param array $search Search criteria array.
     * @return array SQL fragment, params, and join flag.
     */
    public function get_search_sql(array $search): array {
        global $DB;
        static $i = 0;
        [$not, $operator, $value] = $search;
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";

        $sql = "1";
        $params = [];
        $usecontent = false;

        if ($operator === 'USER' || $operator === 'OTHER_USER' || $operator === 'ANY_OF') {
            // Resolve membership by decoding the JSON content in PHP rather than matching
            // the raw text with LIKE. This is format-agnostic: it works whether the user
            // ids were stored as JSON integers ([5,12]) or strings (["5","12"]), because
            // get_all_userids_in_all_entries() json_decodes each row and PHP coerces a
            // numeric string array key to the same integer key.
            if ($operator === 'USER') {
                global $USER;
                $targetuserids = [$USER->id];
            } else {
                // OTHER_USER (view filter) and ANY_OF (customfilter) both carry an array
                // of selected user ids in $value.
                $targetuserids = is_array($value) ? $value : [$value];
            }

            // Collect, de-duplicated, every entry id in which one of the target users is a
            // team member.
            $usermap = $this->get_all_userids_in_all_entries();
            $entryids = [];
            foreach ($targetuserids as $userid) {
                if (isset($usermap[$userid])) {
                    foreach ($usermap[$userid] as $entryid) {
                        $entryids[$entryid] = $entryid;
                    }
                }
            }

            // Match against the entry id set; $usecontent stays false (no content join).
            if (!empty($entryids)) {
                [$insql, $inparams] = $DB->get_in_or_equal(
                    $entryids,
                    SQL_PARAMS_NAMED,
                    "df_{$fieldid}_x_",
                    !$not
                );
                $params = array_merge($params, $inparams);
                $sql = " (e.id $insql) ";
            } else {
                // No entry matches: a positive search yields nothing, a NOT search yields
                // everything.
                $sql = $not ? " 1 = 1 " : " 1 = 0 ";
            }
            $usecontent = false;
        } else if ($operator === '') {
            // This is the "empty" operator.
            $usecontent = false;
            $sqlnot = $DB->sql_like("content", ":{$name}_hascontent");

            // Autocomplete stores [] if empty. Has content when at least [1].
            $params["{$name}_hascontent"] = "___%";

            if ($eids = $this->get_entry_ids_for_content($sqlnot, $params)) {
                // There are
                                                                              // non-empty.
                                                                              // Contents.
                [$contentids, $paramsnot] = $DB->get_in_or_equal(
                    $eids,
                    SQL_PARAMS_NAMED,
                    "df_{$fieldid}_x_",
                    !!$not
                );
                $params = array_merge($params, $paramsnot);
                $sql = " (e.id $contentids) ";
            } else {
                // There are no non-empty contents.
                if ($not) {
                    $sql = " 1 = 0 ";
                } else {
                    $sql = " 1 = 1 ";
                }
            }
        }

        return [$sql, $params, $usecontent];
    }

    /**
     * Parse search parameters.
     *
     * @param object $formdata
     * @param int $i
     * @return mixed
     */
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

    /**
     * Format content for storage.
     *
     * @param stdClass $entry
     * @param ?array $values
     * @return array
     */
    protected function format_content(stdClass $entry, ?array $values = null): array {
        $fieldid = $this->field->id;
        $oldcontents = [];
        $contents = [];

        // Old contents.
        if (isset($entry->{"c{$fieldid}_content"})) {
            $oldcontents[] = $entry->{"c{$fieldid}_content"};
        }

        // Parse values.
        $first = reset($values);
        $selected = !empty($first) ? $first : [];

        // Normalise to a canonical list of positive integer user ids, regardless of the caller:
        // the entry form and CSV import submit strings, while rules (e.g. teammemberbyprofile)
        // pass integers. format_content() is the single write chokepoint for every save path, so
        // normalising here guarantees identical storage everywhere. The -999 sentinel used to force
        // submission of an empty autocomplete is dropped as part of the same filter.
        $selected = array_values(array_filter(
            array_map('intval', (array) $selected),
            static fn($id) => $id > 0
        ));

        $contents[] = json_encode($selected); // Empty values are kept as [].
        return [$contents, $oldcontents];
    }

    /**
     * Get supported search operators.
     *
     * @return array
     */
    public function get_supported_search_operators(): array {
        return ['' => get_string('empty', 'datalynx'),
            'USER' => get_string('iamteammember', 'datalynx'),
            'OTHER_USER' => get_string('useristeammember', 'datalynx')];
    }

    /**
     * Also ANY_OF, which the custom filter writes and get_search_sql() treats like OTHER_USER.
     *
     * @return string[]
     */
    public function get_accepted_search_operators(): array {
        return array_merge(parent::get_accepted_search_operators(), ['ANY_OF']);
    }

    /**
     * Check if group by is supported.
     *
     * @return bool
     */
    public function supports_group_by(): bool {
        return false;
    }

    /**
     * Trigger one team_updated event when a save added or removed team members.
     *
     * The event carries the ids of the added and removed members only: the event data is
     * written to the logs (and exported with the acting user's data), so no user records go
     * into it (mod_datalynx#321).
     *
     * @param stdClass $entry
     * @param stdClass $field
     * @param array|null $oldmembers
     * @param array $newmembers
     */
    public function notify_team_members($entry, $field, $oldmembers, $newmembers): void {
        $ids = fn($members) => array_values(array_unique(array_filter(
            array_map('intval', is_array($members) ? $members : []),
            fn(int $id) => $id > 0
        )));
        $oldmembers = $ids($oldmembers);
        $newmembers = $ids($newmembers);

        $added = array_values(array_diff($newmembers, $oldmembers));
        $removed = array_values(array_diff($oldmembers, $newmembers));
        if (!$added && !$removed) {
            return;
        }

        \mod_datalynx\event\team_updated::create([
            'context' => $this->dlx->context,
            'objectid' => $entry->id,
            'other' => [
                'dataid' => (int) $field->dataid,
                'fieldid' => (int) $field->id,
                'name' => $field->name,
                'addedmembers' => $added,
                'removedmembers' => $removed,
            ],
        ])->trigger();
    }

    /**
     * Allow array in filter definition.
     *
     * @param array $searchparams Search parameters array.
     * @return string|false Formatted search value or false.
     */
    public function format_search_value($searchparams): string|false {
        [$not, $operator, $value] = $searchparams;
        if (is_array($value)) {
            $selected = implode(', ', $value);
            return $not . ' ' . $operator . ' ' . $selected;
        } else {
            return false;
        }
    }

    /**
     * Normalise stored teammemberselect content to the canonical format used by
     * {@see self::format_content()}: a JSON array of positive integer user ids.
     *
     * Historically different writers stored the member list with different element types
     * (the entry form and CSV import stored quoted strings, rules stored integers). This
     * brings existing rows into one consistent shape. Used by the upgrade migration and by
     * restore (after_restore) so on-disk data matches what every runtime save now writes.
     * Idempotent: rows already in canonical form are left untouched.
     *
     * @param int|null $dataid Restrict to one datalynx instance, or null for all instances.
     * @return int Number of content rows updated.
     */
    public static function normalize_stored_content(?int $dataid = null): int {
        global $DB;

        $params = [];
        $sql = "SELECT c.id, c.content
                  FROM {datalynx_contents} c
                  JOIN {datalynx_fields} f ON f.id = c.fieldid
                 WHERE f.type = 'teammemberselect'";
        if ($dataid !== null) {
            $sql .= " AND f.dataid = :dataid";
            $params['dataid'] = $dataid;
        }

        $rs = $DB->get_recordset_sql($sql, $params);
        $updated = 0;
        foreach ($rs as $row) {
            if ($row->content === null || $row->content === '') {
                continue;
            }
            $decoded = json_decode($row->content, true);
            if (!is_array($decoded)) {
                continue;
            }
            $normalized = array_values(array_filter(
                array_map('intval', $decoded),
                static fn($id) => $id > 0
            ));
            $canonical = json_encode($normalized);
            if ($canonical !== $row->content) {
                $DB->set_field('datalynx_contents', 'content', $canonical, ['id' => $row->id]);
                $updated++;
            }
        }
        $rs->close();

        return $updated;
    }

    /**
     * Remove users from the stored member lists, e.g. because their accounts were deleted.
     *
     * Works directly on the content rows: nobody is to be notified about this and no rule must
     * fire, so the entry API is bypassed on purpose. The member list is decoded here because it
     * holds integers or (in legacy rows) numeric strings and cannot be matched reliably in SQL.
     *
     * @param int[]|null $userids Users to remove, or null to remove every user whose account is deleted.
     * @param int|null $dataid Restrict to one datalynx instance, or null for all instances.
     * @return int Number of content rows updated.
     */
    public static function remove_users_from_teams(?array $userids, ?int $dataid = null): int {
        global $DB;

        $params = [];
        $sql = "SELECT c.id, c.content
                  FROM {datalynx_contents} c
                  JOIN {datalynx_fields} f ON f.id = c.fieldid
                 WHERE f.type = 'teammemberselect'";
        if ($dataid !== null) {
            $sql .= " AND f.dataid = :dataid";
            $params['dataid'] = $dataid;
        }
        if ($userids !== null) {
            $userids = array_map('intval', $userids);
            if (!$userids) {
                return 0;
            }
            // Narrow the rows down; the decoded list below decides.
            $likes = [];
            foreach (array_values($userids) as $i => $userid) {
                $likes[] = $DB->sql_like('c.content', ":like$i");
                $params["like$i"] = '%' . $userid . '%';
            }
            $sql .= " AND (" . implode(' OR ', $likes) . ")";
        }

        $rs = $DB->get_recordset_sql($sql, $params);
        $rows = [];
        foreach ($rs as $row) {
            $members = json_decode((string) $row->content, true);
            if (is_array($members) && $members) {
                $rows[$row->id] = array_map('intval', array_filter($members, 'is_numeric'));
            }
        }
        $rs->close();

        if ($userids === null) {
            $allmembers = array_unique(array_merge([], ...array_values($rows)));
            $userids = [];
            foreach (array_chunk($allmembers, 1000) as $chunk) {
                [$insql, $inparams] = $DB->get_in_or_equal($chunk);
                // Members without a user record at all are gone just the same.
                $existing = $DB->get_fieldset_select('user', 'id', "deleted = 0 AND id $insql", $inparams);
                $userids = array_merge($userids, array_diff($chunk, $existing));
            }
        }

        $updated = 0;
        foreach ($rows as $contentid => $members) {
            $remaining = array_values(array_diff($members, $userids));
            if (count($remaining) !== count($members)) {
                $DB->set_field('datalynx_contents', 'content', json_encode($remaining), ['id' => $contentid]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Return all user ids of the field across all entries in the specific datalynx instance.
     *
     * @param string $insql to be used with: AND entryid $insql
     * @param array $inparams
     * @return array a multidimensional array with userid as keys and an array of entryids as value.
     */
    public function get_all_userids_in_all_entries(string $insql = '', array $inparams = []): array {
        global $DB;
        $sql = "SELECT c.id, c.content, c.entryid
                FROM {datalynx_contents} c
                JOIN {datalynx_entries} e ON e.id = c.entryid
                WHERE c.fieldid = :fieldid
                  AND e.dataid = :datalynxid";
        $params = [
                'fieldid' => $this->field->id,
                'datalynxid' => $this->dlx->id(),
        ];
        if (!empty($insql)) {
            $sql .= " AND c.entryid $insql";
            $params = array_merge($params, $inparams);
        }
        $records = $DB->get_records_sql($sql, $params);
        // Iterate through the records and extract user IDs.
        $userarray = [];
        foreach ($records as $record) {
            $content = json_decode($record->content, true);
            if (is_array($content)) {
                foreach ($content as $userid) {
                    if (is_numeric($userid)) {
                        // Ensure the value is a valid user ID.
                        $userarray[$userid][] = $record->entryid; // Use boolean to simplify counting.
                    }
                }
            }
        }
        if (!empty($userarray)) {
            return $userarray;
        } else {
            return [];
        }
    }

    /**
     * Ids of the entries whose team holds a member whose full name contains the search term.
     *
     * Reads the members stored in the entries, so members no longer enrolled are found too, and
     * the member list may hold ids as numbers or strings.
     *
     * @param string $search
     * @return int[]
     */
    public function get_entryids_with_member_named(string $search): array {
        global $DB;

        $usermap = $this->get_all_userids_in_all_entries();
        if (!$usermap || trim($search) === '') {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($usermap), SQL_PARAMS_NAMED, 'tmqsuser');
        $params['tmqsname'] = '%' . $DB->sql_like_escape($search) . '%';
        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $userids = $DB->get_fieldset_sql(
            "SELECT u.id FROM {user} u WHERE u.id $insql AND u.deleted = 0 AND " .
                $DB->sql_like($fullname, ':tmqsname', false, false),
            $params
        );

        $entryids = [];
        foreach ($userids as $userid) {
            foreach ($usermap[$userid] as $entryid) {
                $entryids[$entryid] = (int) $entryid;
            }
        }
        return array_values($entryids);
    }

    /**
     * Are fields of this field type suitable for use in customfilters?
     *
     * @return bool
     */
    public static function is_customfilterfield(): bool {
        return true;
    }

    /**
     * Is $value a valid content or do we see an empty input?
     * @param mixed $value Field value to check.
     * @return bool
     */
    public static function is_fieldvalue_empty($value): bool {
        // Empty unless a user is chosen. The form posts -999 (under the key -1) for an empty
        // selection, so it is a placeholder wherever it sits.
        foreach ((array) $value as $userid) {
            if ((int) $userid > 0) {
                return false;
            }
        }
        return true;
    }
}
