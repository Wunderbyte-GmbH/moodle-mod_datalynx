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
 * Moodle App support: the course module handler and the strings its templates use.
 *
 * The app caches this definition (tool_mobile/plugininfo). Purge the caches after every change
 * here, and use "Force reload site plugins" in the app's developer options.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'mod_datalynx' => [
        'handlers' => [
            'datalynxview' => [
                'displaydata' => [
                    'title' => 'pluginname',
                    'icon' => $CFG->wwwroot . '/mod/datalynx/pix/monologo.svg',
                    'class' => '',
                ],
                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view',
                'styles' => [
                    'url' => $CFG->wwwroot . '/mod/datalynx/mobileapp/styles.css',
                    'version' => 1,
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'datalynx'],
            ['mobilefilterdefault', 'datalynx'],
            ['mobilefilterselect', 'datalynx'],
            ['mobilenoview', 'datalynx'],
            ['mobileopeninbrowser', 'datalynx'],
            ['mobileshowingxofy', 'datalynx'],
            ['mobileviewnotsupported', 'datalynx'],
            ['mobileviewselect', 'datalynx'],
        ],
    ],
];
