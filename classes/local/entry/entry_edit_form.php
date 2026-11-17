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

use core_date;
use DateTime;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;
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
    /** @var string The prefix of a form input: ef_{fieldid}__{input name}. */
    const PREFIX = 'ef_';

    /** @var base */
    private base $view;

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
     *               'fields', 'hasstatus', 'status', 'browserurl']
     */
    public function build(int $entryid, array $posted = []): array {
        $dlx = $this->view->get_dlx();
        $viewid = (int) $this->view->id();
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
            'status' => (int) ($entry->status ?? 0),
            'browserurl' => $this->browser_url($entryid),
        ];

        if ($entryid > 0) {
            $allowed = $entry && $dlx->user_can_manage_entry($entry) && $this->view->confirm_view_action('edit', $viewid);
            $reason = 'vieweditnotallowed';
        } else {
            $allowed = $dlx->user_can_add_new_entry() && (
                $this->view->confirm_view_action('addnewentry', $viewid)
                || $this->view->confirm_view_action('addnewentries', $viewid)
            );
            $reason = 'noaccess';
        }
        if (!$allowed || !$entry) {
            $form['denyreason'] = get_string($reason, 'datalynx');
            return $form;
        }
        $form['allowed'] = true;

        $states = (new entry_submission_service($this->view))->get_field_states($formentryid);
        foreach ($states as $fieldid => $state) {
            if (!$state['visible']) {
                continue;
            }
            $field = $state['field'];
            $renderer = $field->renderer();
            $name = format_string($field->name(), true, ['context' => $dlx->context]);
            if ($state['editable']) {
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
                $control = $renderer->get_static_control($entry, $state['options']);
            }
            if ((string) $fieldid === 'status' && $control['control'] === 'toggle') {
                $form['hasstatus'] = true;
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
     * Turn the inputs of a submitted form into the data submit_entry takes.
     *
     * The form's inputs are named ef_{fieldid}__{input name}. Lists arrive as "a,b" (multiple
     * selects); a datetime as YYYY-MM-DD or YYYY-MM-DDTHH:MM in the user's timezone; a duration
     * in minutes. Each is turned into the value the field's input contract takes.
     *
     * @param array $form the form as build() returned it, to know each input's control
     * @param array $args the submitted arguments
     * @param int|null $status the status to save (draft or final submission), if the form has one
     * @return array [data for submit_entry, posted values by field and input for build()]
     */
    public static function submission(array $form, array $args, ?int $status = null): array {
        $data = [];
        $posted = [];
        foreach ($form['fields'] as $control) {
            $fieldid = (string) $control['fieldid'];
            foreach ($control['inputs'] as $input) {
                $name = $input['formname'];
                if ($fieldid === 'status' && $status !== null) {
                    $raw = (string) $status;
                } else if ($control['control'] === 'hidden') {
                    $raw = (string) $input['value'];
                } else if (array_key_exists($name, $args) && is_scalar($args[$name])) {
                    $raw = trim((string) $args[$name]);
                } else {
                    continue;
                }
                $value = match (true) {
                    $input['list'] => array_values(array_filter(array_map('trim', explode(',', $raw)), fn($v) => $v !== '')),
                    $control['control'] === 'datetime' => (string) self::to_timestamp($raw),
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
     * A day, or a day and a time, entered in the user's timezone.
     *
     * @param string $value YYYY-MM-DD or YYYY-MM-DDTHH:MM; a timestamp is taken as it is
     * @return int 0 when none
     */
    public static function to_timestamp(string $value): int {
        if (ctype_digit($value)) {
            return (int) $value;
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/', $value, $m)) {
            return 0;
        }
        $date = new DateTime(
            sprintf('%s-%s-%s %s:%s:00', $m[1], $m[2], $m[3], $m[4] ?? '00', $m[5] ?? '00'),
            core_date::get_user_timezone_object()
        );

        return $date->getTimestamp();
    }

    /**
     * A timestamp as a date input shows it.
     *
     * @param string $value
     * @param bool $includetime
     * @return string YYYY-MM-DD or YYYY-MM-DDTHH:MM, '' for none
     */
    public static function to_date_input(string $value, bool $includetime): string {
        if (!ctype_digit($value) || (int) $value <= 0) {
            return '';
        }
        $date = (new DateTime('@' . $value))->setTimezone(core_date::get_user_timezone_object());

        return $date->format($includetime ? 'Y-m-d\TH:i' : 'Y-m-d');
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
