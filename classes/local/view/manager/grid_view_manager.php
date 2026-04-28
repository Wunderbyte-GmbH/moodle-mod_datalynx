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
use datalynxfield_entry\field as entry_field;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the structured browse payload for the Grid view pilot.
 *
 * This manager is the first step in separating view data assembly from direct
 * server-side HTML rendering in the legacy view classes.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grid_view_manager {
    /**
     * Build the browse payload for one Grid view page.
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
            throw new coding_exception('Invalid view id for grid browse payload.');
        }

        $viewrecord = $viewrecords[$viewid];
        if ($viewrecord->type !== 'grid') {
            throw new coding_exception('Grid browse payload manager only supports Grid views.');
        }

        $view = $datalynx->get_view($viewrecord->type, $viewrecord, false);
        if (!empty($filteroptions)) {
            $view->set_filter($filteroptions, $view->is_forcing_filter());
        }
        $fields = $view->remove_duplicates($view->get_dl()->get_fields());
        $view->get_filter()->contentfields = array_keys($fields);

        $entries = new datalynx_entries($datalynx, $view->get_filter());
        $entries->set_content();

        $payload = [
            'datalynxid' => $datalynx->id(),
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
            'entries' => array_values($this->build_entries($view, $fields, $entryrecords)),
        ];

        return $payload;
    }

    /**
     * Build the structured entry payloads for the Grid pilot.
     *
     * @param \mod_datalynx\local\view\base $view
     * @param array $fields
     * @param stdClass[] $entryrecords
     * @return array
     */
    protected function build_entries(\mod_datalynx\local\view\base $view, array $fields, array $entryrecords): array {
        $entryfield = new entry_field($view->get_dl());
        $entries = [];

        foreach ($entryrecords as $entry) {
            $entry->baseurl = $view->get_baseurl();
            $entries[] = [
                'id' => (int) $entry->id,
                'fields' => $this->build_field_values($fields, $entry),
                'edithtml' => $this->resolve_definition_html(
                    $entryfield->get_definitions(
                        ['##edit##'],
                        $entry,
                        ['manage' => $view->get_dl()->user_can_manage_entry($entry)]
                    ),
                    '##edit##'
                ),
                'deletehtml' => $this->resolve_definition_html(
                    $entryfield->get_definitions(
                        ['##delete##'],
                        $entry,
                        ['manage' => $view->get_dl()->user_can_manage_entry($entry)]
                    ),
                    '##delete##'
                ),
                'hasactions' => $view->get_dl()->user_can_manage_entry($entry),
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
            if (!is_numeric($field->field->id) || (int) $field->field->id <= 0) {
                continue;
            }

            $name = format_string($field->field->name);
            $tag = '[[' . $field->field->name . ']]';
            if ($field->type === 'userinfo') {
                $tag = "##author:{$field->field->name}##";
            }

            $definitions = $field->get_definitions([$tag], $entry, ['edit' => false, 'manage' => false]);
            $values[] = [
                'name' => $name,
                'size' => 3,
                'valuehtml' => $this->resolve_definition_html($definitions, $tag),
            ];
        }

        return $values;
    }

    /**
     * Resolve the rendered HTML string from a field definition map.
     *
     * @param array $definitions
     * @param string $tag
     * @return string
     */
    protected function resolve_definition_html(array $definitions, string $tag): string {
        if (empty($definitions[$tag][1]) || !is_string($definitions[$tag][1])) {
            return '';
        }

        return $definitions[$tag][1];
    }
}
