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
use stdClass;

/**
 * Builds the structured browse payload for the CSV view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_view_manager extends view_manager {
    /**
     * The view type this manager handles.
     *
     * @return string
     */
    protected function get_view_type(): string {
        return 'csv';
    }

    /**
     * The view type as it appears in exception messages.
     *
     * @return string
     */
    protected function get_view_label(): string {
        return 'CSV';
    }

    /**
     * Build the browse payload for one CSV view page.
     *
     * @param datalynx|int $datalynx the instance, or its id
     * @param int $viewid
     * @param array $filteroptions
     * @return array
     */
    public function get_browse_payload(datalynx|int $datalynx, int $viewid, array $filteroptions = []): array {
        /** @var \datalynxview_csv\view $view */
        $view = $this->open_view($datalynx, $viewid, $filteroptions);
        $entries = $this->load_entries($view);
        $payload = $this->build_payload($view, $entries);

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
            'rows' => array_values($this->build_rows($view, $view->get_dlx()->get_fields(), $entryrecords)),
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
        $columnfields = $this->get_column_fields($view, $fields);
        $rows = [];

        foreach ($entryrecords as $entry) {
            $entry->baseurl = $view->get_navigationurl();
            $cells = [];

            foreach ($columns as $column) {
                [$tag, , $class] = $column;
                $cells[] = [
                    'valuehtml' => $this->resolve_cell_html($view, $columnfields[$tag] ?? null, $entry, $tag),
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
     * The field each column tag belongs to, as the view's pattern scan assigned it.
     *
     * @param \datalynxview_csv\view $view
     * @param array $fields all fields of the instance keyed by id
     * @return array tag => field
     */
    protected function get_column_fields(\datalynxview_csv\view $view, array $fields): array {
        $columnfields = [];
        foreach ($view->get__patterns('field') ?? [] as $fieldid => $patterns) {
            if (!isset($fields[$fieldid])) {
                continue;
            }
            foreach ((array) $patterns as $pattern) {
                $columnfields[$pattern] ??= $fields[$fieldid];
            }
        }
        return $columnfields;
    }

    /**
     * Resolve one configured CSV browse column tag for an entry.
     *
     * @param \datalynxview_csv\view $view
     * @param object|null $field the field the tag belongs to, null for view tags
     * @param stdClass $entry
     * @param string $tag
     * @return string
     */
    protected function resolve_cell_html(\datalynxview_csv\view $view, ?object $field, stdClass $entry, string $tag): string {
        return $field ? $this->render_field($field, $entry, $tag) : self::pattern_html($view, $tag, $entry);
    }
}
