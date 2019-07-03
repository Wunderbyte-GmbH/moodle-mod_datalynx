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
defined('MOODLE_INTERNAL') or die();

$messageproviders = array(

        'event_entry_created' => array('capability' => 'mod/datalynx:notifyentryadded'),

        'event_entry_updated' => array('capability' => 'mod/datalynx:notifyentryupdated'),

        'event_entry_deleted' => array('capability' => 'mod/datalynx:notifyentrydeleted'),

        'event_entry_approved' => array('capability' => 'mod/datalynx:notifyentryapproved'),

        'event_entry_disapproved' => array('capability' => 'mod/datalynx:notifyentrydisapproved'),

        'event_comment_created' => array('capability' => 'mod/datalynx:notifycommentadded'),

        'event_rating_added' => array('capability' => 'mod/datalynx:notifyratingadded'),

        'event_rating_updated' => array('capability' => 'mod/datalynx:notifyratingadded'),

        'event_team_updated' => array('capability' => 'mod/datalynx:notifyteamupdated')
);
