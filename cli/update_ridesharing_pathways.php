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
 * Splits an existing ride sharing activity into an offer pathway and a request pathway.
 *
 * Offering a ride and looking for one need very different forms - an offer describes a vehicle,
 * seats and terms, a request is little more than "from here to there, at this time". They now have
 * a way in each: the five step wizard becomes the offer pathway, and a request is a single page.
 *
 * A field can only carry one default, so the ride type cannot be preselected differently per
 * pathway. The offer pathway therefore keeps the ride type question, preselected on "Fahrt
 * anbieten"; the request pathway does not ask at all, and an update-field rule stamps "Mitfahrt
 * suchen" on any entry created without a type. That rule fires follow-up events, so the matching
 * rules - which look at the ride type - see the finished entry rather than the half-built one.
 *
 * Also adds the email view the match notifications render, so a notification shows the ride that
 * was found rather than a bare link.
 *
 * The script is idempotent: it looks views and rules up by name and updates what it finds.
 *
 * Usage:
 *   php mod/datalynx/cli/update_ridesharing_pathways.php --cmid=1162
 *   php mod/datalynx/cli/update_ridesharing_pathways.php --dataid=129 --dryrun
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

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
    cli_writeln("Split a ride sharing datalynx activity into an offer and a request pathway.

Options:
  -h, --help          Print this help.
      --cmid=ID       Course module id of the activity.
      --dataid=ID     Datalynx instance id, as an alternative to --cmid.
      --dryrun        Report what would change without writing anything.

Example:
  php mod/datalynx/cli/update_ridesharing_pathways.php --cmid=1162
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

// Everything is looked up by name, so the script can be re-run and so a renamed field is reported
// rather than silently skipped.

$fieldids = [];
foreach ($DB->get_records('datalynx_fields', ['dataid' => $dataid], '', 'id, name, type') as $field) {
    $fieldids[$field->name] = (int) $field->id;
}
$behaviorids = $DB->get_records_menu('datalynx_behaviors', ['dataid' => $dataid], '', 'name, id');
$layoutnames = $DB->get_records_menu('datalynx_renderers', ['dataid' => $dataid], '', 'name, id');
$filterids = $DB->get_records_menu('datalynx_filters', ['dataid' => $dataid], '', 'name, id');

$require = function (array $have, array $wanted, string $what): void {
    $missing = array_diff($wanted, array_keys($have));
    if ($missing) {
        cli_error("Missing $what: " . implode(', ', $missing)
            . ". Run create_ridesharing_instance.php first, or rename them back.");
    }
};

$require($fieldids, [
    'Fahrttyp', 'Route', 'Umweg', 'Fahrtmuster', 'Wochentage', 'Abfahrtszeit', 'Ankunftszeit',
    'Fahrzeugart', 'Fahrzeug', 'Kennzeichen', 'Kraftstoff', 'Freie Plätze', 'Gepäck',
    'Mitfahrende', 'Fahrkosten', 'Fixbetrag', 'Bemerkungen',
], 'fields');
$require($behaviorids, ['pflicht', 'angebot', 'angebotpflicht', 'regelmaessig', 'nurlesen'], 'behaviors');
$require($layoutnames, ['l_route', 'l_fahrtmuster', 'l_wochentage', 'l_abfahrtszeit'], 'field layouts');
$require($filterids, ['Meine Einträge'], 'filters');

// View names. The step views refer to one another by name through ##viewsesslink##, so the names
// and every template that mentions them are rewritten together, here, in one place.

$offersteps = [
    1 => 'Fahrtangebot · Schritt 1 – Route',
    2 => 'Fahrtangebot · Schritt 2 – Termin',
    3 => 'Fahrtangebot · Schritt 3 – Fahrzeug',
    4 => 'Fahrtangebot · Schritt 4 – Mitnahme',
    5 => 'Fahrtangebot · Schritt 5 – Übersicht',
];
$offersteplabels = [1 => 'Route', 2 => 'Termin', 3 => 'Fahrzeug', 4 => 'Mitnahme', 5 => 'Übersicht'];
$oldoffersteps = [
    1 => 'Schritt 1 – Fahrttyp und Route',
    2 => 'Schritt 2 – Termin',
    3 => 'Schritt 3 – Fahrzeug',
    4 => 'Schritt 4 – Mitnahme',
    5 => 'Schritt 5 – Übersicht und absenden',
];
$requestview = 'Mitfahrgesuch anlegen';
$emailview = 'Benachrichtigung: Passende Fahrt';
$portalview = 'Mitfahrbörse';
$myridesview = 'Meine Fahrten';

$views = $DB->get_records('datalynx_views', ['dataid' => $dataid], '', 'id, name, type');
$viewidbyname = [];
foreach ($views as $view) {
    $viewidbyname[$view->name] = (int) $view->id;
}

// Resolve the five wizard steps under either their old or their new name, so re-running finds them.
$stepids = [];
foreach ($offersteps as $number => $newname) {
    $stepids[$number] = $viewidbyname[$newname] ?? $viewidbyname[$oldoffersteps[$number]] ?? 0;
    if (!$stepids[$number]) {
        cli_error("Cannot find the wizard step \"{$oldoffersteps[$number]}\" (nor \"$newname\").");
    }
}
if (empty($viewidbyname[$portalview])) {
    cli_error("Cannot find the portal view \"$portalview\".");
}

$tag = function (string $fieldname, string $behavior = '', string $layout = '') use ($fieldids, $layoutnames): string {
    if (empty($fieldids[$fieldname])) {
        cli_error("Unknown field: $fieldname");
    }
    return '[[' . $fieldname . '|' . $behavior . '|' . ($layout && isset($layoutnames[$layout]) ? $layout : '') . ']]';
};

// The breadcrumb and progress bar shared by the offer steps. Earlier steps stay reachable as
// links carrying the entry id, later ones are plain badges.
$stepper = function (int $current) use ($offersteps, $offersteplabels): string {
    $out = '<div class="d-flex flex-wrap mb-2">';
    foreach ($offersteplabels as $number => $label) {
        $caption = "$number. $label";
        if ($number < $current) {
            $out .= '##viewsesslink:' . $offersteps[$number] . ';' . $caption
                . ';editentries=##entryid##|eids=##entryid##;badge rounded-pill bg-success p-2 me-2 mb-1##';
        } else if ($number === $current) {
            $out .= '<span class="badge rounded-pill bg-primary p-2 me-2 mb-1">' . $caption . '</span>';
        } else {
            $out .= '<span class="badge rounded-pill bg-secondary opacity-50 p-2 me-2 mb-1">' . $caption . '</span>';
        }
    }
    $out .= '</div><div class="progress mb-4" style="height: 8px;">'
        . '<div class="progress-bar bg-primary" style="width: ' . ($current * 20) . '%;">&nbsp;</div></div>';

    return $out;
};

// The card every step and the request page are rendered in.
$card = function (
    string $eyebrow,
    string $heading,
    string $stepper,
    string $lead,
    string $body,
    string $submit
): string {
    return '<div class="card shadow-sm border-0 my-4 mx-auto" style="max-width: 820px;">'
        . '<div class="card-header bg-white border-0 pt-4 px-4 pb-0">'
        . '<div class="text-uppercase text-primary fw-bold small mb-1">' . $eyebrow . '</div>'
        . '<h3 class="fw-bold mb-3">' . $heading . '</h3>'
        . $stepper
        . '</div><div class="card-body px-4 pb-4">'
        . '<p class="text-muted">' . $lead . '</p>'
        . $body
        . '<div class="d-flex justify-content-end gap-2 mt-4">##submit:text=' . $submit . '##</div>'
        . '</div></div>';
};

// The offer pathway. The ride type stays on step 1, preselected on "Fahrt anbieten": it is what
// marks the entry as an offer, and it is the one thing the request pathway deliberately omits.
// The conditional behaviors (angebot, angebotpflicht) are kept: they cost nothing, and they still
// do their job should somebody switch the radio button inside the wizard.

$steptemplates = [
    1 => $card(
        'Fahrtangebot · Schritt 1 von 5',
        'Route',
        $stepper(1),
        'Tragen Sie Start, Zwischenstopps und Ziel Ihrer Fahrt ein. Der Fahrttyp steht bereits auf '
            . '„Fahrt anbieten" – wer mitfahren möchte, legt stattdessen ein Mitfahrgesuch an.',
        $tag('Fahrttyp', 'pflicht', 'l_fahrttyp') . $tag('Route', 'pflicht', 'l_route')
            . '[[Umweg:slider||l_umweg]]' . $tag('Fahrtmuster', 'pflicht', 'l_fahrtmuster'),
        'Weiter_zum_Termin'
    ),
    2 => $card(
        'Fahrtangebot · Schritt 2 von 5',
        'Wann fahre ich?',
        $stepper(2),
        'Bei einer einmaligen Fahrt gehören Datum und Uhrzeit an den Start- und den Zielstopp der '
            . 'Route – nur so findet der Abgleich Fahrten zur passenden Zeit. Regelmäßige Fahrten '
            . 'beschreiben Sie über Wochentage und Uhrzeiten.',
        $tag('Route', 'einmalig', 'l_route') . $tag('Wochentage', 'regelmaessig', 'l_wochentage')
            . $tag('Abfahrtszeit', 'regelmaessig', 'l_abfahrtszeit')
            . $tag('Ankunftszeit', 'regelmaessig', 'l_ankunftszeit'),
        'Weiter_zum_Fahrzeug'
    ),
    3 => $card(
        'Fahrtangebot · Schritt 3 von 5',
        'Womit fahre ich?',
        $stepper(3),
        'Beschreiben Sie das Fahrzeug, mit dem Sie unterwegs sind.',
        $tag('Fahrzeugart', 'angebotpflicht', 'l_fahrzeugart') . $tag('Fahrzeug', 'angebot', 'l_fahrzeug')
            . $tag('Kennzeichen', 'angebot', 'l_kennzeichen') . $tag('Kraftstoff', 'angebot', 'l_kraftstoff')
            . $tag('Freie Plätze', 'angebotpflicht', 'l_sitzplaetze') . $tag('Gepäck', 'angebot', 'l_gepaeck'),
        'Weiter_zur_Mitnahme'
    ),
    4 => $card(
        'Fahrtangebot · Schritt 4 von 5',
        'Wen nehme ich mit?',
        $stepper(4),
        'Legen Sie fest, wen Sie mitnehmen und wie die Fahrtkosten aufgeteilt werden.',
        $tag('Mitfahrende', 'angebot', 'l_mitfahrende') . $tag('Fahrkosten', 'angebot', 'l_fahrkosten')
            . $tag('Fixbetrag', 'festbetrag', 'l_fixbetrag') . $tag('Bemerkungen', '', 'l_bemerkungen'),
        'Weiter_zur_Übersicht'
    ),
    5 => $card(
        'Fahrtangebot · Schritt 5 von 5',
        'Übersicht',
        $stepper(5),
        'Bitte prüfen Sie Ihre Angaben. Mit „Fahrt speichern" ist das Angebot veröffentlicht und '
            . 'wird ab sofort automatisch mit den Mitfahrgesuchen abgeglichen.',
        $tag('Fahrttyp', 'nurlesen', 'l_fahrttyp') . $tag('Route', 'nurlesen', 'l_route')
            . $tag('Umweg', 'nurlesen', 'l_umweg') . $tag('Fahrtmuster', 'nurlesen', 'l_fahrtmuster')
            . $tag('Wochentage', 'nurlesenregelmaessig', 'l_wochentage')
            . $tag('Abfahrtszeit', 'nurlesenregelmaessig', 'l_abfahrtszeit')
            . $tag('Ankunftszeit', 'nurlesenregelmaessig', 'l_ankunftszeit')
            . $tag('Fahrzeugart', 'nurlesenangebot', 'l_fahrzeugart')
            . $tag('Fahrzeug', 'nurlesenangebot', 'l_fahrzeug')
            . $tag('Kennzeichen', 'nurlesenangebot', 'l_kennzeichen')
            . $tag('Kraftstoff', 'nurlesenangebot', 'l_kraftstoff')
            . $tag('Freie Plätze', 'nurlesenangebot', 'l_sitzplaetze')
            . $tag('Gepäck', 'nurlesenangebot', 'l_gepaeck')
            . $tag('Mitfahrende', 'nurlesenangebot', 'l_mitfahrende')
            . $tag('Fahrkosten', 'nurlesenangebot', 'l_fahrkosten')
            . $tag('Fixbetrag', 'nurlesenfestbetrag', 'l_fixbetrag')
            . $tag('Bemerkungen', 'nurlesen', 'l_bemerkungen'),
        'Fahrt_speichern'
    ),
];

// The request pathway. A request is start, end and when - the route field already carries the
// stops and their date and time, so a single ride needs nothing else. A recurring one is described
// by weekdays and times instead, which the "regelmaessig" behavior reveals once the pattern is
// chosen. No ride type field here: the update-field rule below stamps it.

$requesttemplate = $card(
    'Mitfahrbörse',
    'Mitfahrgesuch anlegen',
    '',
    'Woher, wohin und wann möchten Sie mitfahren? Tragen Sie bei einer einmaligen Fahrt Datum und '
        . 'Uhrzeit direkt an den Stopps ein. Für eine regelmäßige Fahrt genügen Wochentage und '
        . 'Uhrzeiten – wählen Sie dazu unten „Regelmäßig".',
    $tag('Route', 'pflicht', 'l_route') . $tag('Fahrtmuster', 'pflicht', 'l_fahrtmuster')
        . $tag('Wochentage', 'regelmaessig', 'l_wochentage')
        . $tag('Abfahrtszeit', 'regelmaessig', 'l_abfahrtszeit')
        . $tag('Ankunftszeit', 'regelmaessig', 'l_ankunftszeit')
        . $tag('Bemerkungen', '', 'l_bemerkungen'),
    'Gesuch_speichern'
);

// The portal. "Fahrt eintragen" becomes two buttons, one per pathway.

$portalsection = '<div class="container py-4">'
    . '<div class="p-5 mb-4 bg-white border rounded-4 shadow-sm text-center">'
    . '<h1 class="display-6 fw-bold text-primary mb-3">Mitfahrbörse</h1>'
    . '<p class="lead text-muted mx-auto" style="max-width: 640px;">Gemeinsam fahren statt allein: '
    . 'Tragen Sie Ihre Fahrt ein oder suchen Sie eine Mitfahrgelegenheit. Passen ein Angebot und '
    . 'ein Gesuch zusammen, werden beide Seiten automatisch benachrichtigt.</p></div>'
    . '<div class="row g-4">'
    . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3"><div class="card-body">'
    . '<h3 class="card-title fw-bold mb-3">Fahrt eintragen</h3>'
    . '<p class="card-text text-secondary">Als Fahrer/in ein Angebot mit Route, Termin und Fahrzeug '
    . 'eintragen – oder als Mitfahrer/in ein Gesuch mit Strecke und Zeit.</p></div>'
    . '<div class="card-footer bg-transparent border-0 d-grid gap-2">'
    . '##viewsesslink:' . $offersteps[1] . ';Fahrtangebot anlegen;new=1;btn btn-primary w-100 py-2 fw-bold##'
    . '##viewsesslink:' . $requestview . ';Mitfahrgesuch anlegen;new=1;btn btn-outline-primary w-100 py-2 fw-bold##'
    . '</div></div></div>'
    . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3"><div class="card-body">'
    . '<h3 class="card-title fw-bold mb-3">Fahrt suchen</h3>'
    . '<p class="card-text text-secondary">Angebote entlang Ihrer Strecke finden – auch dann, wenn '
    . 'Sie nur ein Stück der Route mitfahren.</p></div>'
    . '<div class="card-footer bg-transparent border-0">'
    . '##viewlink:Fahrt suchen;Fahrt suchen;;btn btn-primary w-100 py-2 fw-bold##'
    . '</div></div></div>'
    . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3"><div class="card-body">'
    . '<h3 class="card-title fw-bold mb-3">Meine Fahrten</h3>'
    . '<p class="card-text text-secondary">Eigene Angebote und Gesuche ansehen, ändern oder löschen.</p>'
    . '</div><div class="card-footer bg-transparent border-0">'
    . '##viewlink:' . $myridesview . ';Meine Fahrten;;btn btn-outline-primary w-100 py-2##'
    . '</div></div></div>'
    . '</div></div>';

// The notification body. Each side is shown the ride that was found for them, with a link to it
// and back to their own entry; ##notificationotherentrylink## is the counterpart of whichever
// entry the template is rendering.
//
// The field layouts carry their own labels and are set to show nothing when a field is empty, so
// a request - which has no vehicle, seats or fare - simply leaves those lines out instead of
// listing them blank.

$emailtemplate = '<div style="font-family: sans-serif; font-size: 15px; color: #2d3339;">'
    . '<p>es gibt eine passende Fahrt zu Ihrem Eintrag in der Mitfahrbörse:</p>'
    . '<div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 16px; margin: 16px 0;">'
    . $tag('Fahrttyp', '', 'l_fahrttyp') . $tag('Route', '', 'l_route')
    . $tag('Fahrtmuster', '', 'l_fahrtmuster') . $tag('Wochentage', '', 'l_wochentage')
    . $tag('Abfahrtszeit', '', 'l_abfahrtszeit') . $tag('Ankunftszeit', '', 'l_ankunftszeit')
    . $tag('Freie Plätze', '', 'l_sitzplaetze') . $tag('Fahrzeugart', '', 'l_fahrzeugart')
    . $tag('Fahrzeug', '', 'l_fahrzeug') . $tag('Kraftstoff', '', 'l_kraftstoff')
    . $tag('Gepäck', '', 'l_gepaeck') . $tag('Mitfahrende', '', 'l_mitfahrende')
    . $tag('Fahrkosten', '', 'l_fahrkosten') . $tag('Fixbetrag', '', 'l_fixbetrag')
    . $tag('Bemerkungen', '', 'l_bemerkungen')
    . '<div style="margin-top: 8px; font-size: 0.9em; color: #6a737b;">Angelegt von ##author:name##</div>'
    . '</div>'
    . '<p><strong>##notificationentrylink##</strong></p>'
    . '<p style="color: #6a737b;">Ihr eigener Eintrag: ##notificationotherentrylink##</p>'
    . '<p style="color: #6a737b; font-size: 0.9em;">Diese Nachricht kommt aus '
    . '##notificationdatalynxlink##.</p>'
    . '</div>';

// The subject of a match notification. Without it the message is titled after the event that
// happened to trigger it ("Datalynx entry updated"), which says nothing to a traveller.
$matchsubject = 'Mitfahrbörse: passende Fahrt gefunden';

// Write.

$report = [];
$writeview = function (int $viewid, array $data) use ($dlx, $DB, $dryrun, &$report): void {
    $record = $DB->get_record('datalynx_views', ['id' => $viewid], '*', MUST_EXIST);
    $report[] = "  update view $viewid: " . ($data['name'] ?? $record->name);
    if ($dryrun) {
        return;
    }

    // The view API expects permittedfilters as a list and re-encodes it, so the stored JSON
    // string has to be decoded first - passing it through as a string would store [0].
    $payload = array_merge((array) $record, $data);
    if (!array_key_exists('permittedfilters', $data)) {
        $payload['permittedfilters'] = $record->permittedfilters
            ? json_decode($record->permittedfilters, true) : null;
    }
    // The template columns are written through their editor properties, and every editor the view
    // declares is rewritten on save - an editor left out of the payload is emptied, not kept. So
    // the current values are carried over unless the caller replaces them, and the raw columns are
    // dropped so the param1..param10 loop cannot copy a stale param2 over the new template.
    $payload['esection'] = $data['esection'] ?? (string) $record->section;
    $payload['eparam2'] = $data['eparam2'] ?? (string) $record->param2;
    unset($payload['param2'], $payload['section']);

    $view = $dlx->get_view($record->type, $record, false);
    $view->update((object) $payload);
};

cli_writeln('');
cli_writeln('Offer pathway (five steps, renamed and retitled):');
foreach ($offersteps as $number => $name) {
    $writeview($stepids[$number], [
        'name' => $name,
        'description' => 'Fahrtangebot, Schritt ' . $number . ' von 5',
        // The step card is the entry template; the section only positions it on the page.
        'esection' => '<div class="container py-4">##entries##</div>',
        'eparam2' => $steptemplates[$number],
        'param9' => $number < 5 ? 1 : 0,
        'param10' => $number < 5 ? $stepids[$number + 1] : ($viewidbyname[$myridesview] ?? 0),
    ]);
}

cli_writeln('Request pathway (one page):');
$requestdata = [
    'name' => $requestview,
    'description' => 'Mitfahrgesuch in einem Schritt',
    'visible' => 7,
    'perpage' => 0,
    'filter' => (int) $filterids['Meine Einträge'],
    'permittedfilters' => [(int) $filterids['Meine Einträge']],
    'param3' => 'none',
    'param9' => 0,
    'param10' => $viewidbyname[$myridesview] ?? 0,
    'eparam2' => $requesttemplate,
    'esection' => '<div class="container py-4">##entries##</div>',
];
if (!empty($viewidbyname[$requestview])) {
    $writeview($viewidbyname[$requestview], $requestdata);
} else {
    $report[] = "  create view: $requestview";
    if (!$dryrun) {
        $view = $dlx->get_view('grid', 0, false);
        $viewidbyname[$requestview] = (int) $view->add((object) $requestdata);
    }
}

cli_writeln('Portal (two buttons in "Fahrt eintragen"):');
$writeview($viewidbyname[$portalview], ['esection' => $portalsection, 'eparam2' => '']);

cli_writeln('Email view for the match notifications:');
// An email view renders the entry template alone - render_email_entry() never looks at the
// section - so param2 is the whole notification body.
$emaildata = [
    'name' => $emailview,
    'description' => 'Nachrichtentext für den Fahrtenabgleich',
    'visible' => 0,
    'perpage' => 0,
    'filter' => 0,
    'eparam2' => $emailtemplate,
];
if (!empty($viewidbyname[$emailview])) {
    $writeview($viewidbyname[$emailview], $emaildata);
} else {
    $report[] = "  create view: $emailview";
    if (!$dryrun) {
        $view = $dlx->get_view(\mod_datalynx\datalynx::INTERNAL_VIEW_EMAIL, 0, false);
        $viewidbyname[$emailview] = (int) $view->add((object) $emaildata);
    }
}

cli_writeln('Rules:');

// Point the matching rules at the email view, so a notification shows the ride that was found, and
// give them a subject of their own.
$matchrules = $DB->get_records_select(
    'datalynx_rules',
    "dataid = :dataid AND type = 'eventnotification' AND " . $DB->sql_like('param9', ':key'),
    ['dataid' => $dataid, 'key' => '%_matchcriteria%']
);
foreach ($matchrules as $record) {
    $report[] = "  rule {$record->id} ({$record->name}): email template -> {$emailview}";
    if (!$dryrun) {
        $record->param6 = $matchsubject;
        $record->param8 = $viewidbyname[$emailview];
        $DB->update_record('datalynx_rules', $record);
    }
}
if (!$matchrules) {
    cli_writeln('  (no matching rules found - skipping the email template wiring)');
}

// The rule that stamps the ride type on entries created without one, which is exactly the entries
// the request pathway produces. Follow-up events are on so the matching rules, which condition on
// the ride type, run again once it is set.
$stamprulename = 'Mitfahrgesuch: Fahrttyp setzen';
$requestposition = 0;
foreach (explode("\n", (string) $DB->get_field('datalynx_fields', 'param1', ['id' => $fieldids['Fahrttyp']])) as $i => $label) {
    if (trim($label) === 'Mitfahrt suchen') {
        $requestposition = $i + 1;
    }
}
if (!$requestposition) {
    cli_error('The Fahrttyp field has no "Mitfahrt suchen" option.');
}

$stamprule = (object) [
    'dataid' => $dataid,
    'type' => 'updatefield',
    'name' => $stamprulename,
    'description' => 'Setzt den Fahrttyp auf „Mitfahrt suchen", wenn ein Eintrag ohne Fahrttyp angelegt wird.',
    'enabled' => 1,
    'param1' => json_encode(['entry_created']),
    'param2' => $fieldids['Fahrttyp'],
    'param3' => (string) $requestposition,
    // Fire follow-up events: the matching rules select on the ride type, so they have to see the
    // entry again once it has one.
    'param4' => '1',
    // Restrict to the entries that carry no ride type - the ones the request pathway creates.
    'param9' => json_encode([$fieldids['Fahrttyp'] => ['AND' => [['', '', '']]]]),
];
$existing = $DB->get_record('datalynx_rules', ['dataid' => $dataid, 'name' => $stamprulename]);
if ($existing) {
    $stamprule->id = $existing->id;
    $report[] = "  update rule {$existing->id}: $stamprulename";
    if (!$dryrun) {
        $DB->update_record('datalynx_rules', $stamprule);
    }
} else {
    $report[] = "  create rule: $stamprulename";
    if (!$dryrun) {
        $DB->insert_record('datalynx_rules', $stamprule);
    }
}

cli_writeln('');
foreach ($report as $line) {
    cli_writeln($line);
}

if (!$dryrun) {
    // View templates and their parsed patterns are cached.
    purge_all_caches();
    cli_writeln('');
    cli_writeln('Done. Open the activity to check the two buttons under "Fahrt eintragen".');
} else {
    cli_writeln('');
    cli_writeln('Dry run finished - nothing was written.');
}
