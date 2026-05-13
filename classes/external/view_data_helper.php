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

namespace mod_datalynx\external;

use context_module;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared helpers for AJAX-rendered Datalynx external view payloads.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_data_helper {
    /**
     * Bootstrap $PAGE before rendering field output in an external request.
     *
     * Some field renderers, including comments, rely on $PAGE->url for local
     * no-JS links. AJAX service requests must therefore restore the originating
     * page URL instead of leaving $PAGE to guess from /lib/ajax/service.php.
     *
     * @param stdClass $cm
     * @param context_module $context
     * @param string $pageurl
     * @param moodle_url $fallbackurl
     * @return void
     */
    public static function bootstrap_page(
        stdClass $cm,
        context_module $context,
        string $pageurl,
        moodle_url $fallbackurl
    ): void {
        global $PAGE;

        $course = get_course($cm->course);
        $cleanpageurl = clean_param($pageurl, PARAM_LOCALURL);
        $resolvedpageurl = $cleanpageurl === '' ? $fallbackurl : new moodle_url($cleanpageurl);

        $PAGE->set_context($context);
        $PAGE->set_cm($cm, $course);
        $PAGE->set_url($resolvedpageurl);
    }
}
