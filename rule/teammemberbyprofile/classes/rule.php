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
 * @package datalynxrule_teammemberbyprofile
 * @subpackage teammemberbyprofile
 * @copyright 2026 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxrule_teammemberbyprofile;

use mod_datalynx\local\rule\base;
use stdClass;

/**
 * Rule that auto-populates a teammemberselect field with the privileged users whose profile
 * field matches the entry's selected option of a chosen single-select field.
 *
 * This is the write-side counterpart of the select field's MY_PROFILE filter operator: instead
 * of a viewer seeing matching entries, the entry receives the matching privileged users as team
 * members whenever it is created or updated.
 *
 * @package datalynxrule_teammemberbyprofile
 */
class rule extends base {
    /** @var string The rule type. */
    public $type = 'teammemberbyprofile';

    /**
     * React to an entry_created / entry_updated event by writing the matching users into the
     * configured teammemberselect field.
     *
     * {@inheritDoc}
     * @see base::trigger()
     * @param \core\event\base $event
     * @return bool
     */
    public function trigger(\core\event\base $event) {
        global $DB;

        $selectfieldid = (int) ($this->rule->param6 ?? 0);
        $shortname = trim((string) ($this->rule->param7 ?? ''));
        $teamfieldid = (int) ($this->rule->param8 ?? 0);
        $tier = trim((string) ($this->rule->param9 ?? ''));
        $mode = ($this->rule->param2 ?? 'overwrite') === 'merge' ? 'merge' : 'overwrite';

        if (!$selectfieldid || $shortname === '' || !$teamfieldid || $tier === '') {
            return true; // Rule not fully configured.
        }

        $entryid = (int) ($event->get_data()['objectid'] ?? 0);
        if (!$entryid) {
            return true;
        }

        $selectfield = $this->dlx->get_field_from_id($selectfieldid);
        $teamfield = $this->dlx->get_field_from_id($teamfieldid);
        if (empty($selectfield) || empty($teamfield)) {
            return true;
        }
        if ($selectfield->type !== 'select' || $teamfield->type !== 'teammemberselect') {
            return true;
        }

        // The entry's selected option key (a single select stores the scalar 1-based key).
        $optionkey = $DB->get_field(
            'datalynx_contents',
            'content',
            ['entryid' => $entryid, 'fieldid' => $selectfieldid, 'lineid' => 0]
        );
        if ($optionkey === false || $optionkey === null || $optionkey === '') {
            return true; // Nothing selected; leave the team field untouched.
        }

        // Candidate users: enrolled users holding the chosen view-privilege capability.
        $capability = 'mod/datalynx:viewprivilege' . $tier;
        $matchedusers = [];
        foreach (get_enrolled_users($this->dlx->context, $capability, 0, 'u.*') as $user) {
            $value = $selectfield->resolve_user_profile_value($user, $shortname);
            if ($value === null) {
                continue;
            }
            if ((string) $selectfield->get_option_key_for_value($value) === (string) $optionkey) {
                $matchedusers[(int) $user->id] = $user;
            }
        }

        // Current team content for this entry.
        $row = $DB->get_record(
            'datalynx_contents',
            ['entryid' => $entryid, 'fieldid' => $teamfieldid, 'lineid' => 0]
        );
        $existing = [];
        if ($row && $row->content !== null && $row->content !== '') {
            $decoded = json_decode($row->content, true);
            if (is_array($decoded)) {
                $existing = array_values(array_map('intval', $decoded));
            }
        }

        // Compose the new member list according to the configured mode.
        if ($mode === 'merge') {
            $newteam = array_values(array_unique(array_merge($existing, array_keys($matchedusers))));
        } else {
            $newteam = array_values(array_unique(array_keys($matchedusers)));
        }

        // Respect the team field's maximum size: truncate (ordered by name) and log.
        $maxsize = (int) $teamfield->field->param1;
        if ($maxsize > 0 && count($newteam) > $maxsize) {
            $newteam = array_slice($this->order_users_by_name($newteam, $matchedusers), 0, $maxsize);
            debugging("datalynxrule_teammemberbyprofile: the matched team for entry {$entryid} exceeded " .
                "the maximum size {$maxsize} and was truncated.", DEBUG_DEVELOPER);
        }

        // Idempotency: skip the write when the resulting set is unchanged. This avoids spurious
        // team_updated events / notifications and any cross-rule cascade.
        sort($newteam);
        $sortedexisting = $existing;
        sort($sortedexisting);
        if ($newteam === $sortedexisting) {
            return true;
        }

        // Write via the field itself. teammemberselect::update_content() writes datalynx_contents and
        // only fires team_updated (never entry_updated), so this rule does not re-trigger itself.
        $entry = new stdClass();
        $entry->id = $entryid;
        $entry->{"c{$teamfieldid}_id"} = $row ? $row->id : null;
        $entry->{"c{$teamfieldid}_content"} = $row ? $row->content : null;
        $teamfield->update_content($entry, [$newteam]);

        return true;
    }

    /**
     * Orders a list of user ids by lastname, firstname, id for a stable truncation result.
     *
     * @param int[] $ids User ids to order.
     * @param array $users Map of userid => user record (matched users); ids missing here sort first.
     * @return int[] The ordered ids.
     */
    protected function order_users_by_name(array $ids, array $users): array {
        usort($ids, function ($a, $b) use ($users) {
            $ua = $users[$a] ?? null;
            $ub = $users[$b] ?? null;
            $cmp = strcoll($ua->lastname ?? '', $ub->lastname ?? '');
            if ($cmp === 0) {
                $cmp = strcoll($ua->firstname ?? '', $ub->firstname ?? '');
            }
            return $cmp !== 0 ? $cmp : ($a <=> $b);
        });
        return $ids;
    }
}
