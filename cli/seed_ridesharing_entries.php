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
 * Adds example rides to a ride sharing board built by create_ridesharing_instance.php.
 *
 * The rides are defined in \mod_datalynx\local\ride\board_seeder, which
 * `create_ridesharing_instance.php --seed` uses as well.
 *
 * Usage:
 *   php mod/datalynx/cli/seed_ridesharing_entries.php --dataid=123 --clear
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'dataid' => 0,
    'clear' => false,
    'nomatch' => false,
], [
    'h' => 'help',
    'd' => 'dataid',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || empty($options['dataid'])) {
    cli_writeln("Seed a ride sharing datalynx activity with example rides.

Options:
  -h, --help          Print this help.
  -d, --dataid=ID     Datalynx instance id (required).
      --clear         Delete all existing entries first.
      --nomatch       Only create entries, do not run the matching sweep.

Example:
  php mod/datalynx/cli/seed_ridesharing_entries.php --dataid=123 --clear
");
    exit(empty($options['dataid']) ? 1 : 0);
}

\core\session\manager::set_user(get_admin());

$dataid = (int) $options['dataid'];
$fieldnames = $DB->get_fieldset_select('datalynx_fields', 'name', 'dataid = ?', [$dataid]);
foreach (['Fahrttyp', 'Route', 'Termin'] as $required) {
    if (!in_array($required, $fieldnames, true)) {
        cli_error("Instance $dataid has no field '$required' - is this a ride sharing board built by "
            . 'create_ridesharing_instance.php?');
    }
}

if (!empty($options['clear'])) {
    $dlx = new \mod_datalynx\datalynx($dataid);
    $entryids = $DB->get_fieldset_select('datalynx_entries', 'id', 'dataid = ?', [$dataid]);
    if ($entryids) {
        $entries = new \mod_datalynx\local\datalynx_entries($dlx);
        $entries->interactive = false;
        $entries->process_entries('delete', $entryids, null, true);
        cli_writeln('Deleted ' . count($entryids) . ' existing entries.');
    }
}

$seeder = new \mod_datalynx\local\ride\board_seeder($dataid, fn(string $line) => cli_writeln($line));
$created = $seeder->seed(empty($options['nomatch']));
cli_writeln('Created ' . count($created['offers']) . ' offers and ' . count($created['requests']) . ' requests.');
