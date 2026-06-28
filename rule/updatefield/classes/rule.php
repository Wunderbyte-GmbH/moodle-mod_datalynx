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

namespace datalynxrule_updatefield;

use mod_datalynx\local\field\datalynxfield_base;
use mod_datalynx\local\field\datalynxfield_option_multiple;
use mod_datalynx\local\rule\base;

/**
 * Update-field rule.
 *
 * When the configured events fire and the (shared) trigger conditions match, this rule writes a
 * fixed value into one field of the triggering entry. The write goes straight through the field's
 * own {@see datalynxfield_base::update_content()} (the same safe path used by the approval toggle),
 * never through a view/process_entries(), so it cannot start an event→rule→event loop. As an extra
 * safeguard a request-scoped re-entrancy guard prevents the same rule from acting twice on the same
 * entry within one request.
 *
 * @package    datalynxrule_updatefield
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule extends base {
    /** @var string Rule type */
    public $type = 'updatefield';

    /**
     * Field types this rule can write to. Each stores a single scalar (or, for the option-multiple
     * subtypes, a set of option keys) in datalynx_contents and accepts a plain value via
     * update_content(), which keeps the write predictable and safe.
     *
     * @var string[]
     */
    const SUPPORTED_TYPES = ['text', 'textarea', 'select', 'radiobutton', 'checkbox', 'multiselect'];

    /**
     * Re-entrancy guard: "{ruleid}:{entryid}" keys currently being processed in this request.
     *
     * @var array
     */
    protected static $processing = [];

    /**
     * Whether the given field may be used as an update target.
     *
     * @param datalynxfield_base $field
     * @return bool
     */
    public static function is_supported_field($field): bool {
        return $field instanceof datalynxfield_base
            && in_array($field->type, self::SUPPORTED_TYPES, true)
            && $field->is_datalynx_content();
    }

    /**
     * Trigger rule: write the configured value into the target field of the triggering entry.
     *
     * @param \core\event\base $event
     * @return bool true when the rule ran to completion (whether or not the value changed)
     */
    public function trigger(\core\event\base $event) {
        global $DB;

        $entryid = $this->resolve_entryid($event);
        if (!$entryid) {
            return false;
        }

        // Only act when the entry satisfies the configured trigger conditions.
        $conditions = $this->get_trigger_conditions();
        if ($conditions && !$this->entry_matches_conditions($entryid, $conditions)) {
            return false;
        }

        // When "only on change" fields are configured, require at least one of them to have
        // actually changed. This only applies to the entry_updated event (the only one carrying a
        // changed-field list); see base::change_constraint_satisfied().
        $onchangeids = $this->get_onlyonchange_fieldids();
        if (
            $onchangeids && $event instanceof \mod_datalynx\event\entry_updated
            && !$this->change_constraint_satisfied($onchangeids, $event)
        ) {
            return false;
        }

        $fieldid = (int) ($this->rule->param2 ?? 0);
        if (!$fieldid) {
            return false;
        }

        // Re-entrancy guard: never let the same rule act twice on the same entry in one request.
        $key = $this->rule->id . ':' . $entryid;
        if (!empty(self::$processing[$key])) {
            return false;
        }
        self::$processing[$key] = true;

        try {
            $field = $this->dlx()->get_field_from_id($fieldid);
            if (!$field || !self::is_supported_field($field)) {
                return false;
            }

            $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
            if (!$entry) {
                return false;
            }

            // Attach the existing content (id + current value) for this field so update_content()
            // updates the existing row rather than inserting a duplicate, and so change detection
            // is accurate for the optional follow-up event.
            $existing = $DB->get_record(
                'datalynx_contents',
                ['entryid' => $entryid, 'fieldid' => $fieldid],
                'id, content'
            );
            $entry->{"c{$fieldid}_id"} = $existing ? $existing->id : null;
            $entry->{"c{$fieldid}_content"} = $existing ? $existing->content : null;

            $values = $this->value_for_field($field);
            $result = $field->update_content($entry, $values);
            // The update_content() call returns true when nothing changed, or an int id on insert/update.
            $changed = ($result !== true);

            // Optionally let other rules/notifications react, but only when the value really
            // changed. The re-entrancy guard above plus this "only on change" gate make the cascade
            // self-terminating: a second pass finds the value already set and fires no event.
            if ($changed && !empty($this->rule->param4)) {
                \mod_datalynx\event\entry_updated::create([
                    'context' => $this->dlx()->context,
                    'objectid' => $entryid,
                    'other' => ['dataid' => $this->dlx()->id(), 'changed_field_ids' => [$fieldid]],
                ])->trigger();
            }

            return true;
        } finally {
            unset(self::$processing[$key]);
        }
    }

    /**
     * Build the value array passed to the target field's update_content(), in the shape that
     * field type's format_content() expects.
     *
     * @param datalynxfield_base $field
     * @return array
     */
    protected function value_for_field($field): array {
        $stored = $this->rule->param3 ?? '';

        if ($field instanceof datalynxfield_option_multiple) {
            // Checkbox / multiselect: stored as a JSON array of option keys; format_content()
            // expects a single-element array whose element is the array of keys.
            $keys = json_decode((string) $stored, true);
            if (!is_array($keys)) {
                $keys = ($stored === '' || $stored === null) ? [] : [$stored];
            }
            return [array_values($keys)];
        }

        // Text / textarea / select / radiobutton: a single scalar value.
        return [(string) $stored];
    }
}
