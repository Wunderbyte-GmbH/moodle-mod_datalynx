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

namespace datalynxview_email;

use html_writer;
use mod_datalynx\local\view\base;
use stdClass;

/**
 * Internal email template view.
 *
 * @package    datalynxview_email
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class view extends base {
    /** @var string View type identifier. */
    protected string $type = 'email';

    /** @var array Editors used by the email template view. */
    protected array $editors = ['param2'];

    /** @var array Editors used in form processing. */
    protected array $vieweditors = ['param2'];

    /**
     * Constructor.
     *
     * @param int|\mod_datalynx\datalynx $df
     * @param int|\stdClass $view
     * @param bool $filteroptions
     */
    public function __construct($df = 0, $view = 0, $filteroptions = true) {
        parent::__construct($df, $view, $filteroptions);

        if (empty($this->view->id)) {
            $this->view->visible = 1;
            $this->view->filter = 0;
            $this->view->param5 = 0;
            $this->view->param10 = 0;
        }
    }

    /**
     * Email views are internal-only templates and should not appear in browse UX.
     *
     * @return bool
     */
    public function is_internal_view(): bool {
        return true;
    }

    /**
     * Generate the default email template.
     */
    public function generate_default_view() {
        $fields = $this->dl->get_fields();
        if (!$fields) {
            return;
        }

        $fields = parent::remove_duplicates($fields);
        $parts = [];
        foreach ($fields as $field) {
            if (!is_numeric($field->field->id) || $field->field->id <= 0) {
                continue;
            }

            $tag = $field->type == 'userinfo' ? "##author:{$field->name()}##" : "[[{$field->name()}]]";
            $parts[] = html_writer::tag('p', $tag);
        }

        $body = implode("\n", $parts);
        if ($body === '') {
            $body = html_writer::tag('p', '##entryid##');
        }

        $this->view->esection = '##entries##';
        $this->view->eparam2 = html_writer::tag('div', $body, ['class' => 'datalynx-email-entry']);
    }

    /**
     * Flatten rendered entries for the email body.
     *
     * @param array $entriesset
     * @param string $name
     * @return array
     */
    protected function apply_entry_group_layout($entriesset, $name = '') {
        $elements = [];
        foreach ($entriesset as $entrydefinitions) {
            $elements = array_merge($elements, $entrydefinitions);
        }
        return $elements;
    }

    /**
     * Build the definition for a new entry.
     *
     * @param int $entryid
     * @return array
     */
    protected function new_entry_definition($entryid = -1) {
        $elements = [];

        $fields = $this->dl->get_fields();
        $tags = [];
        $patterndefinitions = [];
        $entry = new stdClass();
        foreach ($this->tags['field'] as $fieldid => $patterns) {
            if (!isset($fields[$fieldid])) {
                continue;
            }

            $field = $fields[$fieldid];
            $entry->id = $entryid;
            $options = ['edit' => true, 'manage' => true];
            if ($fielddefinitions = $field->get_definitions($patterns, $entry, $options)) {
                $patterndefinitions = array_merge($patterndefinitions, $fielddefinitions);
            }
            $tags = array_merge($tags, $patterns);
        }

        $parts = $this->split_template_by_tags($tags, $this->view->eparam2);
        foreach ($parts as $part) {
            if (in_array($part, $tags)) {
                if ($def = $patterndefinitions[$part]) {
                    $elements[] = $def;
                }
            } else {
                $elements[] = ['html', $part];
            }
        }

        return $elements;
    }

    /**
     * Render the email template body for one entry.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string
     */
    public function render_email_entry(stdClass $entry, array $options = []): string {
        $originaltemplate = $this->view->eparam2;
        $this->view->eparam2 = file_rewrite_pluginfile_urls(
            $this->view->eparam2,
            'pluginfile.php',
            $this->dl->context->id,
            'mod_datalynx',
            'viewparam2',
            $this->id()
        );

        try {
            $html = $this->render_entry_html($entry, array_merge([
                'edit' => false,
                'manage' => false,
            ], $options));
        } finally {
            $this->view->eparam2 = $originaltemplate;
        }

        return $this->process_calculations($html);
    }

    /**
     * Load one entry through the current view filter.
     *
     * @param int $entryid
     * @return ?stdClass
     */
    public function get_filtered_entry(int $entryid): ?stdClass {
        $this->set_filter(['eids' => $entryid], true);
        $this->set_content(['filter' => $this->get_filter()]);
        $entries = $this->entries ? $this->entries->entries() : [];

        return $entries[$entryid] ?? null;
    }
}
