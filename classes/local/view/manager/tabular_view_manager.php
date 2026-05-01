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
use datalynxfield_approve\field as approve_field;
use datalynxfield_entry\field as entry_field;
use datalynxfield_entryauthor\field as entryauthor_field;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the structured browse payload for the Tabular view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabular_view_manager {
    /**
     * Build the browse payload for one Tabular view page.
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
            throw new coding_exception('Invalid view id for tabular browse payload.');
        }

        $viewrecord = $viewrecords[$viewid];
        if ($viewrecord->type !== 'tabular') {
            throw new coding_exception('Tabular browse payload manager only supports Tabular views.');
        }

        $view = $datalynx->get_view($viewrecord->type, $viewrecord, false);
        if (!empty($filteroptions)) {
            $view->set_filter($filteroptions, $view->is_forcing_filter());
        }

        $allfields = $view->get_dl()->get_fields();
        $fields = $view->remove_duplicates($allfields);
        $view->get_filter()->contentfields = array_keys($fields);

        $entries = new datalynx_entries($datalynx, $view->get_filter());
        $entries->set_content();
        $entryrecords = $entries->entries();
        $showentryactions = $this->has_manageable_entries($view, $entryrecords);

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

        if (empty($entryrecords)) {
            return $payload;
        }

        $payload['hasentries'] = true;
        $payload['groups'][] = [
            'name' => '',
            'hasname' => false,
            'columns' => $this->build_columns($view, $fields, $showentryactions),
            'rows' => array_values($this->build_rows($view, $allfields, $fields, $entryrecords)),
        ];

        return $payload;
    }

    /**
     * Determine whether any visible entry is manageable by the current user.
     *
     * @param \mod_datalynx\local\view\base $view
     * @param stdClass[] $entryrecords
     * @return bool
     */
    protected function has_manageable_entries(\mod_datalynx\local\view\base $view, array $entryrecords): bool {
        foreach ($entryrecords as $entry) {
            if ($view->get_dl()->user_can_manage_entry($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build ordered tabular browse columns.
     *
     * @param \mod_datalynx\local\view\base $view
     * @param array $fields
     * @param bool $showentryactions
     * @return array
     */
    protected function build_columns(\mod_datalynx\local\view\base $view, array $fields, bool $showentryactions): array {
        $columns = [
            ['headerhtml' => '', 'alignclass' => 'text-center'],
            ['headerhtml' => '', 'alignclass' => 'text-left'],
        ];

        foreach ($fields as $field) {
            if (!is_numeric($field->field->id) || (int) $field->field->id <= 0) {
                continue;
            }

            $fieldname = format_string($field->field->name);
            $bulkedittag = "%%{$field->field->name}:bulkedit%%";
            $bulkedit = $this->resolve_pattern_html($view, $bulkedittag, ['showentryactions' => $showentryactions]);
            $columns[] = [
                'headerhtml' => trim($fieldname . ' ' . $bulkedit),
                'alignclass' => 'text-left',
            ];
        }

        foreach ([
            '##multiedit:icon##',
            '##multidelete:icon##',
            '##multiapprove:icon##',
            '##selectallnone##',
        ] as $tag) {
            $columns[] = [
                'headerhtml' => $this->resolve_pattern_html($view, $tag, ['showentryactions' => $showentryactions]),
                'alignclass' => 'text-center',
            ];
        }

        $lastindex = count($columns) - 1;
        foreach ($columns as $index => &$column) {
            $column['columnclass'] = 'c' . $index;
            $column['islast'] = $index === $lastindex;
            $column['alignstyle'] = $column['alignclass'] === 'text-center' ? 'center' : 'left';
        }
        unset($column);

        return $columns;
    }

    /**
     * Build ordered tabular browse rows.
     *
     * @param \mod_datalynx\local\view\base $view
     * @param array $allfields
     * @param array $fields
     * @param stdClass[] $entryrecords
     * @return array
     */
    protected function build_rows(
        \mod_datalynx\local\view\base $view,
        array $allfields,
        array $fields,
        array $entryrecords
    ): array {
        $entryfield = new entry_field($view->get_dl());
        $approvefield = $allfields[approve_field::_APPROVED] ?? null;
        $authorpicturefield = $allfields[entryauthor_field::_USERPICTURE] ?? null;
        $authornamefield = $allfields[entryauthor_field::_USERNAME] ?? null;
        $rows = [];

        foreach ($entryrecords as $entry) {
            $entry->baseurl = $view->get_baseurl();
            $manageable = $view->get_dl()->user_can_manage_entry($entry);
            $cells = [];

            $cells[] = [
                'valuehtml' => $authorpicturefield
                    ? $this->resolve_definition_html(
                        $authorpicturefield->get_definitions(['##author:picture##'], $entry, ['edit' => false, 'manage' => false]),
                        '##author:picture##'
                    )
                    : '',
                'alignclass' => 'text-center',
            ];
            $cells[] = [
                'valuehtml' => $authornamefield
                    ? $this->resolve_definition_html(
                        $authornamefield->get_definitions(['##author:name##'], $entry, ['edit' => false, 'manage' => false]),
                        '##author:name##'
                    )
                    : '',
                'alignclass' => 'text-left',
            ];

            foreach ($fields as $field) {
                if (!is_numeric($field->field->id) || (int) $field->field->id <= 0) {
                    continue;
                }

                $tag = '[[' . $field->field->name . ']]';
                if ($field->type === 'userinfo') {
                    $tag = "##author:{$field->field->name}##";
                }

                $cells[] = [
                    'valuehtml' => $this->resolve_definition_html(
                        $field->get_definitions([$tag], $entry, ['edit' => false, 'manage' => false]),
                        $tag
                    ),
                    'alignclass' => 'text-left',
                ];
            }

            foreach ([
                '##edit##',
                '##delete##',
            ] as $tag) {
                $cells[] = [
                    'valuehtml' => $this->resolve_definition_html(
                        $entryfield->get_definitions([$tag], $entry, ['edit' => false, 'manage' => $manageable]),
                        $tag
                    ),
                    'alignclass' => 'text-center',
                ];
            }

            $cells[] = [
                'valuehtml' => $approvefield
                    ? $this->resolve_definition_html(
                        $approvefield->get_definitions(['##approve##'], $entry, [
                            'edit' => false,
                            'manage' => $manageable,
                            'viewid' => $view->id(),
                        ]),
                        '##approve##'
                    )
                    : '',
                'alignclass' => 'text-center',
            ];
            $cells[] = [
                'valuehtml' => $this->resolve_definition_html(
                    $entryfield->get_definitions(['##select##'], $entry, ['edit' => false, 'manage' => $manageable]),
                    '##select##'
                ),
                'alignclass' => 'text-center',
            ];

            $lastindex = count($cells) - 1;
            foreach ($cells as $index => &$cell) {
                $cell['columnclass'] = 'c' . $index;
                $cell['islast'] = $index === $lastindex;
                $cell['alignstyle'] = $cell['alignclass'] === 'text-center' ? 'center' : 'left';
            }
            unset($cell);

            $rows[] = [
                'id' => (int) $entry->id,
                'rowclass' => 'lastrow',
                'cells' => $cells,
            ];
        }

        return $rows;
    }

    /**
     * Resolve a rendered field definition to HTML.
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

    /**
     * Resolve a view pattern replacement to HTML.
     *
     * @param \mod_datalynx\local\view\base $view
     * @param string $tag
     * @param array $options
     * @return string
     */
    protected function resolve_pattern_html(\mod_datalynx\local\view\base $view, string $tag, array $options = []): string {
        $replacements = $view->patternclass()->get_replacements([$tag], null, $options);
        return $replacements[$tag] ?? '';
    }
}
