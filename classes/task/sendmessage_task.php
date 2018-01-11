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
 * This file defines an adhoc task to send messages.
 *
 * @package    mod_datalynx
 * @copyright 2017 Thomas Niedermaier <thomas.niedermaier@gmail.com>
 * @license    http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

namespace mod_datalynx\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Adhock class, used to send notifications to users.
 *
 * @since      Moodle 2.7
 * @package    mod_datalynx
 * @copyright 2017 Thomas Niedermaier <thomas.niedermaier@gmail.com>
 * @license    http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class sendmessage_task extends \core\task\adhoc_task {

    /**
     * Send out messages.
     */
    public function execute() {
        $data = unserialize(base64_decode($this->get_custom_data_as_string()));
        foreach ($data as $message) {
            mtrace("Sending message to the user with id " . $message->userto->id);
            message_send($message);
            mtrace("Sent.");
        }
    }

}
