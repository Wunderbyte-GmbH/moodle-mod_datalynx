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
 * Tag areas in component mod_datalynx
 *
 * @package   mod_datalynx
 * @copyright 2016 David Bogner
 * @license   http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

defined('MOODLE_INTERNAL') || die();

$tagareas = array(
        array(
                'itemtype' => 'datalynx_contents',
                'component' => 'mod_datalynx',
                'callback' => 'mod_datalynx_get_tagged_entries',
                'callbackfile' => '/mod/datalynx/lib.php',
                'collection' => 'datalynx',
                'showstandard' => core_tag_tag::BOTH_STANDARD_AND_NOT,
        ),
);
