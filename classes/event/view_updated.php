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
 * The mod_datalynx entry updated event.
 *
 * @package mod_datalynx
 * @copyright 2015 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_datalynx\event;

defined('MOODLE_INTERNAL') or die();

/**
 *
 * @package mod_datalynx
 * @since Moodle 2.7
 * @copyright 2015 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view_updated extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'datalynx_views';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('datalynx_entryupdated', 'mod_datalynx');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' updated the datalynx entry with " .
        "id '$this->objectid' in the datalynx activity " .
        "with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/datalynx/view.php',
                array('d' => $this->other['dataid'], 'vid' => $this->objectid));
    }

    /**
     * Get the legacy event log data.
     *
     * @return array
     */
    public function get_legacy_logdata() {
        return array($this->courseid, 'datalynx', 'view_updated',
                'view.php?d=' . $this->other['dataid'] . '&amp;vid=' . $this->objectid,
                $this->other['dataid'], $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception when validation does not pass.
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['dataid'])) {
            throw new \coding_exception('The \'dataid\' value must be set in other.');
        }
    }
}
