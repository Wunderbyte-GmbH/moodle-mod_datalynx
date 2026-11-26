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
 * Builds a complete ride sharing board (Mitfahrbörse) in a course.
 *
 * The board is defined in \mod_datalynx\local\ride\board_builder, in two variants: `basic` and
 * `fahrtenschein` (with the billing extension). The board can also be exported as a datalynx
 * preset or installed in the site presets, so that it can be applied without shell access.
 *
 * Usage:
 *   php mod/datalynx/cli/create_ridesharing_instance.php --course=47 --variant=fahrtenschein --seed
 *   php mod/datalynx/cli/create_ridesharing_instance.php --course=47 --reset
 *   php mod/datalynx/cli/create_ridesharing_instance.php --course=47 --install-site-preset
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/datalynx/preset/preset_manager.php');

use mod_datalynx\local\ride\board_builder;
use mod_datalynx\local\ride\board_seeder;

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'course' => 0,
    'variant' => board_builder::VARIANT_BASIC,
    'name' => '',
    'reset' => false,
    'seed' => false,
    'export-preset' => '',
    'install-site-preset' => false,
], [
    'h' => 'help',
    'c' => 'course',
    'n' => 'name',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

$variants = board_builder::variants();
if ($options['help'] || empty($options['course']) || !isset($variants[$options['variant']])) {
    cli_writeln("Build a ride sharing board (Mitfahrbörse) in a course.

Options:
  -h, --help                Print this help.
  -c, --course=ID           Course id to build the board in (required).
      --variant=VARIANT     basic (default) or fahrtenschein (with the billing extension).
  -n, --name=NAME           Activity name. Default: the name of the variant
                            (" . implode(', ', $variants) . ").
      --reset               Delete an existing activity of that name in the course first.
      --seed                Add example rides; needs at least four enrolled users.
      --export-preset=FILE  Also write the board as a datalynx preset (.mbz, without user data).
      --install-site-preset Also store the board in the site presets, replacing one of the same name.

Example:
  php mod/datalynx/cli/create_ridesharing_instance.php --course=47 --variant=fahrtenschein --seed
");
    exit($options['help'] ? 0 : 1);
}

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['id' => (int) $options['course']], '*', MUST_EXIST);
$name = (string) ($options['name'] ?: $variants[$options['variant']]);

// A rebuild removes the old activity completely: fields, views and entries all hang off the
// instance id, so reusing it half way would leave orphans behind.
if (board_builder::find_existing($course->id, $name)) {
    if (empty($options['reset'])) {
        cli_error("An activity named '$name' already exists in course {$course->id}. Use --reset to replace it.");
    }
    foreach (board_builder::remove_existing($course->id, $name) as $cmid) {
        cli_writeln("Removed existing activity (cmid $cmid).");
    }
}

$builder = new board_builder($course, $options['variant'], $name, fn(string $line) => cli_writeln($line));
$cmid = $builder->build();

if (!empty($options['seed'])) {
    $seeder = new board_seeder($builder->get_dataid(), fn(string $line) => cli_writeln($line));
    $seeder->seed();
}

if ($options['export-preset'] !== '' || !empty($options['install-site-preset'])) {
    $dlx = new \mod_datalynx\datalynx($builder->get_dataid());
    $manager = new datalynx_preset_manager($dlx);
    if (!$manager->create_preset_from_backup('nodata')) {
        cli_error('Could not create the preset.');
    }
    // The preset manager stores the newest preset in the course presets; take it from there.
    $fs = get_file_storage();
    $coursecontext = context_course::instance($course->id);
    $files = $fs->get_area_files(
        $coursecontext->id,
        'mod_datalynx',
        datalynx_preset_manager::PRESET_COURSEAREA,
        false,
        'id DESC',
        false
    );
    $preset = reset($files);
    if ($options['export-preset'] !== '') {
        $preset->copy_content_to($options['export-preset']);
        cli_writeln("Wrote the preset to {$options['export-preset']}.");
    }
    if (!empty($options['install-site-preset'])) {
        $filename = clean_filename(str_replace(' ', '_', $name)) . '.mbz';
        $record = [
            'contextid' => datalynx_preset_manager::PRESET_SITECONTEXT,
            'component' => 'mod_datalynx',
            'filearea' => datalynx_preset_manager::PRESET_SITEAREA,
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ];
        if ($old = $fs->get_file(...array_values($record))) {
            $old->delete();
        }
        $fs->create_file_from_storedfile($record, $preset);
        cli_writeln("Installed the site preset $filename.");
    }
    $preset->delete();
}

purge_all_caches();

cli_writeln('');
cli_writeln('Done. Open: ' . (new moodle_url('/mod/datalynx/view.php', ['id' => $cmid]))->out(false));
cli_writeln('Instance id: ' . $builder->get_dataid());
