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
use datalynxfield_fieldgroup\field as fieldgroup_field;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\view\base;
use stdClass;

/**
 * The browse steps every view type shares: open a view, apply the filter, load one page of entries.
 *
 * A manager per view type builds its own payload on top: the desktop browse services call
 * get_browse_payload(), and the mobile payload builder calls open_view() and load_entries() of
 * the grid and tabular managers. Which entries a request lists, and how the client's filter
 * options may change that, is decided here once for all of them.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class view_manager {
    /** @var string[] Filter options a view that forces its filter does not take from a client. */
    const FORCED_FILTER_OPTIONS = ['filterid', 'search', 'customsearch', 'customsort'];

    /**
     * The view type this manager handles.
     *
     * @return string
     */
    abstract protected function get_view_type(): string;

    /**
     * The view type as it appears in exception messages, e.g. "Grid" or "CSV".
     *
     * @return string
     */
    protected function get_view_label(): string {
        return ucfirst($this->get_view_type());
    }

    /**
     * Open a view the current user can see, with the client's filter options applied.
     *
     * The options come from a web service, so the request's own parameters are never read as
     * filter options. A view that forces its filter ignores the options that would replace or
     * widen it (FORCED_FILTER_OPTIONS); paging and entry selection still apply.
     *
     * @param datalynx|int $datalynx the instance, or its id
     * @param int $viewid
     * @param array $filteroptions options as view\base::set_filter() reads them
     * @return base
     * @throws coding_exception for a view the user cannot see or of another type
     */
    public function open_view(datalynx|int $datalynx, int $viewid, array $filteroptions = []): base {
        $dlx = $datalynx instanceof datalynx ? $datalynx : new datalynx($datalynx);
        $viewrecords = $dlx->get_view_records(true);
        $label = $this->get_view_label();

        if (empty($viewrecords[$viewid])) {
            throw new coding_exception('Invalid view id for ' . strtolower($label) . ' browse payload.');
        }
        $viewrecord = $viewrecords[$viewid];
        if ($viewrecord->type !== $this->get_view_type()) {
            throw new coding_exception("$label browse payload manager only supports $label views.");
        }

        $view = $dlx->get_view($viewrecord->type, $viewrecord, false);
        if ($view->is_forcing_filter()) {
            $filteroptions = array_diff_key($filteroptions, array_flip(self::FORCED_FILTER_OPTIONS));
        }
        if ($filteroptions) {
            $view->set_filter($filteroptions, true);
        }

        return $view;
    }

    /**
     * Load the current page of entries of an opened view, with the content of its fields.
     *
     * @param base $view
     * @return datalynx_entries
     */
    public function load_entries(base $view): datalynx_entries {
        $view->get_filter()->contentfields = $this->get_content_field_ids($this->get_content_fields($view));
        $entries = new datalynx_entries($view->get_dlx(), $view->get_filter());
        $entries->set_content();

        return $entries;
    }

    /**
     * The fields whose content the view shows: all fields of the instance, fieldgroup members
     * removed (the fieldgroup renders them).
     *
     * @param base $view
     * @return array field id => field
     */
    public function get_content_fields(base $view): array {
        return $view->remove_duplicates($view->get_dlx()->get_fields());
    }

    /**
     * The field ids whose content the entries query must load, fieldgroup members included.
     *
     * @param array $fields field id => field
     * @return array
     */
    protected function get_content_field_ids(array $fields): array {
        $ids = [];
        foreach ($fields as $fieldid => $field) {
            $ids[] = is_numeric($fieldid) ? (int) $fieldid : $fieldid;
            if ($field instanceof fieldgroup_field) {
                $ids = array_merge($ids, array_map('intval', $field->fieldids));
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether entries are rendered with the view's entry template rather than field by field.
     *
     * @param base $view
     * @return bool
     */
    public function uses_entry_template(base $view): bool {
        return false;
    }

    /**
     * The payload keys every browse payload starts with.
     *
     * @param base $view
     * @param datalynx_entries $entries
     * @return array
     */
    protected function build_payload(base $view, datalynx_entries $entries): array {
        return [
            'datalynxid' => (int) $view->get_dlx()->id(),
            'viewid' => (int) $view->id(),
            'viewname' => format_string($view->name()),
            'viewtype' => $view->type(),
            'entriescount' => (int) $entries->get_count(),
            'entriesfiltercount' => (int) $entries->get_count(true),
            'hasentries' => false,
            'groups' => [],
            'emptycontent' => get_string('noentries', 'datalynx'),
        ];
    }

    /**
     * Whether the current user may manage any of the given entries.
     *
     * @param base $view
     * @param stdClass[] $entryrecords
     * @return bool
     */
    protected function has_manageable_entries(base $view, array $entryrecords): bool {
        foreach ($entryrecords as $entry) {
            if ($view->get_dlx()->user_can_manage_entry($entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a field stores content of its own, rather than being an internal field.
     *
     * @param object $field
     * @return bool
     */
    public static function is_content_field(object $field): bool {
        return is_numeric($field->field->id) && (int) $field->field->id > 0;
    }

    /**
     * Render one tag of a field for an entry, in display mode.
     *
     * Behaviors and field layouts apply exactly as in a view's own rendering.
     *
     * @param object $field
     * @param stdClass $entry
     * @param string|null $tag the tag to render, the field's plain [[name]] tag if null
     * @param array $options definition options; display mode without entry actions by default
     * @return string HTML, empty if the field renders nothing
     */
    public function render_field(object $field, stdClass $entry, ?string $tag = null, array $options = []): string {
        $tag ??= base::FIELD_TAG_OPEN . $field->field->name . base::FIELD_TAG_CLOSE;
        $options += ['edit' => false, 'manage' => false];

        return self::definition_html($field->get_definitions([$tag], $entry, $options), $tag);
    }

    /**
     * The rendered HTML of one tag in a field definition map.
     *
     * @param array $definitions as field get_definitions() returns them
     * @param string $tag
     * @return string
     */
    protected static function definition_html(array $definitions, string $tag): string {
        // Not empty(): a field whose value is "0" renders "0".
        $html = $definitions[$tag][1] ?? '';

        return is_string($html) ? $html : '';
    }

    /**
     * The rendered HTML of one view pattern.
     *
     * @param base $view
     * @param string $tag
     * @param stdClass|null $entry
     * @param array $options
     * @return string
     */
    protected static function pattern_html(base $view, string $tag, ?stdClass $entry = null, array $options = []): string {
        $replacements = $view->patternclass()->get_replacements([$tag], $entry, $options);
        $html = $replacements[$tag] ?? '';

        return is_string($html) ? $html : '';
    }
}
