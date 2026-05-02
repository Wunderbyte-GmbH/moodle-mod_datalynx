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
 * Builds the structured browse payload for the Report view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view_manager {
    /**
     * Build the browse payload for one Report view page.
     *
     * @param int $datalynxid
     * @param int $viewid
     * @param array $filteroptions
     * @return array
     */
    public function get_browse_payload(int $datalynxid, int $viewid, array $filteroptions = []): array {
        $datalynx = new datalynx($datalynxid);
        $viewrecords = $datalynx->get_view_records(true);

        if (empty($viewrecords[$viewid])) {
            throw new coding_exception('Invalid view id for report browse payload.');
        }

        $viewrecord = $viewrecords[$viewid];
        if ($viewrecord->type !== 'report') {
            throw new coding_exception('Report browse payload manager only supports Report views.');
        }

        /** @var \datalynxview_report\view $view */
        $view = $datalynx->get_view($viewrecord->type, $viewrecord, false);
        if (!empty($filteroptions)) {
            $view->set_filter($filteroptions, $view->is_forcing_filter());
        }

        return $view->get_report_payload();
    }
}
