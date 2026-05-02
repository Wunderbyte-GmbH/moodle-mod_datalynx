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
use stdClass;

/**
 * Builds the structured browse payload for the CSV view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_view_manager {
    /**
     * Build the browse payload for one CSV view page.
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
            throw new coding_exception('Invalid view id for CSV browse payload.');
        }

        $viewrecord = $viewrecords[$viewid];
        if ($viewrecord->type !== 'csv') {
            throw new coding_exception('CSV browse payload manager only supports CSV views.');
        }

        /** @var \datalynxview_csv\view $view */
        $view = $datalynx->get_view($viewrecord->type, $viewrecord, false);
        if (!empty($filteroptions)) {
            $view->set_filter($filteroptions, $view->is_forcing_filter());
        }

        $allfields = $view->get_dl()->get_fields();
        $contentfields = $view->remove_duplicates($allfields);
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

        $payload['hasentries'] = true;
        $payload['groups'][] = [
            'name' => '',
            'hasname' => false,
            'hasheaders' => $view->has_headers_for_browser(),
            'columns' => $this->build_columns($view),
            'rows' => array_values($this->build_rows($view, $allfields, $entryrecords)),
        ];

        return $payload;
    }

    /**
     * Build ordered browse columns from CSV view configuration.
     *
     * @param \datalynxview_csv\view $view
     * @return array
     */
    protected function build_columns(\datalynxview_csv\view $view): array {
        $columns = [];

        foreach ($view->get_columns() as $column) {
            [$tag, $header, $class] = $column;
            $columns[] = [
                'tag' => $tag,
                'headerhtml' => $this->resolve_header_html($view, $header),
                'cellclass' => $class,
            ];
        }

        return $columns;
    }

    /**
     * Build ordered browse rows from CSV view configuration.
     *
     * @param \datalynxview_csv\view $view
     * @param array $fields
     * @param stdClass[] $entryrecords
     * @return array
     */
    protected function build_rows(\datalynxview_csv\view $view, array $fields, array $entryrecords): array {
        $columns = $view->get_columns();
        $rows = [];

        foreach ($entryrecords as $entry) {
            $entry->baseurl = $view->get_baseurl();
            $cells = [];

            foreach ($columns as $column) {
                [$tag, , $class] = $column;
                $cells[] = [
                    'valuehtml' => $this->resolve_cell_html($view, $fields, $entry, $tag),
                    'cellclass' => $class,
                ];
            }

            $rows[] = [
                'id' => (int) $entry->id,
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * Resolve one CSV browse header, allowing existing view tag replacements.
     *
     * @param \datalynxview_csv\view $view
     * @param string $header
     * @return string
     */
    protected function resolve_header_html(\datalynxview_csv\view $view, string $header): string {
        $tags = $view->patternclass()->search($header, false);
        if (empty($tags)) {
            return $header;
        }

        $replacements = $view->patternclass()->get_replacements($tags);
        return str_replace($tags, $replacements, $header);
    }

    /**
     * Resolve one configured CSV browse column tag for an entry.
     *
     * @param \datalynxview_csv\view $view
     * @param array $fields
     * @param stdClass $entry
     * @param string $tag
     * @return string
     */
    protected function resolve_cell_html(\datalynxview_csv\view $view, array $fields, stdClass $entry, string $tag): string {
        foreach ($fields as $field) {
            $definitions = $field->get_definitions([$tag], $entry, ['edit' => false, 'manage' => false]);
            if (!empty($definitions[$tag][1]) && is_string($definitions[$tag][1])) {
                return $definitions[$tag][1];
            }
        }

        $replacements = $view->patternclass()->get_replacements([$tag], $entry);
        if (!empty($replacements[$tag]) && is_string($replacements[$tag])) {
            return $replacements[$tag];
        }

        return '';
    }
}
