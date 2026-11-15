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

use mod_datalynx\datalynx;

/**
 * Builds the structured browse payload for the Report view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_view_manager extends view_manager {
    /**
     * The view type this manager handles.
     *
     * @return string
     */
    protected function get_view_type(): string {
        return 'report';
    }

    /**
     * Build the browse payload for one Report view page.
     *
     * @param datalynx|int $datalynx the instance, or its id
     * @param int $viewid
     * @param array $filteroptions filter options, plus 'timescope' for the report's time scope
     * @return array
     */
    public function get_browse_payload(datalynx|int $datalynx, int $viewid, array $filteroptions = []): array {
        $timescope = $filteroptions['timescope'] ?? null;
        unset($filteroptions['timescope']);

        /** @var \datalynxview_report\view $view */
        $view = $this->open_view($datalynx, $viewid, $filteroptions);
        if (!empty($timescope)) {
            $view->set_report_scope($timescope);
        }

        return $view->get_report_payload();
    }
}
