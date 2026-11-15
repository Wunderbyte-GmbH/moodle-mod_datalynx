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

use mod_datalynx\datalynx;
use mod_datalynx\local\view\base;

/**
 * Builds the structured browse payload for the PDF view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_view_manager extends view_manager {
    /**
     * The view type this manager handles.
     *
     * @return string
     */
    protected function get_view_type(): string {
        return 'pdf';
    }

    /**
     * The view type as it appears in exception messages.
     *
     * @return string
     */
    protected function get_view_label(): string {
        return 'PDF';
    }

    /**
     * The fields the PDF view's templates use, or all fields when they use none.
     *
     * @param base $view
     * @return array field id => field
     */
    public function get_content_fields(base $view): array {
        // A PDF view, as get_browse_payload() opened it.
        $fields = $view instanceof \datalynxview_pdf\view ? $view->get_view_fields() : [];

        return $fields ?: parent::get_content_fields($view);
    }

    /**
     * Build the browse payload for one PDF view page.
     *
     * @param datalynx|int $datalynx the instance, or its id
     * @param int $viewid
     * @param array $filteroptions
     * @return array
     */
    public function get_browse_payload(datalynx|int $datalynx, int $viewid, array $filteroptions = []): array {
        /** @var \datalynxview_pdf\view $view */
        $view = $this->open_view($datalynx, $viewid, $filteroptions);
        $entries = $this->load_entries($view);
        $payload = $this->build_payload($view, $entries);

        $entryrecords = $entries->entries();
        if (empty($entryrecords)) {
            return $payload;
        }

        $view->set_view_tags([
            'entriescount' => $payload['entriescount'],
            'entriesfiltercount' => $payload['entriesfiltercount'],
            'hidenewentry' => 0,
            'showentryactions' => $this->has_manageable_entries($view, $entryrecords),
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
                    $view->get_dlx()->user_can_manage_entry($entry)
                ),
            ];
        }

        return $entries;
    }
}
