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

namespace mod_datalynx\output;

defined('MOODLE_INTERNAL') || die();

use context_module;

/**
 * Mobile output class for datalynx based on mod_certificate and mod_questionnaire.
 *
 * @package    mod_datalynx
 * @copyright  2020 Michael Pollak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns all entries of an instance for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     */
    public static function mobile_course_view($args) {
        global $OUTPUT, $USER, $DB, $CFG;
        require_once("$CFG->dirroot/mod/datalynx/classes/datalynx.php");

        $args = (object) $args;
        $cm = get_coursemodule_from_id('datalynx', $args->cmid);

        // Capabilities check.
        require_login($args->courseid , false , $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability ('mod/datalynx:viewentry', $context);
        if ($args->userid != $USER->id) {
            require_capability('mod/datalynx:manageentries', $context);
        }

        $datalynx = new \mod_datalynx\datalynx($cm->instance);

        $view = 0;
        if (!empty($datalynx->data->defaultview)) {
            $view = $datalynx->data->defaultview;
        }

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $datalynx->get_content_inline($cm->instance, $view),
                ],
            ],
            'javascript' => '',
            'otherdata' => '',
        ];
    }
}
