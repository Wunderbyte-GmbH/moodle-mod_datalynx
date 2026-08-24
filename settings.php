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

use mod_datalynx\local\map\provider_config;

if ($ADMIN->fulltree) {
    // Acknowledgement that this major upgrade has been tested on a staging/test site.
    // Shown to site admins on every Datalynx view page until checked; see view.php.
    $settings->add(
        new admin_setting_heading(
            'mod_datalynx/stagingtesthdr',
            get_string('stagingtesthdr', 'datalynx'),
            get_string('stagingtesthdr_desc', 'datalynx')
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'mod_datalynx/stagingtestacknowledged',
            get_string('stagingtestacknowledged', 'datalynx'),
            get_string('stagingtestacknowledged_desc', 'datalynx'),
            0
        )
    );

    // Enable rss feeds.
    if (empty($CFG->enablerssfeeds)) {
        $options = [0 => get_string('rssglobaldisabled', 'admin')];
        $str = get_string('configenablerssfeeds', 'datalynx') . '<br />' .
                get_string('configenablerssfeedsdisabled2', 'admin');
    } else {
        $options = [0 => get_string('no'), 1 => get_string('yes')];
        $str = get_string('configenablerssfeeds', 'datalynx');
    }
    $settings->add(
        new admin_setting_configselect(
            'datalynx_enablerssfeeds',
            get_string('enablerssfeeds', 'admin'),
            $str,
            0,
            $options
        )
    );

    $unlimited = get_string('unlimited');
    $keys = range(0, 500);
    $values = range(1, 500);
    array_unshift($values, $unlimited);

    // Max fields.
    $options = array_combine($keys, $values);
    $settings->add(
        new admin_setting_configselect(
            'datalynx_maxfields',
            get_string('fieldsmax', 'datalynx'),
            get_string('configmaxfields', 'datalynx'),
            0,
            $options
        )
    );

    // Max views.
    $options = array_combine($keys, $values);
    $settings->add(
        new admin_setting_configselect(
            'datalynx_maxviews',
            get_string('viewsmax', 'datalynx'),
            get_string('configmaxviews', 'datalynx'),
            0,
            $options
        )
    );

    // Max filters.
    $options = array_combine($keys, $values);
    $settings->add(
        new admin_setting_configselect(
            'datalynx_maxfilters',
            get_string('filtersmax', 'datalynx'),
            get_string('configmaxfilters', 'datalynx'),
            0,
            $options
        )
    );

    // Max entries.
    $keys = range(-1, 500);
    $values = range(0, 500);
    array_unshift($values, $unlimited);
    $options = array_combine($keys, $values);
    $settings->add(
        new admin_setting_configselect(
            'datalynx_maxentries',
            get_string('entriesmax', 'datalynx'),
            get_string('configmaxentries', 'datalynx'),
            -1,
            $options
        )
    );

    // Allow anonymous entries.
    $options = [0 => get_string('no'), 1 => get_string('yes')];
    $settings->add(
        new admin_setting_configselect(
            'datalynx_anonymous',
            get_string('entriesanonymous', 'datalynx'),
            get_string('configanonymousentries', 'datalynx'),
            0,
            $options
        )
    );

    // Map services, used by the location field type.
    // Note these use plugin scoped names (mod_datalynx/...) rather than the
    // legacy global datalynx_* names above.
    $settings->add(
        new admin_setting_heading(
            'mod_datalynx/mapserviceshdr',
            get_string('mapservices', 'datalynx'),
            get_string('mapservices_desc', 'datalynx')
        )
    );

    if (provider_config::uses_shared_endpoint()) {
        $settings->add(
            new admin_setting_description(
                'mod_datalynx/mapsharedwarning',
                '',
                $OUTPUT->notification(get_string('mapsharedwarning', 'datalynx'), 'warning', false)
            )
        );
    }

    // Basemap. Tiles are fetched by the browser directly from the tile server.
    $settings->add(
        new admin_setting_configtext(
            'mod_datalynx/map_tileurl',
            get_string('map_tileurl', 'datalynx'),
            get_string('map_tileurl_desc', 'datalynx'),
            provider_config::DEFAULT_TILE_URL,
            PARAM_RAW_TRIMMED
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'mod_datalynx/map_tileattribution',
            get_string('map_tileattribution', 'datalynx'),
            get_string('map_tileattribution_desc', 'datalynx'),
            provider_config::DEFAULT_TILE_ATTRIBUTION,
            PARAM_RAW_TRIMMED
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'mod_datalynx/map_tilekey',
            get_string('map_tilekey', 'datalynx'),
            get_string('map_tilekey_desc', 'datalynx'),
            ''
        )
    );

    $zoomlevels = array_combine(range(1, 20), range(1, 20));
    $settings->add(
        new admin_setting_configselect(
            'mod_datalynx/map_maxzoom',
            get_string('map_maxzoom', 'datalynx'),
            get_string('map_maxzoom_desc', 'datalynx'),
            19,
            $zoomlevels
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'mod_datalynx/map_defaultcentre',
            get_string('map_defaultcentre', 'datalynx'),
            get_string('map_defaultcentre_desc', 'datalynx'),
            '',
            PARAM_RAW_TRIMMED
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'mod_datalynx/map_defaultzoom',
            get_string('map_defaultzoom', 'datalynx'),
            get_string('map_defaultzoom_desc', 'datalynx'),
            12,
            $zoomlevels
        )
    );

    // Geocoding. Always proxied through this site, never called from the browser.
    $settings->add(
        new admin_setting_configselect(
            'mod_datalynx/geocoder',
            get_string('geocoder', 'datalynx'),
            get_string('geocoder_desc', 'datalynx'),
            provider_config::GEOCODER_PHOTON,
            provider_config::geocoder_menu()
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'mod_datalynx/geocoderurl',
            get_string('geocoderurl', 'datalynx'),
            get_string('geocoderurl_desc', 'datalynx'),
            '',
            PARAM_URL
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'mod_datalynx/geocoderkey',
            get_string('geocoderkey', 'datalynx'),
            get_string('geocoderkey_desc', 'datalynx'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'mod_datalynx/geocodercountries',
            get_string('geocodercountries', 'datalynx'),
            get_string('geocodercountries_desc', 'datalynx'),
            '',
            PARAM_RAW_TRIMMED
        )
    );

    $settings->add(
        new admin_setting_configduration(
            'mod_datalynx/geocodercachettl',
            get_string('geocodercachettl', 'datalynx'),
            get_string('geocodercachettl_desc', 'datalynx'),
            30 * DAYSECS
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'mod_datalynx/geocoderrate',
            get_string('geocoderrate', 'datalynx'),
            get_string('geocoderrate_desc', 'datalynx'),
            1,
            [1 => '1', 2 => '2', 5 => '5', 10 => '10', 20 => '20', 50 => '50']
        )
    );

    $settings->add(
        new admin_setting_configselect(
            'mod_datalynx/router',
            get_string('router', 'datalynx'),
            get_string('router_desc', 'datalynx'),
            provider_config::ROUTER_OSRM,
            provider_config::router_menu()
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'mod_datalynx/routerurl',
            get_string('routerurl', 'datalynx'),
            get_string('routerurl_desc', 'datalynx'),
            '',
            PARAM_URL
        )
    );

    $settings->add(
        new admin_setting_configpasswordunmask(
            'mod_datalynx/routerkey',
            get_string('routerkey', 'datalynx'),
            get_string('routerkey_desc', 'datalynx'),
            ''
        )
    );

    $settings->add(
        new admin_setting_configtext(
            'mod_datalynx/map_contactemail',
            get_string('map_contactemail', 'datalynx'),
            get_string('map_contactemail_desc', 'datalynx'),
            '',
            PARAM_NOTAGS
        )
    );
}
