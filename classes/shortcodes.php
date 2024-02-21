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
 * Shortcodes for mod_datalynx
 *
 * @package mod_datalynx
 * @subpackage db
 * @since Moodle 4.1
 * @copyright 2023 Georg Maißer
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/booking/lib.php');

/**
 * Deals with local_shortcodes regarding booking.
 */
class shortcodes {

    /**
     * This shortcode shows a view of a datalynx instance.
     * Arguments: view="My view name" datalynx=5 (cmid)
     *
     * @param string $shortcode
     * @param array $args
     * @param string|null $content
     * @param object $env
     * @param Closure $next
     * @return string
     */
    public static function displayview($shortcode, $args, $content, $env, $next) {
        global $DB, $CFG, $PAGE;
        require_once("{$CFG->dirroot}/mod/datalynx/locallib.php");
        if (isset($args['view']) && isset($args['cmid'])) {
            $viewname = $args['view'];
            $cmid = $args['cmid'];
            $cm = get_coursemodule_from_id('datalynx', $cmid);
            if (!$cm) {
                return get_string('invalidcoursemodule', 'error');
            }
            // Sanity check in case the designated datalynx has been deleted or does not exist.
            if (!$DB->record_exists('datalynx', array('id' => $cm->instance))) {
                return get_string('datalynxinstance_deleted', 'mod_datalynxcoursepage');
            }

            // Sanity check in case the designated view has been deleted.
            if (!$DB->record_exists('datalynx_views',
                            array('dataid' => $cm->instance, 'name' => $viewname))) {
                return get_string('datalynxview_deleted', 'mod_datalynxcoursepage');
            }
            $dl = new datalynx($cm->instance, $cmid);
            $view = $dl->get_view_by_name($viewname);
            // If view has not been found, the user has no right to view it. Return an empty string instead.
            if (!has_capability('mod/datalynx:viewentry', $dl->context) || !$view) {
                // No right to view datalynx instance or view or entry. Return empty string.
                return '';
            }
            $jsurl = new moodle_url('/mod/datalynxcoursepage/js.php', array('id' => $cmid));
            $PAGE->requires->js($jsurl);
            $options = ['tohtml' => true, 'skiplogincheck' => true];
            return datalynx::get_content_inline($cm->instance, $view->id, null, $options);
        } else {
            return "You must set arguments view and datalynx. Here is an example: [displayview view=\"My datalynx viewname\" cmid=5]";
        }
    }
}
