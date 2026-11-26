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

namespace mod_datalynx\event;

/**
 * A request to join the team of an entry changed (mod_datalynx#341).
 *
 * The user concerned is the related user; ids only.
 *
 * @property-read array $other {
 *                - int dataid: the id of the datalynx activity.
 *                - int fieldid: the id of the team member select field.
 *                }
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class team_request_base extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'datalynx_entries';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/datalynx/view.php', ['d' => $this->other['dataid'], 'eids' => $this->objectid]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception when validation does not pass.
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['dataid']) || !isset($this->other['fieldid'])) {
            throw new \coding_exception('The \'dataid\' and \'fieldid\' values must be set in other.');
        }
        if (empty($this->relateduserid)) {
            throw new \coding_exception('The related user must be set.');
        }
    }

    /**
     * Mapping of the ids in other for restoring logs.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'dataid' => ['db' => 'datalynx', 'restore' => 'datalynx'],
            'fieldid' => ['db' => 'datalynx_fields', 'restore' => 'datalynx_field'],
        ];
    }
}
