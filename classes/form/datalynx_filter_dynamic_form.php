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

namespace mod_datalynx\form;

use context;
use context_module;
use core_form\dynamic_form;
use mod_datalynx\datalynx;

/**
 * AJAX (modal) version of the View Filter editor.
 *
 * Renders the same controls and uses the same element names as the legacy {@see datalynx_filter_form},
 * so the serialized customsort/customsearch it writes is byte-identical and existing filters keep
 * working. Selecting a search field reloads only that row's operator/value widgets over AJAX (the
 * no-submit "addsearchsettings" button) instead of reloading the whole page.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datalynx_filter_dynamic_form extends dynamic_form {
    use filter_form_elements;

    /** @var datalynx The datalynx instance, reconstructed from the AJAX payload. */
    protected $dlx = null;

    /** @var \stdClass|null The filter being edited, loaded in set_data_for_dynamic_submission(). */
    private $storedfilter = null;

    /**
     * Lazily build the datalynx instance from the AJAX form data (d = dataid, cmid = course module id).
     *
     * @return datalynx
     */
    protected function get_dlx(): datalynx {
        if ($this->dlx === null) {
            $d = (int) ($this->optional_param('d', 0, PARAM_INT));
            $cmid = (int) ($this->optional_param('cmid', 0, PARAM_INT));
            $this->dlx = new datalynx($d, $cmid);
        }
        return $this->dlx;
    }

    /**
     * The form operates in the datalynx module context.
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        return context_module::instance($this->get_dlx()->cm->id);
    }

    /**
     * Only users who can manage templates may edit filters.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('mod/datalynx:managetemplates', $this->get_context_for_dynamic_submission());
    }

    /**
     * Define the static part of the form. The dynamic sort/search rows are built in
     * {@see self::definition_after_data()} because they depend on the chosen fields.
     */
    public function definition() {
        $mform = $this->_form;
        $dlx = $this->get_dlx();

        // The modal manages its own lifecycle and is saved over AJAX, so the page-level "unsaved
        // changes" guard is not wanted here.
        $mform->disable_form_change_checker();

        $mform->addElement('hidden', 'd', $dlx->id());
        $mform->setType('d', PARAM_INT);
        $mform->addElement('hidden', 'cmid', $dlx->cm->id);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'fid', 0);
        $mform->setType('fid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'description', get_string('description'), ['size' => '64']);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('select', 'visible', get_string('visible'), [0 => 'hidden', 1 => 'visible']);
        $mform->setDefault('visible', 1);

        $perpageoptions = [0 => get_string('choose'), 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
                7 => 7, 8 => 8, 9 => 9, 10 => 10, 15 => 15, 20 => 20, 30 => 30, 40 => 40, 50 => 50,
                100 => 100, 200 => 200, 300 => 300, 400 => 400, 500 => 500, 1000 => 1000];
        $mform->addElement('select', 'perpage', get_string('viewperpage', 'datalynx'), $perpageoptions);

        $selectionoptions = [0 => get_string('filterbypage', 'datalynx'), 1 => get_string('random', 'datalynx')];
        $mform->addElement('select', 'selection', get_string('filterselection', 'datalynx'), $selectionoptions);
        $mform->disabledIf('selection', 'perpage', 'eq', '0');

        $groupbyfieldoptions = [0 => get_string('choose')];
        foreach ($dlx->get_fields() as $field) {
            if ($field->supports_group_by()) {
                $groupbyfieldoptions[$field->id()] = $field->name();
            }
        }
        $mform->addElement('select', 'groupby', get_string('filtergroupby', 'datalynx'), $groupbyfieldoptions);

        $mform->addElement('text', 'search', get_string('search'));
        $mform->setType('search', PARAM_TEXT);
    }

    /**
     * Build the dynamic sort and search rows.
     *
     * The rows are derived from the *submitted* values when the form was submitted (so a freshly
     * selected field gains its operator/value widgets on the AJAX reload), otherwise from the filter
     * that was loaded for editing. The trait builders produce exactly the same element names the
     * legacy form does, so {@see datalynx_filter_manager::get_search_options_from_form()} parses them
     * unchanged.
     */
    public function definition_after_data() {
        parent::definition_after_data();
        $mform = $this->_form;
        $dlx = $this->get_dlx();
        $fields = $dlx->get_fields();
        $fieldoptions = [0 => get_string('choose')] + $dlx->get_fields(['entry'], true);

        if ($mform->isSubmitted()) {
            $fm = $dlx->get_filter_manager();
            $formdata = (object) ($this->_ajaxformdata ?? []);
            $customsort = $fm->get_sort_options_from_form($formdata);
            $customsearch = $fm->get_search_options_from_form($formdata, false);
        } else {
            $customsort = $this->storedfilter->customsort ?? '';
            $customsearch = $this->storedfilter->customsearch ?? '';
        }

        $mform->addElement('header', 'customsorthdr', get_string('filtercustomsort', 'datalynx'));
        $mform->setExpanded('customsorthdr');
        $this->custom_sort_definition($customsort, $fields, $fieldoptions, true);

        $mform->addElement('header', 'customsearchhdr', get_string('filtercustomsearch', 'datalynx'));
        $mform->setExpanded('customsearchhdr');
        $this->custom_search_definition($customsearch, $fields, $fieldoptions, true);
    }

    /**
     * Seed the form from the filter being edited (or a blank filter for a new one).
     */
    public function set_data_for_dynamic_submission(): void {
        $dlx = $this->get_dlx();
        $fm = $dlx->get_filter_manager();
        $fid = (int) $this->optional_param('fid', 0, PARAM_INT);
        $filter = $fm->get_filter_from_id($fid ?: $fm::BLANK_FILTER);
        $this->storedfilter = $filter;

        $this->set_data((object) [
            'd' => $dlx->id(),
            'cmid' => $dlx->cm->id,
            'fid' => !empty($filter->id) ? $filter->id : 0,
            'name' => $filter->name ?? '',
            'description' => $filter->description ?? '',
            'visible' => $filter->visible ?? 1,
            'perpage' => $filter->perpage ?? 0,
            'selection' => $filter->selection ?? 0,
            'groupby' => $filter->groupby ?? 0,
            'search' => $filter->search ?? '',
        ]);
    }

    /**
     * Persist the filter, reusing the same serialization and save path as the legacy form.
     *
     * @return array{filterid: int, name: string}
     */
    public function process_dynamic_submission(): array {
        $dlx = $this->get_dlx();
        $fm = $dlx->get_filter_manager();
        $fid = (int) $this->optional_param('fid', 0, PARAM_INT);
        $filter = $fm->get_filter_from_id($fid ?: $fm::BLANK_FILTER);

        $formdata = $this->get_data();
        $filter = $fm->get_filter_from_form($filter, $formdata, true);
        $filter = $fm->save_filter($filter);

        return ['filterid' => (int) $filter->id, 'name' => $filter->name];
    }

    /**
     * Validate the filter name (required and unique within the datalynx instance).
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $dlx = $this->get_dlx();
        $fid = (int) ($data['fid'] ?? 0);
        if (empty($data['name']) || $dlx->name_exists('filters', $data['name'], $fid)) {
            $errors['name'] = get_string('invalidname', 'datalynx', get_string('filter', 'datalynx'));
        }
        return $errors;
    }

    /**
     * URL of the page hosting the form (the filter management page).
     *
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/mod/datalynx/filter/index.php', ['d' => $this->get_dlx()->id()]);
    }
}
