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
// CLI: verify (1) portal links resolve, (2) email bodies resolve [[field]] tags via the real
// notification render path, (3) wizard fields are wired to label-renderers + behaviors.

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
global $DB, $CFG, $PAGE, $USER;

$cm = $DB->get_record_sql("SELECT cm.* FROM {course_modules} cm
    JOIN {modules} m ON m.id=cm.module AND m.name='datalynx'
    JOIN {course} c ON c.id=cm.course AND c.shortname='DOCSCHOOL' ORDER BY cm.id DESC LIMIT 1");
if (!$cm) {
    cli_error('no DOCSCHOOL datalynx cm');
}
\core\session\manager::set_user(get_admin());
$PAGE->set_url('/mod/datalynx/view.php', ['id' => $cm->id]);
$PAGE->set_context(context_module::instance($cm->id));
$PAGE->set_course($DB->get_record('course', ['id' => $cm->course]));
$PAGE->set_pagelayout('incourse');
$dlx = new \mod_datalynx\datalynx($cm->instance, $cm->id);
$iid = $cm->instance;
$vid = function ($name) use ($DB, $iid) {
    return (int) $DB->get_field('datalynx_views', 'id', ['dataid' => $iid, 'name' => $name]);
};
$ok = true;

// --- 1. Portal links resolve ---
$portal = $dlx->get_current_view_from_id($DB->get_field('datalynx', 'defaultview', ['id' => $iid]));
$portal->set_content();
$pout = $portal->display(['tohtml' => true]);
$raw = substr_count($pout, '##viewlink') + substr_count($pout, '##viewsesslink');
$links = preg_match_all('/href="[^"]*view\.php[^"]*"/', $pout, $mm);
$p = ($raw === 0 && $links >= 4);
$ok = $ok && $p;
echo "1. PORTAL: unresolved tags=$raw resolved links=$links -> " . ($p ? "OK" : "FAIL") . "\n";

// --- 2. Email body resolves [[field]] via the real email render path ---
$eid = $DB->insert_record('datalynx_entries', (object) ['dataid' => $iid, 'userid' => $USER->id,
    'groupid' => 0, 'timecreated' => time(), 'timemodified' => time(), 'status' => 1, 'approved' => 1]);
foreach (['antragstyp' => '1', 'sektion' => '5', 'pd_name' => 'Erika Mustermann', 'reise_ziel' => 'Berlin'] as $n => $c) {
    $DB->insert_record('datalynx_contents', (object) ['entryid' => $eid,
        'fieldid' => (int) $DB->get_field('datalynx_fields', 'id', ['dataid' => $iid, 'name' => $n]), 'content' => $c]);
}
$manager = new \mod_datalynx\local\view\manager\email_view_manager();
$payload = $manager->get_entry_payload(
    $iid,
    $vid('email_n1'),
    $eid,
    ['notificationentryurl' => 'http://x/', 'notificationentrylink' => 'L', 'notificationdatalynxurl' => 'http://x', 'notificationdatalynxlink' => 'D']
);
$e = strpos(json_encode($payload), 'Erika Mustermann') !== false;
$ok = $ok && $e;
echo "2. EMAIL body: [[pd_name]] resolved=" . ($e ? 'y' : 'n') . " -> " . ($e ? "OK" : "FAIL") . "\n";
$DB->delete_records('datalynx_contents', ['entryid' => $eid]);
$DB->delete_records('datalynx_entries', ['id' => $eid]);

// --- 3. Wizard fields wired to label-renderers + behaviors (config check) ---
$s3 = $DB->get_field('datalynx_views', 'param2', ['dataid' => $iid, 'name' => 'Schritt 3 – Antragsdetails']);
$bind = strpos($s3, '[[reise_ziel|b_reise|r_reise_ziel]]') !== false;
$rnd = $DB->get_record('datalynx_renderers', ['dataid' => $iid, 'name' => 'r_reise_ziel']);
$rndok = $rnd && strpos($rnd->edittemplate, 'Ziel') !== false && strpos($rnd->edittemplate, '#input') !== false
    && $rnd->notvisibletemplate === '___0___';
$nrend = $DB->count_records('datalynx_renderers', ['dataid' => $iid]);
$w = $bind && $rndok;
$ok = $ok && $w;
echo "3. WIZARD wiring: template binds [[field|behavior|renderer]]=" . ($bind ? 'y' : 'n')
    . " renderer(label+#input,hide-when-invisible)=" . ($rndok ? 'y' : 'n') . " renderers=$nrend -> " . ($w ? "OK" : "FAIL") . "\n";

echo ($ok ? "ALL CHECKS PASS ✓\n" : "SOME CHECKS FAILED ✗\n");
