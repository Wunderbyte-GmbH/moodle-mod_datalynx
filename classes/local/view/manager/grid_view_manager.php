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

use datalynxfield_entry\field as entry_field;
use mod_datalynx\datalynx;
use mod_datalynx\local\view\base;
use stdClass;

/**
 * Builds the structured browse payload for Grid views.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grid_view_manager extends view_manager {
    /**
     * The view type this manager handles.
     *
     * @return string
     */
    protected function get_view_type(): string {
        return 'grid';
    }

    /**
     * Build the browse payload for one Grid view page.
     *
     * @param datalynx|int $datalynx the instance, or its id
     * @param int $viewid
     * @param array $filteroptions
     * @return array
     */
    public function get_browse_payload(datalynx|int $datalynx, int $viewid, array $filteroptions = []): array {
        $view = $this->open_view($datalynx, $viewid, $filteroptions);
        $entries = $this->load_entries($view);

        $param3 = trim((string) ($view->view->param3 ?? ''));
        $param4 = trim((string) ($view->view->param4 ?? ''));

        // Default to 'entry' for existing views where no setting is saved.
        if ($param3 === '') {
            $param3 = 'entry';
        }

        $nowrapper = ($param3 === 'none');
        $entrywrapperclass = '';

        if (!$nowrapper) {
            if ($param3 === 'custom') {
                $entrywrapperclass = ($param4 !== '') ? $param4 : 'entry';
            } else {
                $entrywrapperclass = $param3;
            }
        }

        $groupclass = '';
        if ($entrywrapperclass === 'col') {
            $groupclass = 'row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4';
        } else if (strpos($entrywrapperclass, 'col-') !== false) {
            $groupclass = 'row g-4';
        }

        $payload = $this->build_payload($view, $entries) + [
            'nowrapper' => $nowrapper,
            'entrywrapperclass' => $entrywrapperclass,
            'groupclass' => $groupclass,
        ];

        $entryrecords = $entries->entries();
        if (empty($entryrecords)) {
            return $payload;
        }

        $payload['hasentries'] = true;
        $payload['groups'][] = [
            'name' => '',
            'hasname' => false,
            'entries' => array_values($this->build_entries($view, $this->get_content_fields($view), $entryrecords)),
        ];

        return $payload;
    }

    /**
     * Whether entries are rendered with the view's entry template.
     *
     * Any non-empty entry template is rendered through the template itself so that the template
     * fully controls the entry output (which field tags appear, in which order, and any surrounding
     * markup or action tags). Only a genuinely empty template falls back to the generic structured
     * field loop. Previously a "tag only" template (one stripped to whitespace once field and action
     * tags were removed) was discarded in favour of that generic loop, which rendered every field
     * regardless of the template, so edits to such a template had no visible effect.
     *
     * @param base $view
     * @return bool
     */
    public function uses_entry_template(base $view): bool {
        return trim((string) ($view->view->eparam2 ?? '')) !== '';
    }

    /**
     * Build the structured entry payloads.
     *
     * @param base $view
     * @param array $fields
     * @param stdClass[] $entryrecords
     * @return array
     */
    protected function build_entries(base $view, array $fields, array $entryrecords): array {
        $entryfield = new entry_field($view->get_dlx());
        $useentryhtml = $this->uses_entry_template($view);
        $entries = [];

        foreach ($entryrecords as $entry) {
            $manageable = $view->get_dlx()->user_can_manage_entry($entry);
            $actionoptions = ['manage' => $manageable];
            $entry->baseurl = $view->get_navigationurl();
            $entryhtml = $useentryhtml ? $view->render_entry_html($entry, ['edit' => false, 'manage' => $manageable]) : '';
            $entries[] = [
                'id' => (int) $entry->id,
                'entryhtml' => $entryhtml,
                // The browser template lists the fields only when there is no entry html, so do not
                // render every field of the instance for entries that have it.
                'fields' => $entryhtml === '' ? $this->build_field_values($fields, $entry) : [],
                'edithtml' => $useentryhtml ? '' : $this->render_field($entryfield, $entry, '##edit##', $actionoptions),
                'deletehtml' => $useentryhtml ? '' : $this->render_field($entryfield, $entry, '##delete##', $actionoptions),
                'hasactions' => !$useentryhtml && $manageable,
            ];
        }

        return $entries;
    }

    /**
     * Build the structured field payload for one entry.
     *
     * @param array $fields
     * @param stdClass $entry
     * @return array
     */
    protected function build_field_values(array $fields, stdClass $entry): array {
        $values = [];

        foreach ($fields as $field) {
            if (!self::is_content_field($field)) {
                continue;
            }
            $values[] = [
                'name' => format_string($field->field->name),
                'size' => 3,
                'valuehtml' => $this->render_field($field, $entry),
            ];
        }

        return $values;
    }
}
