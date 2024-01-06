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
 * The mod_datalynx comment created event.
 *
 * @package mod_datalynx
 * @copyright 2023 Thomas Winkler
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_datalynx comment created event class.
 *
 * @package mod_datalynx
 * @since Moodle 4.0
 * @copyright 2023 Thomas Winkler
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cron_trigger extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'datalynx';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('cron_trigger', 'mod_datalynx');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "Cron Job trigger occured";
    }
}
