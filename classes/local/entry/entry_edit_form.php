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

use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\date_input;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\local\mobile\app_links;
use mod_datalynx\local\view\base;
use moodle_url;
use stdClass;

/**
 * The form to add or edit an entry through a view, for clients other than the web's entry form
 * (mod_datalynx#272): the web service get_entry_edit_form and the Moodle App's edit screen.
 *
 * Nothing is decided here again:
 * - whether the user may add or edit through the view: the checks of the save
 *   (user_can_manage_entry(), confirm_view_action(), user_can_add_new_entry());
 * - what each field shows and allows: entry_submission_service::get_field_states();
 * - the control of each field and its inputs: the field's renderer (get_edit_control(), on the
 *   input contract), so the values go back through submit_entry unchanged.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry_edit_form {
    /** @var string Marks the place of a control in the rendered entry template (index into the fields). */
    const FIELDMARKER = '@@DLXAPPFIELD%d@@';

    /** @var string The prefix of a form input: ef_{fieldid}__{input name}. */
    const PREFIX = 'ef_';

    /** @var base */
    private base $view;

    /** @var array field id => [prefix, suffix] of the field layout of its tag (edited or shown), see build() */
    private array $layouts = [];

    /**
     * Constructor.
     *
     * @param base $view the view the entry is added or edited through
     */
    public function __construct(base $view) {
        $this->view = $view;
    }

    /**
     * The form of an entry.
     *
     * @param int $entryid the entry to edit, 0 to add one
     * @param array $posted fieldid => input name => value: values to show instead of the stored
     *                      ones, e.g. those just submitted when the save found errors
     * @return array ['entryid', 'formentryid', 'allowed', 'denyreason', 'supported', 'unsupportedfields',
     *               'fields', 'hasstatus', 'hasfiles', 'status', 'browserurl', 'submitlabel' (the label of the view's
     *               ##submit## tag, '' for none)]
     */
    public function build(int $entryid, array $posted = []): array {
        $dlx = $this->view->get_dlx();
        $formentryid = $entryid > 0 ? $entryid : -1;
        $entry = $this->load_entry($formentryid);

        $form = [
            'entryid' => $entryid > 0 ? $entryid : 0,
            'formentryid' => $formentryid,
            'allowed' => false,
            'denyreason' => '',
            'supported' => true,
            'unsupportedfields' => [],
            'fields' => [],
            'hasstatus' => false,
            // Files the app uploads to a draft area before it saves (mod_datalynx#334).
            'hasfiles' => false,
            'status' => (int) ($entry->status ?? 0),
            'browserurl' => $this->browser_url($entryid),
            'submitlabel' => '',
        ];

        if ($entryid > 0) {
            $allowed = $entry && $this->view->user_can_act_on_entry('edit', $entry);
            $reason = 'vieweditnotallowed';
        } else {
            $allowed = $this->view->user_can_add_entry();
            $reason = 'noaccess';
        }
        if (!$allowed || !$entry) {
            $form['denyreason'] = get_string($reason, 'datalynx');
            return $form;
        }
        $form['allowed'] = true;
        $form['submitlabel'] = $this->submit_label();

        $states = (new entry_submission_service($this->view))->get_field_states($formentryid);
        foreach ($states as $fieldid => $state) {
            if (!$state['visible']) {
                continue;
            }
            $field = $state['field'];
            $renderer = $field->renderer();
            if ($renderer instanceof \datalynxfield_submit\renderer) {
                // The save button of the form (see submit_label()), no control.
                continue;
            }
            $name = format_string($field->name(), true, ['context' => $dlx->context]);
            if ($state['editable']) {
                $this->layouts[(string) $fieldid] = $renderer->get_edit_layout((string) ($state['tags'][0] ?? ''));
                $control = $renderer->get_edit_control($entry, $state['options']);
                if ($control === null) {
                    $form['unsupportedfields'][] = $name;
                    $form['supported'] = $form['supported'] && !$state['required'];
                    continue;
                }
            } else {
                if ($entryid <= 0) {
                    continue;
                }
                // Shown, not edited: its display layout labels it, as on the web.
                $this->layouts[(string) $fieldid] = $renderer->get_display_layout((string) ($state['tags'][0] ?? ''));
                $control = $renderer->get_static_control($entry, $state['options']);
                // Internal fields that show nothing here (entry actions, cancel) get no control.
                if ($field->is_internal() && trim(strip_tags((string) ($control['html'] ?? ''), '<img>')) === '') {
                    continue;
                }
            }
            if ((string) $fieldid === 'status' && $control['control'] === 'toggle') {
                $form['hasstatus'] = true;
            }
            if ($control['control'] === 'files') {
                $form['hasfiles'] = true;
            }
            $control['name'] = $name;
            $control['required'] = (bool) $state['required'];
            $control['error'] = '';
            foreach ($control['inputs'] as &$input) {
                if (isset($posted[(string) $fieldid]) && array_key_exists($input['name'], $posted[(string) $fieldid])) {
                    $input['value'] = $posted[(string) $fieldid][$input['name']];
                }
                $input['formname'] = self::PREFIX . $fieldid . '__' . $input['name'];
            }
            unset($input);
            $form['fields'][] = $control;
        }

        return $form;
    }

    /**
     * The label of the view's save button, from its first ##submit## tag
     * (e.g. ##submit:text=Weiter_zum_Termin## gives "Weiter zum Termin").
     *
     * @return string '' when the view has no submit tag
     */
    private function submit_label(): string {
        $submit = $this->view->get_dlx()->get_field_from_id('submit');
        if (!$submit) {
            return '';
        }
        $record = $this->view->view;
        $tags = $submit->renderer()->search(($record->section ?? '') . ($record->param2 ?? ''));

        return $tags ? $submit->renderer()->get_button_label((string) reset($tags)) : '';
    }

    /**
     * Turn the inputs of a submitted form into the data submit_entry takes.
     *
     * The form's inputs are named ef_{fieldid}__{input name}. Lists arrive as "a,b" (multiple
     * selects); a datetime as YYYY-MM-DD or YYYY-MM-DDTHH:MM in the user's timezone; a duration
     * in minutes. An itinerary sends one address per stop (ef_{fieldid}__waypoints__{n}), with the
     * position of a place picked from the suggestions (…__{n}__lat, …__{n}__lng), a
     * schedule its weekdays as one list (ef_{fieldid}__days). Each is turned into the value the
     * field's input contract takes.
     *
     * @param array $form the form as build() returned it, to know each input's control
     * @param array $args the submitted arguments
     * @param int|null $status the status to save (draft or final submission), if the form has one
     * @return array [data for submit_entry, posted values by field and input for build()]
     */
    public static function submission(array $form, array $args, ?int $status = null): array {
        $data = [];
        $posted = [];
        $arg = static fn(string $name): ?string =>
            array_key_exists($name, $args) && is_scalar($args[$name]) ? trim((string) $args[$name]) : null;
        foreach ($form['fields'] as $control) {
            $fieldid = (string) $control['fieldid'];
            foreach ($control['inputs'] as $input) {
                $name = $input['formname'];
                if ($fieldid === 'status' && $status !== null) {
                    $raw = (string) $status;
                } else if ($control['control'] === 'hidden') {
                    $raw = (string) $input['value'];
                } else if ($control['control'] === 'files') {
                    // The draft area the app uploaded the files to; without one the stored files stay.
                    $raw = $arg($name);
                    if ($raw === null || (int) $raw <= 0) {
                        continue;
                    }
                } else if ($control['control'] === 'itinerary' && $input['name'] === 'waypoints') {
                    // One address per stop, ef_{fieldid}__waypoints__{n}; the field looks them up.
                    if ($arg("{$name}__0") === null) {
                        continue;
                    }
                    $stops = [];
                    for ($n = 0; $n < (int) ($control['maxstops'] ?? 0); $n++) {
                        $address = $arg("{$name}__{$n}");
                        if ($address === null || $address === '') {
                            continue;
                        }
                        // A place picked from the suggestions comes with its position.
                        $lat = $arg("{$name}__{$n}__lat");
                        $lng = $arg("{$name}__{$n}__lng");
                        $stops[] = is_numeric($lat) && is_numeric($lng) && abs((float) $lat) <= 90 && abs((float) $lng) <= 180
                            ? ['address' => $address, 'lat' => (float) $lat, 'lng' => (float) $lng]
                            : ['address' => $address];
                    }
                    $raw = json_encode($stops, JSON_UNESCAPED_UNICODE);
                } else if ($control['control'] === 'schedule' && preg_match('/^day(\d)$/', $input['name'], $day)) {
                    // The weekdays come as one multiple select, ef_{fieldid}__days.
                    $days = $arg(self::PREFIX . $fieldid . '__days');
                    if ($days === null) {
                        continue;
                    }
                    $raw = in_array($day[1], array_map('trim', explode(',', $days)), true) ? '1' : '0';
                } else if (($raw = $arg($name)) === null) {
                    if ($control['control'] === 'schedule' && $input['name'] === 'mode') {
                        // A field with one shape has no choice of it in the form.
                        $raw = (string) $input['value'];
                    } else {
                        continue;
                    }
                }
                $isdate = $control['control'] === 'datetime'
                    || ($control['control'] === 'schedule' && in_array($input['name'], ['date', 'from', 'until'], true));
                $value = match (true) {
                    $input['list'] => array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== '')),
                    $isdate => (string) date_input::to_timestamp($raw),
                    $control['control'] === 'duration' => $raw === '' ? '' : (string) (int) round((float) $raw * 60),
                    default => $raw,
                };
                $posted[$fieldid][$input['name']] = $value;
                $data[] = [
                    'fieldid' => $fieldid,
                    'name' => $input['name'],
                    'value' => is_array($value) ? json_encode($value) : $value,
                ];
            }
        }

        return [$data, $posted];
    }

    /**
     * The entry template of the view as the Moodle App shows it around the inputs of the form.
     *
     * The template is rendered as the app lists an entry (action tags empty, links through
     * app_links), with a marker in place of the tags of each field the form has a control for.
     * Cut at the markers it gives the parts in order: HTML, links to other views and the place of
     * each control. A field whose tag appears more than once gets its control at the first one; a
     * control without a tag in the template (none so far) follows at the end. Without an entry
     * template, or when it cannot be rendered, the parts are only the controls, as before.
     *
     * @param array $form see build()
     * @return array parts, each with html (string), isfield (bool), field (index into
     *               $form['fields'], -1 for none) and label (the question of the field's layout, or
     *               ''), and a link part with link (see app_links::parts())
     */
    public function parts(array $form): array {
        global $USER;

        $controls = [];
        foreach ($form['fields'] as $index => $control) {
            $controls[(string) $control['fieldid']] = $index;
        }
        $labels = [];
        $fieldpart = function (int $index) use (&$labels): array {
            return ['html' => '', 'entries' => false, 'isfield' => true, 'field' => $index, 'label' => $labels[$index] ?? ''];
        };
        $onlycontrols = array_map($fieldpart, array_keys($form['fields']));
        if (!$form['allowed'] || trim((string) ($this->view->view->param2 ?? '')) === '') {
            return $onlycontrols;
        }

        // The field layout around an input: the text before it (the question) labels the app's
        // input, the HTML after it follows the input. A layout without text before the input
        // keeps its HTML and the field name stays the label.
        $markers = [];
        foreach ($controls as $fieldid => $index) {
            [$prefix, $suffix] = $this->layouts[$fieldid] ?? ['', ''];
            $label = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($prefix), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($label !== '') {
                $labels[$index] = $label;
                $prefix = '';
            }
            $markers[$fieldid] = $prefix . sprintf(self::FIELDMARKER, $index) . $suffix;
        }
        $entry = $this->load_entry($form['formentryid']);
        if (!$entry) {
            return $onlycontrols;
        }
        if ($entry->id < 0) {
            $entry->userid = (int) $USER->id;
            $entry->groupid = 0;
            $entry->approved = 0;
            $entry->timecreated = $entry->timemodified = time();
        }
        $links = new app_links($this->view->get_dlx());
        try {
            $html = $this->view->render_entry_html(
                $entry,
                ['edit' => false, 'manage' => false] + $links->render_options(),
                $markers
            );
        } catch (\Throwable $e) {
            debugging('Entry template not rendered for the app: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $onlycontrols;
        }

        $marker = str_replace('%d', '(\d+)', preg_quote(self::FIELDMARKER, '/'));
        $pieces = preg_split('/' . $marker . '/', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = [];
        $placed = [];
        foreach ($pieces as $i => $piece) {
            if ($i % 2 === 0) {
                foreach ($links->parts($piece) as $part) {
                    $parts[] = $part + ['isfield' => false, 'field' => -1, 'label' => ''];
                }
            } else if (!isset($placed[(int) $piece]) && isset($form['fields'][(int) $piece])) {
                $placed[(int) $piece] = true;
                $parts[] = $fieldpart((int) $piece);
            }
        }
        foreach (array_keys($form['fields']) as $index) {
            if (!isset($placed[$index])) {
                $parts[] = $fieldpart($index);
            }
        }

        return $parts;
    }

    /**
     * The entry with its contents, as the entry form loads it; a new entry as an id of -1.
     *
     * @param int $entryid
     * @return stdClass|null null for an entry not in this instance
     */
    private function load_entry(int $entryid): ?stdClass {
        if ($entryid < 0) {
            return (object) ['id' => $entryid, 'status' => 0];
        }
        $dlx = $this->view->get_dlx();
        $filter = new datalynx_filter((object) ['dataid' => $dlx->id(), 'eids' => (string) $entryid]);
        $filter->contentfields = array_keys(array_filter($dlx->get_fields(), fn($field) => !$field::is_internal()));
        $entries = (new datalynx_entries($dlx, $filter))->get_entries(['filter' => $filter])->entries ?? [];

        return $entries[$entryid] ?? (reset($entries) ?: null);
    }

    /**
     * The entry form of the view on the web, for the "open in browser" fallback.
     *
     * @param int $entryid
     * @return string
     */
    private function browser_url(int $entryid): string {
        $params = ['d' => $this->view->get_dlx()->id(), 'view' => $this->view->id()];
        if ($entryid > 0) {
            $params['eids'] = $entryid;
            $params['editentries'] = $entryid;
        } else {
            $params['new'] = 1;
        }

        return (new moodle_url('/mod/datalynx/view.php', $params))->out(false);
    }
}
