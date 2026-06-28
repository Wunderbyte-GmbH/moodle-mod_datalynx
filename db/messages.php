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
 * Defines message providers (types of messages being sent)
 *
 * @package mod_datalynx
 * @copyright 2015 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

// Note: these providers intentionally declare no 'capability' requirement. The recipients of an
// event notification are chosen explicitly by the rule configuration (author, roles, teams,
// specific users). A per-provider capability gate makes message_send() silently drop any
// rule-selected recipient who lacks that capability — most notably the entry author, who is
// typically a student without the notify* capabilities. The rule is the sole authority on who
// receives the notification, so no additional capability filter is applied here.
$messageproviders = [

        'event_entry_created' => [],

        'event_entry_updated' => [],

        'event_entry_deleted' => [],

        'event_entry_approved' => [],

        'event_entry_disapproved' => [],

        'event_comment_created' => [],

        'event_rating_added' => [],

        'event_rating_updated' => [],

        'event_team_updated' => [],
];
