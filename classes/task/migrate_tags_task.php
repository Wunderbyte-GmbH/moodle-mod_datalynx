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
     * Execute the task: migrate the views of one instance, or of all instances of a course
     * (tasks queued by older versions).
     */
    public function execute() {
        global $DB;
        $data = $this->get_custom_data();
        $dataids = !empty($data->datalynxid)
            ? [(int) $data->datalynxid]
            : $DB->get_fieldset_select('datalynx', 'id', 'course = ?', [(int) $data->courseid]);
        foreach ($dataids as $dataid) {
            self::migrate_view_link_tags((int) $dataid);
        }
    }

    /**
     * Rewrite the view link tags of an instance's views from #{{viewlink:...}}# to ##viewlink:...##
     * (and viewsesslink). Templates without old tags stay as they are, so running it again, or on
     * current views, changes nothing.
     *
     * @param int $dataid
     * @return int number of views changed
     */
    public static function migrate_view_link_tags(int $dataid): int {
        global $DB;

        $textcolumns = [
            'section', 'param1', 'param2', 'param3', 'param4', 'param5',
            'param6', 'param7', 'param8', 'param9', 'param10',
        ];
        $changedviews = 0;
        foreach ($DB->get_records('datalynx_views', ['dataid' => $dataid]) as $view) {
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
                // The cached pattern list is built again from the changed templates.
                $view->patterns = null;
                $DB->update_record('datalynx_views', $view);
                $changedviews++;
            }
        }
        return $changedviews;
    }
}
