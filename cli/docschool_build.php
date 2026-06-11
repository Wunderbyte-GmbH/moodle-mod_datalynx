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
// CLI: build the DocSchool Förderantrags-Workflow datalynx instance and export it as a .mbz preset.
// Run inside the moodle-docker webserver container:
// docker exec moodle-datalynx-webserver-1 php /var/www/html/mod/datalynx/cli/docschool_build.php

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

global $DB, $USER, $CFG;
$USER = get_admin();

function out($m) {
    echo $m . "\n";
}

// ---------------------------------------------------------------------------
// 0. Clean previous build (idempotent).
// ---------------------------------------------------------------------------
$shortname = 'DOCSCHOOL';
if ($old = $DB->get_record('course', ['shortname' => $shortname])) {
    out("Deleting previous course id {$old->id} ...");
    delete_course($old->id, false);
    rebuild_course_cache($old->id, true);
}

// ---------------------------------------------------------------------------
// 1. Course.
// ---------------------------------------------------------------------------
$course = create_course((object) [
    'fullname'  => 'DocSchool Förderanträge',
    'shortname' => $shortname,
    'category'  => 1,
    'format'    => 'topics',
    'numsections' => 1,
    'visible'   => 1,
]);
out("Course id {$course->id}");

// ---------------------------------------------------------------------------
// 2. Datalynx activity instance.
// ---------------------------------------------------------------------------
$module = $DB->get_record('modules', ['name' => 'datalynx'], '*', MUST_EXIST);
$defaults = ['intro' => 'Förderanträge der DocSchool', 'introformat' => FORMAT_HTML, 'timemodified' => time(),
    'timeavailable' => 0, 'timedue' => 0, 'timeinterval' => 0, 'intervalcount' => 1,
    'allowlate' => 0, 'grade' => 0, 'grademethod' => 0, 'anonymous' => 0,
    'notification' => 0, 'notificationformat' => 1, 'entriesrequired' => 0,
    'entriestoview' => 0, 'maxentries' => -1, 'timelimit' => -1, 'approval' => 0,
    'grouped' => 0, 'rating' => 0, 'singleedit' => 0, 'singleview' => 0, 'rssarticles' => 0,
    'rss' => 0, 'css' => null, 'cssincludes' => null, 'js' => null, 'jsincludes' => null,
    'defaultview' => 0, 'defaultfilter' => 0, 'completionentries' => 0, 'assessed' => 0,
    'scale' => 0, 'ratingtime' => 0, 'assesstimestart' => 0, 'assesstimefinish' => 0];
$mi = (object) array_merge($defaults, [
    'modulename' => 'datalynx', 'module' => $module->id, 'course' => $course->id,
    'section' => 0, 'visible' => 1, 'name' => 'Förderanträge DocSchool',
    'cmidnumber' => '', 'groupmode' => 0, 'groupingid' => 0,
]);
$mi = add_moduleinfo($mi, $course);
$cmid = $mi->coursemodule;
$instanceid = $mi->instance;
out("Datalynx instance {$instanceid} (cmid {$cmid})");

$dlx = new \mod_datalynx\datalynx($instanceid, $cmid);

// ---------------------------------------------------------------------------
// 3. Fields.
// ---------------------------------------------------------------------------
$sektionopts = "Historische und Kulturwissenschaftliche Studien\nSozialwissenschaftliche Studien\n"
    . "Verhaltens- und Kognitionswissenschaften\nPhilologische und Kulturwissenschaftliche Studien\n"
    . "Wirtschaftswissenschaften\nRechtswissenschaften\nInformatik und Medientechnologie\n"
    . "Naturwissenschaften und Mathematik\nLebenswissenschaften und Medizin\n"
    . "Philosophie und Bildungswissenschaft\nKatholische und Evangelische Theologie\nKoordinationsbüro";
$antragstypopts = "Reisekostenzuschuss\nPublikationsförderung\nAbschlussstipendium\nVeranstaltungsförderung\n"
    . "Anschaffung von Forschungsmaterialien\nAuslandsaufenthalte\nSonstiges";
$statusopts = "In Überprüfung durch Koordination\nIn Überprüfung bei Sektionsleitung\nBewilligt\n"
    . "Bewilligt und noch nicht abgerechnet\nNicht bewilligt\nBelege wurden eingereicht\n"
    . "Belege in Überprüfung\nNachforderung von Belegen\nAn Finanzabteilung weitergeleitet\nAusbezahlt";

// type, name(ascii shortname), label(DE), param1..; returns id stored in $F.
$F = [];
$LBL = [];
$addf = function ($type, $name, $label, $params = []) use ($dlx, &$F, &$LBL) {
    $rec = (object) ['name' => $name, 'label' => $label, 'description' => ''];
    foreach ($params as $k => $v) {
        $rec->$k = $v;
    }
    $field = $dlx->get_field($type);
    $id = $field->insert_field($rec);
    $F[$name] = (int) $id;
    $LBL[$name] = $label;
    return $id;
};

// Sub-fields for the cost-table fieldgroup must exist first.
$addf('text', 'kost_pos', 'Beschreibung der Ausgabe');
$addf('number', 'kost_betrag', 'Kosten (€)');

$addf('select', 'antragstyp', 'Antragstyp', ['addoptions' => $antragstypopts]);
$addf('select', 'sektion', 'Sektion / Gremium', ['addoptions' => $sektionopts]);
$addf('text', 'pd_name', 'Name');
$addf('text', 'pd_email', 'E-Mail');
$addf('text', 'pd_telefon', 'Telefon');
$addf('radiobutton', 'bearbeitungsstatus', 'Bearbeitungsstatus', ['addoptions' => $statusopts]);
$addf('checkbox', 'eingereicht', 'Antrag verbindlich einreichen', ['addoptions' => 'Ja']);
$addf('teammemberselect', 'sektionsleitung_team', 'Zuständige Sektionsleitung', ['param1' => '5']);
// Reviewer pool the coordination/section lead can pick from manually: all (editing +
// non-editing) teachers. param2 = admissible datalynx permission ids; PERMISSION_TEACHER (2)
// maps to the viewprivilegeteacher capability, held by both teacher and editingteacher.
$addf('teammemberselect', 'gutachter_team', 'Gutachter/innen', [
    'param1' => '10', // Max team size.
    'param2' => json_encode([\mod_datalynx\datalynx::PERMISSION_TEACHER]), // Teachers + non-editing teachers.
    'param3' => '0', // Min team size.
]);
$addf('textarea', 'sl_begruendung', 'Begründung der Ablehnung');

// Cost-table fieldgroup (param1 = JSON array of sub-field ids; param2 max rows, param3 default, param4 required).
$addf('fieldgroup', 'kost', 'Kostenaufstellung', [
    'param1' => json_encode([$F['kost_pos'], $F['kost_betrag']]),
    'param2' => '10', 'param3' => '3', 'param4' => '1',
]);

// Antragstyp-specific.
$addf('text', 'reise_ziel', 'Ziel');
$addf('textarea', 'reise_zweck', 'Zweck');
$addf('text', 'reise_dauer', 'Dauer');
$addf('textarea', 'reise_relevanz', 'Relevanz für die Dissertation');
$addf('select', 'pub_art', 'Bewerbung für', ['addoptions' => "Professionelles Lektorat\nEigentumsrechte\nÜbersetzung\nDruckkosten\nAnderes"]);
$addf('text', 'pub_titel', 'Titel des Artikels');
$addf('text', 'pub_laenge', 'Länge');
$addf('text', 'pub_sprache', 'Sprache');
$addf('url', 'pub_journal', 'Link zum Journal');
$addf('number', 'abschluss_monate', 'Anzahl Monate (max. 6)');
$addf('file', 'abschluss_docs', 'Dokumente (PDF)', ['param2' => '5', 'param3' => 'application/pdf']);
$addf('text', 'ver_titelformat', 'Titel & Format der Veranstaltung');
$addf('number', 'ver_teilnehmer', 'Zahl der Teilnehmenden');
$addf('text', 'ver_zielgruppe', 'Zielgruppe');
$addf('text', 'ver_datumort', 'Datum & Ort');
$addf('textarea', 'ver_beschreibung', 'Kurzdarstellung & Relevanz');
$addf('select', 'mat_art', 'Bewerbung für', ['addoptions' => "Digitalisate\nReproduktionskosten\nMittel für Datenerhebungen\nAnderes"]);
$addf('textarea', 'mat_begruendung', 'Begründung der Notwendigkeit');
$addf('text', 'ausland_uni', 'Ausländische Universität + Kontaktperson');
$addf('text', 'ausland_zeitraum', 'Zeitraum des Aufenthalts');
$addf('file', 'ausland_docs', 'Dokumente (PDF)', ['param2' => '6', 'param3' => 'application/pdf']);
$addf('textarea', 'sonstiges_beschreibung', 'Beschreibung');

// Phase 2.
$addf('file', 'belege', 'Belege (PDF)', ['param2' => '20', 'param3' => 'application/pdf']);
$addf('textarea', 'kurzbericht', 'Kurzbericht (~½ Seite)');
$addf('textarea', 'nachforderung_text', 'Nachforderung');
$addf('file', 'nachforderung_docs', 'Zusätzliche Dokumente', ['param2' => '10', 'param3' => 'application/pdf']);

out('Fields created: ' . count($F));

// ---------------------------------------------------------------------------
// 4. Rules.
// ---------------------------------------------------------------------------
$addrule = function ($rec) use ($dlx, $DB) {
    $rec->dataid = $dlx->id();
    $rec->enabled = 1;
    if (!isset($rec->description)) {
        $rec->description = '';
    }
    return (int) $DB->insert_record('datalynx_rules', $rec);
};

// 4.1 Auto-assign Sektionsleitung from the Sektion select.
$addrule((object) [
    'type' => 'teammemberbyprofile', 'name' => 'auto_sektionsleitung',
    'param2' => 'overwrite', 'param6' => $F['sektion'], 'param7' => 'sektion',
    'param8' => $F['sektionsleitung_team'], 'param9' => 'teacher',
]);

// 4.2 Notifications. recipients: roles use datalynx permission ids (manager=1).
// NOTE: current datalynx stores rule param1 (triggers) and param3 (recipients) as JSON.
// param8 (email-template view id) is wired later, after the email views are created (§7c).
$R = [];
$notif = function ($name, $event, $condfield, $condval, $recipients, $subject) use ($addrule, &$R) {
    $R[$name] = $addrule((object) [
        'type' => 'eventnotification', 'name' => $name,
        'param1' => json_encode([$event]),
        'param2' => 1, // sender = current user
        'param3' => json_encode($recipients),
        'param5' => $condfield ?: 0, // condition field id (0 = none)
        'param6' => $subject,
        'param10' => $condval, // condition value (option index)
    ]);
};
$notif('n1_eingereicht', 'entry_updated', $F['eingereicht'], '1', ['roles' => [1]], 'Neuer Förderantrag eingereicht');
$notif('n2_zugewiesen', 'entry_updated', $F['bearbeitungsstatus'], '2', ['teams' => [$F['sektionsleitung_team']]], 'Antrag zur Begutachtung');
$notif('n3_bewilligt', 'entry_updated', $F['bearbeitungsstatus'], '3', ['author' => 1, 'roles' => [1]], 'Ihr Förderantrag wurde bewilligt');
$notif('n3b_abgelehnt', 'entry_updated', $F['bearbeitungsstatus'], '5', ['author' => 1, 'roles' => [1]], 'Entscheidung zu Ihrem Förderantrag');
$notif('n4_belege', 'entry_updated', $F['bearbeitungsstatus'], '6', ['roles' => [1]], 'Belege wurden eingereicht');
$notif('n5_nachforderung', 'entry_updated', $F['bearbeitungsstatus'], '8', ['author' => 1], 'Nachforderung von Belegen');
$notif('n6_weitergeleitet', 'entry_updated', $F['bearbeitungsstatus'], '9', ['author' => 1], 'Antrag an Finanzabteilung weitergeleitet');
$notif('n7_ausbezahlt', 'entry_updated', $F['bearbeitungsstatus'], '10', ['author' => 1], 'Auszahlung erfolgt');
$R['n_kommentar'] = $addrule((object) [
    'type' => 'eventnotification', 'name' => 'n_kommentar',
    'param1' => json_encode(['comment_created']), 'param2' => 1,
    'param3' => json_encode(['author' => 1]), 'param5' => 0, 'param6' => 'Neue Nachricht zu Ihrem Antrag',
]);
out('Rules created (1 auto-assign + 9 notifications).');

// ---------------------------------------------------------------------------
// 5. Behaviors (conditional logic definitions).
// ---------------------------------------------------------------------------
$addbehavior = function ($name, $conditions, $visibleperms, $editperms, $required = 0) use ($dlx, $DB) {
    return (int) $DB->insert_record('datalynx_behaviors', (object) [
        'dataid' => $dlx->id(), 'name' => $name, 'description' => '',
        'visibleto' => serialize(['permissions' => $visibleperms]),
        'editableby' => serialize($editperms),
        'required' => $required, 'editableafterfinal' => 0,
        'conditions' => json_encode($conditions),
    ]);
};
$cond = function ($srcfield, $value) {
    return ['match' => 'all', 'rules' => [['sourcefieldid' => $srcfield, 'not' => '', 'operator' => '=', 'value' => $value]]];
};
$all = [1, 2, 4];
$staff = [1, 2];
$addbehavior('b_reise', $cond($F['antragstyp'], '1'), $all, [4], 1);
$addbehavior('b_pub', $cond($F['antragstyp'], '2'), $all, [4], 0);
$addbehavior('b_abschluss', $cond($F['antragstyp'], '3'), $all, [4], 1);
$addbehavior('b_veranstaltung', $cond($F['antragstyp'], '4'), $all, [4], 0);
$addbehavior('b_material', $cond($F['antragstyp'], '5'), $all, [4], 0);
$addbehavior('b_ausland', $cond($F['antragstyp'], '6'), $all, [4], 1);
$addbehavior('b_sonstiges', $cond($F['antragstyp'], '7'), $all, [4], 1);
$addbehavior('b_staff_only', ['match' => 'all', 'rules' => []], $staff, $staff, 0);
$addbehavior('b_begruendung', $cond($F['bearbeitungsstatus'], '5'), $all, $staff, 1);
out('Behaviors created.');

// ---------------------------------------------------------------------------
// 6. Filters.
// ---------------------------------------------------------------------------
$addfilter = function ($name, $customsearch) use ($dlx, $DB) {
    return (int) $DB->insert_record('datalynx_filters', (object) [
        'dataid' => $dlx->id(), 'name' => $name, 'description' => '',
        'visible' => 1, 'perpage' => 20, 'selection' => 0, 'groupby' => '',
        'search' => '', 'customsort' => '', 'customsearch' => serialize($customsearch),
    ]);
};
// Own entries (author = ME) — entryauthor internal userid field.
$filter_own = $addfilter('own-entries', ['userid' => ['AND' => [['', 'ME', '']]]]);
// Reviewer: my section (select MY_PROFILE on the sektion profile field).
$filter_sl = $addfilter('my-section-review', [$F['sektion'] => ['AND' => [['', 'MY_PROFILE', 'sektion']]]]);
// Koordination inbox: bearbeitungsstatus = 1.
$filter_inbox = $addfilter('coord-inbox', [$F['bearbeitungsstatus'] => ['AND' => [['', '=', '1']]]]);
// Begutachtung: entries where the current user is a member of the assigned section-lead
// team (teammemberselect operator USER = "I am a team member").
$filter_begutachtung = $addfilter(
    'begutachtung-teammember',
    [$F['sektionsleitung_team'] => ['AND' => [['', 'USER', '']]]]
);
out('Filters created.');

// ---------------------------------------------------------------------------
// 7. Renderers — wrap each field with a BS5 label + help text. The whole block
// respects the field behavior: when a behavior hides the field, its label
// disappears too ('___0___' = NOT_VISIBLE_SHOW_NOTHING). #input = edit input,
// #value = display value.
// ---------------------------------------------------------------------------
$RND = [];
$help = [
    'antragstyp' => 'Wählen Sie die Art der Förderung. Davon hängt ab, welche Angaben im nächsten Schritt benötigt werden.',
    'sektion' => 'Ihre Sektion bestimmt, welche Sektionsleitung den Antrag begutachtet. „Koordinationsbüro" nur für Gremiumsanträge wählen.',
    'pd_name' => 'Vor- und Nachname, wie er für die Korrespondenz verwendet werden soll.',
    'pd_email' => 'An diese Adresse senden wir alle Benachrichtigungen zu Ihrem Antrag.',
    'pd_telefon' => 'Optional, für Rückfragen der Koordination.',
    'reise_ziel' => 'Reiseziel (Ort, Land) bzw. Veranstaltung, zu der Sie reisen.',
    'reise_zweck' => 'Beschreiben Sie Zweck und Nutzen der Reise für Ihr Dissertationsprojekt.',
    'reise_dauer' => 'Zeitraum bzw. Dauer der Reise (z. B. „3 Tage, 12.–14.09.2026").',
    'reise_relevanz' => 'Optional: Wichtigkeit/Relevanz der Reise für Ihre Dissertation.',
    'pub_art' => 'Wofür beantragen Sie die Publikationsförderung?',
    'pub_titel' => 'Titel des geplanten Artikels.',
    'pub_laenge' => 'Umfang des Artikels (z. B. Seiten oder Zeichen).',
    'pub_sprache' => 'Sprache der Publikation.',
    'pub_journal' => 'Link zum Journal bzw. Verlag (falls vorhanden).',
    'abschluss_monate' => 'Für wie viele Monate beantragen Sie das Abschlussstipendium? (max. 6)',
    'abschluss_docs' => 'Nur PDF: Teile der Dissertation, Referenzschreiben, Zeitplan.',
    'ver_titelformat' => 'Titel und Format der geplanten Veranstaltung (z. B. Workshop, Tagung).',
    'ver_teilnehmer' => 'Erwartete Zahl der Teilnehmenden.',
    'ver_zielgruppe' => 'An wen richtet sich die Veranstaltung?',
    'ver_datumort' => 'Datum und Ort der Veranstaltung.',
    'ver_beschreibung' => 'Kurze Darstellung der Veranstaltung und Relevanz für Ihr Forschungsinteresse.',
    'mat_art' => 'Wofür benötigen Sie die Forschungsmaterialien?',
    'mat_begruendung' => 'Begründen Sie die Notwendigkeit für Ihr Dissertationsprojekt.',
    'ausland_uni' => 'Name der ausländischen Universität und Kontaktperson.',
    'ausland_zeitraum' => 'Geplanter Zeitraum des Auslandsaufenthalts.',
    'ausland_docs' => 'Nur PDF: Motivationsschreiben, CV, Einladungsbrief, Referenzschreiben.',
    'sonstiges_beschreibung' => 'Beschreiben Sie Ihr Anliegen und den Förderbedarf.',
    'kost' => 'Tragen Sie die geplanten Ausgaben einzeln ein (Beschreibung + Kosten). Zeilen können hinzugefügt werden.',
    'belege' => 'Laden Sie Ihre Belege als PDF hoch. Mehrere Dateien möglich.',
    'kurzbericht' => 'Kurzer Bericht über die durchgeführte Aktivität (~½ Seite).',
    'nachforderung_docs' => 'Optional: zusätzlich angeforderte Dokumente nachreichen.',
    'bearbeitungsstatus' => 'Aktueller Status des Antrags im Workflow.',
    'sl_begruendung' => 'Bei Ablehnung: kurze Begründung für Antragsteller/in und Koordination.',
    'nachforderung_text' => 'Beschreiben Sie, welche Belege noch fehlen bzw. nachgefordert werden.',
    'sektionsleitung_team' => 'Automatisch anhand der gewählten Sektion zugeordnet.',
    'gutachter_team' => 'Wählen Sie eine oder mehrere begutachtende Personen aus dem Kreis der Lehrenden (inkl. nicht-bearbeitende Lehrende).',
    'eingereicht' => 'Mit dem Absenden wird der Antrag verbindlich eingereicht und kann nicht mehr bearbeitet werden.',
];
$addrenderer = function ($fieldname) use ($dlx, $DB, &$RND, $LBL, $help) {
    if (isset($RND[$fieldname])) {
        return $RND[$fieldname];
    }
    $label = $LBL[$fieldname] ?? $fieldname;
    $hint = $help[$fieldname] ?? '';
    $edit = '<div class="mb-4">'
        . '<label class="form-label fw-semibold mb-1">' . $label . '</label>'
        . ($hint ? '<div class="form-text mt-0 mb-2">' . $hint . '</div>' : '')
        . '<div>#input</div></div>';
    $disp = '<div class="d-flex mb-2 pb-2 border-bottom"><div class="text-secondary fw-semibold me-2" '
        . 'style="min-width:230px;">' . $label . '</div><div>#value</div></div>';
    $DB->insert_record('datalynx_renderers', (object) [
        'dataid' => $dlx->id(), 'type' => '', 'name' => 'r_' . $fieldname, 'description' => '',
        'notvisibletemplate' => '___0___', 'displaytemplate' => $disp,
        'novaluetemplate' => '___0___', 'edittemplate' => $edit, 'noteditabletemplate' => '___1___',
    ]);
    $RND[$fieldname] = 'r_' . $fieldname;
    return $RND[$fieldname];
};
foreach (array_keys($LBL) as $fn) {
    $addrenderer($fn);
}

// Field tag with label-renderer (and optional behavior): [[field|behavior|r_field]].
$f = function ($name, $behavior = '') use ($RND) {
    return '[[' . $name . '|' . $behavior . '|' . ($RND[$name] ?? '') . ']]';
};

// ---------------------------------------------------------------------------
// 7b. Wizard UI helpers (Bootstrap 5).
// ---------------------------------------------------------------------------
$cleansection = '<div class="container py-4" style="max-width:900px;">##entries##</div>';
$stepper = function ($current) {
    $labels = [1 => 'Antragsart', 2 => 'Persönliche Daten', 3 => 'Details', 4 => 'Absenden'];
    $pct = (int) round($current / 4 * 100);
    $badges = '';
    foreach ($labels as $n => $l) {
        $cls = $n < $current ? 'bg-success' : ($n == $current ? 'bg-primary' : 'bg-secondary opacity-50');
        $badges .= '<span class="badge rounded-pill ' . $cls . ' me-2 mb-1">' . $n . '. ' . $l . '</span>';
    }
    return '<div class="d-flex flex-wrap mb-2">' . $badges . '</div>'
        . '<div class="progress mb-4" style="height:8px;"><div class="progress-bar bg-primary" '
        . 'style="width:' . $pct . '%;"></div></div>';
};
$stepcard = function ($step, $title, $intro, $body, $footer = '') use ($stepper) {
    return '<div class="card shadow-sm border-0 my-4">'
        . '<div class="card-header bg-white border-0 pt-4 px-4 pb-0">'
        . '<div class="text-uppercase text-primary fw-bold small mb-1">Förderantrag · Schritt ' . $step . ' von 4</div>'
        . '<h3 class="fw-bold mb-3">' . $title . '</h3>' . $stepper($step) . '</div>'
        . '<div class="card-body px-4 pb-4">'
        . ($intro ? '<p class="text-muted">' . $intro . '</p>' : '') . $body
        . ($footer ? '<div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">' . $footer . '</div>' : '')
        . '</div></div>';
};
$editbtn = '<div class="text-muted small">'
    . '<i class="fa fa-pencil me-1"></i>##edit##</div>';

// Read-only summary card for list views (Meine Anträge, Begutachtung). The body fields use
// the label-renderers, whose novaluetemplate is '___0___', so any field the applicant left
// empty disappears automatically — only the relevant data for each application is shown.
$listcard = function ($accent, $bodyfields, $footer = '') {
    return '<div class="card shadow-sm border-0 border-start border-4 border-' . $accent . ' mb-3">'
        . '<div class="card-body px-4 pt-3">'
        . '<div class="d-flex justify-content-between align-items-start mb-3">'
        . '<h5 class="fw-bold mb-0">[[antragstyp]]</h5>'
        . '<span class="badge rounded-pill bg-light text-secondary border">##status##</span></div>'
        . $bodyfields . '</div>'
        . ($footer ? '<div class="card-footer bg-white border-top-0 d-flex justify-content-end gap-2 px-4 pb-3 pt-0">'
        . $footer . '</div>' : '') . '</div>';
};

// ---------------------------------------------------------------------------
// 7c. Views.
// ---------------------------------------------------------------------------
$V = [];
$addview = function ($type, $name, $entrytemplate = null, $extra = [], $section = null) use ($dlx, &$V) {
    $v = $dlx->get_view($type, 0);
    $v->generate_default_view();
    $data = (object) array_merge(['name' => $name, 'description' => '', 'visible' => 7,
        'perpage' => 20, 'groupby' => '', 'filter' => 0], $extra);
    $data->esection = ($section !== null) ? $section : ($v->view->esection ?? '');
    if ($entrytemplate !== null) {
        $data->eparam2 = $entrytemplate;
    } else if (isset($v->view->eparam2)) {
        $data->eparam2 = $v->view->eparam2;
    }
    if (isset($v->view->param3)) {
        $data->param3 = $v->view->param3;
    }
    $id = $v->add($data);
    $V[$name] = (int) $id;
    return $id;
};

// Each card's edit button is a viewsesslink that jumps into the wizard at Schritt 1 with
// this entry loaded for editing (editentries=##entryid## resolves to the entry's id).
$mineedit = '##viewsesslink:Schritt 1 – Antragstyp und Sektion;'
    . '<i class="fa fa-pencil me-2"></i>Bearbeiten;editentries=##entryid##;btn btn-outline-primary btn-sm##';
$addview(
    'grid',
    'Meine Anträge',
    $listcard(
        'primary',
        $f('sektion') . $f('bearbeitungsstatus') . $f('pd_name')
        . $f('reise_ziel') . $f('reise_dauer') . $f('pub_titel') . $f('pub_art')
        . $f('abschluss_monate') . $f('ver_titelformat') . $f('ver_datumort') . $f('mat_art')
        . $f('ausland_uni') . $f('ausland_zeitraum') . $f('sonstiges_beschreibung') . $f('kurzbericht'),
        $mineedit
    ),
    ['filter' => $filter_own],
    '<div class="container py-4" style="max-width:820px;">'
    . '<h2 class="fw-bold mb-1">Meine Anträge</h2>'
    . '<p class="text-muted mb-4">Übersicht Ihrer Förderanträge. Klicken Sie auf „Bearbeiten", um einen Antrag fortzusetzen.</p>'
    . '##entries##'
    . '<div class="text-center mt-4">'
    . '##viewsesslink:Schritt 1 – Antragstyp und Sektion;<i class="fa fa-plus me-2"></i>Neuen Antrag stellen;new=1;btn btn-primary btn-lg##'
    . '</div></div>'
);

$addview(
    'grid',
    'Schritt 1 – Antragstyp und Sektion',
    $stepcard(
        1,
        'Antragsart und Sektion wählen',
        'Wählen Sie zunächst die Förderart und Ihre Sektion. Im nächsten Schritt erscheinen die passenden Felder.',
        $f('antragstyp') . $f('sektion')
    ),
    ['filter' => $filter_own],
    // ##addnewentry## is required here so a brand-new application can be started from this view.
    '<div class="container py-4" style="max-width:900px;">##entries##'
    . '<div class="text-center mt-3">##addnewentry##</div></div>'
);

$addview(
    'grid',
    'Schritt 2 – Persönliche Daten',
    $stepcard(
        2,
        'Persönliche Daten',
        'Diese Angaben verwenden wir für die Korrespondenz zu Ihrem Antrag.',
        $f('pd_name') . $f('pd_email') . $f('pd_telefon'),
        $editbtn
    ),
    ['filter' => $filter_own],
    $cleansection
);

$addview(
    'grid',
    'Schritt 3 – Antragsdetails',
    $stepcard(
        3,
        'Antragsdetails',
        'Je nach gewählter Förderart werden hier nur die relevanten Felder angezeigt.',
        $f('reise_ziel', 'b_reise') . $f('reise_zweck', 'b_reise') . $f('reise_dauer', 'b_reise') . $f('reise_relevanz', 'b_reise')
        . $f('pub_art', 'b_pub') . $f('pub_titel', 'b_pub') . $f('pub_laenge', 'b_pub') . $f('pub_sprache', 'b_pub') . $f('pub_journal', 'b_pub')
        . $f('abschluss_monate', 'b_abschluss') . $f('abschluss_docs', 'b_abschluss')
        . $f('ver_titelformat', 'b_veranstaltung') . $f('ver_teilnehmer', 'b_veranstaltung') . $f('ver_zielgruppe', 'b_veranstaltung')
        . $f('ver_datumort', 'b_veranstaltung') . $f('ver_beschreibung', 'b_veranstaltung')
        . $f('mat_art', 'b_material') . $f('mat_begruendung', 'b_material')
        . $f('ausland_uni', 'b_ausland') . $f('ausland_zeitraum', 'b_ausland') . $f('ausland_docs', 'b_ausland')
        . $f('sonstiges_beschreibung', 'b_sonstiges')
        . '<hr class="my-4"><h5 class="fw-bold mb-3">Kostenaufstellung</h5>' . $f('kost'),
        $editbtn
    ),
    ['filter' => $filter_own],
    $cleansection
);

$addview(
    'grid',
    'Schritt 4 – Übersicht und Absenden',
    $stepcard(
        4,
        'Überprüfen und verbindlich absenden',
        'Prüfen Sie Ihre Angaben. Mit „Endgültige Abgabe" wird der Antrag verbindlich eingereicht und für die Bearbeitung freigegeben.',
        '<div class="alert alert-warning d-flex align-items-center"><i class="fa fa-exclamation-triangle me-2"></i>'
        . 'Nach dem verbindlichen Absenden kann der Antrag nicht mehr bearbeitet werden.</div>'
        . $f('eingereicht')
        . '<div class="mt-3 p-3 bg-light rounded"><div class="fw-semibold mb-2">Verbindliche Abgabe</div>##status##</div>',
        $editbtn
    ),
    ['filter' => $filter_own],
    $cleansection
);

$addview(
    'grid',
    'Belege und Bericht',
    '<div class="container py-4" style="max-width:900px;"><div class="card shadow-sm border-0 my-4">'
    . '<div class="card-header bg-white border-0 pt-4 px-4"><h3 class="fw-bold mb-0">Belege und Kurzbericht</h3>'
    . '<p class="text-muted mb-0">Nach der Bewilligung reichen Sie hier Ihre Belege und einen kurzen Bericht ein.</p></div>'
    . '<div class="card-body px-4 pb-4">' . $f('belege') . $f('kurzbericht') . $f('nachforderung_docs') . '</div></div></div>',
    ['filter' => $filter_own],
    $cleansection
);

// Reviewer's queue: a card grid of the applications assigned to the current user's section
// team. The "Begutachten" button opens the detailed review view for that entry.
$pruefenedit = '##viewsesslink:Antrag prüfen;<i class="fa fa-gavel me-2"></i>Begutachten;editentries=##entryid##;btn btn-warning btn-sm##';
$addview(
    'grid',
    'Begutachtung',
    $listcard(
        'warning',
        $f('pd_name') . $f('sektion')
        . $f('reise_ziel') . $f('reise_zweck') . $f('pub_titel') . $f('abschluss_monate')
        . $f('ver_titelformat') . $f('mat_art') . $f('ausland_uni') . $f('sonstiges_beschreibung')
        . $f('kost'),
        $pruefenedit
    ),
    ['filter' => $filter_begutachtung],
    '<div class="container py-4" style="max-width:860px;">'
    . '<h2 class="fw-bold mb-1">Begutachtung</h2>'
    . '<p class="text-muted mb-4">Die folgenden Anträge sind Ihrer Sektion zur Begutachtung zugewiesen.</p>'
    . '##entries##</div>'
);

$addview(
    'grid',
    'Antrag prüfen',
    '<div class="container py-4" style="max-width:920px;"><div class="card shadow-sm border-0 my-4">'
    . '<div class="card-header bg-warning-subtle border-0 pt-4 px-4"><h3 class="fw-bold mb-0">Antrag begutachten</h3></div>'
    . '<div class="card-body px-4 pb-4">'
    . $f('pd_name') . $f('antragstyp') . $f('sektion') . $f('kost')
    . '<hr class="my-4"><h5 class="fw-bold mb-3">Entscheidung</h5>'
    . $f('bearbeitungsstatus') . $f('sl_begruendung', 'b_begruendung')
    . '<hr class="my-4"><h5 class="fw-bold mb-3">Kommunikation</h5><div>##comments##</div>'
    . '</div></div></div>',
    ['filter' => $filter_sl],
    $cleansection
);

$addview('tabular', 'Koordination – Eingang und Zuweisung', null, ['filter' => $filter_inbox]);

$addview(
    'grid',
    'Koordination – Antrag bearbeiten',
    '<div class="container py-4" style="max-width:920px;"><div class="card shadow-sm border-0 my-4">'
    . '<div class="card-header bg-info-subtle border-0 pt-4 px-4"><h3 class="fw-bold mb-0">Antrag verwalten</h3></div>'
    . '<div class="card-body px-4 pb-4">'
    . $f('pd_name') . $f('antragstyp') . $f('sektion') . $f('sektionsleitung_team') . $f('gutachter_team')
    . $f('bearbeitungsstatus') . $f('nachforderung_text')
    . '<hr class="my-4"><h5 class="fw-bold mb-3">Kommunikation</h5><div>##comments##</div>'
    . '</div></div></div>',
    [],
    $cleansection
);

$addview('report', 'Dashboard / Reporting', null, ['groupby' => (string) $F['bearbeitungsstatus']]);
out('Views created: ' . count($V));

// Wizard chaining: param10 = redirect-to next view, param9 = continue editing.
$chain = function ($from, $to, $continue = 1) use ($DB, $V) {
    $DB->set_field('datalynx_views', 'param10', $V[$to], ['id' => $V[$from]]);
    $DB->set_field('datalynx_views', 'param9', $continue, ['id' => $V[$from]]);
};
$chain('Schritt 1 – Antragstyp und Sektion', 'Schritt 2 – Persönliche Daten');
$chain('Schritt 2 – Persönliche Daten', 'Schritt 3 – Antragsdetails');
$chain('Schritt 3 – Antragsdetails', 'Schritt 4 – Übersicht und Absenden');
$chain('Schritt 4 – Übersicht und Absenden', 'Meine Anträge', 0);

// ---------------------------------------------------------------------------
// 7d. Email templates (internal email views). Beautiful inline-styled HTML bodies
// ([[field]] for entry details, ##notificationentryurl## for the direct link).
// Wired into the notification rules via param8.
// ---------------------------------------------------------------------------
$EV = [];
$addemail = function ($name, $body) use ($dlx, $DB, &$EV) {
    $v = $dlx->get_view('email', 0);
    $id = (int) $v->add((object) ['name' => $name, 'description' => '', 'visible' => 0,
        'perpage' => 0, 'groupby' => '', 'filter' => 0, 'esection' => '##entries##', 'eparam2' => $body]);
    $DB->set_field('datalynx_views', 'section', '##entries##', ['id' => $id]);
    $EV[$name] = $id;
    return $id;
};
$row = function ($label, $tag) {
    return '<tr><td style="padding:7px 10px;color:#6b7280;font-size:13px;border-bottom:1px solid #f0f0f0;width:40%;">'
        . $label . '</td><td style="padding:7px 10px;font-size:14px;border-bottom:1px solid #f0f0f0;">' . $tag . '</td></tr>';
};
$emailbody = function ($accent, $heading, $intro, $detailrows = '', $note = '', $linktext = 'Antrag öffnen') {
    $details = $detailrows ? '<table style="width:100%;border-collapse:collapse;margin:18px 0;">' . $detailrows . '</table>' : '';
    $notehtml = $note ? '<div style="background:#fff8e1;border-left:4px solid #f0ad4e;border-radius:6px;padding:12px 14px;'
        . 'font-size:14px;color:#5c4a1a;margin:16px 0;">' . $note . '</div>' : '';
    return '<div style="font-family:Helvetica,Arial,sans-serif;max-width:600px;margin:0 auto;color:#1d2433;background:#fff;">'
        . '<div style="background:' . $accent . ';padding:24px 28px;border-radius:10px 10px 0 0;">'
        . '<div style="color:rgba(255,255,255,.8);font-size:11px;letter-spacing:1.5px;text-transform:uppercase;">DocSchool Antragsportal</div>'
        . '<h1 style="color:#fff;font-size:22px;margin:6px 0 0;">' . $heading . '</h1></div>'
        . '<div style="border:1px solid #e6e8eb;border-top:0;border-radius:0 0 10px 10px;padding:24px 28px;">'
        . '<p style="font-size:15px;line-height:1.6;margin:0;">' . $intro . '</p>' . $details . $notehtml
        . '<p style="margin:26px 0 6px;"><a href="##notificationentryurl##" style="background:' . $accent . ';color:#fff;'
        . 'text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;display:inline-block;">' . $linktext . ' &rarr;</a></p>'
        . '<hr style="border:0;border-top:1px solid #eee;margin:22px 0;">'
        . '<p style="color:#8a94a6;font-size:12px;margin:0;">Automatische Nachricht des DocSchool Antragsportals · '
        . 'bitte nicht direkt auf diese E-Mail antworten.</p></div></div>';
};
$blue = '#0d6efd';
$green = '#198754';
$amber = '#fd7e14';
$red = '#dc3545';
$teal = '#0dcaf0';
$baserows = $row('Antragsteller/in', '[[pd_name]]') . $row('Antragstyp', '[[antragstyp]]') . $row('Sektion', '[[sektion]]');

$addemail('email_n1', $emailbody(
    $blue,
    'Neuer Förderantrag eingereicht',
    'Es wurde ein neuer Förderantrag eingereicht und wartet auf die Überprüfung durch die Koordination.',
    $baserows,
    '',
    'Zur Prüfung öffnen'
));
$addemail('email_n2', $emailbody(
    $amber,
    'Antrag zur Begutachtung',
    'Ihnen wurde ein Förderantrag zur Begutachtung zugewiesen. Bitte prüfen Sie den Antrag und tragen Sie Ihre Entscheidung ein.',
    $baserows,
    '',
    'Antrag begutachten'
));
$addemail('email_n3', $emailbody(
    $green,
    'Ihr Förderantrag wurde bewilligt',
    'Herzlichen Glückwunsch! Ihr Antrag wurde bewilligt.',
    $baserows,
    'Bitte sammeln Sie nun Ihre Belege und reichen Sie diese zusammen mit einem kurzen Bericht über das Portal ein.'
));
$addemail('email_n3b', $emailbody(
    $red,
    'Entscheidung zu Ihrem Förderantrag',
    'Ihr Antrag wurde leider nicht bewilligt.',
    $baserows . $row('Begründung', '[[sl_begruendung]]')
));
$addemail('email_n4', $emailbody(
    $blue,
    'Belege wurden eingereicht',
    'Für den folgenden Antrag wurden Belege und ein Bericht eingereicht. Diese können nun geprüft werden.',
    $baserows,
    '',
    'Belege prüfen'
));
$addemail('email_n5', $emailbody(
    $amber,
    'Nachforderung von Belegen',
    'Für Ihren Antrag werden noch Belege benötigt:',
    $row('Nachforderung', '[[nachforderung_text]]'),
    'Bitte laden Sie die fehlenden Dokumente über das Portal hoch.',
    'Belege hochladen'
));
$addemail('email_n6', $emailbody(
    $green,
    'Antrag an Finanzabteilung weitergeleitet',
    'Ihre Belege wurden akzeptiert und Ihr Antrag wurde an die Finanzabteilung weitergeleitet.',
    $baserows,
    'Hinweis: Die Auszahlung kann bis zu 4 Wochen dauern.'
));
$addemail('email_n7', $emailbody(
    $green,
    'Auszahlung erfolgt',
    'Die Förderung für Ihren Antrag wurde ausbezahlt. Wir wünschen Ihnen viel Erfolg!',
    $baserows
));
$addemail('email_kommentar', $emailbody(
    $teal,
    'Neue Nachricht zu Ihrem Antrag',
    'Zu Ihrem Förderantrag wurde eine neue Nachricht hinterlegt. Öffnen Sie den Antrag, um sie zu lesen und zu antworten.',
    $baserows,
    '',
    'Nachricht öffnen'
));
out('Email templates created: ' . count($EV));

// Wire each notification rule to its email template (param8).
$emailmap = ['n1_eingereicht' => 'email_n1', 'n2_zugewiesen' => 'email_n2', 'n3_bewilligt' => 'email_n3',
    'n3b_abgelehnt' => 'email_n3b', 'n4_belege' => 'email_n4', 'n5_nachforderung' => 'email_n5',
    'n6_weitergeleitet' => 'email_n6', 'n7_ausbezahlt' => 'email_n7', 'n_kommentar' => 'email_kommentar'];
foreach ($emailmap as $rule => $email) {
    if (!empty($R[$rule]) && !empty($EV[$email])) {
        $DB->set_field('datalynx_rules', 'param8', $EV[$email], ['id' => $R[$rule]]);
    }
}
out('Notifications wired to email templates.');

// ---------------------------------------------------------------------------
// 7e. Portal landing view (role-based overview). Links resolve via ##viewlink## /
// ##viewsesslink## tags so they survive export/import.
// ---------------------------------------------------------------------------
$portal = '<div class="container py-4">'
. '<div class="p-5 mb-5 bg-white border rounded-4 shadow-sm text-center">'
. '<h1 class="display-5 fw-bold text-primary mb-3">DocSchool Antragsportal</h1>'
. '<p class="lead text-muted mx-auto" style="max-width:620px;">Willkommen im Online-Portal der DocSchool. '
. 'Hier können Sie Förderanträge stellen, den Status laufender Anträge einsehen sowie Begutachtungen und Freigaben durchführen.</p>'
. '</div><div class="row g-4">'
. '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3">'
. '<div class="card-body"><div class="display-6 text-primary mb-3"><i class="fa fa-graduation-cap"></i></div>'
. '<h3 class="card-title fw-bold mb-3">PhD-Studierende</h3>'
. '<p class="card-text text-secondary mb-4">Reichen Sie einen neuen Antrag ein (z. B. Reisekosten, Abschlussstipendium, '
. 'Publikationen) oder verwalten Sie Ihre bestehenden Anträge.</p></div>'
. '<div class="card-footer bg-transparent border-0 px-0 pb-0">'
. '##viewsesslink:Schritt 1 – Antragstyp und Sektion;Neuen Antrag stellen;new=1;btn btn-primary w-100 py-2 fw-bold mb-2##'
. '##viewlink:Meine Anträge;Meine Anträge anzeigen;;btn btn-outline-primary w-100 py-2##'
. '</div></div></div>'
. '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3">'
. '<div class="card-body"><div class="display-6 text-warning mb-3"><i class="fa fa-gavel"></i></div>'
. '<h3 class="card-title fw-bold mb-3">Sektionsleitung</h3>'
. '<p class="card-text text-secondary mb-4">Prüfen Sie die Ihnen zur Begutachtung zugewiesenen Förderanträge '
. 'der PhD-Studierenden und tragen Sie Ihre Entscheidung ein.</p></div>'
. '<div class="card-footer bg-transparent border-0 px-0 pb-0">'
. '##viewlink:Begutachtung;Zur Begutachtung;;btn btn-warning w-100 py-2 fw-bold##'
. '</div></div></div>'
. '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3">'
. '<div class="card-body"><div class="display-6 text-info mb-3"><i class="fa fa-tasks"></i></div>'
. '<h3 class="card-title fw-bold mb-3">Koordination</h3>'
. '<p class="card-text text-secondary mb-4">Verwalten Sie den gesamten Workflow: Prüfen Sie Neueingänge, '
. 'weisen Sie Reviewer zu, kontrollieren Sie Belege und leiten Sie Anträge zur Auszahlung weiter.</p></div>'
. '<div class="card-footer bg-transparent border-0 px-0 pb-0">'
. '##viewlink:Koordination – Eingang und Zuweisung;Zum Koordinations-Dashboard;;btn btn-info w-100 py-2 fw-bold##'
. '</div></div></div>'
. '</div></div>';
$pv = $dlx->get_view('grid', 0);
$pid = (int) $pv->add((object) ['name' => 'Antragsportal', 'description' => '', 'visible' => 7,
    'perpage' => 0, 'groupby' => '', 'filter' => 0, 'esection' => $portal, 'eparam2' => '', 'param3' => 'col']);
$V['Antragsportal'] = $pid;
out('Portal landing view created (id ' . $pid . ').');

// Default view = Antragsportal.
$DB->set_field('datalynx', 'defaultview', $V['Antragsportal'], ['id' => $instanceid]);

// ---------------------------------------------------------------------------
// 8. Export as .mbz.
// ---------------------------------------------------------------------------
out('Running activity backup ...');
$bc = new backup_controller(
    backup::TYPE_1ACTIVITY,
    $cmid,
    backup::FORMAT_MOODLE,
    backup::INTERACTIVE_NO,
    backup::MODE_GENERAL,
    $USER->id
);
$bc->execute_plan();
$results = $bc->get_results();
$bc->destroy();

$dest = $CFG->dirroot . '/dist';
if (!is_dir($dest)) {
    mkdir($dest, 0777, true);
}
$path = $dest . '/docschool-foerderantraege.mbz';
if (!empty($results['backup_destination']) && $results['backup_destination']) {
    $results['backup_destination']->copy_content_to($path);
    out("EXPORTED: $path (" . filesize($path) . " bytes)");
} else {
    out('ERROR: no backup_destination in results: ' . json_encode(array_keys($results)));
}
out('DONE.');
