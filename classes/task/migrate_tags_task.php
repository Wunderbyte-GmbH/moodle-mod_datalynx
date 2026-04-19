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
 * Ad-hoc task to migrate tags after restore.
 *
 * @package mod_datalynx
 * @copyright 2024 onwards David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\task;

/**
 * Class migrate_tags_task
 * @package mod_datalynx\task
 */
class migrate_tags_task extends \core\task\adhoc_task {
    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        $courseid = $data->courseid;
        $datalynxid = $data->datalynxid ?? null;

        // Fetch views only for the restored course/instance.
        $params = [$courseid];
        $sql = "SELECT v.* FROM {datalynx_views} v
                  JOIN {datalynx} d ON v.datalynxid = d.id
                 WHERE d.course = ?";
        if ($datalynxid) {
            $sql .= " AND d.id = ?";
            $params[] = $datalynxid;
        }

        $views = $DB->get_records_sql($sql, $params);

        $textcolumns = [
            'section', 'param1', 'param2', 'param3', 'param4', 'param5',
            'param6', 'param7', 'param8', 'param9', 'param10',
        ];

        foreach ($views as $view) {
            $changed = false;
            foreach ($textcolumns as $col) {
                if (empty($view->$col)) {
                    continue;
                }

                $newval = preg_replace('/#\{\{(viewlink:[^}]+)\}\}#/', '##$1##', $view->$col);
                $newval = preg_replace('/#\{\{(viewsesslink:[^}]+)\}\}#/', '##$1##', $newval);

                if ($newval !== $view->$col) {
                    $view->$col = $newval;
                    $changed = true;
                }
            }
            if ($changed) {
                $DB->update_record('datalynx_views', $view);
            }
        }

        // Clear patterns cache for these views.
        if ($datalynxid) {
            $DB->set_field('datalynx_views', 'patterns', null, ['datalynxid' => $datalynxid]);
        } else {
            $DB->set_field_select(
                'datalynx_views',
                'patterns',
                null,
                'datalynxid IN (SELECT id FROM {datalynx} WHERE course = ?)',
                [$courseid]
            );
        }
    }
}
