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
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // The definition records of one instance (fields, views, filters, custom filters, behaviors,
    // layouts, field formats, rules), keyed by instance id. They change only through the
    // management pages, presets and restore, all of which invalidate the key through
    // \mod_datalynx\local\definition_cache. The records are stored as arrays of scalars, so
    // they qualify as simple data: static acceleration then hands out copies without
    // unserialising the whole instance definition on every read.
    'definitions' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],

    // Instances whose definitions this request changed inside a database transaction; they are
    // read from the database until the transaction is over, and invalidated again after it.
    'definitionspending' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
        'simpledata' => true,
    ],

    // Parsed view tags per view id, valid while their signature matches (see view base::set__patterns()).
    'patterns' => ['mode' => cache_store::MODE_APPLICATION, 'simplekeys' => true],

    // Geocoding results, shared site-wide. Caching is not an optimisation here:
    // the Nominatim usage policy requires results to be cached, and reusing a
    // lookup is what keeps the site inside the providers' rate limits.
    // Entries carry their own timestamp so that changing the configured
    // lifetime takes effect immediately.
    'geocode' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
    ],

    // Routing results, shared site-wide. Same reasoning as the geocode cache: the
    // public routing endpoints are volunteer run, and a journey's route only
    // changes when its stops do.
    'route' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
    ],

    // Timestamp of the last outbound geocoding request, used to pace requests.
    'geocodethrottle' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
    ],
];
