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
 * Mobile output class for datalynx based on mod_certificate.
 *
 * @package    mod_datalynx
 * @copyright  2020 Michael Pollak
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the certificate course view for the mobile app.
     * @param  array $args Arguments from tool_mobile_get_content WS
     *
     * @return array       HTML, javascript and otherdata
     */
    public static function mobile_course_view($args) {
        global $OUTPUT, $USER, $DB;

        $args = (object) $args;
        $cm = get_coursemodule_from_id('datalynx', $args->cmid);

        // Capabilities check.
        require_login($args->courseid , false , $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability ('mod/datalynx:viewentry', $context);
        if ($args->userid != $USER->id) {
            require_capability('mod/datalynx:manageentries', $context);
        }

        $datalynx = $DB->get_record('datalynx', array('id' => $cm->instance));

        $entries = self::get_entries(7);

        $data['intro'] = $datalynx->intro;
        $data['cmid'] = $cm->id;
        $data['entries'] = $entries;
        $data['courseid'] = $args->courseid;

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_datalynx/mobile_view_page', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => '',
        ];
    }

    public static function get_entries($id) {
        global $DB;

        $sql = "SELECT {datalynx_contents}.id, entryid, fieldid, content FROM {datalynx_entries}
            JOIN {datalynx_contents} ON {datalynx_entries}.id = entryid  WHERE dataid = $id";
        $tempentries = $DB->get_records_sql($sql);

        // Create a format that mustache can handle.
        foreach ($tempentries as $entry) {
            $entryid = $entry->entryid;
            $entries[$entryid]['id'] = $entryid;
            $entries[$entryid]['contents'][]['content'] = $entry->content;
            $entries[$entryid]['contents'][]['fieldid'] = $entry->content;
        }

        // Set keys to start at 0, does not work otherwhise.
        return array_values($entries);
    }
}
