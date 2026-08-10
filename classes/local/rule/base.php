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
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\rule;

use coding_exception;
use dml_exception;
use mod_datalynx\datalynx;
use moodle_exception;
use moodle_url;
use stdClass;

/**
 * Base class for Datalynx Rule Types
 */
abstract class base {
    /**
     * Reserved param9 key holding the "only trigger on change" field IDs.
     *
     * Stored alongside the numeric condition rows but kept separate from them so it applies
     * independently of the trigger conditions. Shared by all rule types that gate on changes.
     *
     * @var string
     */
    const ONCHANGE_KEY = '_onlyonchangefields';

    /**
     * Reserved param9 key holding the counterpart matching configuration.
     *
     * Kept beside the condition rows for the same reason as {@see self::ONCHANGE_KEY}: it is not
     * a condition on the triggering entry but a separate setting, and param9 is the one rule
     * param whose field ids the backup and restore code already walks.
     *
     * @var string
     */
    const MATCH_KEY = '_matchcriteria';

    /**
     * Subclasses must override the type with their name.
     * @var string
     */
    public $type = 'unknown';

    /**
     * The datalynx object that this rule belongs to.
     *
     * @var ?datalynx
     */
    public $dlx = null;

    /**
     * The rule object itself, if we know it.
     * @var object
     */
    public $rule = null;

    /**
     * base constructor.
     *
     * @param int|datalynx $dlx
     * @param int|object $rule
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct($dlx = 0, $rule = 0) {
        if (empty($dlx)) {
            throw new coding_exception('Datalynx id or object must be passed to view constructor.');
        } else {
            if ($dlx instanceof datalynx) {
                $this->dlx = $dlx;
            } else { // Datalynx id/object.
                $this->dlx = new datalynx($dlx);
            }
        }

        if (!empty($rule)) {
            // Variable $rule is the rule record.
            if (is_object($rule)) {
                $this->rule = $rule; // Programmer knows what they are doing, we hope.

                // Variable $rule is a rule id.
            } else {
                if ($ruleobj = $this->dlx->get_rule_from_id($rule)) {
                    $this->rule = $ruleobj->rule;
                } else {
                    throw new moodle_exception('invalidrule', 'datalynx', null, null, $rule);
                }
            }
        }

        if (empty($this->rule)) { // We need to define some default values.
            $this->set_rule();
        }
    }

    /**
     *
     * @param \core\event\base $event
     * @return bool
     */
    abstract public function trigger(\core\event\base $event);

    /**
     * Checks if the rule triggers on the given event
     *
     * @param string $eventname full name of the event (with namespaces)
     * @return bool
     */
    public function is_triggered_by($eventname) {
        if (!is_string($eventname) || $eventname === '') {
            return false;
        }

        $eventname = explode('\\', trim($eventname, '\\'))[2];
        $param1 = $this->rule->param1 ?? null;
        if (empty($param1)) {
            return false;
        }
        $triggers = array_map(
            function ($element) {
                return explode(':', $element)[0];
            },
            json_decode($param1, true) ?? []
        );
        return array_search($eventname, $triggers) !== false;
    }

    /**
     * Returns the list of the triggers
     *
     * @return array
     */
    public function get_triggers() {
        static $triggers = [];
        if (empty($triggers)) {
            $param1 = $this->rule->param1 ?? null;
            $triggers = empty($param1) ? [] : array_map(
                function ($element) {
                    return explode(':', $element)[0];
                },
                json_decode($param1, true) ?? []
            );
        }
        return $triggers;
    }

    /**
     * Resolve the entry id that the given event concerns.
     *
     * Comment events carry the comment id in objectid and the entry id in other.itemid;
     * all other (entry/rating/team) events carry the entry id directly in objectid.
     *
     * @param \core\event\base $event
     * @return int entry id, or 0 when none could be resolved
     */
    protected function resolve_entryid(\core\event\base $event): int {
        $eventname = (new \ReflectionClass($event))->getShortName();
        if (strpos($eventname, 'comment') !== false) {
            return (int) ($event->get_data()['other']['itemid'] ?? 0);
        }
        return (int) ($event->get_data()['objectid'] ?? 0);
    }

    /**
     * Resolve the trigger conditions for this rule as a customsearch array.
     *
     * Reads the JSON-stored multi-condition customsearch from param9, dropping the reserved
     * on-change key (which is not a condition row). Subclasses may override to add legacy
     * fallbacks.
     *
     * @return array customsearch aggregated by field id, or [] when no condition is set.
     */
    protected function get_trigger_conditions(): array {
        if (empty($this->rule->param9)) {
            return [];
        }
        $decoded = json_decode($this->rule->param9, true);
        if (!is_array($decoded)) {
            return [];
        }
        // The on-change field list and the matching configuration live under reserved keys, not
        // condition rows.
        unset($decoded[self::ONCHANGE_KEY], $decoded[self::MATCH_KEY]);
        return $decoded;
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
    protected function entry_matches_conditions(int $entryid, array $conditions): bool {
        global $DB;

        if (!$entryid) {
            return false;
        }

        [$tables, $where, $params] = $this->conditions_sql($conditions);
        $params['conddataid'] = $this->dlx()->id();
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
     * Build the search SQL for a customsearch through the saved-filter engine.
     *
     * @param array $conditions customsearch aggregated by field id
     * @return array [$tables, $where, $params]
     */
    private function conditions_sql(array $conditions): array {
        $dlx = $this->dlx();

        $filter = new \mod_datalynx\local\filter\datalynx_filter(
            (object) ['dataid' => $dlx->id(), 'customsearch' => $conditions]
        );
        $filter->init_filter_sql();

        return $filter->get_search_sql($dlx->get_fields());
    }

    /**
     * Entries other than the given one that match the conditions.
     *
     * The counterpart of {@see self::entry_matches_conditions()}: the same criteria, evaluated by
     * the same engine, but asking which other entries satisfy them rather than whether one
     * particular entry does.
     *
     * @param int $subjectentryid the entry to search around, always excluded from the result
     * @param array $conditions customsearch aggregated by field id
     * @param array $options 'excludeownauthor' (default true) drops the entries of the subject's
     *                       author, 'requireapproved' (default true) drops unapproved entries,
     *                       'limit' caps the number of results (0 for no cap)
     * @return int[] entry ids
     */
    protected function find_matching_entries(int $subjectentryid, array $conditions, array $options = []): array {
        global $DB;

        if (!$subjectentryid) {
            return [];
        }

        [$tables, $where, $params] = $this->conditions_sql($conditions);
        $params['conddataid'] = $this->dlx()->id();
        $params['condeid'] = $subjectentryid;

        $extra = '';
        if ($options['excludeownauthor'] ?? true) {
            $params['condauthor'] = (int) $DB->get_field('datalynx_entries', 'userid', ['id' => $subjectentryid]);
            $extra .= ' AND e.userid <> :condauthor';
        }
        if ($options['requireapproved'] ?? true) {
            $extra .= ' AND e.approved = 1';
        }

        // DISTINCT because the content joins are LEFT JOINs and a field used inside a fieldgroup
        // has one content record per repetition, which would otherwise return the entry once per
        // record. entry_matches_conditions() does not need it: it only asks whether a row exists.
        $sql = "SELECT DISTINCT e.id
                  FROM {datalynx_entries} e
                  JOIN {user} u ON u.id = e.userid
                       $tables
                 WHERE e.dataid = :conddataid AND e.id <> :condeid $extra $where";

        $records = $DB->get_records_sql($sql, $params, 0, (int) ($options['limit'] ?? 0));

        return array_map('intval', array_keys($records));
    }

    /**
     * Field IDs configured for the "only trigger when these field values change" option.
     *
     * Stored as a JSON array under the reserved {@see self::ONCHANGE_KEY} key in param9, kept
     * separate from the numeric condition rows so it applies independently of them.
     *
     * @return array
     */
    protected function get_onlyonchange_fieldids(): array {
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
    protected function change_constraint_satisfied(array $onchangeids, \core\event\base $event): bool {
        if (!$onchangeids) {
            return true;
        }
        $changed = $event->other['changed_field_ids'] ?? [];
        return (bool) array_intersect($onchangeids, $changed);
    }

    /**
     * Sets up a rule object
     *
     * @param ?stdClass $forminput Form input data.
     */
    public function set_rule($forminput = null) {
        $this->rule = new stdClass();
        $this->rule->id = !empty($forminput->id) ? $forminput->id : 0;
        $this->rule->type = $this->type;
        $this->rule->dataid = $this->dlx->id();
        $this->rule->name = !empty($forminput->name) && is_scalar($forminput->name) ? trim((string) $forminput->name) : '';
        $this->rule->description = !empty($forminput->description) && is_scalar($forminput->description) ?
                trim((string) $forminput->description) : '';
        $this->rule->enabled = isset($forminput->enabled) ? $forminput->enabled : 1;
        for ($i = 1; $i <= 10; $i++) {
            $value = $forminput->{"param$i"} ?? null;
            if (is_string($value)) {
                $this->rule->{"param$i"} = $value !== '' ? trim($value) : null;
            } else if (is_scalar($value)) {
                $this->rule->{"param$i"} = (string) $value;
            } else {
                $this->rule->{"param$i"} = $value ?: null;
            }
        }
    }

    /**
     * Insert a new rule in the database
     * @param string $fromform
     * @return bool|int
     * @throws dml_exception
     */
    public function insert_rule($fromform = null) {
        global $DB, $OUTPUT;

        if (!empty($fromform)) {
            $this->set_rule($fromform);
        }
        $this->rule->id = $DB->insert_record('datalynx_rules', $this->rule);
        if (!$this->rule->id) {
            echo $OUTPUT->notification('Insertion of new rule failed!');
            return false;
        } else {
            return $this->rule->id;
        }
    }

    /**
     * Update a rule in the database
     *
     * @param null $fromform
     * @return bool
     * @throws dml_exception
     */
    public function update_rule($fromform = null) {
        global $DB, $OUTPUT;
        if (!empty($fromform)) {
            $this->set_rule($fromform);
        }

        if (!$DB->update_record('datalynx_rules', $this->rule)) {
            echo $OUTPUT->notification('updating of rule failed!');
            return false;
        }
        return true;
    }

    /**
     * Delete a rule completely
     *
     * @return bool
     * @throws dml_exception
     */
    public function delete_rule() {
        global $DB;

        if (!empty($this->rule->id)) {
            $DB->delete_records('datalynx_rules', ['id' => $this->rule->id]);
        }
        return true;
    }

    /**
     * Returns the rule id
     *
     * @return number
     */
    public function get_id() {
        return $this->rule->id;
    }

    /**
     * Is enabled
     *
     * @return boolean
     */
    public function is_enabled() {
        return $this->rule->enabled;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Returns the name of the rule
     * @return string
     */
    public function get_name() {
        return $this->rule->name;
    }

    /**
     * Returns the type name of the rule
     *
     * @return string
     */
    public function typename() {
        return get_string('pluginname', "datalynxrule_{$this->type}");
    }

    /**
     * Get datalynx object
     *
     * @return ?datalynx
     */
    public function dlx() {
        return $this->dlx;
    }

    /**
     * Get form
     *
     * @return mixed
     * @throws moodle_exception
     */
    public function get_form() {
        $formclass = manager::get_rule_form_class_name($this->type);
        $actionurl = new moodle_url(
            '/mod/datalynx/rule/rule_edit.php',
            ['d' => $this->dlx->id(), 'rid' => $this->get_id(), 'type' => $this->type]
        );
        return new $formclass($actionurl, ['rule' => $this]);
    }

    /**
     * To form
     *
     * @return object
     */
    public function to_form() {
        return $this->rule;
    }

    /**
     * Get SQL query
     *
     * @return string
     */
    public function get_select_sql() {
        if ($this->rule->id > 0) {
            $id = " c{$this->rule->id}.id AS c{$this->rule->id}_id ";
            $content = $this->get_sql_compare_text() . " AS c{$this->rule->id}_content";
            return " $id , $content ";
        } else {
            return '';
        }
    }

    /**
     * Get sort part for SQL query
     *
     * @param string $paramname
     * @param string $paramcount
     * @return ?array
     */
    public function get_sort_from_sql($paramname = 'sortie', $paramcount = '') {
        $ruleid = $this->rule->id;
        if ($ruleid > 0) {
            $sql = " LEFT JOIN {datalynx_contents} c$ruleid
            ON (c$ruleid.entryid = e.id AND c$ruleid.ruleid = :$paramname$paramcount) ";
            return [$sql, $ruleid];
        } else {
            return null;
        }
    }

    /**
     * Returngs empty string??
     * @return string
     */
    public function get_sort_sql() {
        return '';
    }
}
