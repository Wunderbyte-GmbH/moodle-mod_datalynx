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
 * Splits the weekly offers of a ride sharing board into single rides, once.
 *
 * A board with a "split a weekly schedule" rule (mod_datalynx#342) splits an offer when it is
 * published. Offers published before the rule existed are split by this script, through the same
 * rule. Idempotent: a single ride is not split again.
 *
 * Usage:
 *   php mod/datalynx/cli/split_ridesharing_offers.php --cmid=1162 --dryrun
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use mod_datalynx\local\ride\schedule;

[$options, $unrecognised] = cli_get_params(['help' => false, 'cmid' => 0, 'dryrun' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}
if ($options['help'] || empty($options['cmid'])) {
    cli_writeln("Split the weekly offers of a ride sharing board into single rides.

Options:
  -h, --help      Print this help.
      --cmid=ID   Course module id of the board.
      --dryrun    Only report what would be split.
");
    exit(0);
}

\core\session\manager::set_user(get_admin());
$cm = $DB->get_record('course_modules', ['id' => (int) $options['cmid']], '*', MUST_EXIST);
$dlx = new \mod_datalynx\datalynx((int) $cm->instance);
$rules = $DB->get_records('datalynx_rules', ['dataid' => $dlx->id(), 'type' => 'splitschedule', 'enabled' => 1]);
if (!$rules) {
    cli_error('The board has no "split a weekly schedule" rule.');
}
foreach ($rules as $record) {
    $rule = new \datalynxrule_splitschedule\rule($dlx, $record);
    $fieldid = (int) $record->param2;
    $split = 0;
    foreach ($DB->get_records_menu('datalynx_contents', ['fieldid' => $fieldid], '', 'entryid, content') as $entryid => $json) {
        if (schedule::from_json($json)->mode() !== schedule::MODE_WEEKLY) {
            continue;
        }
        // Only the entries the rule would act on (e.g. published offers).
        if (!$rule->applies_to((int) $entryid)) {
            continue;
        }
        cli_writeln("  entry $entryid: weekly, " . count(\datalynxrule_splitschedule\rule::dates(
            schedule::from_json($json),
            (int) $record->param3 ?: 6
        )) . ' dates');
        if (empty($options['dryrun']) && $rule->split((int) $entryid)) {
            $split++;
        }
    }
    cli_writeln("Rule {$record->name}: " . (empty($options['dryrun']) ? "split $split offers." : 'dry run.'));
}
