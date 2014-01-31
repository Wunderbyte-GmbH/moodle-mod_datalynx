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
 * Defines message providers (types of messages being sent)
 *
 * @package mod
 * @package datalynx
 * @copyright  2012 Itamar Tzadok
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$messageproviders = array (

    'datalynx_entryadded' => array (
        'capability' => 'mod/datalynx:notifyentryadded',
    ),

    'datalynx_entryupdated' => array (
        'capability' => 'mod/datalynx:notifyentryupdated',
    ),

    'datalynx_entrydeleted' => array (
        'capability' => 'mod/datalynx:notifyentrydeleted',
    ),

    'datalynx_entryapproved' => array (
        'capability' => 'mod/datalynx:notifyentryapproved',
    ),

    'datalynx_entrydisapproved' => array (
        'capability' => 'mod/datalynx:notifyentrydisapproved',
    ),

    'datalynx_commentadded' => array (
        'capability' => 'mod/datalynx:notifycommentadded',
    ),

    'datalynx_ratingadded' => array (
        'capability' => 'mod/datalynx:notifyratingadded',
    ),

    'datalynx_ratingupdated' => array (
        'capability' => 'mod/datalynx:notifyratingadded',
    ),

    'datalynx_memberadded' => array (
        'capability' => 'mod/datalynx:notifymemberadded',
    ),

    'datalynx_memberremoved' => array (
        'capability' => 'mod/datalynx:notifymemberremoved',
    ),

);
