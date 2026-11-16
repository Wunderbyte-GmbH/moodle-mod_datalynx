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

namespace mod_datalynx\local\filter;

use mod_datalynx\datalynx;
use mod_datalynx\local\view\base;
use stdClass;

/**
 * What a custom filter searches in a view, and the search it makes of the values entered.
 *
 * One place for both clients (mod_datalynx#270): the custom filter form of the web
 * (datalynx_customfilter_frontend_form, datalynx_filter_manager::get_filter_from_customfilterform())
 * and the Moodle App (output\mobile) take their fields from get_fields() and hand the values
 * entered to criteria(). Each field turns its value into criteria itself
 * (datalynxfield_base::get_customfilter_criteria()), so the web and the app cannot search
 * differently for the same input.
 *
 * Values, keyed like the fields:
 * - 'search': the full-text search
 * - 'userid': author user ids
 * - a field id (internal fields by their id: approve, status, timecreated, timemodified): the
 *   value in the shape the field takes it
 * - 'sort': ['field' => field id, 'dir' => 0 ascending | 1 descending]
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class customfilter_search {
    /** @var string[] internal field id => the customfilter column that turns it on */
    const INTERNAL = [
        'approve' => 'approve',
        'status' => 'status',
        'timecreated' => 'timecreated',
        'timemodified' => 'timemodified',
    ];

    /** @var string[] internal field id => the customfilter column that makes it sortable */
    const INTERNAL_SORTABLE = [
        'timecreated' => 'timecreatedsortable',
        'timemodified' => 'timemodifiedsortable',
    ];

    /** @var datalynx */
    private datalynx $dlx;

    /** @var stdClass the datalynx_customfilters record */
    private stdClass $customfilter;

    /**
     * Constructor.
     *
     * @param datalynx $dlx
     * @param stdClass $customfilter a datalynx_customfilters record of this instance
     */
    public function __construct(datalynx $dlx, stdClass $customfilter) {
        $this->dlx = $dlx;
        $this->customfilter = $customfilter;
    }

    /**
     * The custom filter record.
     *
     * @return stdClass
     */
    public function get_customfilter(): stdClass {
        return $this->customfilter;
    }

    /**
     * The context of the instance.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->dlx->context;
    }

    /**
     * The fields the filter lists, as configured: field id => ['name' => …, 'sortable' => bool].
     *
     * @return array
     */
    private function get_fieldlist(): array {
        $list = json_decode((string) ($this->customfilter->fieldlist ?? ''), true);
        return is_array($list) ? $list : [];
    }

    /**
     * The fields the filter searches in a view, in the order of the view.
     *
     * A field must be shown by the view and be listed by the filter; an internal field (approve,
     * status, time created and modified) must be turned on in the filter. Fields that cannot be
     * searched are left out.
     *
     * @param base|null $view null: the fields of the whole instance
     * @return array field id => field object
     */
    public function get_fields(?base $view): array {
        $listed = $this->get_fieldlist();
        $fields = [];
        foreach ($this->get_candidate_fields($view) as $fieldid => $field) {
            $key = (string) $fieldid;
            if ($field::is_internal()) {
                $column = self::INTERNAL[$key] ?? null;
                if (!$column || empty($this->customfilter->$column)) {
                    continue;
                }
            } else if (!array_key_exists($key, $listed)) {
                continue;
            }
            if (!$field->supports_search() || empty($field->get_supported_search_operators())) {
                continue;
            }
            $fields[$fieldid] = $field;
        }

        return $fields;
    }

    /**
     * The fields the filter lets the user sort by, in the order of the view: field id => name.
     *
     * @param base|null $view null: the fields of the whole instance
     * @return array
     */
    public function get_sort_fields(?base $view): array {
        $listed = $this->get_fieldlist();
        $sortfields = [];
        foreach ($this->get_candidate_fields($view) as $fieldid => $field) {
            $key = (string) $fieldid;
            if ($field::is_internal()) {
                $column = self::INTERNAL_SORTABLE[$key] ?? null;
                $sortable = $column && !empty($this->customfilter->$column);
            } else {
                $sortable = !empty($listed[$key]['sortable']);
            }
            if ($sortable && $field->supports_sort()) {
                $sortfields[$fieldid] = $field->name();
            }
        }

        return $sortfields;
    }

    /**
     * The fields a filter can offer: those the view shows, or all fields of the instance.
     *
     * @param base|null $view
     * @return array field id => field object
     */
    private function get_candidate_fields(?base $view): array {
        return $view ? $view->get_view_fields() : $this->dlx->get_fields();
    }

    /**
     * The users who wrote entries in this instance, for the author search: user id => full name.
     *
     * @return array
     */
    public function get_authors(): array {
        global $DB;

        if (empty($this->customfilter->authorsearch)) {
            return [];
        }
        $authors = $DB->get_records_sql(
            'SELECT DISTINCT u.id, ' . implode(', ', array_map(fn($name) => "u.$name", \core_user\fields::get_name_fields())) . '
               FROM {datalynx_entries} e
               JOIN {user} u ON u.id = e.userid
              WHERE e.dataid = :dataid',
            ['dataid' => $this->dlx->id()]
        );
        $menu = [];
        foreach ($authors as $author) {
            $menu[(int) $author->id] = fullname($author);
        }
        \core_collator::asort($menu);

        return $menu;
    }

    /**
     * The search the entered values make.
     *
     * @param base|null $view the view the filter is used in; null: the whole instance
     * @param array $values see the class description
     * @return array ['search' => string, 'customsearch' => criteria, 'customsort' => [field id => dir]]
     */
    public function criteria(?base $view, array $values): array {
        $result = ['search' => '', 'customsearch' => [], 'customsort' => []];

        if (!empty($this->customfilter->fulltextsearch) && is_scalar($values['search'] ?? null)) {
            $result['search'] = trim((string) $values['search']);
        }

        $search = [];
        if (!empty($this->customfilter->authorsearch) && isset($values['userid'])) {
            $authors = array_values(array_unique(array_filter(
                array_map('intval', (array) $values['userid']),
                fn(int $id) => $id > 0
            )));
            if ($authors) {
                $search['userid']['AND'][] = ['', '=', $authors];
            }
        }
        foreach ($this->get_fields($view) as $fieldid => $field) {
            if (!array_key_exists((string) $fieldid, $values)) {
                continue;
            }
            foreach ($field->get_customfilter_criteria($values[(string) $fieldid]) as $criterion) {
                $search[$fieldid]['AND'][] = $criterion;
            }
        }
        $result['customsearch'] = stored_criteria::normalise_search($search, stored_criteria::field_types((int) $this->dlx->id()));

        $sort = $values['sort'] ?? [];
        $sortfield = is_array($sort) && is_scalar($sort['field'] ?? null) ? (string) $sort['field'] : '';
        if ($sortfield !== '' && array_key_exists($sortfield, $this->get_sort_fields($view))) {
            $result['customsort'] = [$sortfield => empty($sort['dir']) ? 0 : 1];
        }

        return $result;
    }
}
