<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// CLI: restore the exported .mbz into a fresh course and assert the datalynx config reproduces.
// docker exec moodle-datalynx-webserver-1 php /var/www/html/mod/datalynx/cli/docschool_restore_test.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

global $DB, $USER, $CFG;
$USER = get_admin();
function out($m) {
    echo $m . "\n";
}

$mbz = $CFG->dirroot . '/dist/docschool-foerderantraege.mbz';
if (!file_exists($mbz)) {
    cli_error("Missing $mbz");
}

// Fresh target course.
$shortname = 'DOCSCHOOL_IMPORT';
if ($old = $DB->get_record('course', ['shortname' => $shortname])) {
    delete_course($old->id, false);
}
$course = create_course((object) [
    'fullname' => 'DocSchool Import-Test', 'shortname' => $shortname,
    'category' => 1, 'format' => 'topics', 'numsections' => 1, 'visible' => 1,
]);
out("Fresh course id {$course->id}");

// Extract and restore the activity into the fresh course.
$tmpname = 'docschool_restore_' . time();
$tmpdir = make_backup_temp_directory($tmpname);
$fp = get_file_packer('application/vnd.moodle.backup');
$fp->extract_to_pathname($mbz, $tmpdir);

$rc = new restore_controller(
    $tmpname,
    $course->id,
    backup::INTERACTIVE_NO,
    backup::MODE_GENERAL,
    $USER->id,
    backup::TARGET_CURRENT_ADDING
);
if (!$rc->execute_precheck()) {
    $pr = $rc->get_precheck_results();
    if (!empty($pr['errors'])) {
        out('PRECHECK ERRORS: ' . json_encode($pr['errors']));
    }
}
$rc->execute_plan();
$rc->destroy();
out('Restore executed.');

// Find the restored datalynx instance in the new course.
$cms = get_coursemodules_in_course('datalynx', $course->id);
$cm = reset($cms);
if (!$cm) {
    cli_error('No datalynx restored!');
}
$newid = $cm->instance;
out("Restored datalynx instance {$newid}");

// Assert config counts + option integrity.
$counts = [
    'fields'    => $DB->count_records('datalynx_fields', ['dataid' => $newid]),
    'views'     => $DB->count_records('datalynx_views', ['dataid' => $newid]),
    'rules'     => $DB->count_records('datalynx_rules', ['dataid' => $newid]),
    'behaviors' => $DB->count_records('datalynx_behaviors', ['dataid' => $newid]),
    'filters'   => $DB->count_records('datalynx_filters', ['dataid' => $newid]),
];
out('Restored counts: ' . json_encode($counts));

$antrag = $DB->get_record('datalynx_fields', ['dataid' => $newid, 'name' => 'antragstyp']);
$sektion = $DB->get_record('datalynx_fields', ['dataid' => $newid, 'name' => 'sektion']);
$rule = $DB->get_record('datalynx_rules', ['dataid' => $newid, 'name' => 'auto_sektionsleitung']);
out('antragstyp options: ' . count(explode("\n", $antrag->param1)) . ' (expect 7)');
out('sektion options: ' . count(explode("\n", $sektion->param1)) . ' (expect 12)');
out('teammemberbyprofile rule param7(profilefield)=' . ($rule->param7 ?? 'MISSING') . ' (expect sektion)');

// Reference-integrity checks: every restored id-reference must point inside this instance.
$fieldids = $DB->get_records_menu('datalynx_fields', ['dataid' => $newid], '', 'id, name');
$team = $DB->get_record('datalynx_fields', ['dataid' => $newid, 'name' => 'sektionsleitung_team']);
$reise = $DB->get_record('datalynx_behaviors', ['dataid' => $newid, 'name' => 'b_reise']);
$cond = json_decode($reise->conditions ?? '', true);
$condfid = $cond['rules'][0]['sourcefieldid'] ?? 0;
$rule_p6_ok = isset($fieldids[$rule->param6]) && $fieldids[$rule->param6] === 'sektion';
$rule_p8_ok = isset($fieldids[$rule->param8]) && $fieldids[$rule->param8] === 'sektionsleitung_team';
$cond_ok = isset($fieldids[$condfid]) && $fieldids[$condfid] === 'antragstyp';
out('rule param6 -> ' . ($fieldids[$rule->param6] ?? 'STALE') . ' (expect sektion)');
out('rule param8 -> ' . ($fieldids[$rule->param8] ?? 'STALE') . ' (expect sektionsleitung_team)');
out('b_reise condition -> ' . ($fieldids[$condfid] ?? 'STALE/EMPTY') . ' (expect antragstyp)');

// Renderers, email templates, portal default, and email param8 remapping.
$nrenderers = $DB->count_records('datalynx_renderers', ['dataid' => $newid]);
$nemail = $DB->count_records('datalynx_views', ['dataid' => $newid, 'type' => 'email']);
$viewnames = $DB->get_records_menu('datalynx_views', ['dataid' => $newid], '', 'id, name');
$portal = $DB->get_record('datalynx_views', ['dataid' => $newid, 'name' => 'Antragsportal']);
$isdefault = $portal && (int) $DB->get_field('datalynx', 'defaultview', ['id' => $newid]) === (int) $portal->id;
$n1 = $DB->get_record('datalynx_rules', ['dataid' => $newid, 'name' => 'n1_eingereicht']);
$p8ok = $n1 && isset($viewnames[$n1->param8]) && $viewnames[$n1->param8] === 'email_n1';
out('renderers=' . $nrenderers . ' (expect 39); email views=' . $nemail . ' (expect 9)');
out('portal is default: ' . ($isdefault ? 'yes' : 'NO'));
out('n1 param8 -> ' . ($viewnames[$n1->param8] ?? 'STALE') . ' (expect email_n1)');

$ok = $counts['fields'] == 39 && $counts['views'] == 21 && $counts['rules'] == 10
    && $counts['behaviors'] == 9 && $counts['filters'] == 4
    && count(explode("\n", $antrag->param1)) == 7
    && count(explode("\n", $sektion->param1)) == 12
    && ($rule->param7 ?? '') === 'sektion'
    && $rule_p6_ok && $rule_p8_ok && $cond_ok
    && $nrenderers == 39 && $nemail == 9 && $isdefault && $p8ok;
out($ok ? 'ROUND-TRIP OK ✓ (structure + options + references + renderers + emails + portal)' : 'ROUND-TRIP MISMATCH ✗');
