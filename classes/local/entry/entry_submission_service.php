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

namespace mod_datalynx\local\entry;

use datalynxfield_fieldgroup\field as fieldgroup_field;
use mod_datalynx\local\view\base;
use moodle_exception;
use stdClass;

/**
 * Validates and saves entries submitted through a view, whoever submits them.
 *
 * The entry form of a view and the web services hand their submission to this class, so the same
 * rules decide what may be saved: which fields the view offers for editing, what the behaviors of
 * their tags allow the current user (visibility, editability, required, availability conditions,
 * the final-submission and single-choice locks, all resolved by
 * {@see \mod_datalynx\local\field\datalynxfield_renderer::resolve_render_options()}), and what each
 * field's validation accepts.
 *
 * A submission is keyed the way the entry form names its elements: field_{fieldid}_{entryid},
 * optionally followed by a content name (field_{fieldid}_{entryid}_url) or, for a subfield of a
 * fieldgroup, by the line (field_{fieldid}_{entryid}_fieldgroup_{fieldgroupid}_{line}).
 * Entry ids are those of existing entries, or negative for new ones (-1, -2, ...).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_submission_service {
    /** @var string Element names of field values: fieldid, entryid, and what follows. */
    const ELEMENT_REGEX = '/^field_([^_]+)_(-?\d+)(?:_(.*))?$/';

    /** @var string The part of an element name that places a subfield value in a fieldgroup line. */
    const LINE_REGEX = '/^fieldgroup_(\d+)_(\d+)(?:_|$)/';

    /** @var base The view the entries are submitted through. */
    private base $view;

    /** @var array Field states by entry id, see get_field_states(). */
    private array $states = [];

    /**
     * Constructor.
     *
     * @param base $view the view the entries are submitted through
     */
    public function __construct(base $view) {
        $this->view = $view;
    }

    /**
     * The entry ids of a submission.
     *
     * New entries are submitted as their count, negative: -3 stands for the new entries -1, -2, -3.
     *
     * @param array $entryids as submitted
     * @return int[]
     */
    public static function expand_entry_ids(array $entryids): array {
        $entryids = array_values(array_map('intval', $entryids));
        if (count($entryids) === 1 && $entryids[0] < 0) {
            return range(-1, $entryids[0]);
        }

        return $entryids;
    }

    /**
     * The entry a submission is for, as the edit form renders it.
     *
     * @param int $entryid an existing entry, or negative for a new one
     * @return stdClass
     */
    private function get_entry(int $entryid): stdClass {
        global $DB;

        if ($entryid > 0) {
            $entry = $DB->get_record('datalynx_entries', ['id' => $entryid, 'dataid' => $this->view->get_dlx()->id()]);
            if ($entry) {
                return $entry;
            }
        }

        // A new entry, as the views' new_entry_definition() builds it.
        return (object) ['id' => $entryid];
    }

    /**
     * What the view's entry template lets the current user do with each of its fields in an entry.
     *
     * @param int $entryid
     * @return array fieldid => ['field' => field, 'tags' => editable tags, 'options' => render
     *               options of the first editable tag, 'visible' => bool, 'editable' => bool,
     *               'required' => bool, 'subfields' => for a fieldgroup:
     *               subfieldid => ['field', 'options', 'tag', 'editable', 'required']]
     */
    public function get_field_states(int $entryid): array {
        if (isset($this->states[$entryid])) {
            return $this->states[$entryid];
        }

        $entry = $this->get_entry($entryid);
        $fields = $this->view->get_dlx()->get_fields();
        $states = [];
        foreach ($this->view->get_entry_form_patterns() as $fieldid => $tags) {
            if (!isset($fields[$fieldid])) {
                continue;
            }
            $field = $fields[$fieldid];
            $state = ['field' => $field, 'tags' => [], 'options' => [], 'visible' => false, 'editable' => false,
                'required' => false];
            foreach ((array) $tags as $tag) {
                [$options] = $field->renderer()->resolve_render_options($tag, $entry, ['edit' => true, 'manage' => true]);
                // Internal fields (status, approval, author, group) have no behaviors; a tag of
                // theirs is an input only where its renderer says so, e.g. ##author:edit## but not
                // ##author:name##, and only for users who may change the value.
                if ($field->is_internal()) {
                    $options['editable'] = $field->renderer()->accepts_input($tag, $entry);
                }
                $state['visible'] = $state['visible'] || $options['visible'];
                if ($options['visible'] && $options['editable']) {
                    // The input contract follows the tag the field is edited under.
                    $state['options'] = $state['editable'] ? $state['options'] : $options;
                    $state['editable'] = true;
                    $state['required'] = $state['required'] || $options['required'];
                    $state['tags'][] = $tag;
                }
            }
            if ($field instanceof fieldgroup_field) {
                $state['subfields'] = $this->get_subfield_states($field, $entry);
            }
            $states[$fieldid] = $state;
        }

        return $this->states[$entryid] = $states;
    }

    /**
     * What the current user may do with each subfield of a fieldgroup in an entry.
     *
     * @param fieldgroup_field $fieldgroup
     * @param stdClass $entry
     * @return array subfieldid => ['field', 'options', 'tag', 'editable', 'required']
     */
    private function get_subfield_states(fieldgroup_field $fieldgroup, stdClass $entry): array {
        $renderer = $fieldgroup->renderer();
        $states = [];
        foreach ($renderer->get_subfields() as $key => $subfield) {
            [$options] = $renderer->resolve_subfield_options($key, $entry, ['edit' => true, 'manage' => true]);
            $modifiers = explode('|', explode(':', $key, 2)[1] ?? '', 3);
            $states[(int) $subfield->id()] = [
                'field' => $subfield,
                'options' => $options,
                // The tag the subfield's own validation reads its behavior from.
                'tag' => '[[' . $subfield->name() . '|' . ($modifiers[1] ?? '') . '|' . ($modifiers[2] ?? '') . ']]',
                'editable' => !empty($options['visible']) && !empty($options['editable']),
                'required' => !empty($options['required']),
            ];
        }

        return $states;
    }

    /**
     * Keep only the values of fields the view lets the current user edit in the submitted entries,
     * cleaned by each field's input contract.
     *
     * Anything else that looks like a field value is dropped: a field the view does not show, a
     * field its behavior does not let this user edit, a subfield of a fieldgroup that is not
     * editable, an entry that is not part of the submission, an input the field's contract does
     * not name or a value without the shape it declares
     * ({@see \mod_datalynx\local\field\datalynxfield_base::get_input_contract()}). Other keys
     * are kept.
     *
     * @param int[] $entryids
     * @param stdClass $data
     * @return stdClass
     */
    public function filter_data(array $entryids, stdClass $data): stdClass {
        $entryids = self::expand_entry_ids($entryids);
        $filtered = new stdClass();
        foreach ((array) $data as $name => $value) {
            $name = (string) $name;
            if (strpos($name, 'field_') !== 0) {
                $filtered->$name = $value;
                continue;
            }
            $input = $this->get_editable_input($name, $entryids);
            if ($input === null) {
                continue;
            }
            [$field, $contentname, $options] = $input;
            $clean = $field->clean_submitted_value($contentname, $value, $options);
            if ($clean !== null) {
                $filtered->$name = $clean;
            }
        }

        return $filtered;
    }

    /**
     * The field a submitted element is an input of, if the current user may edit it.
     *
     * @param string $name element name
     * @param int[] $entryids the entries of the submission
     * @return array|null [$field, $contentname, $options]: the field, the content name that
     *                    follows its element name ('' for none) and the render options it is
     *                    edited under; null if the element is not an editable input
     */
    private function get_editable_input(string $name, array $entryids): ?array {
        if (!preg_match(self::ELEMENT_REGEX, $name, $matches)) {
            return null;
        }
        [, $fieldid, $entryid] = $matches;
        $rest = $matches[3] ?? '';
        if (!in_array((int) $entryid, array_map('intval', $entryids), true)) {
            return null;
        }

        $states = $this->get_field_states((int) $entryid);
        if (preg_match(self::LINE_REGEX, $rest, $line)) {
            $group = $states[$line[1]] ?? null;
            $substate = $group['subfields'][(int) $fieldid] ?? null;
            if (!$group || !$group['editable'] || !$substate || !$substate['editable']) {
                return null;
            }
            return [$substate['field'], ltrim(substr($rest, strlen($line[0])), '_'), $substate['options']];
        }

        $state = $states[$fieldid] ?? null;
        if (!$state || !$state['editable']) {
            return null;
        }

        return [$state['field'], $rest, $state['options']];
    }

    /**
     * Validate a submission: every editable field of every submitted entry, as the view shows it.
     *
     * Whether a field is required is decided here, from the behaviors of the tags it is editable
     * under (availability conditions included); the field only says when its submitted value is
     * empty. A required field that is left out of the submission is empty, so leaving it out of a
     * request is no way around it. Each field's own validation then checks the rest: formats,
     * uniqueness, limits.
     *
     * @param int[] $entryids
     * @param stdClass|array $data
     * @return array element name => error message
     */
    public function validate(array $entryids, stdClass|array $data): array {
        $entryids = self::expand_entry_ids($entryids);
        $data = (object) $data;
        $errors = [];
        foreach ($entryids as $entryid) {
            $entryid = (int) $entryid;
            foreach ($this->get_field_states($entryid) as $fieldid => $state) {
                if (!$state['editable']) {
                    continue;
                }
                $field = $state['field'];
                if ($field instanceof fieldgroup_field) {
                    $errors = array_merge($errors, $this->validate_fieldgroup($field, $state, $entryid, $data));
                    continue;
                }
                $errors = array_merge($errors, $field->renderer()->validate($entryid, $state['tags'], $data));
                if ($state['required'] && !$field->is_internal()) {
                    $errors = array_merge($errors, self::required_error($field, "field_{$fieldid}_{$entryid}", $data, $errors));
                }
            }
        }

        return $errors;
    }

    /**
     * Validate the lines of a fieldgroup.
     *
     * The lines checked are the fieldgroup's required lines and every line that holds a value. In a
     * required line every editable subfield needs a value; in the others a subfield is required if
     * its behavior says so. Each subfield's own validation runs on each of these lines.
     *
     * @param fieldgroup_field $fieldgroup
     * @param array $state the fieldgroup's state, see get_field_states()
     * @param int $entryid
     * @param stdClass $data
     * @return array element name => error message
     */
    private function validate_fieldgroup(fieldgroup_field $fieldgroup, array $state, int $entryid, stdClass $data): array {
        $groupid = (int) $fieldgroup->id();
        $requiredlines = (int) ($fieldgroup->field->param4 ?? 0);
        $maxlines = max(1, (int) ($fieldgroup->field->param2 ?? 3));

        // The required lines, and every other line that holds a value. The form posts all lines,
        // the hidden empty ones too, and saving drops the lines in which no subfield has a value
        // by the same test (the fieldgroup's line_has_value()).
        $lines = $requiredlines ? range(0, min($requiredlines, $maxlines) - 1) : [];
        foreach (array_keys((array) $data) as $name) {
            if (
                preg_match(self::ELEMENT_REGEX, (string) $name, $matches)
                && (int) $matches[2] === $entryid
                && preg_match(self::LINE_REGEX, $matches[3] ?? '', $line)
                && (int) $line[1] === $groupid
                && (int) $line[2] < $maxlines
            ) {
                $lines[(int) $line[2]] = (int) $line[2];
            }
        }
        $lines = array_filter(
            $lines,
            fn(int $line): bool => $line < $requiredlines || $fieldgroup->line_has_value($entryid, $line, $data)
        );

        $errors = [];
        foreach (array_unique($lines) as $line) {
            $lineentryid = "{$entryid}_fieldgroup_{$groupid}_{$line}";
            foreach ($state['subfields'] as $subfieldid => $substate) {
                if (!$substate['editable']) {
                    continue;
                }
                $errors = array_merge(
                    $errors,
                    $substate['field']->renderer()->validate($lineentryid, [$substate['tag']], $data)
                );
                if ($line < $requiredlines || $substate['required']) {
                    $name = "field_{$subfieldid}_{$lineentryid}";
                    $errors = array_merge($errors, self::required_error($substate['field'], $name, $data, $errors));
                }
            }
        }

        return $errors;
    }

    /**
     * Save a submission through the view: check the view allows it, keep only editable fields,
     * validate, and store the entries.
     *
     * @param int[] $entryids existing entry ids, or one negative number: the count of new entries
     * @param stdClass $data the submission
     * @param bool $validated true when the caller ran validate() on the same data already (the
     *                        entry form does, as part of its own validation)
     * @return array ['entryids' => saved entry ids, 'errors' => element name => message,
     *               'message' => the notification of the entries manager]
     * @throws moodle_exception when the view does not allow adding or editing these entries
     */
    public function save(array $entryids, stdClass $data, bool $validated = false): array {
        $entryids = array_values(array_map('intval', $entryids));
        $isnew = count($entryids) === 1 && $entryids[0] < 0;
        $viewid = (int) $this->view->id();
        $allowed = $isnew
            ? $this->view->confirm_view_action('addnewentry', $viewid) || $this->view->confirm_view_action('addnewentries', $viewid)
            : $this->view->confirm_view_action('edit', $viewid);
        if (!$allowed) {
            throw new moodle_exception($isnew ? 'noaccess' : 'vieweditnotallowed', 'datalynx');
        }

        $data = $this->filter_data($entryids, $data);
        if (!$validated && ($errors = $this->validate($entryids, $data))) {
            return ['entryids' => [], 'errors' => $errors, 'message' => ''];
        }

        // Which fields stay editable after final submission is the view's to say, not the client's.
        $data->editablefinalfieldids = $this->view->get_editable_after_final_fieldids();

        $entries = $this->view->get_entries_manager();
        $update = $isnew ? (string) $entryids[0] : implode(',', $entryids);
        $processed = $entries->process_entries('update', $update, $data, true);
        // With no entry the user may change, the ids come back as ''.
        $savedids = is_array($processed[1] ?? null) ? array_map('intval', $processed[1]) : [];

        // What fields keep outside the entry's contents, e.g. a profile field of the author.
        if ($savedids) {
            foreach (self::expand_entry_ids($entryids) as $entryid) {
                foreach ($this->get_field_states($entryid) as $state) {
                    if ($state['editable']) {
                        $state['field']->after_entry_saved($entryid, $data);
                    }
                }
            }
        }

        return [
            'entryids' => $savedids,
            'errors' => [],
            'message' => (string) ($processed[0] ?? ''),
        ];
    }

    /**
     * The error of a required field the submission leaves empty.
     *
     * The field says when its value is empty and which element shows the error. A field that has an
     * error already keeps that one.
     *
     * @param object $field the field
     * @param string $name the element name of its value
     * @param stdClass $data the submission
     * @param array $errors the errors found so far
     * @return array element name => message, empty if the field has a value
     */
    private static function required_error(object $field, string $name, stdClass $data, array $errors): array {
        if (!$field->is_submitted_value_empty($name, $data)) {
            return [];
        }
        $renderer = $field->renderer();
        $element = $renderer->get_error_element($name);
        if (isset($errors[$element]) || isset($errors[$name])) {
            return [];
        }

        return [$element => $renderer->get_required_message()];
    }
}
