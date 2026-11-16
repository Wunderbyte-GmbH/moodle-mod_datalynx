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

use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\external\browse_request;
use mod_datalynx\local\filter\search_criteria;
use mod_datalynx\local\view\base;
use mod_datalynx\local\view\manager\grid_view_manager;
use mod_datalynx\local\view\manager\tabular_view_manager;
use mod_datalynx\local\view\manager\view_manager;
use moodle_url;
use stdClass;

/**
 * Builds the entries payload the Moodle App browses.
 *
 * Both the first paint (\mod_datalynx\output\mobile::mobile_course_view()) and every later refresh
 * (\mod_datalynx\external\get_mobile_entries) go through this one builder, so what the app shows
 * after a search, a view switch or a pull-to-refresh is exactly what it showed on opening.
 *
 * Grid and tabular views are browsable. The entries are opened, filtered and loaded by the same
 * view managers the desktop browse services use, so the app lists exactly what the web lists.
 * Their desktop payloads differ in shape (grouped entries vs. rows of cells) and carry Bootstrap
 * classes and action links, so they are not passed through. The builder projects both onto one
 * mobile shape instead:
 * - `html`: the entry rendered with the view's own entry template, so an entry looks in the app
 *   the way its author designed it. Action tags render empty (manage => false), and markup the
 *   app cannot use is removed by html_sanitiser.
 * - `fields`: name, HTML and plain text of every field the view shows, for tabular views (which
 *   the app lists rather than draws as a table), for accessible labels, and as the fallback when
 *   a grid view has no entry template.
 *
 * What the user may do with the entries (canadd, canedit, candelete) is decided by the checks the
 * services that do it apply: the user's rights on the entry and the action tags of the view, as
 * on the web, where a view without ##edit## offers no editing.
 *
 * The filter state arrives with every request and is never stored, unlike the desktop's user
 * preferences: two devices of one user would otherwise overwrite each other's filter.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entries_payload_builder {
    /** @var string[] View types the app can browse, with the manager that loads their entries. */
    const MANAGERS = [
        'grid' => grid_view_manager::class,
        'tabular' => tabular_view_manager::class,
    ];

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
            'supported' => $record && isset(self::MANAGERS[$record->type]),
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
            'canadd' => false,
            'entries' => [],
            'views' => $this->build_views($records, $record),
            'filters' => [],
            'emptycontent' => get_string('noentries', 'datalynx'),
        ];

        if (!$payload['supported']) {
            return $payload;
        }

        $request['perpage'] = (int) $request['perpage'];
        $options = browse_request::filter_options([
            'filterid' => (int) $request['filterid'],
            'page' => (int) $request['page'],
            'perpage' => $request['perpage'] > 0 ? min($request['perpage'], self::MAX_PERPAGE) : 0,
            'eids' => (string) $request['eids'],
            'search' => trim((string) $request['search']),
            'customsearch' => (string) $request['criteria'],
            'customsort' => (string) $request['sort'],
        ], $this->dlx);

        $managerclass = self::MANAGERS[$record->type];
        /** @var view_manager $manager */
        $manager = new $managerclass();
        // A view that forces its filter offers no search on the desktop either, so the manager
        // ignores what the client sends for it rather than letting it widen what the view lists.
        $view = $manager->open_view($this->dlx, (int) $record->id, $options);
        $canfilter = !$view->is_forcing_filter();
        $filtermenu = $canfilter ? $this->get_filter_menu($view) : [];
        if ($options['filterid'] && !array_key_exists($options['filterid'], $filtermenu)) {
            throw new invalid_parameter_exception('Filter not available in this view.');
        }

        $filter = $view->get_filter();
        $filter->perpage = min((int) $filter->perpage, self::MAX_PERPAGE);
        $entries = $manager->load_entries($view);

        $payload['canfilter'] = $canfilter;
        $payload['filterid'] = (int) ($filter->id ?? 0);
        $payload['page'] = (int) $filter->page;
        $payload['perpage'] = (int) $filter->perpage;
        $payload['search'] = $canfilter ? (string) $filter->search : '';
        $payload['criteria'] = $canfilter ? search_criteria::encode($options['customsearch'] ?? []) : '';
        $payload['sort'] = $canfilter ? search_criteria::encode($options['customsort'] ?? []) : '';
        $payload['entriescount'] = $entries->get_count();
        $payload['entriesfiltercount'] = $entries->get_count(true);
        $payload['hasmore'] = $filter->perpage > 0
            && ($payload['page'] + 1) * $payload['perpage'] < $payload['entriesfiltercount'];
        $payload['canadd'] = $this->dlx->user_can_add_new_entry() && (
            $view->confirm_view_action('addnewentry', (int) $record->id)
            || $view->confirm_view_action('addnewentries', (int) $record->id)
        );
        $payload['entries'] = $this->build_entries($manager, $view, $entries->entries() ?? []);
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
        if ($default && isset(self::MANAGERS[$default->type])) {
            return $default;
        }
        foreach ($records as $record) {
            if (isset(self::MANAGERS[$record->type])) {
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
                'supported' => isset(self::MANAGERS[$record->type]),
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
     * @param view_manager $manager the manager that loaded the entries
     * @param base $view
     * @param stdClass[] $records
     * @return array
     */
    private function build_entries(view_manager $manager, base $view, array $records): array {
        $usetemplate = $manager->uses_entry_template($view);
        $shown = $this->get_shown_fields($view, $manager->get_content_fields($view));
        $viewid = (int) $view->id();
        $viewallows = [
            'edit' => $view->confirm_view_action('edit', $viewid),
            'delete' => $view->confirm_view_action('delete', $viewid),
        ];
        $entries = [];

        foreach ($records as $record) {
            $manageable = $this->dlx->user_can_manage_entry($record);
            $fields = $this->build_field_values($manager, $shown, $record);
            $record->baseurl = $view->get_navigationurl();
            $html = $usetemplate
                ? html_sanitiser::for_app($view->render_entry_html($record, ['edit' => false, 'manage' => false]))
                : '';

            $entries[] = [
                'id' => (int) $record->id,
                'html' => $html,
                'title' => $fields ? $fields[0]['valuetext'] : '',
                'fields' => $fields,
                'userid' => (int) $record->userid,
                'author' => fullname($record),
                'approved' => !empty($record->approved),
                'status' => (int) ($record->status ?? 0),
                'timecreated' => (int) $record->timecreated,
                'timemodified' => (int) $record->timemodified,
                'canedit' => $manageable && $viewallows['edit'],
                'candelete' => $manageable && $viewallows['delete'],
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
        $fields = array_filter($fields, [view_manager::class, 'is_content_field']);
        $used = array_intersect_key($fields, $view->get__patterns('field') ?? []);

        return $used ?: $fields;
    }

    /**
     * Render each shown field of one entry, leaving out the empty ones.
     *
     * The field's own display path is used, so behaviors (visibility, conditions) and field
     * layouts apply exactly as on the web.
     *
     * @param view_manager $manager
     * @param array $fields
     * @param stdClass $entry
     * @return array
     */
    private function build_field_values(view_manager $manager, array $fields, stdClass $entry): array {
        $values = [];
        foreach ($fields as $field) {
            $html = html_sanitiser::for_app($manager->render_field($field, $entry));
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
}
