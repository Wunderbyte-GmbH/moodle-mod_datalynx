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

namespace datalynxrule_eventnotification\task;

use core\task\adhoc_task;
use mod_datalynx\datalynx;
use moodle_exception;

/**
 * Looks for the entries matching the one an event touched, and notifies the pairs it finds.
 *
 * Runs out of band because datalynx dispatches rules synchronously from inside the entry save,
 * while a search for counterparts costs a query per criterion and, for a route tested in the
 * direction the corridor query cannot express, one more per candidate.
 *
 * @package    datalynxrule_eventnotification
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class find_matches extends adhoc_task {
    /**
     * A human readable name for the task, shown in the task logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskfindmatches', 'datalynxrule_eventnotification');
    }

    /**
     * Sweep for matching entries and send the notifications.
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();

        $ruleid = (int) ($data['ruleid'] ?? 0);
        $dataid = (int) ($data['dataid'] ?? 0);
        $context = (array) ($data['context'] ?? []);

        if (!$ruleid || !$dataid || empty($context['entryid'])) {
            mtrace('datalynxrule_eventnotification: incomplete task data, nothing to do.');

            return;
        }

        try {
            $rule = (new datalynx($dataid))->get_rule_manager()->get_rule_from_id($ruleid);
        } catch (moodle_exception $e) {
            // The activity was removed between the save and this run.
            mtrace("datalynxrule_eventnotification: datalynx {$dataid} is gone, skipping rule {$ruleid}.");

            return;
        }

        if (!$rule instanceof \datalynxrule_eventnotification\rule || !$rule->is_enabled()) {
            mtrace("datalynxrule_eventnotification: rule {$ruleid} is disabled or of another type, skipping.");

            return;
        }

        $rule->run_matching($context);
    }
}
