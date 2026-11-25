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
 * Describes the vehicle of a ride offer by brand and model.
 *
 * The vehicle step asked for a kind of car (compact, estate, …), a free-text name and the
 * drive. It now asks for the brand, picked with an autocomplete from the car brands sold in
 * Germany, and the model; the drive is no longer asked, and the free seats start at 0.
 *
 * - "Fahrzeugart" becomes "Marke" (a select with autocomplete). Each entry's brand is read off its
 *   old free text ("VW Polo" is a Volkswagen), the kind of car is dropped.
 * - "Fahrzeug" becomes "Modell" and keeps the rest of that text ("Polo").
 * - "Kraftstoff" ("Antrieb") is deleted with its content and its field layout.
 * - "Freie Plätze" offers 0 to 8; stored choices are moved so that every entry keeps its number.
 *
 * Views and filters follow the renamed fields; the field layouts are renamed and relabelled.
 * Only for activities built before: create_ridesharing_instance.php and the scripts after it
 * build the vehicle step this way already. For an older activity, run it before
 * update_ridesharing_pathways.php, which expects the new fields. Idempotent: what is done
 * already is left alone.
 *
 * Usage:
 *   php mod/datalynx/cli/update_ridesharing_vehicle.php --cmid=1162 --dryrun
 *   php mod/datalynx/cli/update_ridesharing_vehicle.php --cmid=1162
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_datalynx\local\definition_cache;
use mod_datalynx\local\ride\vehicle;

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'cmid' => 0,
    'dataid' => 0,
    'dryrun' => false,
], [
    'h' => 'help',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || (empty($options['cmid']) && empty($options['dataid']))) {
    cli_writeln("Describe the vehicle of a ride offer by brand and model.

Options:
  -h, --help          Print this help.
      --cmid=ID       Course module id of the activity.
      --dataid=ID     Datalynx instance id, as an alternative to --cmid.
      --dryrun        Report what would change without writing anything.

Example:
  php mod/datalynx/cli/update_ridesharing_vehicle.php --cmid=1162 --dryrun
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

$fieldid = function (string $name) use ($DB, $dataid): int {
    return (int) $DB->get_field('datalynx_fields', 'id', ['dataid' => $dataid, 'name' => $name]);
};
$contents = function (int $fieldid) use ($DB): array {
    return $fieldid ? $DB->get_records_menu('datalynx_contents', ['fieldid' => $fieldid], 'entryid', 'entryid, content') : [];
};
// The rows of a field layout, by name.
$layout = function (string $name) use ($DB, $dataid) {
    return $DB->get_record('datalynx_renderers', ['dataid' => $dataid, 'name' => $name]);
};
$changed = false;

// The brand, read off the old free text, and the model: the rest of it. Read before any rename.
$brandid = $fieldid('Marke') ?: $fieldid('Fahrzeugart');
$modelid = $fieldid('Modell') ?: $fieldid('Fahrzeug');
if (!$brandid || !$modelid) {
    cli_error('Cannot find the fields "Fahrzeugart"/"Marke" and "Fahrzeug"/"Modell".');
}
$brandoptions = implode("\n", vehicle::BRANDS);
$brandfield = $DB->get_record('datalynx_fields', ['id' => $brandid], '*', MUST_EXIST);
if ($brandfield->name !== 'Marke' || $brandfield->param1 !== $brandoptions || empty($brandfield->param6)) {
    $brands = [];
    $models = [];
    foreach ($contents($modelid) as $entryid => $text) {
        [$brand, $model] = vehicle::split((string) $text);
        if ($brand !== null) {
            $brands[$entryid] = array_search($brand, vehicle::BRANDS) + 1;
            $models[$entryid] = $model;
        }
        cli_writeln(sprintf(
            '  entry %d: "%s" -> Marke %s, Modell "%s"',
            $entryid,
            $text,
            $brand ?? '(leer)',
            $brand !== null ? $model : $text
        ));
    }
    $changed = true;
    if (!$dryrun) {
        $field = $dlx->get_field_from_id($brandid, true);
        $field->field->name = 'Marke';
        $field->field->param1 = $brandoptions;
        $field->field->param6 = 1;
        // The kinds of car stored so far mean nothing as brands.
        $DB->delete_records('datalynx_contents', ['fieldid' => $brandid]);
        $field->update_field();
        foreach ($brands as $entryid => $option) {
            $DB->insert_record('datalynx_contents', (object) ['fieldid' => $brandid, 'entryid' => $entryid,
                'content' => (string) $option]);
        }
        foreach ($models as $entryid => $model) {
            // A text that was only a brand leaves no model; a missing row is no value.
            if ($model === '') {
                $DB->delete_records('datalynx_contents', ['fieldid' => $modelid, 'entryid' => $entryid]);
            } else {
                $DB->set_field('datalynx_contents', 'content', $model, ['fieldid' => $modelid, 'entryid' => $entryid]);
            }
        }
    }
    cli_writeln('Fahrzeugart -> Marke (' . count(vehicle::BRANDS) . ' brands, autocomplete).');
}

$modelfield = $DB->get_record('datalynx_fields', ['id' => $modelid], '*', MUST_EXIST);
if ($modelfield->name !== 'Modell') {
    $changed = true;
    if (!$dryrun) {
        $field = $dlx->get_field_from_id($modelid, true);
        $field->field->name = 'Modell';
        $field->update_field();
    }
    cli_writeln('Fahrzeug -> Modell.');
}

// The drive is no longer asked.
if ($driveid = $fieldid('Kraftstoff')) {
    $changed = true;
    if (!$dryrun) {
        $field = $dlx->get_field_from_id($driveid, true);
        $field->delete_field();
        $dlx->replace_field_in_views('Kraftstoff', '');
        $dlx->replace_field_in_filters('Kraftstoff', '');
    }
    cli_writeln('Kraftstoff deleted.');
}

// Free seats from 0. A stored choice is the position of its option: "3" was the third (3 seats)
// and is the fourth now.
$seatsid = $fieldid('Freie Plätze');
$seatoptions = implode("\n", range(0, 8));
if ($seatsid && $DB->get_field('datalynx_fields', 'param1', ['id' => $seatsid]) !== $seatoptions) {
    $changed = true;
    if (!$dryrun) {
        $field = $dlx->get_field_from_id($seatsid, true);
        $field->field->param1 = $seatoptions;
        $field->update_field();
        foreach ($contents($seatsid) as $entryid => $option) {
            if (is_numeric($option)) {
                $DB->set_field(
                    'datalynx_contents',
                    'content',
                    (string) ((int) $option + 1),
                    ['fieldid' => $seatsid, 'entryid' => $entryid]
                );
            }
        }
    }
    cli_writeln('Freie Plätze: 0 to 8.');
}

// Views and filters name the fields by their new names. Done apart from the renames, so that a
// run that renamed a field but not its tags is completed by the next one.
foreach (['Fahrzeugart' => 'Marke', 'Fahrzeug' => 'Modell'] as $oldname => $newname) {
    $tag = '/\[\[' . preg_quote($oldname, '/') . '[:|#\]]/u';
    $views = $DB->get_records('datalynx_views', ['dataid' => $dataid]);
    $inuse = array_filter($views, fn($view): bool => preg_match($tag, json_encode($view, JSON_UNESCAPED_UNICODE)) === 1);
    if (!$inuse) {
        continue;
    }
    $changed = true;
    if (!$dryrun) {
        $dlx->replace_field_in_views($oldname, $newname);
        $dlx->replace_field_in_filters($oldname, $newname);
    }
    cli_writeln("Views: [[$oldname]] -> [[$newname]] (" . count($inuse) . ' views).');
}

// The field layouts: new names and labels; the one of the drive goes.
$relabel = [
    'l_fahrzeugart' => ['l_marke', ['Art des Fahrzeugs' => 'Marke',
        '#input</div>' => '#input<div class="form-text">Tippen Sie die ersten Buchstaben, '
            . 'zum Beispiel „Volks“ für Volkswagen.</div></div>']],
    'l_fahrzeug' => ['l_modell', ['Bezeichnung' => 'Modell', 'Zum Beispiel: VW Polo' => 'Zum Beispiel: Polo']],
];
foreach ($relabel as $oldname => [$newname, $texts]) {
    if (!$record = $layout($oldname)) {
        continue;
    }
    $changed = true;
    if (!$dryrun) {
        $record->name = $newname;
        foreach (['displaytemplate', 'novaluetemplate', 'edittemplate', 'noteditabletemplate', 'notvisibletemplate'] as $column) {
            $record->$column = str_replace(array_keys($texts), array_values($texts), (string) $record->$column);
        }
        $DB->update_record('datalynx_renderers', $record);
        $dlx->replace_layout_in_views($oldname, $newname);
    }
    cli_writeln("Field layout $oldname -> $newname.");
}
if ($record = $layout('l_kraftstoff')) {
    $changed = true;
    if (!$dryrun) {
        $DB->delete_records('datalynx_renderers', ['id' => $record->id]);
        $dlx->replace_layout_in_views('l_kraftstoff', '');
    }
    cli_writeln('Field layout l_kraftstoff deleted.');
}

if (!$dryrun) {
    definition_cache::invalidate($dataid);
}
cli_writeln($changed ? ($dryrun ? 'Would update the vehicle step.' : 'Vehicle step updated.') : 'Nothing to do.');
