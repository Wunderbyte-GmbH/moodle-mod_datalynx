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
 * The mod_datalynx teammember added event.
 *
 * @package mod_datalynx
 * @copyright 2015 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_datalynx\event;


/**
 * The mod_datalynx team updated event class.
 *
 * Ids only: the event data is written to the logs and exported with the data of the acting
 * user, so it must not hold user records (mod_datalynx#321).
 *
 * @property-read array $other {
 *                Extra information about event.
 *
 *                - int dataid: the id of the datalynx activity.
 *                - int fieldid: the id of the team member select field.
 *                - string name: the name of the field.
 *                - int[] addedmembers: ids of the users who joined the team.
 *                - int[] removedmembers: ids of the users who left the team.
 *                }
 *
 * @package mod_datalynx
 * @since Moodle 2.7
 * @copyright 2015 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class team_updated extends \core\event\base {
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
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('datalynxteamupdated', 'mod_datalynx');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' changed the team of the entry with " .
        "id '$this->objectid' for the datalynx activity " .
        "with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url(
            '/mod/datalynx/view.php',
            ['d' => $this->other['dataid'], 'eids' => $this->objectid]
        );
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
        foreach (['addedmembers', 'removedmembers'] as $key) {
            $members = $this->other[$key] ?? [];
            if (!is_array($members) || array_filter($members, fn($id) => !is_int($id))) {
                throw new \coding_exception("The '$key' value in other must be a list of user ids.");
            }
        }
    }

    /**
     * Mapping of the ids in other for restoring logs.
     *
     * The member lists are lists of user ids; the log restore maps single ids only, so they
     * are not mapped.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'dataid' => ['db' => 'datalynx', 'restore' => 'datalynx'],
            'fieldid' => ['db' => 'datalynx_fields', 'restore' => 'datalynx_field'],
            'addedmembers' => \core\event\base::NOT_MAPPED,
            'removedmembers' => \core\event\base::NOT_MAPPED,
        ];
    }

    /**
     * The other data of a logged event with the member lists reduced to user ids.
     *
     * Before mod_datalynx#321 the lists were JSON strings of complete user records (with
     * password hash, email and login data). Lists that hold ids only come back unchanged.
     *
     * @param array $other
     * @return array
     */
    public static function member_ids_only(array $other): array {
        foreach (['addedmembers', 'removedmembers'] as $key) {
            if (!array_key_exists($key, $other)) {
                continue;
            }
            $members = $other[$key];
            if (is_string($members)) {
                $members = json_decode($members, true);
            }
            $ids = [];
            foreach (is_array($members) ? $members : [] as $member) {
                $id = is_array($member) ? ($member['id'] ?? 0) : $member;
                if (is_numeric($id) && (int) $id > 0) {
                    $ids[] = (int) $id;
                }
            }
            $other[$key] = array_values(array_unique($ids));
        }
        return $other;
    }
}
