<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Trigger an event in a periodic time
 *
 * @package    mod_datalynx
 * @copyright  2023 Thomas Winkler
 * @author Thomas Winkler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_datalynx\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Trigger an event in a periodic time
 *
 * @package    mod_datalynx
 * @copyright  2023 Thomas Winkler
 * @author Thomas Winkler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class cron_trigger extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('cron_trigger', 'mod_datalynx');
    }

    /**
     *
     * Close off any overdue attempts.
     */
    public function execute() {
        global $DB;
        $records = $DB->get_records('datalynx_rules', null, '', 'DISTINCT dataid');
        foreach ($records as $record) {
            // Needed to prevent errors. In the past datalynx_rules was not deleted when dl instance was deleted.
            // TODO: In upgrade.php remove all deleted rules.
            if ($DB->record_exists('datalynx', ['id' => $record->dataid])) {
                $df = new \mod_datalynx\datalynx($record->dataid);
                $event = \mod_datalynx\event\cron_trigger::create(array('context' => $df->context, 'objectid' => $df->id()));
                $event->trigger();
            }
        }
    }
}
