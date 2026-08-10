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

namespace datalynxfield_schedule;

use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use mod_datalynx\local\ride\schedule;
use MoodleQuickForm;
use stdClass;

/**
 * Schedule field renderer.
 *
 * The edit widget is built from stock form elements rather than a JSON payload with its own
 * JavaScript: a schedule has a handful of controls, all of which MoodleQuickForm already models,
 * and hideIf() is enough to show only the half that the chosen mode needs. That keeps the field
 * usable in views whose entries arrive through a web service, where inline scripts do not survive.
 *
 * @package    datalynxfield_schedule
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends datalynxfield_renderer {
    // This is a datalynx field renderer, not a core_renderer: $this->page and
    // $this->output do not exist here.
    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Render the field in edit mode.
     *
     * One control per part, each carrying its own name, so MoodleQuickForm validates and
     * repopulates them normally; the field lists them in content_names() and reassembles them
     * into one value in {@see field::schedule_from_values()}. hideIf() shows only the half the
     * chosen mode needs, which is what the separate "one-off or recurring?" question used to do
     * across two pages.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param array $options
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->field;
        $fieldid = $field->id();
        $prefix = "field_{$fieldid}_{$entry->id}";
        $current = $field->schedule_of($entry);
        $allowed = $field->get_allowed_mode();

        $modes = [
            schedule::MODE_ONCE => get_string('modeonce', 'datalynxfield_schedule'),
            schedule::MODE_WEEKLY => get_string('modeweekly', 'datalynxfield_schedule'),
        ];
        if ($allowed !== 'both') {
            $modes = [$allowed => $modes[$allowed]];
        }
        $mode = $current->is_valid() ? $current->mode() : array_key_first($modes);

        if (count($modes) > 1) {
            $mform->addElement('select', "{$prefix}_mode", get_string('mode', 'datalynxfield_schedule'), $modes);
        } else {
            $mform->addElement('hidden', "{$prefix}_mode", $mode);
            $mform->setType("{$prefix}_mode", PARAM_ALPHA);
        }
        $mform->setDefault("{$prefix}_mode", $mode);

        // One-off: the date it happens.
        $mform->addElement('date_selector', "{$prefix}_date", get_string('ondate', 'datalynxfield_schedule'));
        $mform->setDefault("{$prefix}_date", $current->date() !== '' ? schedule::day_start($current->date()) : time());
        $mform->hideIf("{$prefix}_date", "{$prefix}_mode", 'neq', schedule::MODE_ONCE);

        // Weekly: which days it runs on.
        $daygroup = [];
        foreach (field::weekdays_menu() as $day => $label) {
            $daygroup[] = $mform->createElement('advcheckbox', "{$prefix}_day{$day}", '', $label, ['class' => 'me-2']);
        }
        $mform->addGroup($daygroup, "{$prefix}_days", get_string('ondays', 'datalynxfield_schedule'), ' ', false);
        foreach ($current->days() as $day) {
            $mform->setDefault("{$prefix}_day{$day}", 1);
        }
        $mform->hideIf("{$prefix}_days", "{$prefix}_mode", 'neq', schedule::MODE_WEEKLY);

        // Both: the departure, and optionally the arrival.
        $times = $field->times_menu();
        $mform->addElement('select', "{$prefix}_start", get_string('departure', 'datalynxfield_schedule'), $times);
        $mform->setDefault("{$prefix}_start", self::nearest_time($current->start_minute(), $times));

        if ($field->wants_arrival()) {
            $mform->addElement(
                'select',
                "{$prefix}_end",
                get_string('arrival', 'datalynxfield_schedule'),
                ['' => get_string('choosedots')] + $times
            );
            $mform->setDefault("{$prefix}_end", $current->end_minute());
        }

        // Weekly: how long the pattern holds.
        foreach (['from' => 'validfrom', 'until' => 'validuntil'] as $key => $stringid) {
            $name = "{$prefix}_{$key}";
            $mform->addElement(
                'date_selector',
                $name,
                get_string($stringid, 'datalynxfield_schedule'),
                ['optional' => true]
            );
            $value = $key === 'from' ? $current->from() : $current->until();
            $mform->setDefault($name, $value !== '' ? schedule::day_start($value) : 0);
            $mform->hideIf($name, "{$prefix}_mode", 'neq', schedule::MODE_WEEKLY);
        }
    }

    /**
     * Render the field in display mode.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string HTML
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $current = $this->field->schedule_of($entry);
        if (!$current->is_valid()) {
            return '';
        }

        $parts = [];
        if ($current->mode() === schedule::MODE_ONCE) {
            $parts[] = html_writer::tag(
                'span',
                userdate((int) schedule::day_start($current->date()), get_string('strftimedaydate', 'core_langconfig')),
                ['class' => 'datalynx-schedule__when']
            );
        } else {
            $days = field::weekdays_menu();
            $labels = [];
            foreach ($current->days() as $day) {
                $labels[] = $days[$day];
            }
            $parts[] = html_writer::tag(
                'span',
                get_string('everyweek', 'datalynxfield_schedule', implode(', ', $labels)),
                ['class' => 'datalynx-schedule__when']
            );
        }

        $time = field::format_minute((int) $current->start_minute());
        if ($current->end_minute() !== null) {
            $time .= ' – ' . field::format_minute($current->end_minute());
        }
        $parts[] = html_writer::tag('span', $time, ['class' => 'datalynx-schedule__time']);

        if ($current->mode() === schedule::MODE_WEEKLY && $current->until() !== '') {
            $parts[] = html_writer::tag(
                'span',
                get_string(
                    'untildate',
                    'datalynxfield_schedule',
                    userdate((int) schedule::day_start($current->until()), get_string('strftimedate', 'core_langconfig'))
                ),
                ['class' => 'datalynx-schedule__until text-muted small']
            );
        }

        return html_writer::tag('span', implode(' ', $parts), ['class' => 'datalynx-schedule']);
    }

    /**
     * Render the search controls: a day, a time of day and how far off it may be.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param mixed $value
     * @return array [$elements, $separators]
     */
    public function render_search_mode(MoodleQuickForm &$mform, $i = 0, $value = '') {
        $fieldid = $this->field->id();
        $prefix = "f_{$i}_{$fieldid}";
        $search = self::search_parts($value);

        $elements = [];

        $datename = "{$prefix}_date";
        $elements[] = $mform->createElement(
            'date_selector',
            $datename,
            get_string('searchdate', 'datalynxfield_schedule'),
            ['optional' => true]
        );
        $mform->setDefault($datename, (int) ($search['date'] ?? 0));

        $minutename = "{$prefix}_minute";
        $elements[] = $mform->createElement(
            'select',
            $minutename,
            get_string('searchtime', 'datalynxfield_schedule'),
            ['' => get_string('anytime', 'datalynxfield_schedule')] + $this->field->times_menu()
        );
        $mform->setDefault($minutename, $search['minute'] ?? '');

        $tolerancename = "{$prefix}_tolerance";
        $elements[] = $mform->createElement(
            'select',
            $tolerancename,
            get_string('searchtolerance', 'datalynxfield_schedule'),
            self::tolerance_menu()
        );
        $mform->setType($tolerancename, PARAM_INT);
        $mform->setDefault($tolerancename, (int) ($search['tolerance'] ?? $this->field->get_match_tolerance()));

        return [$elements, [' ', ' ', ' ']];
    }

    /**
     * The tolerances a traveller can pick, in minutes.
     *
     * The whole day comes first and is the default: a rule exists to introduce two people who
     * might share a ride, and the exact hour is theirs to settle.
     *
     * @return array
     */
    public static function tolerance_menu(): array {
        return [
            field::TOLERANCE_WHOLE_DAY => get_string('toleranceday', 'datalynxfield_schedule'),
            180 => get_string('tolerancehours', 'datalynxfield_schedule', 3),
            60 => get_string('tolerancehours', 'datalynxfield_schedule', 1),
            30 => get_string('toleranceminutes', 'datalynxfield_schedule', 30),
        ];
    }

    /**
     * Normalise a stored filter value into its parts.
     *
     * The filter and behavior forms JSON encode array values before they reach a renderer, so
     * both shapes have to be accepted.
     *
     * @param mixed $value
     * @return array
     */
    protected static function search_parts($value): array {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * The offered time closest to a stored minute, so a value entered at a finer granularity
     * than the field now offers still selects something sensible.
     *
     * @param int|null $minute
     * @param array $times
     * @return int
     */
    protected static function nearest_time(?int $minute, array $times): int {
        if ($minute === null) {
            return (int) array_key_first($times);
        }
        $best = (int) array_key_first($times);
        foreach (array_keys($times) as $candidate) {
            if (abs($candidate - $minute) < abs($best - $minute)) {
                $best = (int) $candidate;
            }
        }

        return $best;
    }

    /**
     * Validate the submitted schedule.
     *
     * @param int $entryid
     * @param array $tags
     * @param stdClass $formdata
     * @return array
     */
    public function validate($entryid, $tags, $formdata) {
        $errors = [];
        $prefix = "field_{$this->field->id()}_{$entryid}";
        $values = $this->field->get_content_from_data($entryid, $formdata);
        if (!$values) {
            return $errors;
        }

        $current = field::schedule_from_values($values);
        if ($current->is_valid()) {
            return $errors;
        }

        // Point at the control that is actually wrong, so the message lands where the fix is.
        if ($current->mode() === schedule::MODE_WEEKLY && !$current->days()) {
            $errors["{$prefix}_days"] = get_string('errnodays', 'datalynxfield_schedule');
        } else if ($current->mode() === schedule::MODE_WEEKLY && $current->until() !== '') {
            $errors["{$prefix}_until"] = get_string('erruntilbeforefrom', 'datalynxfield_schedule');
        } else if ($current->mode() === schedule::MODE_ONCE && $current->date() === '') {
            $errors["{$prefix}_date"] = get_string('errnodate', 'datalynxfield_schedule');
        } else if (!empty($this->field->get('required'))) {
            $errors["{$prefix}_start"] = get_string('errnoschedule', 'datalynxfield_schedule');
        }

        return $errors;
    }
}
