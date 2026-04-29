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

namespace mod_datalynx\local\view\manager;

use coding_exception;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the structured browse payload for the PDF view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_view_manager {
    /**
     * Build the browse payload for one PDF view page.
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
            throw new coding_exception('Invalid view id for PDF browse payload.');
        }

        $viewrecord = $viewrecords[$viewid];
        if ($viewrecord->type !== 'pdf') {
            throw new coding_exception('PDF browse payload manager only supports PDF views.');
        }

        /** @var \datalynxview_pdf\view $view */
        $view = $datalynx->get_view($viewrecord->type, $viewrecord, false);
        if (!empty($filteroptions)) {
            $view->set_filter($filteroptions, $view->is_forcing_filter());
        }

        $contentfields = $view->get_view_fields();
        if (empty($contentfields)) {
            $contentfields = $view->remove_duplicates($view->get_dl()->get_fields());
        }
        $view->get_filter()->contentfields = array_keys($contentfields);

        $entries = new datalynx_entries($datalynx, $view->get_filter());
        $entries->set_content();

        $payload = [
            'datalynxid' => (int) $datalynx->id(),
            'viewid' => (int) $viewrecord->id,
            'viewname' => format_string($view->name()),
            'viewtype' => $view->type(),
            'entriescount' => (int) $entries->get_count(),
            'entriesfiltercount' => (int) $entries->get_count(true),
            'hasentries' => false,
            'groups' => [],
            'emptycontent' => get_string('noentries', 'datalynx'),
        ];

        $entryrecords = $entries->entries();
        if (empty($entryrecords)) {
            return $payload;
        }

        $showentryactions = $this->has_manageable_entries($view, $entryrecords);
        $view->set_view_tags([
            'entriescount' => $payload['entriescount'],
            'entriesfiltercount' => $payload['entriesfiltercount'],
            'hidenewentry' => 0,
            'showentryactions' => $showentryactions,
        ]);

        $payload['hasentries'] = true;
        $payload['groups'][] = [
            'name' => '',
            'hasname' => false,
            'entries' => array_values($this->build_entries($view, $entryrecords)),
        ];

        return $payload;
    }

    /**
     * Determine whether any visible entry is manageable by the current user.
     *
     * @param \datalynxview_pdf\view $view
     * @param array $entryrecords
     * @return bool
     */
    protected function has_manageable_entries(\datalynxview_pdf\view $view, array $entryrecords): bool {
        foreach ($entryrecords as $entry) {
            if ($view->get_dl()->user_can_manage_entry($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build rendered browse entries for the PDF view.
     *
     * @param \datalynxview_pdf\view $view
     * @param array $entryrecords
     * @return array
     */
    protected function build_entries(\datalynxview_pdf\view $view, array $entryrecords): array {
        $entries = [];

        foreach ($entryrecords as $entry) {
            $entries[] = [
                'id' => (int) $entry->id,
                'entryhtml' => $view->render_browse_entry_html(
                    $entry,
                    $view->get_dl()->user_can_manage_entry($entry)
                ),
            ];
        }

        return $entries;
    }
}
