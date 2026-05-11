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

/**
 *
 * @package datalynxfield_approve
 * @subpackage _approve
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_approve;

use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;

// phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
/**
 * Renderer for the internal approval field patterns.
 *
 * @package mod_datalynx
 * @subpackage _approve
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends datalynxfield_renderer {
    /**
     * Returns tag replacement pairs for the approve field patterns.
     *
     * @param ?array  $tags    The list of tag patterns to replace.
     * @param ?object $entry   The current entry object.
     * @param ?array  $options Rendering options.
     * @return array
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        $dlx = $this->field->dlx();

        $canapprove = has_capability('mod/datalynx:approve', $dlx->context);
        $edit = !empty($options['edit']) ? $options['edit'] && $canapprove : false;
        $replacements = [];
        // Just one tag, empty until we check df settings.
        $replacements['##approve##'] = '';

        if ($dlx->data->approval) {
            if (!$entry || $edit) {
                $replacements['##approve##'] = ['', [[$this, 'display_edit'], [$entry]]];

                // Existing entry to browse.
            } else {
                // Ensure the link is rendered for the common [[approve]] tag.
                $replacements['##approve##'] = ['html', $this->display_browse($entry, $options)];
                $replacements['##approve##@'] = ['html', $this->display_browse($entry, $options)];
            }
        }

        return $replacements;
    }

    /**
     * Renders the search mode form element for this field.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param int    $i     The search field index.
     * @param string $value The current search value.
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $field = $this->field;
        $fieldid = $field->id();

        $options = [0 => ucfirst(get_string('approvednot', 'datalynx')),
                1 => ucfirst(get_string('approved', 'datalynx'))];
        $select = &$mform->createElement('select', "f_{$i}_$fieldid", null, $options);
        $select->setSelected($value);
        // Disable the 'not' and 'operator' fields.
        $mform->disabledIf("searchnot$i", "f_{$i}_$fieldid", 'neq', 2);
        $mform->disabledIf("searchoperator$i", "f_{$i}_$fieldid", 'neq', 2);

        return [[$select], null];
    }

    /**
     * Renders the edit mode form element for the approve field.
     *
     * @param MoodleQuickForm $mform   The form object.
     * @param object          $entry   The current entry.
     * @param ?array      $options Rendering options.
     * @return void
     */
    public function display_edit(&$mform, $entry, ?array $options = null) {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        if ($entryid > 0) {
            $checked = $entry->approved;
        } else {
            $checked = 0;
        }

        $fieldname = "field_{$fieldid}_{$entryid}";
        $mform->addElement('advcheckbox', $fieldname, null, null, null, [0, 1]);
        $mform->setDefault($fieldname, $checked);
    }

    /**
     * Renders the browse (read) mode HTML for the approve toggle.
     *
     * @param object     $entry  The current entry.
     * @param ?array $params Optional display parameters.
     * @return string
     */
    protected function display_browse($entry, $params = null) {
        global $PAGE;

        $field = $this->field;
        $isapproved = !empty($entry->approved);
        $statelabel = $isapproved
            ? get_string('approvalstateapproved', 'mod_datalynx')
            : get_string('approvalstatenotapproved', 'mod_datalynx');
        $togglecontent = html_writer::span('', 'datalynxfield_approve-switch', ['aria-hidden' => 'true']);
        $togglecontent .= html_writer::span($statelabel, 'datalynxfield_approve-label');
        $baseattributes = [
            'class' => 'datalynxfield_approve',
            'data-approved' => $isapproved ? 1 : 0,
            'data-approved-label' => get_string('approvalstateapproved', 'mod_datalynx'),
            'data-not-approved-label' => get_string('approvalstatenotapproved', 'mod_datalynx'),
            'aria-label' => $statelabel,
        ];

        if (has_capability('mod/datalynx:approve', $field->dlx()->context)) {
            $PAGE->requires->js_call_amd('mod_datalynx/approve', 'init');

            $currentview = $this->field->dlx()->get_current_view();
            $currentviewid = !empty($params['viewid']) ? (int) $params['viewid'] : ($currentview ? $currentview->id() : 0);

            return html_writer::tag('button', $togglecontent, $baseattributes + [
                'type' => 'button',
                'role' => 'switch',
                'aria-checked' => $isapproved ? 'true' : 'false',
                'title' => $statelabel,
                'data-entryid' => $entry->id,
                'data-d' => $field->dlx()->data->id,
                'data-view' => $currentviewid,
                'data-sesskey' => sesskey(),
            ]);
        } else {
            return html_writer::tag(
                'span',
                $togglecontent,
                $baseattributes + ['class' => 'datalynxfield_approve datalynxfield_approve-readonly', 'role' => 'status']
            );
        }
    }

    /**
     * Render one approval toggle for AJAX refreshes.
     *
     * @param object $entry The current entry.
     * @param array $params Optional display parameters.
     * @return string
     */
    public function render_toggle($entry, array $params = []): string {
        return $this->display_browse($entry, $params);
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $cat = get_string('actions', 'datalynx');

        $patterns = [];
        $patterns["##approve##"] = [true, $cat];

        return $patterns;
    }
}
// phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal
