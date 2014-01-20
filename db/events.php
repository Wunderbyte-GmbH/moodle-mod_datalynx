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
 * Event handler definition.
 *
 * @package mod
 * @package dataform
 * @copyright  2014 Ivan Sakic
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die;

/* List of handlers */
$handlers = array (
    'dataform_entryadded' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_entryadded'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_entryupdated' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_entryupdated'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_entrydeleted' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_entrydeleted'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_entryapproved' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_entryapproved'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_entrydisapproved' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_entrydisapproved'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),
    'dataform_commentadded' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_commentadded'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_ratingadded' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_ratingadded'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_ratingupdated' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_ratingupdated'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_memberadded' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_memberadded'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),

    'dataform_memberremoved' => array (
        'handlerfile'      => '/mod/dataform/event_class.php',
        'handlerfunction'  => array('dataform_event_handler', 'handle_memberremoved'),
        'schedule'         => 'instant',
        'internal'         => 1,
    ),


);
