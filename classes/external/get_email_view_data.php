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
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use mod_datalynx\local\view\manager\email_view_manager;
use mod_datalynx\output\email_view_browser;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Web service to fetch the rendered payload for one Email view entry.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_email_view_data extends external_api {
    /**
     * Define service parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'd' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'view' => new external_value(PARAM_INT, 'Email view ID'),
            'entryid' => new external_value(PARAM_INT, 'Entry ID to render'),
            'notificationentryurl' => new external_value(PARAM_RAW, 'Optional entry URL', VALUE_DEFAULT, ''),
            'notificationentrylink' => new external_value(PARAM_RAW, 'Optional entry link HTML', VALUE_DEFAULT, ''),
            'notificationdatalynxurl' => new external_value(PARAM_RAW, 'Optional datalynx URL', VALUE_DEFAULT, ''),
            'notificationdatalynxlink' => new external_value(PARAM_RAW, 'Optional datalynx link HTML', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return the rendered payload for one Email view entry.
     *
     * @param int $d
     * @param int $view
     * @param int $entryid
     * @param string $notificationentryurl
     * @param string $notificationentrylink
     * @param string $notificationdatalynxurl
     * @param string $notificationdatalynxlink
     * @return array
     */
    public static function execute(
        int $d,
        int $view,
        int $entryid,
        string $notificationentryurl = '',
        string $notificationentrylink = '',
        string $notificationdatalynxurl = '',
        string $notificationdatalynxlink = ''
    ): array {
        global $OUTPUT;

        $params = self::validate_parameters(self::execute_parameters(), [
            'd' => $d,
            'view' => $view,
            'entryid' => $entryid,
            'notificationentryurl' => $notificationentryurl,
            'notificationentrylink' => $notificationentrylink,
            'notificationdatalynxurl' => $notificationdatalynxurl,
            'notificationdatalynxlink' => $notificationdatalynxlink,
        ]);

        $cm = get_coursemodule_from_instance('datalynx', $params['d'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        require_login($cm->course, true, $cm);
        require_capability('mod/datalynx:managetemplates', $context);

        $manager = new email_view_manager();
        $payload = $manager->get_entry_payload($params['d'], $params['view'], $params['entryid'], [
            'notificationentryurl' => $params['notificationentryurl'],
            'notificationentrylink' => $params['notificationentrylink'],
            'notificationdatalynxurl' => $params['notificationdatalynxurl'],
            'notificationdatalynxlink' => $params['notificationdatalynxlink'],
        ]);
        $renderable = new email_view_browser($payload);

        return $renderable->export_for_template($OUTPUT);
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'datalynxid' => new external_value(PARAM_INT, 'Datalynx instance ID'),
            'viewid' => new external_value(PARAM_INT, 'View ID'),
            'viewname' => new external_value(PARAM_TEXT, 'View name'),
            'viewtype' => new external_value(PARAM_ALPHA, 'View type'),
            'entryid' => new external_value(PARAM_INT, 'Rendered entry ID'),
            'hascontent' => new external_value(PARAM_BOOL, 'Whether the email body has content'),
            'bodyhtml' => new external_value(PARAM_RAW, 'Rendered email body HTML'),
        ]);
    }
}
