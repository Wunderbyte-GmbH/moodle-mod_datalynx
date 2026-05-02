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

namespace mod_datalynx\local\view\manager;

use coding_exception;
use mod_datalynx\datalynx;

/**
 * Builds the structured payload for one internal Email view render.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class email_view_manager {
    /**
     * Build the payload for rendering one Email view entry.
     *
     * @param int $datalynxid
     * @param int $viewid
     * @param int $entryid
     * @param array $options
     * @return array
     */
    public function get_entry_payload(int $datalynxid, int $viewid, int $entryid, array $options = []): array {
        $datalynx = new datalynx($datalynxid);
        $viewrecords = $datalynx->get_all_views();

        if (empty($viewrecords[$viewid])) {
            throw new coding_exception('Invalid view id for email payload.');
        }

        $viewrecord = $viewrecords[$viewid];
        if ($viewrecord->type !== datalynx::INTERNAL_VIEW_EMAIL) {
            throw new coding_exception('Email payload manager only supports internal Email views.');
        }

        /** @var \datalynxview_email\view $view */
        $view = $datalynx->get_view($viewrecord->type, $viewrecord);
        $entry = $view->get_filtered_entry($entryid);

        $payload = [
            'datalynxid' => $datalynx->id(),
            'viewid' => (int) $viewrecord->id,
            'viewname' => format_string($view->name()),
            'viewtype' => $view->type(),
            'entryid' => $entryid,
            'hascontent' => false,
            'bodyhtml' => '',
        ];

        if (!$entry) {
            return $payload;
        }

        $payload['hascontent'] = true;
        $payload['bodyhtml'] = $view->render_email_entry($entry, $options);

        return $payload;
    }
}
