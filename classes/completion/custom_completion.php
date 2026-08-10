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
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Custom completion rules for mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_datalynx\completion;

use core_completion\activity_custom_completion;

/**
 * Defines mod_datalynx's custom completion rules and reports their state for a given user.
 *
 * @package mod_datalynx
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetch the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $dlx = $DB->get_record('datalynx', ['id' => $this->cm->instance], 'id, approval, completionentries', MUST_EXIST);

        // Count the user's entries; when approval is enabled only approved entries count.
        $params = ['dataid' => $dlx->id, 'userid' => $this->userid];
        if ($dlx->approval) {
            $params['approved'] = 1;
        }
        $userentries = $DB->count_records('datalynx_entries', $params);

        return ($dlx->completionentries <= $userentries) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionentries'];
    }

    /**
     * Return an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        $entries = $this->cm->customdata['customcompletionrules']['completionentries'] ?? 0;
        return [
            'completionentries' => get_string('completiondetail:entries', 'datalynx', $entries),
        ];
    }

    /**
     * Return the completion rules in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionentries',
            'completionusegrade',
            'completionpassgrade',
        ];
    }
}
