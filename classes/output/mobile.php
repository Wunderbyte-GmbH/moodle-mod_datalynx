<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx\output;

use context_module;
use mod_datalynx\datalynx;
use mod_datalynx\local\date_input;
use mod_datalynx\external\submit_entry;
use mod_datalynx\local\entry\entry_edit_form;
use mod_datalynx\local\mobile\customfilter_form;
use mod_datalynx\local\mobile\entries_payload_builder;
use stdClass;

/**
 * Content for the Moodle App, served through tool_mobile_get_content.
 *
 * Each method is kept thin: normalise the arguments, check access, let a builder in
 * \mod_datalynx\local\mobile assemble the data, render the template.
 *
 * The templates switch their Mustache delimiters to <% %> so that {{ }} stays Angular's. Data
 * the app shows travels in otherdata and is bound with property bindings, never written into the
 * template source: entry content is user content, and anything in the template source is
 * compiled by Angular.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /** @var int Entries the app loads first, and how many more each scroll to the end adds. */
    const PERPAGE = 20;

    /**
     * Browse the entries of the activity.
     *
     * The app opens this with cmid and courseid. View switching, searching and growing the list
     * call it again with the rest of the state, which is otherwise kept in otherdata.state.
     *
     * @param array $args arguments of tool_mobile_get_content, all strings
     * @return array templates, javascript and otherdata
     */
    public static function mobile_course_view(array $args): array {
        global $OUTPUT;

        $rawargs = $args;
        $args = self::args($args, [
            'cmid' => PARAM_INT,
            'courseid' => PARAM_INT,
            'viewid' => PARAM_INT,
            'filterid' => PARAM_INT,
            'perpage' => PARAM_INT,
            'search' => PARAM_TEXT,
            'criteria' => PARAM_RAW,
            'sort' => PARAM_RAW,
            'eids' => PARAM_SEQUENCE,
            'customfilterid' => PARAM_INT,
            'customvalues' => PARAM_RAW,
        ]);
        [$cm, $context] = self::context($args->cmid);

        $dlx = new datalynx($cm->instance);
        $payload = (new entries_payload_builder($dlx))->build([
            'viewid' => $args->viewid,
            'filterid' => $args->filterid,
            'page' => 0,
            'perpage' => $args->perpage ?: self::PERPAGE,
            'search' => $args->search,
            'criteria' => $args->criteria,
            'sort' => $args->sort,
            'eids' => $args->eids,
            'customfilterid' => $args->customfilterid,
            // The form's inputs when the filter was just applied, else those the page carries.
            'customfilterinputs' => customfilter_form::inputs($rawargs, $args->customvalues),
        ]);

        // The list grows by asking for a larger first page rather than appending the next one,
        // which a template could only do with custom JavaScript. Growth stops at the most one
        // request may return; beyond that the page offers the web view instead.
        $nextperpage = min($payload['perpage'] + self::PERPAGE, entries_payload_builder::MAX_PERPAGE);
        $otherdata = $payload + [
            'courseid' => (int) $cm->course,
            'intro' => format_module_intro('datalynx', $dlx->data, $cm->id, false),
            'hasview' => $payload['viewid'] > 0,
            'cangrow' => $payload['hasmore'] && $nextperpage > $payload['perpage'],
            'nextperpage' => $nextperpage,
            // Carried on by every later request of the page, so the custom filter stays applied.
            'customvalues' => $payload['customfilterid'] ? json_encode((object) $payload['customfilterinputs']) : '',
        ];

        $html = $OUTPUT->render_from_template('mod_datalynx/mobile/view_page', [
            'cmid' => (int) $cm->id,
            'courseid' => (int) $cm->course,
        ]);

        return [
            'templates' => [
                ['id' => 'main', 'html' => $html],
            ],
            'javascript' => '',
            'otherdata' => self::otherdata($otherdata),
        ];
    }

    /**
     * Add or edit an entry (mod_datalynx#273).
     *
     * The form is the one get_entry_edit_form serves (local\entry\entry_edit_form); saving goes
     * through submit_entry, the same path as every other client. The app sends the form's inputs
     * (ef_{fieldid}__{input}) with save=1, and status=1 (draft) or 2 (final submission) where the
     * form has a status. On errors the page comes back with the values entered and the errors at
     * their fields.
     *
     * @param array $args arguments of tool_mobile_get_content, all strings
     * @return array templates, javascript and otherdata
     */
    public static function mobile_edit_entry(array $args): array {
        global $OUTPUT;

        $rawargs = $args;
        $args = self::args($args, [
            'cmid' => PARAM_INT,
            'courseid' => PARAM_INT,
            'viewid' => PARAM_INT,
            'entryid' => PARAM_INT,
            'save' => PARAM_INT,
            'status' => PARAM_INT,
            'saved' => PARAM_INT,
        ]);
        [$cm] = self::context($args->cmid);
        $dlx = new datalynx($cm->instance);
        $records = $dlx->get_view_records();
        if (empty($records[$args->viewid])) {
            throw new \invalid_parameter_exception('View not available.');
        }
        $record = $records[$args->viewid];
        $builder = new entry_edit_form($dlx->get_view($record->type, $record));

        $entryid = max(0, $args->entryid);
        $form = $builder->build($entryid);
        // A step of a chain of views opens with the confirmation of the step before.
        $saved = $args->saved > 0;
        $message = $saved ? get_string('mobileentrysaved', 'datalynx') : '';
        if ($args->save && $form['allowed'] && $form['supported']) {
            $status = $form['hasstatus'] && in_array($args->status, [1, 2], true) ? $args->status : null;
            [$data, $posted] = entry_edit_form::submission($form, $rawargs, $status);
            $result = submit_entry::execute($dlx->id(), $args->viewid, $entryid, $data);
            if ($result['status']) {
                $saved = true;
                $entryid = (int) $result['entryid'];
                if ($result['nextviewid'] && $result['nextviewid'] !== $args->viewid) {
                    return self::open_next_step($cm, $result);
                }
                $form = $builder->build($entryid);
                $message = get_string('mobileentrysaved', 'datalynx');
            } else {
                $form = $builder->build($entryid, $posted);
                $errors = [];
                foreach ($result['errors'] as $error) {
                    $errors[(string) $error['fieldid']] = $error['message'];
                }
                foreach ($form['fields'] as &$field) {
                    $field['error'] = $errors[(string) $field['fieldid']] ?? '';
                }
                unset($field);
                $message = $result['errors']
                    ? get_string('mobilecheckentry', 'datalynx')
                    : implode(' ', array_column($result['warnings'], 'message'));
            }
        }

        // The entry template around the inputs, as on the web; an input whose
        // field layout asks a question before it is labelled with that question.
        $parts = $builder->parts($form);
        foreach ($parts as $part) {
            if ($part['isfield'] && $part['label'] !== '') {
                $form['fields'][$part['field']]['name'] = $part['label'];
            }
        }

        $html = $OUTPUT->render_from_template('mod_datalynx/mobile/edit_entry', [
            'cmid' => (int) $cm->id,
            'courseid' => (int) $cm->course,
        ]);

        return [
            'templates' => [
                ['id' => 'main', 'html' => $html],
            ],
            'javascript' => '',
            'otherdata' => self::otherdata(self::edit_state($form) + [
                'parts' => $parts,
                'viewid' => (int) $args->viewid,
                'saved' => $saved,
                'message' => $message,
            ]),
        ];
    }

    /**
     * The rows of an itinerary in the app: one address per stop, at least a start and a
     * destination, and the labels of the rows and their buttons.
     *
     * @param array $field the itinerary control
     * @return array stops (each with its address), labels
     */
    private static function itinerary_state(array $field): array {
        $stops = [];
        foreach ((array) json_decode((string) $field['show']['waypoints'], true) as $stop) {
            if (is_array($stop) && trim((string) ($stop['address'] ?? '')) !== '') {
                $stops[] = ['address' => (string) $stop['address']];
            }
        }
        while (count($stops) < 2) {
            $stops[] = ['address' => ''];
        }
        $strings = ['start' => 'appstopstart', 'via' => 'appstopvia', 'end' => 'appstopend', 'add' => 'addwaypoint',
            'remove' => 'dropwaypoint', 'up' => 'moveup', 'down' => 'movedown'];
        $labels = array_map(fn(string $stringid): string => get_string($stringid, 'datalynxfield_itinerary'), $strings);

        return ['stops' => array_slice($stops, 0, max(2, (int) $field['maxstops'])), 'labels' => $labels];
    }

    /**
     * A schedule in the app: the chosen shape, the chosen weekdays as one list, and the labels.
     *
     * @param array $field the schedule control
     * @return array mode, days, labels
     */
    private static function schedule_state(array $field): array {
        $days = [];
        foreach ($field['show'] as $name => $value) {
            if (preg_match('/^day(\d)$/', $name, $day) && (string) $value === '1') {
                $days[] = $day[1];
            }
        }
        $strings = ['mode' => 'mode', 'date' => 'ondate', 'days' => 'ondays', 'start' => 'departure', 'end' => 'arrival',
            'from' => 'validfrom', 'until' => 'validuntil'];
        $labels = array_map(fn(string $stringid): string => get_string($stringid, 'datalynxfield_schedule'), $strings);

        return ['mode' => (string) $field['show']['mode'], 'days' => $days, 'labels' => $labels,
            'daysname' => entry_edit_form::PREFIX . $field['fieldid'] . '__days'];
    }

    /**
     * After a save, open the view the saved view leads to ("Redirect on submit"), as the web does:
     * the saved entry in its edit form when the view continues editing, else its list.
     *
     * The page replaces its own content with the next one, without the save arguments, so that
     * pulling to refresh shows the next step rather than saving again. A content method cannot
     * switch to another one by itself, hence the line of JavaScript.
     *
     * @param stdClass $cm
     * @param array $result of submit_entry
     * @return array templates, javascript and otherdata
     */
    private static function open_next_step(stdClass $cm, array $result): array {
        $args = ['cmid' => (int) $cm->id, 'courseid' => (int) $cm->course, 'viewid' => (int) $result['nextviewid']];
        if ($result['continueediting']) {
            $args += ['entryid' => (int) $result['entryid'], 'saved' => 1];
        }
        $method = $result['continueediting'] ? 'mobile_edit_entry' : 'mobile_course_view';

        return [
            'templates' => [
                ['id' => 'main', 'html' => '<core-loading [hideUntil]="false"></core-loading>'],
            ],
            'javascript' => sprintf(
                'this.updateContent(%s, "mod_datalynx", %s, undefined, {getFromCache: false});',
                json_encode($args),
                json_encode($method)
            ),
            'otherdata' => self::otherdata(['nextstep' => $args + ['method' => $method]]),
        ];
    }

    /**
     * The form as the edit template shows it: each input with the value its control shows (a day
     * for a datetime, minutes for a duration).
     *
     * @param array $form see entry_edit_form::build()
     * @return array
     */
    private static function edit_state(array $form): array {
        foreach ($form['fields'] as &$field) {
            $field['show'] = [];
            $field['formnames'] = [];
            foreach ($field['inputs'] as &$input) {
                $value = $input['value'];
                $isdate = $field['control'] === 'datetime'
                    || ($field['control'] === 'schedule' && in_array($input['name'], ['date', 'from', 'until'], true));
                $input['show'] = match (true) {
                    $isdate => date_input::to_string($value, !empty($field['includetime'])),
                    $field['control'] === 'duration' => $value === '' ? '' : (string) round(((int) $value) / 60, 2),
                    default => $value,
                };
                $field['show'][$input['name']] = $input['show'];
                $field['formnames'][$input['name']] = $input['formname'];
            }
            unset($input);
            if ($field['control'] === 'itinerary') {
                $field += self::itinerary_state($field);
            } else if ($field['control'] === 'schedule') {
                $field += self::schedule_state($field);
            }
        }
        unset($field);
        $form['unsupportedlist'] = implode(', ', $form['unsupportedfields']);

        return $form;
    }

    /**
     * Read the arguments a method expects, each cleaned to its type.
     *
     * tool_mobile_get_content hands every argument over as a raw string, and the app sends only
     * the ones it has, so nothing reads $args directly: a missing argument becomes the empty
     * value of its type.
     *
     * @param array $args
     * @param array $types name => PARAM_* type
     * @return stdClass
     */
    private static function args(array $args, array $types): stdClass {
        $clean = new stdClass();
        foreach ($types as $name => $type) {
            $value = isset($args[$name]) && is_scalar($args[$name]) ? (string) $args[$name] : '';
            $value = clean_param($value, $type);
            $clean->$name = $type === PARAM_INT ? (int) $value : $value;
        }

        return $clean;
    }

    /**
     * Load the course module and check the user may browse it.
     *
     * @param int $cmid
     * @return array [$cm, $context]
     */
    private static function context(int $cmid): array {
        $cm = get_coursemodule_from_id('datalynx', $cmid, 0, false, MUST_EXIST);
        require_login($cm->course, false, $cm, true, true);
        $context = context_module::instance($cm->id);
        require_capability('mod/datalynx:viewentry', $context);

        return [$cm, $context];
    }

    /**
     * Encode otherdata for the app.
     *
     * tool_mobile_get_content hands every otherdata value over as a string, and the app parses a
     * value as JSON only when it starts with "{" or "[" (Moodle App 4.x/5.x). Sent one by one, a
     * number would arrive as a string (the view menu could not match "5" to view 5), "false" would
     * be true, and a search starting with "[" would be parsed. The whole state therefore travels
     * as one JSON object under "state", which the app parses with every type intact; the template
     * reads CONTENT_OTHERDATA.state.
     *
     * @param array $data
     * @return array ['state' => JSON object]
     */
    private static function otherdata(array $data): array {
        return ['state' => json_encode((object) $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }
}
