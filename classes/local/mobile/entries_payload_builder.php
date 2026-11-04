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

namespace mod_datalynx\local\mobile;

use datalynxfield_fieldgroup\field as fieldgroup_field;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\search_criteria;
use mod_datalynx\local\view\base;
use moodle_url;
use stdClass;

/**
 * Builds the entries payload the Moodle App browses.
 *
 * Both the first paint (\mod_datalynx\output\mobile::mobile_course_view()) and every later refresh
 * (\mod_datalynx\external\get_mobile_entries) go through this one builder, so what the app shows
 * after a search, a view switch or a pull-to-refresh is exactly what it showed on opening.
 *
 * Grid and tabular views are browsable. Their desktop browse payloads differ in shape (grouped
 * entries vs. rows of cells) and carry Bootstrap classes and action links, so they are not passed
 * through. The builder projects both onto one mobile shape instead:
 * - `html`: the entry rendered with the view's own entry template, so an entry looks in the app
 *   the way its author designed it. Action tags render empty (manage => false), and markup the
 *   app cannot use is removed by html_sanitiser.
 * - `fields`: name, HTML and plain text of every field the view shows, for tabular views (which
 *   the app lists rather than draws as a table), for accessible labels, and as the fallback when
 *   a grid view has no entry template.
 *
 * The filter state arrives with every request and is never stored, unlike the desktop's user
 * preferences: two devices of one user would otherwise overwrite each other's filter.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entries_payload_builder {
    /** @var string[] View types the app can browse. */
    const SUPPORTED_TYPES = ['grid', 'tabular'];

    /** @var int Most entries one request may ask for; the app grows its window up to this. */
    const MAX_PERPAGE = 200;

    /** @var datalynx */
    private datalynx $dlx;

    /**
     * Constructor.
     *
     * @param datalynx $dlx
     */
    public function __construct(datalynx $dlx) {
        $this->dlx = $dlx;
    }

    /**
     * Build the payload for one browse request.
     *
     * @param array $request any of viewid, filterid, page, perpage, search, criteria (JSON),
     *                       sort (JSON), eids (comma separated ids)
     * @return array
     * @throws invalid_parameter_exception for a view, filter or criteria the user may not use
     */
    public function build(array $request): array {
        $request += ['viewid' => 0, 'filterid' => 0, 'page' => 0, 'perpage' => 0, 'search' => '',
            'criteria' => '', 'sort' => '', 'eids' => ''];

        $records = $this->dlx->get_view_records();
        // Creation order, whatever order the database returns them in, so the view menu is stable.
        ksort($records);
        $record = $this->resolve_view_record($records, (int) $request['viewid']);

        $payload = [
            'datalynxid' => (int) $this->dlx->id(),
            'cmid' => (int) $this->dlx->cm->id,
            'viewid' => $record ? (int) $record->id : 0,
            'viewname' => $record ? format_string($record->name, true, ['context' => $this->dlx->context]) : '',
            'viewtype' => $record ? $record->type : '',
            'supported' => $record && in_array($record->type, self::SUPPORTED_TYPES, true),
            'browserurl' => $this->browser_url($record),
            'canfilter' => false,
            'filterid' => 0,
            'page' => 0,
            'perpage' => 0,
            'search' => '',
            'criteria' => '',
            'sort' => '',
            'entriescount' => 0,
            'entriesfiltercount' => 0,
            'hasmore' => false,
            'entries' => [],
            'views' => $this->build_views($records, $record),
            'filters' => [],
            'emptycontent' => get_string('noentries', 'datalynx'),
        ];

        if (!$payload['supported']) {
            return $payload;
        }

        $view = $this->dlx->get_view($record->type, $record, false);
        $canfilter = !$view->is_forcing_filter();
        $filtermenu = $canfilter ? $this->get_filter_menu($view) : [];

        $filterid = (int) $request['filterid'];
        if ($filterid && !array_key_exists($filterid, $filtermenu)) {
            throw new invalid_parameter_exception('Filter not available in this view.');
        }

        $options = [
            'filterid' => $filterid,
            'page' => max(0, (int) $request['page']),
            // Always set, so an eids value the request happens to carry under that name is not
            // picked up from the URL by set_filter().
            'eids' => clean_param((string) $request['eids'], PARAM_SEQUENCE),
        ];
        if ((int) $request['perpage'] > 0) {
            $options['perpage'] = min((int) $request['perpage'], self::MAX_PERPAGE);
        }
        // A view that forces its filter offers no search on the desktop either, so whatever the
        // client sends for it is ignored rather than allowed to widen what the view lists.
        $criteria = $sort = [];
        if ($canfilter) {
            $options['search'] = trim((string) $request['search']);
            $criteria = search_criteria::decode_search((string) $request['criteria'], $this->dlx);
            $sort = search_criteria::decode_sort((string) $request['sort'], $this->dlx);
            $options['customsearch'] = $criteria;
            $options['customsort'] = $sort;
        }

        $view->set_filter($options, true);
        $filter = $view->get_filter();
        $filter->perpage = min((int) $filter->perpage, self::MAX_PERPAGE);
        $fields = $view->remove_duplicates($this->dlx->get_fields());
        $filter->contentfields = $this->expand_content_field_ids($fields);

        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();

        $payload['canfilter'] = $canfilter;
        $payload['filterid'] = (int) ($filter->id ?? 0);
        $payload['page'] = (int) $filter->page;
        $payload['perpage'] = (int) $filter->perpage;
        $payload['search'] = $canfilter ? (string) $filter->search : '';
        $payload['criteria'] = search_criteria::encode($criteria);
        $payload['sort'] = search_criteria::encode($sort);
        $payload['entriescount'] = $entries->get_count();
        $payload['entriesfiltercount'] = $entries->get_count(true);
        $payload['hasmore'] = $filter->perpage > 0
            && ($payload['page'] + 1) * $payload['perpage'] < $payload['entriesfiltercount'];
        $payload['entries'] = $this->build_entries($view, $fields, $entries->entries() ?? []);
        $payload['filters'] = $this->build_filters($filtermenu, $payload['filterid']);

        return $payload;
    }

    /**
     * Pick the view to browse.
     *
     * No view requested means the activity's default view, or the first one the app can show
     * when the default is hidden from this user or is a type the app cannot browse.
     *
     * @param stdClass[] $records the views visible to this user
     * @param int $viewid the requested view, 0 for the default
     * @return stdClass|null null when the user can see no view at all
     * @throws invalid_parameter_exception for a view the user cannot see
     */
    private function resolve_view_record(array $records, int $viewid): ?stdClass {
        if ($viewid) {
            if (empty($records[$viewid])) {
                throw new invalid_parameter_exception('View not available.');
            }
            return $records[$viewid];
        }

        $default = $records[$this->dlx->get_default_view_id()] ?? null;
        if ($default && in_array($default->type, self::SUPPORTED_TYPES, true)) {
            return $default;
        }
        foreach ($records as $record) {
            if (in_array($record->type, self::SUPPORTED_TYPES, true)) {
                return $record;
            }
        }

        return $default ?: (reset($records) ?: null);
    }

    /**
     * The web address of a view, for the "open in browser" fallback.
     *
     * @param stdClass|null $record
     * @return string
     */
    private function browser_url(?stdClass $record): string {
        $params = ['d' => $this->dlx->id()];
        if ($record) {
            $params['view'] = $record->id;
        }

        return (new moodle_url('/mod/datalynx/view.php', $params))->out(false);
    }

    /**
     * The view switcher: every view this user can see, marked with whether the app can show it.
     *
     * @param stdClass[] $records
     * @param stdClass|null $current
     * @return array
     */
    private function build_views(array $records, ?stdClass $current): array {
        $views = [];
        foreach ($records as $record) {
            $views[] = [
                'id' => (int) $record->id,
                'name' => format_string($record->name, true, ['context' => $this->dlx->context]),
                'type' => $record->type,
                'supported' => in_array($record->type, self::SUPPORTED_TYPES, true),
                'active' => $current && (int) $current->id === (int) $record->id,
            ];
        }

        return $views;
    }

    /**
     * The saved filters this view lets the user switch to, as the desktop filter menu offers them.
     *
     * @param base $view
     * @return array filter id => name
     */
    private function get_filter_menu(base $view): array {
        $menu = $this->dlx->get_filter_manager()->get_filters(null, true) ?: [];
        if ($view->is_filter_whitelist_active()) {
            $menu = array_intersect_key($menu, array_flip($view->get_permitted_filter_ids()));
        }

        return $menu;
    }

    /**
     * The filter switcher entries.
     *
     * @param array $menu filter id => name
     * @param int $activeid
     * @return array
     */
    private function build_filters(array $menu, int $activeid): array {
        $filters = [];
        foreach ($menu as $id => $name) {
            $filters[] = [
                'id' => (int) $id,
                'name' => format_string($name, true, ['context' => $this->dlx->context]),
                'active' => (int) $id === $activeid,
            ];
        }

        return $filters;
    }

    /**
     * Project the entries of one page onto the mobile entry shape.
     *
     * @param base $view
     * @param array $fields all fields of the instance, fieldgroup members removed
     * @param stdClass[] $records
     * @return array
     */
    private function build_entries(base $view, array $fields, array $records): array {
        $usetemplate = $view->type() === 'grid' && trim((string) ($view->view->eparam2 ?? '')) !== '';
        $shown = $this->get_shown_fields($view, $fields);
        $entries = [];

        foreach ($records as $record) {
            $record->baseurl = $view->get_navigationurl();
            $html = $usetemplate
                ? html_sanitiser::for_app($view->render_entry_html($record, ['edit' => false, 'manage' => false]))
                : '';

            $entries[] = [
                'id' => (int) $record->id,
                'html' => $html,
                'fields' => $this->build_field_values($shown, $record),
                'userid' => (int) $record->userid,
                'author' => fullname($record),
                'approved' => !empty($record->approved),
                'status' => (int) ($record->status ?? 0),
                'timecreated' => (int) $record->timecreated,
                'timemodified' => (int) $record->timemodified,
            ];
        }

        return $entries;
    }

    /**
     * The fields to list for each entry: those the view's templates use, or all of them when
     * the view names none.
     *
     * @param base $view
     * @param array $fields
     * @return array
     */
    private function get_shown_fields(base $view, array $fields): array {
        $fields = array_filter($fields, fn($field) => is_numeric($field->field->id) && (int) $field->field->id > 0);
        $used = array_intersect_key($fields, $view->get__patterns('field') ?? []);

        return $used ?: $fields;
    }

    /**
     * Render each shown field of one entry, leaving out the empty ones.
     *
     * The field's own display path is used, so behaviors (visibility, conditions) and field
     * layouts apply exactly as on the web.
     *
     * @param array $fields
     * @param stdClass $entry
     * @return array
     */
    private function build_field_values(array $fields, stdClass $entry): array {
        $values = [];
        foreach ($fields as $field) {
            $tag = base::FIELD_TAG_OPEN . $field->field->name . base::FIELD_TAG_CLOSE;
            $definitions = $field->get_definitions([$tag], $entry, ['edit' => false, 'manage' => false]);
            $html = isset($definitions[$tag][1]) && is_string($definitions[$tag][1])
                ? html_sanitiser::for_app($definitions[$tag][1])
                : '';
            $text = trim(html_to_text($html, 0, false));
            if ($text === '' && !str_contains($html, '<img')) {
                continue;
            }
            $values[] = [
                'fieldid' => (int) $field->field->id,
                'name' => format_string($field->field->name, true, ['context' => $this->dlx->context]),
                'valuehtml' => $html,
                'valuetext' => $text,
            ];
        }

        return $values;
    }

    /**
     * The field ids whose content the entries query must load, fieldgroup members included.
     *
     * @param array $fields
     * @return array
     */
    private function expand_content_field_ids(array $fields): array {
        $ids = [];
        foreach ($fields as $fieldid => $field) {
            $ids[] = is_numeric($fieldid) ? (int) $fieldid : $fieldid;
            if ($field instanceof fieldgroup_field) {
                $ids = array_merge($ids, array_map('intval', $field->fieldids));
            }
        }

        return array_values(array_unique($ids));
    }
}
