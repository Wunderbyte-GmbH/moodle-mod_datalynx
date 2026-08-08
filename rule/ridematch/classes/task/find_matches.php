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

namespace datalynxrule_ridematch\task;

use core\task\adhoc_task;
use datalynxrule_ridematch\matcher_service;

/**
 * Looks for journeys matching one saved entry, and notifies the pairs it finds.
 *
 * Runs out of band because datalynx dispatches rules synchronously from inside the
 * entry save; see {@see \datalynxrule_ridematch\rule::trigger()}.
 *
 * @package    datalynxrule_ridematch
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class find_matches extends adhoc_task {
    /**
     * A human readable name for the task, shown in the task logs.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskfindmatches', 'datalynxrule_ridematch');
    }

    /**
     * Sweep for matches and send the notifications.
     */
    public function execute(): void {
        $data = (array) $this->get_custom_data();

        $ruleid = (int) ($data['ruleid'] ?? 0);
        $dataid = (int) ($data['dataid'] ?? 0);
        $entryid = (int) ($data['entryid'] ?? 0);

        if (!$ruleid || !$dataid || !$entryid) {
            mtrace('datalynxrule_ridematch: incomplete task data, nothing to do.');

            return;
        }

        $service = matcher_service::create($dataid, $ruleid);
        if ($service === null) {
            // The rule or the activity was removed between the save and this run.
            mtrace("datalynxrule_ridematch: rule {$ruleid} is gone, skipping entry {$entryid}.");

            return;
        }

        $notified = $service->process_entry($entryid);
        mtrace("datalynxrule_ridematch: entry {$entryid} produced {$notified} new match(es).");
    }
}
