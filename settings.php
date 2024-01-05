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
 *
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Enable rss feeds.
    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'datalynx') . '<br />' .
                get_string('configenablerssfeedsdisabled2', 'admin');
    } else {
        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $str = get_string('configenablerssfeeds', 'datalynx');
    }
    $settings->add(
            new admin_setting_configselect('datalynx_enablerssfeeds',
                    get_string('enablerssfeeds', 'admin'), $str, 0, $options));

    $unlimited = get_string('unlimited');
    $keys = range(0, 500);
    $values = range(1, 500);
    array_unshift($values, $unlimited);

    // Max fields.
    $options = array_combine($keys, $values);
    $settings->add(
            new admin_setting_configselect('datalynx_maxfields', get_string('fieldsmax', 'datalynx'),
                    get_string('configmaxfields', 'datalynx'), 0, $options));

    // Max views.
    $options = array_combine($keys, $values);
    $settings->add(
            new admin_setting_configselect('datalynx_maxviews', get_string('viewsmax', 'datalynx'),
                    get_string('configmaxviews', 'datalynx'), 0, $options));

    // Max filters.
    $options = array_combine($keys, $values);
    $settings->add(
            new admin_setting_configselect('datalynx_maxfilters',
                    get_string('filtersmax', 'datalynx'), get_string('configmaxfilters', 'datalynx'),
                    0, $options));

    // Max entries.
    $keys = range(-1, 500);
    $values = range(0, 500);
    array_unshift($values, $unlimited);
    $options = array_combine($keys, $values);
    $settings->add(
            new admin_setting_configselect('datalynx_maxentries',
                    get_string('entriesmax', 'datalynx'), get_string('configmaxentries', 'datalynx'),
                    -1, $options));

    // Allow anonymous entries.
    $options = array(0 => get_string('no'), 1 => get_string('yes'));
    $settings->add(
            new admin_setting_configselect('datalynx_anonymous',
                    get_string('entriesanonymous', 'datalynx'),
                    get_string('configanonymousentries', 'datalynx'), 0, $options));
}
