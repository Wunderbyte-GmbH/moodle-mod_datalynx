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
 * Moves a ride board's timing onto one schedule field.
 *
 * A board built before the schedule field described "when" with four fields - a pattern radio
 * button, a weekday multi-select, a time-of-day select, and a date typed onto the first stop of
 * the itinerary. Only ever half of them applied to any one entry, nothing enforced which half,
 * and no query read any of them: a recurring ride was invisible to the time filter and therefore
 * matched every request on its route, while a search by weekday could never find a one-off ride.
 *
 * This creates the `Termin` field, converts every entry onto it, and rewires the views, the
 * matching rules and the search form.
 *
 * **Run this before saving any entry through the new forms.** A one-off ride's date lives on its
 * itinerary, and once the itinerary stops carrying times, re-saving an entry drops it.
 *
 * Idempotent: everything is looked up by name and updated in place.
 *
 * Usage:
 *   php mod/datalynx/cli/migrate_ridesharing_schedule.php --cmid=1162 --dryrun
 *   php mod/datalynx/cli/migrate_ridesharing_schedule.php --cmid=1162
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_datalynx\local\ride\schedule;
use mod_datalynx\local\ride\schedule_migrator;

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'cmid' => 0,
    'dataid' => 0,
    'dryrun' => false,
    'dropoldfields' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || (empty($options['cmid']) && empty($options['dataid']))) {
    cli_writeln("Move a ride board's timing onto one schedule field.

Options:
  -h, --help          Print this help.
      --cmid=ID       Course module id of the activity.
      --dataid=ID     Datalynx instance id, as an alternative to --cmid.
      --dryrun        Report what would change without writing anything.
      --dropoldfields Delete the four superseded fields and their content. Irreversible;
                      run it only once the converted schedules look right.

Example:
  php mod/datalynx/cli/migrate_ridesharing_schedule.php --cmid=1162 --dryrun
");
    exit(0);
}

$dataid = (int) $options['dataid'];
if (!$dataid) {
    $cm = $DB->get_record('course_modules', ['id' => (int) $options['cmid']], '*', MUST_EXIST);
    $dataid = (int) $cm->instance;
}
$dryrun = !empty($options['dryrun']);

$dlx = new \mod_datalynx\datalynx($dataid);
cli_writeln("Activity: {$dlx->name()} (datalynx $dataid)");
if ($dryrun) {
    cli_writeln('Dry run - nothing will be written.');
}

$oldnames = [
    'pattern' => 'Fahrtmuster',
    'weekday' => 'Wochentage',
    'time' => 'Abfahrtszeit',
    'arrival' => 'Ankunftszeit',
    'itinerary' => 'Route',
];
$schedulename = 'Termin';
$layoutname = 'l_termin';

$fields = [];
foreach ($DB->get_records('datalynx_fields', ['dataid' => $dataid]) as $record) {
    $fields[$record->name] = $record;
}
foreach (['pattern', 'weekday', 'time', 'itinerary'] as $key) {
    if (empty($fields[$oldnames[$key]])) {
        cli_error("Cannot find the field \"{$oldnames[$key]}\". Nothing to migrate.");
    }
}

// The weekday multi-select's option positions become ISO weekday numbers, which only holds if it
// lists Monday first. Check rather than assume - a reordered list would silently shift every ride.
if (!schedule_migrator::options_are_iso_weekdays((string) $fields[$oldnames['weekday']]->param1)) {
    cli_error("The \"{$oldnames['weekday']}\" field does not list the seven weekdays starting at Monday, "
        . 'so its options cannot be mapped to weekdays. Fix the option list and re-run.');
}

// Which option of the pattern field means "recurring".
$recurring = 0;
foreach (explode("\n", (string) $fields[$oldnames['pattern']]->param1) as $index => $label) {
    if (\core_text::strtolower(trim($label)) === \core_text::strtolower('Regelmäßig')) {
        $recurring = $index + 1;
    }
}
if (!$recurring) {
    cli_error("The \"{$oldnames['pattern']}\" field has no \"Regelmäßig\" option.");
}

// The step between the time select's options, read from the option list itself.
$timeoptions = array_values(array_filter(array_map('trim', explode("\n", (string) $fields[$oldnames['time']]->param1))));
$timestep = count($timeoptions) > 1 ? (int) round(schedule::DAY_MINUTES / count($timeoptions)) : 30;

$report = [];

// The schedule field.
if (empty($fields[$schedulename])) {
    $report[] = "  create field: $schedulename (schedule)";
    if (!$dryrun) {
        $record = (object) [
            'dataid' => $dataid,
            'type' => 'schedule',
            'name' => $schedulename,
            'description' => 'Wann die Fahrt stattfindet - einmalig oder jede Woche.',
            'required' => 1,
            'visible' => 2,
            'editable' => -1,
            'param1' => $timestep,
            'param2' => \datalynxfield_schedule\field::TOLERANCE_WHOLE_DAY,
            'param3' => 'both',
            'param4' => 0,
            'param5' => 1,
        ];
        $record->id = $DB->insert_record('datalynx_fields', $record);
        $fields[$schedulename] = $record;
    }
} else {
    $report[] = "  field exists: $schedulename";
}

// A field layout, so the Termin field is presented like its neighbours.
if (!$DB->record_exists('datalynx_renderers', ['dataid' => $dataid, 'name' => $layoutname]) && !$dryrun) {
    $DB->insert_record('datalynx_renderers', (object) [
        'dataid' => $dataid,
        'name' => $layoutname,
        'description' => '',
        'notvisibletemplate' => '___0___',
        'displaytemplate' => '<div class="my-3"><div class="small fw-semibold text-muted mb-1">Termin</div>'
            . '<div>#value</div></div>',
        'novaluetemplate' => '___0___',
        'edittemplate' => '<div class="mb-3 datalynx-ride-field"><label class="form-label fw-semibold">Termin</label>'
            . '#input<div class="form-text">Einmalige Fahrt: Datum und Uhrzeit. Regelmäßige Fahrt: '
            . 'Wochentage, Uhrzeit und bis wann sie gilt.</div></div>',
        'noteditabletemplate' => '___5___',
    ]);
    $report[] = "  create field layout: $layoutname";
}

// Convert the entries.
if (!$dryrun && empty($fields[$schedulename])) {
    cli_error('The schedule field could not be created.');
}

$migrator = new schedule_migrator(
    $dlx,
    [
        'pattern' => (int) $fields[$oldnames['pattern']]->id,
        'weekday' => (int) $fields[$oldnames['weekday']]->id,
        'time' => (int) $fields[$oldnames['time']]->id,
        'itinerary' => (int) $fields[$oldnames['itinerary']]->id,
    ],
    $recurring,
    $timestep
);

$entries = $DB->get_records('datalynx_entries', ['dataid' => $dataid], 'id', 'id, timecreated');
$converted = 0;
$skipped = 0;
$flagged = [];

foreach ($entries as $entry) {
    $values = [];
    $contents = $DB->get_records('datalynx_contents', ['entryid' => $entry->id]);
    foreach ($contents as $content) {
        $values[(int) $content->fieldid] = $content->content;
        if ((int) $content->fieldid === (int) $fields[$oldnames['itinerary']]->id) {
            // The one-off date lived on the itinerary's earliest stop time.
            $values['departure'] = (int) $content->content3;
        }
    }

    $migrator->reset_notes();
    $result = $migrator->schedule_for($values, (int) $entry->timecreated);
    foreach ($migrator->notes() as $note) {
        $flagged[] = "    entry {$entry->id}: $note";
    }

    if ($result === null || !$result->is_valid()) {
        $skipped++;
        continue;
    }
    $converted++;

    if ($dryrun) {
        continue;
    }

    $schedulefield = $dlx->get_field_from_id((int) $fields[$schedulename]->id);
    $entryrecord = $DB->get_record('datalynx_entries', ['id' => $entry->id]);
    $fieldid = (int) $fields[$schedulename]->id;
    $existing = $DB->get_record('datalynx_contents', ['entryid' => $entry->id, 'fieldid' => $fieldid]);
    if ($existing) {
        $entryrecord->{"c{$fieldid}_id"} = $existing->id;
        $entryrecord->{"c{$fieldid}_content"} = $existing->content;
    }
    $schedulefield->update_content($entryrecord, ['schedule' => $result->to_json()]);
}

$report[] = "  entries: $converted converted, $skipped left without a schedule";
$report = array_merge($report, $flagged);

// Views: the four tags become one.
$tagfor = static function (array $names): string {
    return '/\[\[(?:' . implode('|', array_map(
        static fn($name) => preg_quote($name, '/'),
        $names
    )) . ')(?::[^\]|]*)?(\|[^\]]*)?\]\]/u';
};
// All four superseded tags, and separately the three that actually said *when* a ride runs. A
// template carrying only the pattern radio button was asking "one-off or recurring?" - a question
// the schedule field now asks itself - so there the tag is dropped rather than replaced.
$oldtags = $tagfor([$oldnames['pattern'], $oldnames['weekday'], $oldnames['time'], $oldnames['arrival']]);
$timetags = $tagfor([$oldnames['weekday'], $oldnames['time'], $oldnames['arrival']]);
$scheduletag = $tagfor([$schedulename]);

// Behaviours that condition on the pattern field are about to become unsatisfiable, because
// nothing fills that field in any more. Each one is replaced by the behaviour that says the same
// thing without the condition - "read only when recurring" becomes plain "read only" - and where
// there is no such twin the tag simply carries no behaviour, which is the same thing again.
$behaviours = $DB->get_records('datalynx_behaviors', ['dataid' => $dataid]);
$isdead = static function ($behaviour) use ($fields, $oldnames): bool {
    return strpos(
        (string) $behaviour->conditions,
        '"sourcefieldid":' . (int) $fields[$oldnames['pattern']]->id
    ) !== false;
};
$replacement = [];
foreach ($behaviours as $behaviour) {
    if (!$isdead($behaviour)) {
        continue;
    }
    $replacement[$behaviour->name] = '';
    foreach ($behaviours as $candidate) {
        if (
            empty($candidate->conditions)
            && $candidate->visibleto === $behaviour->visibleto
            && $candidate->editableby === $behaviour->editableby
            && (int) $candidate->required === (int) $behaviour->required
            && (int) $candidate->editableafterfinal === (int) $behaviour->editableafterfinal
        ) {
            $replacement[$behaviour->name] = $candidate->name;
            break;
        }
    }
}

// The behaviour of a [[Field|behaviour|layout]] tag, resolved to something that still holds.
$knownbehaviours = array_column($behaviours, 'name', 'name');
$behaviourof = static function (?string $tail) use ($replacement, $knownbehaviours): string {
    if ($tail === null || $tail === '') {
        return '';
    }
    // The captured tail starts with exactly one pipe; stripping more would promote the layout
    // name into the behaviour slot.
    $parts = explode('|', substr($tail, 1));
    $behaviour = $parts[0] ?? '';

    if (array_key_exists($behaviour, $replacement)) {
        return $replacement[$behaviour];
    }

    // Anything that is not a behaviour of this instance - a layout name that ended up in the
    // wrong slot, say - is no behaviour at all.
    return array_key_exists($behaviour, $knownbehaviours) ? $behaviour : '';
};

foreach ($DB->get_records('datalynx_views', ['dataid' => $dataid]) as $view) {
    $changed = false;
    $update = (object) ['id' => $view->id];

    foreach (['section', 'param2'] as $column) {
        $template = (string) $view->$column;
        // Enter for a template still carrying the old tags, and also for one an earlier run
        // already rewrote, so a re-run can repair what it wrote.
        if ($template === '' || !preg_match($oldtags, $template) && !preg_match($scheduletag, $template)) {
            continue;
        }

        // Where the template actually asked when the ride runs, the first of those tags becomes the
        // schedule tag, carrying over its behaviour, and the rest are dropped. Where it only asked
        // for the pattern, the tag goes without replacement. Editing in place keeps any hand
        // tuning of the surrounding markup.
        $wantsschedule = (bool) preg_match($timetags, $template);
        $first = true;
        $new = preg_replace_callback(
            $oldtags,
            static function ($matches) use (&$first, $wantsschedule, $schedulename, $layoutname, $behaviourof) {
                if (!$first || !$wantsschedule) {
                    return '';
                }
                $first = false;

                return "[[{$schedulename}|{$behaviourof($matches[1] ?? '')}|{$layoutname}]]";
            },
            $template
        );

        // Re-running has to be able to repair a schedule tag written by an earlier run that
        // carried a behaviour conditioning on the retired pattern field.
        $new = preg_replace_callback(
            $scheduletag,
            static function ($matches) use ($schedulename, $layoutname, $behaviourof) {
                return "[[{$schedulename}|{$behaviourof($matches[1] ?? '')}|{$layoutname}]]";
            },
            $new
        );

        // Anything else gated on the pattern field is gated on a question nobody answers any
        // more - on this board, a second copy of the route that existed only to carry a date.
        $new = preg_replace_callback(
            '/\[\[[^\]|]+(?::[^\]|]*)?\|([^|\]]*)\|[^\]]*\]\]/u',
            static function ($matches) use ($replacement) {
                $behaviour = $matches[1];

                return (array_key_exists($behaviour, $replacement) && $behaviour !== '') ? '' : $matches[0];
            },
            $new
        );

        if ($new !== $template) {
            $update->$column = $new;
            $changed = true;
        }
    }

    if ($changed) {
        $report[] = "  update view {$view->id}: {$view->name}";
        if (!$dryrun) {
            // The parsed pattern cache has to go with the template it was parsed from.
            $update->patterns = null;
            $DB->update_record('datalynx_views', $update);
        }
    }
}

// Rules: the departure tolerance moves from the itinerary to the schedule field.
foreach (
    $DB->get_records_select(
        'datalynx_rules',
        "dataid = :dataid AND type = 'eventnotification' AND " . $DB->sql_like('param9', ':key'),
        ['dataid' => $dataid, 'key' => '%_matchcriteria%']
    ) as $rule
) {
    $param9 = json_decode((string) $rule->param9, true);
    $config = $param9[\mod_datalynx\local\rule\base::MATCH_KEY] ?? null;
    if (!is_array($config) || empty($config['criteria'])) {
        continue;
    }

    $criteria = [];
    $seen = false;
    foreach ($config['criteria'] as $criterion) {
        if (($criterion['op'] ?? '') === 'within') {
            // The whole day: a rule is there to introduce two people who might travel together.
            $criterion = [
                'fieldid' => (int) ($fields[$schedulename]->id ?? 0),
                'op' => 'within',
                'tolerance' => \datalynxfield_schedule\field::TOLERANCE_WHOLE_DAY,
                'unit' => MINSECS,
            ];
            $seen = true;
        }
        $criteria[] = $criterion;
    }
    if (!$seen) {
        continue;
    }

    $report[] = "  update rule {$rule->id} ({$rule->name}): time criterion -> $schedulename";
    if (!$dryrun) {
        $config['criteria'] = $criteria;
        $param9[\mod_datalynx\local\rule\base::MATCH_KEY] = $config;
        $DB->set_field('datalynx_rules', 'param9', json_encode($param9), ['id' => $rule->id]);
    }
}

// The search form: one date beats three option pickers.
foreach ($DB->get_records('datalynx_customfilters', ['dataid' => $dataid]) as $customfilter) {
    $fieldlist = json_decode((string) $customfilter->fieldlist, true) ?: [];
    $new = [];
    $replaced = false;
    foreach ($fieldlist as $fieldid => $spec) {
        $name = $DB->get_field('datalynx_fields', 'name', ['id' => $fieldid], IGNORE_MISSING);
        if (in_array($name, [$oldnames['pattern'], $oldnames['weekday'], $oldnames['time']], true)) {
            $replaced = true;
            continue;
        }
        $new[$fieldid] = $spec;
    }
    if ($replaced && !empty($fields[$schedulename])) {
        $new[(int) $fields[$schedulename]->id] = ['name' => $schedulename, 'sortable' => 1];
    }
    if ($replaced) {
        $report[] = "  update search form {$customfilter->id}: {$customfilter->name}";
        if (!$dryrun) {
            $DB->set_field('datalynx_customfilters', 'fieldlist', json_encode($new), ['id' => $customfilter->id]);
        }
    }
}

// The old fields, once their schedules look right.
if (!empty($options['dropoldfields'])) {
    foreach (['pattern', 'weekday', 'time', 'arrival'] as $key) {
        if (empty($fields[$oldnames[$key]])) {
            continue;
        }
        $report[] = "  DELETE field: {$oldnames[$key]} (and its content)";
        if (!$dryrun) {
            $dlx->get_field_from_id((int) $fields[$oldnames[$key]]->id)->delete_field();
        }
    }
} else {
    $report[] = '  old fields kept; re-run with --dropoldfields once the schedules look right';
}

cli_writeln('');
foreach ($report as $line) {
    cli_writeln($line);
}

if (!$dryrun) {
    purge_all_caches();
    cli_writeln('');
    cli_writeln('Done.');
} else {
    cli_writeln('');
    cli_writeln('Dry run finished - nothing was written.');
}
