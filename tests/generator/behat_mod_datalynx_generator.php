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
 * Behat data generator for mod_datalynx: ride sharing boards and their rides, for the core step
 * "the following "mod_datalynx > ride boards" exist".
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_datalynx_generator extends behat_generator_base {
    /**
     * The entities Behat can create with the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'ride boards' => [
                'singular' => 'ride board',
                'datagenerator' => 'ride_board',
                'required' => ['course'],
                'switchids' => ['course' => 'courseid'],
            ],
            'rides' => [
                'singular' => 'ride',
                'datagenerator' => 'ride',
                'required' => ['board', 'driver'],
                'switchids' => ['board' => 'datalynxid', 'driver' => 'userid'],
            ],
        ];
    }

    /**
     * The id of a datalynx activity by its name.
     *
     * @param string $name
     * @return int
     */
    protected function get_board_id(string $name): int {
        global $DB;
        return (int) $DB->get_field('datalynx', 'id', ['name' => $name], MUST_EXIST);
    }

    /**
     * The id of a user by username.
     *
     * @param string $username
     * @return int
     */
    protected function get_driver_id(string $username): int {
        return $this->get_user_id($username);
    }
}
