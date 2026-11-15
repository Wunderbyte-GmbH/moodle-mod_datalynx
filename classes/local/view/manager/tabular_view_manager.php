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

use datalynxfield_approve\field as approve_field;
use datalynxfield_entry\field as entry_field;
use datalynxfield_entryauthor\field as entryauthor_field;
use mod_datalynx\datalynx;
use mod_datalynx\local\view\base;
use stdClass;

/**
 * Builds the structured browse payload for the Tabular view.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabular_view_manager extends view_manager {
    /**
     * The view type this manager handles.
     *
     * @return string
     */
    protected function get_view_type(): string {
        return 'tabular';
    }

    /**
     * Build the browse payload for one Tabular view page.
     *
     * @param datalynx|int $datalynx the instance, or its id
     * @param int $viewid
     * @param array $filteroptions
     * @return array
     */
    public function get_browse_payload(datalynx|int $datalynx, int $viewid, array $filteroptions = []): array {
        $view = $this->open_view($datalynx, $viewid, $filteroptions);
        $entries = $this->load_entries($view);
        $payload = $this->build_payload($view, $entries);

        $entryrecords = $entries->entries();
        if (empty($entryrecords)) {
            return $payload;
        }

        $fields = $this->get_content_fields($view);
        $showentryactions = $this->has_manageable_entries($view, $entryrecords);
        $payload['hasentries'] = true;
        $payload['groups'][] = [
            'name' => '',
            'hasname' => false,
            'columns' => $this->build_columns($view, $fields, $showentryactions),
            'rows' => array_values($this->build_rows($view, $view->get_dlx()->get_fields(), $fields, $entryrecords)),
        ];

        return $payload;
    }

    /**
     * Build ordered tabular browse columns.
     *
     * @param base $view
     * @param array $fields
     * @param bool $showentryactions
     * @return array
     */
    protected function build_columns(base $view, array $fields, bool $showentryactions): array {
        $columns = [
            ['headerhtml' => '', 'alignclass' => 'text-center'],
            ['headerhtml' => '', 'alignclass' => 'text-left'],
        ];

        foreach ($fields as $field) {
            if (!self::is_content_field($field)) {
                continue;
            }

            $fieldname = format_string($field->field->name);
            $bulkedittag = "%%{$field->field->name}:bulkedit%%";
            $bulkedit = self::pattern_html($view, $bulkedittag, null, ['showentryactions' => $showentryactions]);
            $columns[] = [
                'headerhtml' => trim($fieldname . ' ' . $bulkedit),
                'alignclass' => 'text-left',
            ];
        }

        foreach (
            [
                '##multiedit:icon##',
                '##multidelete:icon##',
                '##multiapprove:icon##',
                '##selectallnone##',
            ] as $tag
        ) {
            $columns[] = [
                'headerhtml' => self::pattern_html($view, $tag, null, ['showentryactions' => $showentryactions]),
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
     * @param base $view
     * @param array $allfields
     * @param array $fields
     * @param stdClass[] $entryrecords
     * @return array
     */
    protected function build_rows(
        base $view,
        array $allfields,
        array $fields,
        array $entryrecords
    ): array {
        $entryfield = new entry_field($view->get_dlx());
        $approvefield = $allfields[approve_field::_APPROVED] ?? null;
        $authorpicturefield = $allfields[entryauthor_field::_USERPICTURE] ?? null;
        $authornamefield = $allfields[entryauthor_field::_USERNAME] ?? null;
        $rows = [];

        foreach ($entryrecords as $entry) {
            $entry->baseurl = $view->get_navigationurl();
            $manageable = $view->get_dlx()->user_can_manage_entry($entry);
            $cells = [];

            $cells[] = [
                'valuehtml' => $authorpicturefield
                    ? self::definition_html(
                        $authorpicturefield->get_definitions(['##author:picture##'], $entry, ['edit' => false, 'manage' => false]),
                        '##author:picture##'
                    )
                    : '',
                'alignclass' => 'text-center',
            ];
            $cells[] = [
                'valuehtml' => $authornamefield
                    ? self::definition_html(
                        $authornamefield->get_definitions(['##author:name##'], $entry, ['edit' => false, 'manage' => false]),
                        '##author:name##'
                    )
                    : '',
                'alignclass' => 'text-left',
            ];

            foreach ($fields as $field) {
                if (!self::is_content_field($field)) {
                    continue;
                }

                $tag = base::FIELD_TAG_OPEN . $field->field->name . base::FIELD_TAG_CLOSE;

                $cells[] = [
                    'valuehtml' => self::definition_html(
                        $field->get_definitions([$tag], $entry, ['edit' => false, 'manage' => false]),
                        $tag
                    ),
                    'alignclass' => 'text-left',
                ];
            }

            foreach (
                [
                    '##edit##',
                    '##delete##',
                ] as $tag
            ) {
                $cells[] = [
                    'valuehtml' => self::definition_html(
                        $entryfield->get_definitions([$tag], $entry, ['edit' => false, 'manage' => $manageable]),
                        $tag
                    ),
                    'alignclass' => 'text-center',
                ];
            }

            $cells[] = [
                'valuehtml' => $approvefield
                    ? self::definition_html(
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
                'valuehtml' => self::definition_html(
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
}
