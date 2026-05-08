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
 * Datalynx external functions and service definitions.
 *
 * @package mod_datalynx
 * @copyright 2020 Michael Pollak <moodle@michaelpollak.org>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
        'mod_datalynx_get_view_names' => [
                'classname'   => 'mod_datalynx\external\get_view_names',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch all views for a datalynx instance.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:managetemplates',
                'ajax'        => true,
        ],
        'mod_datalynx_get_text_field_names' => [
                'classname'   => 'mod_datalynx\external\get_text_field_names',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch all text fields for a datalynx instance.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:managetemplates',
                'ajax'        => true,
        ],
        'mod_datalynx_get_grid_view_data' => [
                'classname'   => 'mod_datalynx\external\get_grid_view_data',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch structured browse data for a Grid view pilot.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:viewentry',
                'ajax'        => true,
        ],
        'mod_datalynx_get_tabular_view_data' => [
                'classname'   => 'mod_datalynx\external\get_tabular_view_data',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch structured browse data for a Tabular view.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:viewentry',
                'ajax'        => true,
        ],
        'mod_datalynx_get_csv_view_data' => [
                'classname'   => 'mod_datalynx\external\get_csv_view_data',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch structured browse data for a CSV view.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:viewentry',
                'ajax'        => true,
        ],
        'mod_datalynx_get_pdf_view_data' => [
                'classname'   => 'mod_datalynx\external\get_pdf_view_data',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch structured browse data for a PDF view.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:viewentry',
                'ajax'        => true,
        ],
        'mod_datalynx_get_report_view_data' => [
                'classname'   => 'mod_datalynx\external\get_report_view_data',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch structured browse data for a Report view.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:viewentry',
                'ajax'        => true,
        ],
        'mod_datalynx_get_email_view_data' => [
                'classname'   => 'mod_datalynx\external\get_email_view_data',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Fetch rendered data for an internal Email view entry.',
                'type'        => 'read',
                'capabilities' => 'mod/datalynx:managetemplates',
                'ajax'        => true,
        ],
        'mod_datalynx_team_subscription' => [
                'classname'   => 'mod_datalynx\external\team_subscription',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Subscribe or unsubscribe users in a datalynx team.',
                'type'        => 'write',
                'capabilities' => 'mod/datalynx:teamsubscribe',
                'ajax'        => true,
        ],
        'mod_datalynx_toggle_behavior' => [
                'classname'   => 'mod_datalynx\external\toggle_behavior',
                'methodname'  => 'execute',
                'classpath'   => '',
                'description' => 'Toggle behavior settings from the behavior management table.',
                'type'        => 'write',
                'capabilities' => 'mod/datalynx:managetemplates',
                'ajax'        => true,
        ],
];
