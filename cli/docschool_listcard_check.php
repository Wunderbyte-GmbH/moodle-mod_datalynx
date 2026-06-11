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
// CLI: verify the new list-card views (Meine Anträge, Begutachtung) and the new field/filter.
// (1) "Meine Anträge" card edit button resolves to the Schritt-1 view carrying this entry id.
// (2) "Begutachtung" card button resolves to the "Antrag prüfen" view carrying this entry id.
// (3) "Begutachtung" is a grid view filtered by teammemberselect USER on sektionsleitung_team.
// (4) New gutachter_team teammemberselect admits teachers + non-editing teachers.
// (5) "Schritt 1" carries ##addnewentry## so a new application can be started.
// (6) The list-card entry templates bind the label-renderers (so empty fields auto-hide).
// docker exec moodle-datalynx-webserver-1 php /var/www/html/mod/datalynx/cli/docschool_listcard_check.php

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
$fid = function ($n) use ($DB, $iid) {
    return (int) $DB->get_field('datalynx_fields', 'id', ['dataid' => $iid, 'name' => $n]);
};
$vrec = function ($n) use ($DB, $iid) {
    return $DB->get_record('datalynx_views', ['dataid' => $iid, 'name' => $n]);
};
$ok = true;

// A synthetic entry id used to confirm the per-entry edit link carries the right entry.
$EID = 4242;
$entry = (object) ['id' => $EID, 'userid' => $USER->id, 'dataid' => $iid, 'groupid' => 0];

// Resolve the (single) ##viewsesslink## found in a view's entry template against $entry.
// Use datalynx's own tag scanner (search) to extract the tag exactly as the renderer would —
// its regex tolerates the nested ##entryid## inside the url-query segment.
$resolve_link = function ($viewname) use ($dlx, $entry, $vrec) {
    $v = $vrec($viewname);
    $view = $dlx->get_current_view_from_id((int) $v->id);
    $view->set_filter();
    $tag = '';
    foreach ($view->patternclass()->search($v->param2, false) as $t) {
        if (strpos($t, '##viewsesslink:') === 0) {
            $tag = $t;
            break;
        }
    }
    $reps = $view->patternclass()->get_replacements([$tag], $entry, []);
    return [$tag, $reps[$tag] ?? ''];
};

// (1) Meine Anträge edit button -> Schritt 1 + editentries=<EID>.
$s1 = $vrec('Schritt 1 – Antragstyp und Sektion');
[$tag1, $link1] = $resolve_link('Meine Anträge');
$c1 = strpos($link1, "view={$s1->id}") !== false && strpos($link1, "editentries={$EID}") !== false
    && strpos($link1, '##') === false;
$ok = $ok && $c1;
echo "1. MEINE ANTRÄGE edit -> view={$s1->id}(Schritt1)=" . (strpos($link1, "view={$s1->id}") !== false ? 'y' : 'n')
    . " editentries={$EID}=" . (strpos($link1, "editentries={$EID}") !== false ? 'y' : 'n')
    . " -> " . ($c1 ? 'OK' : 'FAIL') . "\n";

// (2) Begutachtung button -> Antrag prüfen + editentries=<EID>.
$pruefen = $vrec('Antrag prüfen');
[$tag2, $link2] = $resolve_link('Begutachtung');
$c2 = strpos($link2, "view={$pruefen->id}") !== false && strpos($link2, "editentries={$EID}") !== false
    && strpos($link2, '##') === false;
$ok = $ok && $c2;
echo "2. BEGUTACHTUNG button -> view={$pruefen->id}(Antrag prüfen)=" . (strpos($link2, "view={$pruefen->id}") !== false ? 'y' : 'n')
    . " editentries={$EID}=" . (strpos($link2, "editentries={$EID}") !== false ? 'y' : 'n')
    . " -> " . ($c2 ? 'OK' : 'FAIL') . "\n";

// (3) Begutachtung = grid + teammemberselect USER filter on sektionsleitung_team.
$bview = $vrec('Begutachtung');
$filter = $DB->get_record('datalynx_filters', ['id' => $bview->filter]);
$cs = $filter ? unserialize($filter->customsearch) : [];
$op = $cs[$fid('sektionsleitung_team')]['AND'][0][1] ?? '';
$c3 = $bview->type === 'grid' && $op === 'USER';
$ok = $ok && $c3;
echo "3. BEGUTACHTUNG type={$bview->type} filter op on sektionsleitung_team='{$op}' -> " . ($c3 ? 'OK' : 'FAIL') . "\n";

// (4) gutachter_team admits teachers (PERMISSION_TEACHER=2).
$g = $DB->get_record('datalynx_fields', ['dataid' => $iid, 'name' => 'gutachter_team']);
$roles = $g ? json_decode($g->param2, true) : [];
$c4 = $g && $g->type === 'teammemberselect' && is_array($roles)
    && in_array(\mod_datalynx\datalynx::PERMISSION_TEACHER, $roles);
$ok = $ok && $c4;
echo "4. GUTACHTER_TEAM type=" . ($g->type ?? '?') . " admissibleroles=" . json_encode($roles) . " -> " . ($c4 ? 'OK' : 'FAIL') . "\n";

// (5) Schritt 1 section carries ##addnewentry##.
$c5 = strpos($s1->section, '##addnewentry##') !== false;
$ok = $ok && $c5;
echo "5. SCHRITT 1 has ##addnewentry## -> " . ($c5 ? 'OK' : 'FAIL') . "\n";

// (6) List-card templates bind the label-renderers (novaluetemplate '___0___' hides empty fields).
$mine = $vrec('Meine Anträge');
$bindsrenderer = strpos($mine->param2, '|r_reise_ziel]]') !== false && strpos($bview->param2, '|r_kost]]') !== false;
$rnd = $DB->get_record('datalynx_renderers', ['dataid' => $iid, 'name' => 'r_reise_ziel']);
$hidesempty = $rnd && $rnd->novaluetemplate === '___0___';
$c6 = $bindsrenderer && $hidesempty;
$ok = $ok && $c6;
echo "6. LIST CARDS bind renderers=" . ($bindsrenderer ? 'y' : 'n') . " hide-empty(novaluetemplate=___0___)="
    . ($hidesempty ? 'y' : 'n') . " -> " . ($c6 ? 'OK' : 'FAIL') . "\n";

echo ($ok ? "ALL LIST-CARD CHECKS PASS ✓\n" : "SOME CHECKS FAILED ✗\n");
