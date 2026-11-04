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

use context_module;
use mod_datalynx\datalynx;
use mod_datalynx\local\mobile\entries_payload_builder;
use stdClass;

/**
 * Content for the Moodle App, served through tool_mobile_get_content.
 *
 * Each method is kept thin: normalise the arguments, check access, let a builder in
 * \mod_datalynx\local\mobile assemble the data, render the template.
 *
 * The templates switch their Mustache delimiters to <% %> so that {{ }} stays Angular's. Data
 * the app shows travels in otherdata and is bound with property bindings, never written into the
 * template source: entry content is user content, and anything in the template source is
 * compiled by Angular.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /** @var int Entries the app loads first, and how many more each scroll to the end adds. */
    const PERPAGE = 20;

    /**
     * Browse the entries of the activity.
     *
     * The app opens this with cmid and courseid. View switching, searching and growing the list
     * call it again with the rest of the state, which is otherwise kept in otherdata.
     *
     * @param array $args arguments of tool_mobile_get_content, all strings
     * @return array templates, javascript and otherdata
     */
    public static function mobile_course_view(array $args): array {
        global $OUTPUT;

        $args = self::args($args, [
            'cmid' => PARAM_INT,
            'courseid' => PARAM_INT,
            'viewid' => PARAM_INT,
            'filterid' => PARAM_INT,
            'perpage' => PARAM_INT,
            'search' => PARAM_TEXT,
            'criteria' => PARAM_RAW,
            'sort' => PARAM_RAW,
            'eids' => PARAM_SEQUENCE,
        ]);
        [$cm, $context] = self::context($args->cmid);

        $dlx = new datalynx($cm->instance);
        $payload = (new entries_payload_builder($dlx))->build([
            'viewid' => $args->viewid,
            'filterid' => $args->filterid,
            'page' => 0,
            'perpage' => $args->perpage ?: self::PERPAGE,
            'search' => $args->search,
            'criteria' => $args->criteria,
            'sort' => $args->sort,
            'eids' => $args->eids,
        ]);

        // The list grows by asking for a larger first page rather than appending the next one,
        // which a template could only do with custom JavaScript. Growth stops at the most one
        // request may return; beyond that the page offers the web view instead.
        $nextperpage = min($payload['perpage'] + self::PERPAGE, entries_payload_builder::MAX_PERPAGE);
        $otherdata = $payload + [
            'courseid' => (int) $cm->course,
            'intro' => format_module_intro('datalynx', $dlx->data, $cm->id, false),
            'hasview' => $payload['viewid'] > 0,
            'cangrow' => $payload['hasmore'] && $nextperpage > $payload['perpage'],
            'nextperpage' => $nextperpage,
        ];

        $html = $OUTPUT->render_from_template('mod_datalynx/mobile/view_page', [
            'cmid' => (int) $cm->id,
            'courseid' => (int) $cm->course,
        ]);

        return [
            'templates' => [
                ['id' => 'main', 'html' => $html],
            ],
            'javascript' => '',
            'otherdata' => self::otherdata($otherdata),
        ];
    }

    /**
     * Read the arguments a method expects, each cleaned to its type.
     *
     * tool_mobile_get_content hands every argument over as a raw string, and the app sends only
     * the ones it has, so nothing reads $args directly: a missing argument becomes the empty
     * value of its type.
     *
     * @param array $args
     * @param array $types name => PARAM_* type
     * @return stdClass
     */
    private static function args(array $args, array $types): stdClass {
        $clean = new stdClass();
        foreach ($types as $name => $type) {
            $value = isset($args[$name]) && is_scalar($args[$name]) ? (string) $args[$name] : '';
            $value = clean_param($value, $type);
            $clean->$name = $type === PARAM_INT ? (int) $value : $value;
        }

        return $clean;
    }

    /**
     * Load the course module and check the user may browse it.
     *
     * @param int $cmid
     * @return array [$cm, $context]
     */
    private static function context(int $cmid): array {
        $cm = get_coursemodule_from_id('datalynx', $cmid, 0, false, MUST_EXIST);
        require_login($cm->course, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability('mod/datalynx:viewentry', $context);

        return [$cm, $context];
    }

    /**
     * Encode otherdata for the app.
     *
     * The app parses every otherdata value as JSON. Encoding each value here, strings included,
     * makes that parse return exactly the value sent: a search term or the criteria JSON stays a
     * string instead of turning into a number or an object on the way.
     *
     * @param array $data
     * @return array name => JSON
     */
    private static function otherdata(array $data): array {
        return array_map(fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $data);
    }
}
