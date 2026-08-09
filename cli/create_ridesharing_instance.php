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
 * Builds a ride sharing activity: fields, field layouts, behaviors, filters, the
 * multi-step entry views and the ride matching rule.
 *
 * The multi-step form is a chain of views rather than JavaScript: every step is a
 * grid view showing a subset of the fields, `param10` names the next step and
 * `param9` ("redirect and continue editing") hands the same entry on in edit mode.
 *
 * Usage:
 *   php mod/datalynx/cli/create_ridesharing_instance.php --course=47
 *   php mod/datalynx/cli/create_ridesharing_instance.php --course=47 --reset
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/course/lib.php');

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'course' => 0,
    'name' => 'Mitfahrbörse',
    'reset' => false,
], [
    'h' => 'help',
    'c' => 'course',
    'n' => 'name',
]);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || empty($options['course'])) {
    cli_writeln("Build a ride sharing datalynx activity in a course.

Options:
  -h, --help          Print this help.
  -c, --course=ID     Course id to build the activity in (required).
  -n, --name=NAME     Activity name. Default: Mitfahrbörse.
      --reset         Delete an existing activity of that name in the course first.

Example:
  php mod/datalynx/cli/create_ridesharing_instance.php --course=47 --reset
");
    exit(empty($options['course']) ? 1 : 0);
}

\core\session\manager::set_user(get_admin());

$course = $DB->get_record('course', ['id' => (int) $options['course']], '*', MUST_EXIST);
$name = (string) $options['name'];

// A rebuild has to remove the old activity completely: fields, views and entries all
// hang off the instance id, so reusing it half way would leave orphans behind.
$existing = $DB->get_records('datalynx', ['course' => $course->id, 'name' => $name]);
if ($existing && empty($options['reset'])) {
    cli_error("An activity named '$name' already exists in course {$course->id}. Use --reset to replace it.");
}
foreach ($existing as $instance) {
    $cm = get_coursemodule_from_instance('datalynx', $instance->id, $course->id, false, MUST_EXIST);
    course_delete_module($cm->id);
    cli_writeln("Removed existing activity (cmid {$cm->id}).");
}

// The activity.

$intro = '<p>Fahrgemeinschaften für Fahrten zum Campus und darüber hinaus: '
    . 'Fahrten anbieten, Mitfahrgelegenheiten suchen und automatisch benachrichtigt werden, '
    . 'sobald Angebot und Gesuch zusammenpassen.</p>';

$moduleinfo = (object) [
    'modulename' => 'datalynx',
    'module' => $DB->get_field('modules', 'id', ['name' => 'datalynx'], MUST_EXIST),
    'name' => $name,
    'course' => $course->id,
    'section' => 0,
    'visible' => 1,
    'visibleoncoursepage' => 1,
    'introeditor' => ['text' => $intro, 'format' => FORMAT_HTML, 'itemid' => 0],
    'cmidnumber' => '',
    'timeavailable' => 0,
    'timedue' => 0,
    'timeinterval' => 0,
    'intervalcount' => 1,
    'allowlate' => 1,
    'grade' => 0,
    'grademethod' => 0,
    'anonymous' => 0,
    'notification' => 0,
    'notificationformat' => FORMAT_HTML,
    'entriesrequired' => 0,
    'entriestoview' => 0,
    'maxentries' => -1,
    'timelimit' => -1,
    'approval' => 0,
    'grouped' => 0,
    'rating' => 0,
    'singleedit' => 0,
    'singleview' => 0,
    'rssarticles' => 0,
    'rss' => 0,
    'defaultview' => 0,
    'defaultfilter' => 0,
    'completionentries' => 0,
];
$moduleinfo = add_moduleinfo($moduleinfo, $course);
$dataid = (int) $moduleinfo->instance;
$cmid = (int) $moduleinfo->coursemodule;

cli_writeln("Created datalynx instance $dataid (cmid $cmid) in course {$course->id}.");

$dlx = new \mod_datalynx\datalynx($dataid);

// Fields.

$halfhours = [];
for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
    $halfhours[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

$offerlabel = 'Fahrt anbieten';
$requestlabel = 'Mitfahrt suchen';

/*
 * Every field carries the label and the hint shown in the form. They live in the field
 * layout rather than in the view template so that a field hidden by a condition takes
 * its label with it instead of leaving a stray caption behind.
 */
$fielddefs = [
    'fahrttyp' => [
        'type' => 'radiobutton',
        'name' => 'Fahrttyp',
        'label' => 'Ich möchte …',
        'hint' => 'Angebote und Gesuche werden automatisch miteinander abgeglichen.',
        'params' => ['param1' => "$offerlabel\n$requestlabel", 'param2' => $offerlabel, 'param3' => 1],
    ],
    'route' => [
        'type' => 'itinerary',
        'name' => 'Route',
        'label' => 'Route: von – über – nach',
        'hint' => 'Adresse eintippen und einen Vorschlag auswählen. Zwischenstopps helfen beim Finden '
            . 'von Mitfahrenden entlang der Strecke. Bei einer einmaligen Fahrt Datum und Uhrzeit '
            . 'direkt beim Start- und Zielstopp eintragen.',
        'params' => [
            'param1' => 8, // Maximum stops.
            'param2' => 2, // Rows shown initially.
            'param3' => 'both', // Map and list.
            'param4' => 8, // Default zoom.
            'param5' => 5, // Default corridor radius in km.
            'param6' => 'approximate',
            'param7' => 'at,de,ch,it,si,sk,cz,hu',
            'param8' => 0, // A time per stop is optional.
        ],
        'fullwidth' => true,
    ],
    'umweg' => [
        'type' => 'number',
        'name' => 'Umweg',
        'label' => 'Umweg, den ich in Kauf nehme (km)',
        'hint' => 'Wie weit dürfen Mitfahrende von meiner Strecke entfernt wohnen?',
        'params' => ['param1' => 0, 'param4' => 1],
        'default' => 5,
    ],
    'fahrtmuster' => [
        'type' => 'radiobutton',
        'name' => 'Fahrtmuster',
        'label' => 'Wie oft fahre ich?',
        'hint' => '',
        'params' => ['param1' => "Einmalig\nRegelmäßig", 'param2' => 'Einmalig', 'param3' => 1],
    ],
    'wochentage' => [
        'type' => 'multiselect',
        'name' => 'Wochentage',
        'label' => 'An diesen Tagen fahre ich',
        'hint' => 'Mehrfachauswahl möglich.',
        'params' => [
            'param1' => "Montag\nDienstag\nMittwoch\nDonnerstag\nFreitag\nSamstag\nSonntag",
            'param3' => 1,
            'param6' => 1,
        ],
    ],
    'abfahrtszeit' => [
        'type' => 'select',
        'name' => 'Abfahrtszeit',
        'label' => 'Abfahrt (Uhrzeit)',
        'hint' => '',
        'params' => ['param1' => implode("\n", $halfhours), 'param6' => 1],
    ],
    'ankunftszeit' => [
        'type' => 'select',
        'name' => 'Ankunftszeit',
        'label' => 'Ankunft (Uhrzeit)',
        'hint' => '',
        'params' => ['param1' => implode("\n", $halfhours), 'param6' => 1],
    ],
    'fahrzeugart' => [
        'type' => 'select',
        'name' => 'Fahrzeugart',
        'label' => 'Art des Fahrzeugs',
        'hint' => '',
        'params' => [
            'param1' => "Kompaktwagen\nLimousine\nCabrio\nKombi\nSUV\nMini Van\nKleinbus\nReisebus",
        ],
    ],
    'fahrzeug' => [
        'type' => 'text',
        'name' => 'Fahrzeug',
        'label' => 'Bezeichnung',
        'hint' => 'Zum Beispiel: VW Polo',
        'params' => ['param2' => 30, 'param3' => 'px'],
    ],
    'kennzeichen' => [
        'type' => 'text',
        'name' => 'Kennzeichen',
        'label' => 'Kennzeichen',
        'hint' => 'Hilft beim Wiedererkennen am Treffpunkt.',
        'params' => ['param2' => 20, 'param3' => 'px'],
    ],
    'kraftstoff' => [
        'type' => 'select',
        'name' => 'Kraftstoff',
        'label' => 'Antrieb',
        'hint' => '',
        'params' => ['param1' => "Verbrenner\nHybrid\nE-Auto"],
    ],
    'sitzplaetze' => [
        'type' => 'select',
        'name' => 'Freie Plätze',
        'label' => 'Freie Plätze',
        'hint' => 'Wie viele Personen kann ich auf dieser Fahrt mitnehmen?',
        'params' => ['param1' => implode("\n", range(1, 8))],
    ],
    'gepaeck' => [
        'type' => 'select',
        'name' => 'Gepäck',
        'label' => 'Platz für Gepäck',
        'hint' => '',
        'params' => ['param1' => "Keines\nHandtasche\n1 Koffer\n2 Koffer"],
    ],
    'mitfahrende' => [
        'type' => 'select',
        'name' => 'Mitfahrende',
        'label' => 'Ich nehme mit',
        'hint' => '',
        'params' => ['param1' => "jedes Geschlecht\nmännlich\nweiblich\ndivers", 'param2' => 'jedes Geschlecht'],
    ],
    'fahrkosten' => [
        'type' => 'select',
        'name' => 'Fahrkosten',
        'label' => 'Fahrkosten',
        'hint' => '',
        'params' => ['param1' => "Aufteilen\nFestbetrag\nkostenlos"],
    ],
    'fixbetrag' => [
        'type' => 'number',
        'name' => 'Fixbetrag',
        'label' => 'Festbetrag pro Person (€)',
        'hint' => '',
        'params' => ['param1' => 2, 'param4' => 1],
    ],
    'bemerkungen' => [
        'type' => 'textarea',
        'name' => 'Bemerkungen',
        'label' => 'Bemerkungen',
        'hint' => 'Zum Beispiel: Treffpunkt, Nichtraucherfahrzeug, Tierhaare, Musikgeschmack.',
        'params' => ['param2' => 60, 'param3' => 4],
        'fullwidth' => true,
    ],
];

$fieldids = [];
foreach ($fielddefs as $key => $def) {
    $record = (object) array_merge([
        'dataid' => $dataid,
        'type' => $def['type'],
        'name' => $def['name'],
        'description' => '',
    ], $def['params']);
    $fieldids[$key] = (int) $DB->insert_record('datalynx_fields', $record);
}
cli_writeln('Created ' . count($fieldids) . ' fields.');

// Field layouts: label, hint and wrapper markup travel with the field.

$layoutnames = [];
foreach ($fielddefs as $key => $def) {
    $label = s($def['label']);
    $hint = $def['hint'] !== '' ? '<div class="form-text">' . s($def['hint']) . '</div>' : '';
    $fullwidth = !empty($def['fullwidth']);

    $edit = '<div class="mb-3 datalynx-ride-field">'
        . '<label class="form-label fw-semibold">' . $label . '</label>'
        . '#input'
        . $hint . '</div>';

    if ($fullwidth) {
        $display = '<div class="my-3"><div class="small fw-semibold text-muted mb-1">' . $label . '</div>'
            . '<div>#value</div></div>';
    } else {
        $display = '<div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-1">'
            . '<span class="small fw-semibold text-nowrap">' . $label . '</span>'
            . '<span class="text-end">#value</span></div>';
    }

    $layoutnames[$key] = 'l_' . $key;
    $DB->insert_record('datalynx_renderers', (object) [
        'dataid' => $dataid,
        'type' => $def['type'],
        'name' => $layoutnames[$key],
        'description' => 'Layout für ' . $def['name'],
        'notvisibletemplate' => \mod_datalynx\local\field\datalynxfield_layout::NOT_VISIBLE_SHOW_NOTHING,
        'displaytemplate' => $display,
        'novaluetemplate' => \mod_datalynx\local\field\datalynxfield_layout::NO_VALUE_SHOW_NOTHING,
        'edittemplate' => $edit,
        // Dependent rather than "as display mode": on the summary step, where every
        // field is read-only, a field without a value then disappears with its label
        // instead of leaving an empty caption behind.
        'noteditabletemplate' => \mod_datalynx\local\field\datalynxfield_layout::NOT_EDITABLE_SHOW_DEPENDENT,
    ]);
}
cli_writeln('Created ' . count($layoutnames) . ' field layouts.');

// Behaviors: required fields and fields that only apply to some journeys.

$permissions = [
    \mod_datalynx\datalynx::PERMISSION_MANAGER,
    \mod_datalynx\datalynx::PERMISSION_TEACHER,
    \mod_datalynx\datalynx::PERMISSION_STUDENT,
    \mod_datalynx\datalynx::PERMISSION_AUTHOR,
    \mod_datalynx\datalynx::PERMISSION_GUEST,
];
$editableby = [
    \mod_datalynx\datalynx::PERMISSION_MANAGER,
    \mod_datalynx\datalynx::PERMISSION_TEACHER,
    \mod_datalynx\datalynx::PERMISSION_STUDENT,
    \mod_datalynx\datalynx::PERMISSION_AUTHOR,
];

/*
 * A condition is evaluated server side against the *saved* entry, so the field it
 * depends on always sits in an earlier step of the chain. Option fields store the
 * position of the option, not its label, hence the numeric values below.
 */
$condition = function (int $sourcefieldid, array $optionkeys): string {
    return json_encode([
        'match' => 'all',
        'rules' => [[
            'sourcefieldid' => $sourcefieldid,
            'not' => '',
            'operator' => 'ANY_OF',
            'value' => array_map('strval', $optionkeys),
        ]],
    ]);
};

$behaviordefs = [
    'pflicht' => ['required' => 1, 'conditions' => ''],
    'angebot' => ['required' => 0, 'conditions' => $condition($fieldids['fahrttyp'], [1])],
    'angebotpflicht' => ['required' => 1, 'conditions' => $condition($fieldids['fahrttyp'], [1])],
    'einmalig' => ['required' => 0, 'conditions' => $condition($fieldids['fahrtmuster'], [1])],
    'regelmaessig' => ['required' => 0, 'conditions' => $condition($fieldids['fahrtmuster'], [2])],
    'festbetrag' => ['required' => 0, 'conditions' => $condition($fieldids['fahrkosten'], [2])],
    // Read-only twins for the summary step: it arrives in edit mode like every other
    // step, and "nobody may edit" is what turns the fields there back into a recap.
    // Each keeps the condition of its editable twin, so a field that does not apply to
    // this journey stays out of the summary as well.
    'nurlesen' => ['required' => 0, 'conditions' => '', 'editableby' => []],
    'nurlesenangebot' => [
        'required' => 0,
        'conditions' => $condition($fieldids['fahrttyp'], [1]),
        'editableby' => [],
    ],
    'nurlesenregelmaessig' => [
        'required' => 0,
        'conditions' => $condition($fieldids['fahrtmuster'], [2]),
        'editableby' => [],
    ],
    'nurlesenfestbetrag' => [
        'required' => 0,
        'conditions' => $condition($fieldids['fahrkosten'], [2]),
        'editableby' => [],
    ],
];

$behaviornames = [];
foreach ($behaviordefs as $bname => $def) {
    $DB->insert_record('datalynx_behaviors', (object) [
        'dataid' => $dataid,
        'name' => $bname,
        'description' => '',
        'visibleto' => serialize(['permissions' => $permissions]),
        'editableby' => serialize($def['editableby'] ?? $editableby),
        'required' => $def['required'],
        'editableafterfinal' => 0,
        'conditions' => $def['conditions'],
    ]);
    $behaviornames[] = $bname;
}
cli_writeln('Created ' . count($behaviornames) . ' behaviors.');

// Filters.

$makefilter = function (string $fname, $customsearch, int $perpage = 20) use ($DB, $dataid): int {
    return (int) $DB->insert_record('datalynx_filters', (object) [
        'dataid' => $dataid,
        'name' => $fname,
        'description' => '',
        'visible' => 1,
        'perpage' => $perpage,
        'selection' => 0,
        'groupby' => '',
        'search' => '',
        'customsort' => '',
        'customsearch' => $customsearch ? serialize($customsearch) : '',
    ]);
};

$filterids = [
    'own' => $makefilter('Meine Einträge', ['userid' => ['AND' => [['', 'ME', '']]]]),
    'offers' => $makefilter('Nur Angebote', [$fieldids['fahrttyp'] => ['AND' => [['', 'ANY_OF', ['1']]]]]),
    'requests' => $makefilter('Nur Gesuche', [$fieldids['fahrttyp'] => ['AND' => [['', 'ANY_OF', ['2']]]]]),
];
cli_writeln('Created ' . count($filterids) . ' filters.');

// View templates.

$stepnames = [
    1 => 'Schritt 1 – Fahrttyp und Route',
    2 => 'Schritt 2 – Termin',
    3 => 'Schritt 3 – Fahrzeug',
    4 => 'Schritt 4 – Mitnahme',
    5 => 'Schritt 5 – Übersicht und absenden',
];
$steptitles = [1 => 'Route', 2 => 'Termin', 3 => 'Fahrzeug', 4 => 'Mitnahme', 5 => 'Übersicht'];

// The badges of completed steps link back to that step with the same entry, which is
// what makes the chain navigable in both directions.
$stepper = function (int $current) use ($stepnames, $steptitles): string {
    $badges = '';
    foreach ($steptitles as $number => $title) {
        $caption = $number . '. ' . $title;
        if ($number < $current) {
            $badges .= '##viewsesslink:' . $stepnames[$number] . ';' . $caption
                . ';editentries=##entryid##|eids=##entryid##;badge rounded-pill bg-success p-2 me-2 mb-1##';
        } else if ($number === $current) {
            $badges .= '<span class="badge rounded-pill bg-primary p-2 me-2 mb-1">' . $caption . '</span>';
        } else {
            $badges .= '<span class="badge rounded-pill bg-secondary opacity-50 p-2 me-2 mb-1">' . $caption . '</span>';
        }
    }

    $percent = (int) round($current / count($steptitles) * 100);

    return '<div class="d-flex flex-wrap mb-2">' . $badges . '</div>'
        . '<div class="progress mb-4" style="height: 8px;">'
        . '<div class="progress-bar bg-primary" style="width: ' . $percent . '%;">&nbsp;</div></div>';
};

$stepcard = function (int $number, string $heading, string $lead, string $body, string $submit)
 use ($stepper, $steptitles): string {
    return '<div class="card shadow-sm border-0 my-4 mx-auto" style="max-width: 820px;">'
        . '<div class="card-header bg-white border-0 pt-4 px-4 pb-0">'
        . '<div class="text-uppercase text-primary fw-bold small mb-1">Mitfahrbörse · Schritt '
        . $number . ' von ' . count($steptitles) . '</div>'
        . '<h3 class="fw-bold mb-3">' . $heading . '</h3>'
        . $stepper($number)
        . '</div>'
        . '<div class="card-body px-4 pb-4">'
        . ($lead !== '' ? '<p class="text-muted">' . $lead . '</p>' : '')
        . $body
        . '<div class="d-flex justify-content-end gap-2 mt-4">' . $submit . '</div>'
        . '</div></div>';
};

// A field tag carrying its behavior and its layout.
$tag = function (string $key, string $behavior = '') use ($fielddefs, $layoutnames): string {
    return '[[' . $fielddefs[$key]['name'] . '|' . $behavior . '|' . $layoutnames[$key] . ']]';
};

$section = function (string $inner = '##entries##'): string {
    return '<div class="container py-4">' . $inner . '</div>';
};

// The card used to present a ride in the browse and search views. The surrounding
// column comes from the view's own entry wrapper (param3), so the card only brings
// what is inside it.
$ridecard = '<div class="card h-100 shadow-sm border-0">'
    . '<div class="card-body">'
    . '<div class="d-flex justify-content-between align-items-center mb-2">'
    . '<span class="badge bg-primary">[[' . $fielddefs['fahrttyp']['name'] . ']]</span>'
    . '<span class="small text-muted">##author:name##</span></div>'
    . $tag('route')
    . $tag('fahrtmuster') . $tag('wochentage') . $tag('abfahrtszeit') . $tag('ankunftszeit')
    . $tag('sitzplaetze') . $tag('fahrzeugart') . $tag('fahrzeug') . $tag('kraftstoff')
    . $tag('gepaeck') . $tag('mitfahrende') . $tag('fahrkosten') . $tag('fixbetrag')
    . $tag('bemerkungen')
    . '</div>'
    . '<div class="card-footer bg-transparent border-0 d-flex gap-2">##edit####delete##</div>'
    . '</div>';

// Views.

$viewids = [];

// Every view is a grid view; the entry wrapper is switched off ('none') because the
// templates below bring their own Bootstrap grid.
$createview = function (string $key, array $data) use ($dlx, &$viewids): void {
    $view = $dlx->get_view('grid', 0, false);
    $viewids[$key] = (int) $view->add((object) array_merge(['param3' => 'none'], $data));
};

$createview('portal', [
    'name' => 'Mitfahrbörse',
    'description' => 'Startseite der Mitfahrbörse',
    'visible' => 7,
    'perpage' => 0,
    'filter' => 0,
    'esection' => $section(
        '<div class="p-5 mb-4 bg-white border rounded-4 shadow-sm text-center">'
        . '<h1 class="display-6 fw-bold text-primary mb-3">Mitfahrbörse</h1>'
        . '<p class="lead text-muted mx-auto" style="max-width: 640px;">Gemeinsam fahren statt allein: '
        . 'Tragen Sie Ihre Fahrt ein oder suchen Sie eine Mitfahrgelegenheit. Passen ein Angebot und '
        . 'ein Gesuch zusammen, werden beide Seiten automatisch benachrichtigt.</p></div>'
        . '<div class="row g-4">'
        . '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3"><div class="card-body">'
        . '<h3 class="card-title fw-bold mb-3">Fahrt eintragen</h3>'
        . '<p class="card-text text-secondary">Route, Termin und Fahrzeug in fünf Schritten eintragen – '
        . 'als Angebot oder als Gesuch.</p></div>'
        . '<div class="card-footer bg-transparent border-0">'
        . '##viewsesslink:' . $stepnames[1] . ';Fahrt eintragen;new=1;btn btn-primary w-100 py-2 fw-bold##'
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
        . '##viewlink:Meine Fahrten;Meine Fahrten;;btn btn-outline-primary w-100 py-2##'
        . '</div></div></div>'
        . '</div>'
    ),
    'eparam2' => '',
]);

$createview('step1', [
    'name' => $stepnames[1],
    'description' => '',
    'visible' => 7,
    'perpage' => 0,
    'filter' => $filterids['own'],
    'permittedfilters' => [$filterids['own']],
    'param9' => 1,
    'esection' => $section(),
    'eparam2' => $stepcard(
        1,
        'Fahrttyp und Route',
        'Bieten Sie eine Fahrt an oder suchen Sie eine Mitfahrgelegenheit? Tragen Sie anschließend '
            . 'Start, Zwischenstopps und Ziel ein.',
        $tag('fahrttyp', 'pflicht') . $tag('route', 'pflicht') . $tag('umweg') . $tag('fahrtmuster', 'pflicht'),
        '##submit:text=Weiter_zum_Termin##'
    ),
]);

$createview('step2', [
    'name' => $stepnames[2],
    'description' => '',
    'visible' => 7,
    'perpage' => 0,
    'filter' => $filterids['own'],
    'permittedfilters' => [$filterids['own']],
    'param9' => 1,
    'esection' => $section(),
    'eparam2' => $stepcard(
        2,
        'Wann fahre ich?',
        'Bei einer einmaligen Fahrt gehören Datum und Uhrzeit an den Start- und den Zielstopp der Route – '
            . 'nur so findet der Abgleich Fahrten zur passenden Zeit. Regelmäßige Fahrten beschreiben Sie '
            . 'über Wochentage und Uhrzeiten.',
        $tag('route', 'einmalig')
            . $tag('wochentage', 'regelmaessig')
            . $tag('abfahrtszeit', 'regelmaessig')
            . $tag('ankunftszeit', 'regelmaessig'),
        '##submit:text=Weiter_zum_Fahrzeug##'
    ),
]);

$createview('step3', [
    'name' => $stepnames[3],
    'description' => '',
    'visible' => 7,
    'perpage' => 0,
    'filter' => $filterids['own'],
    'permittedfilters' => [$filterids['own']],
    'param9' => 1,
    'esection' => $section(),
    'eparam2' => $stepcard(
        3,
        'Womit fahre ich?',
        'Diese Angaben brauchen nur Fahrten, die angeboten werden. Wer eine Mitfahrgelegenheit sucht, '
            . 'geht direkt weiter.',
        $tag('fahrzeugart', 'angebotpflicht') . $tag('fahrzeug', 'angebot') . $tag('kennzeichen', 'angebot')
            . $tag('kraftstoff', 'angebot') . $tag('sitzplaetze', 'angebotpflicht') . $tag('gepaeck', 'angebot'),
        '##submit:text=Weiter_zur_Mitnahme##'
    ),
]);

$createview('step4', [
    'name' => $stepnames[4],
    'description' => '',
    'visible' => 7,
    'perpage' => 0,
    'filter' => $filterids['own'],
    'permittedfilters' => [$filterids['own']],
    'param9' => 1,
    'esection' => $section(),
    'eparam2' => $stepcard(
        4,
        'Wen nehme ich mit?',
        'Wer eine Mitfahrgelegenheit sucht, kann hier direkt zur Übersicht weitergehen.',
        $tag('mitfahrende', 'angebot') . $tag('fahrkosten', 'angebot') . $tag('fixbetrag', 'festbetrag')
            . $tag('bemerkungen'),
        '##submit:text=Weiter_zur_Übersicht##'
    ),
]);

$createview('step5', [
    'name' => $stepnames[5],
    'description' => '',
    'visible' => 7,
    'perpage' => 0,
    'filter' => $filterids['own'],
    'permittedfilters' => [$filterids['own']],
    'param9' => 0,
    'esection' => $section(),
    'eparam2' => '<div class="card shadow-sm border-0 my-4 mx-auto" style="max-width: 820px;">'
        . '<div class="card-header bg-white border-0 pt-4 px-4 pb-0">'
        . '<div class="text-uppercase text-primary fw-bold small mb-1">Mitfahrbörse · Schritt 5 von 5</div>'
        . '<h3 class="fw-bold mb-3">Übersicht</h3>'
        . $stepper(5)
        . '</div><div class="card-body px-4 pb-4">'
        . '<p class="text-muted">Bitte prüfen Sie Ihre Angaben. Mit „Fahrt speichern" ist die Fahrt '
        . 'veröffentlicht und wird ab sofort automatisch abgeglichen.</p>'
        . $tag('fahrttyp', 'nurlesen') . $tag('route', 'nurlesen') . $tag('umweg', 'nurlesen')
        . $tag('fahrtmuster', 'nurlesen') . $tag('wochentage', 'nurlesenregelmaessig')
        . $tag('abfahrtszeit', 'nurlesenregelmaessig') . $tag('ankunftszeit', 'nurlesenregelmaessig')
        . $tag('fahrzeugart', 'nurlesenangebot') . $tag('fahrzeug', 'nurlesenangebot')
        . $tag('kennzeichen', 'nurlesenangebot') . $tag('kraftstoff', 'nurlesenangebot')
        . $tag('sitzplaetze', 'nurlesenangebot') . $tag('gepaeck', 'nurlesenangebot')
        . $tag('mitfahrende', 'nurlesenangebot') . $tag('fahrkosten', 'nurlesenangebot')
        . $tag('fixbetrag', 'nurlesenfestbetrag') . $tag('bemerkungen', 'nurlesen')
        . '<div class="d-flex justify-content-end gap-2 mt-4">'
        . '##submit:text=Fahrt_speichern##</div>'
        . '</div></div>',
]);

$createview('search', [
    'param3' => 'col-12 col-md-6',
    'name' => 'Fahrt suchen',
    'description' => '',
    'visible' => 7,
    'perpage' => 20,
    'filter' => $filterids['offers'],
    // Allow all filters: a view that forces its filter hides the search form
    // (base::is_forcing_filter()) and a permitted-filters whitelist blocks ad-hoc
    // filters outright (base::set_filter()), which is exactly what a customfilter is.
    // The offers filter above still applies until the visitor searches.
    'param5' => 1,
    'esection' => $section(
        '<h2 class="fw-bold mb-3">Fahrt suchen</h2>'
        . '<p class="text-muted">Tragen Sie ein, wo Sie losfahren und wo Sie hinwollen. Gefunden werden '
        . 'auch Fahrten, bei denen Sie nur ein Stück mitfahren.</p>'
        . '##customfilter:Fahrt suchen##'
        . '<div class="mt-3">##entries##</div>'
        . '<div class="alert alert-info mt-4">Nichts Passendes gefunden? '
        . '##viewsesslink:' . $stepnames[1] . ';Tragen Sie ein Gesuch ein;new=1;alert-link## – '
        . 'sobald ein passendes Angebot eingetragen wird, werden Sie benachrichtigt.</div>'
    ),
    'eparam2' => $ridecard,
]);

$createview('mine', [
    'param3' => 'col-12 col-md-6',
    'name' => 'Meine Fahrten',
    'description' => '',
    'visible' => 7,
    'perpage' => 20,
    'filter' => $filterids['own'],
    'esection' => $section(
        '<h2 class="fw-bold mb-3">Meine Fahrten</h2>'
        . '<div class="mb-3">##viewsesslink:' . $stepnames[1]
        . ';Neue Fahrt eintragen;new=1;btn btn-primary##</div>'
        . '##entries##'
    ),
    'eparam2' => $ridecard,
]);

$createview('all', [
    'param3' => 'col-12 col-md-6',
    'name' => 'Alle Fahrten',
    'description' => '',
    'visible' => 7,
    'perpage' => 20,
    'filter' => 0,
    'esection' => $section('<h2 class="fw-bold mb-3">Alle Fahrten</h2>##entries##'),
    'eparam2' => $ridecard,
]);

// The chain: each step points at the next one, the portal at the first step.
$chain = [
    'portal' => 'step1',
    'step1' => 'step2',
    'step2' => 'step3',
    'step3' => 'step4',
    'step4' => 'step5',
    'step5' => 'mine',
];
foreach ($chain as $from => $to) {
    $DB->set_field('datalynx_views', 'param10', $viewids[$to], ['id' => $viewids[$from]]);
}
$DB->set_field('datalynx', 'defaultview', $viewids['portal'], ['id' => $dataid]);
cli_writeln('Created ' . count($viewids) . ' views and wired the step chain.');

// The search form.

$customfilterfields = [];
foreach (['fahrttyp', 'route', 'fahrtmuster', 'wochentage', 'abfahrtszeit'] as $key) {
    $customfilterfields[$fieldids[$key]] = [
        'name' => $fielddefs[$key]['name'],
        'sortable' => $key === 'abfahrtszeit' ? 1 : 0,
    ];
}
$DB->insert_record('datalynx_customfilters', (object) [
    'dataid' => $dataid,
    'name' => 'Fahrt suchen',
    'description' => 'Suche nach Fahrten entlang einer Strecke',
    'visible' => 1,
    'fulltextsearch' => 0,
    'timecreated' => 0,
    'timecreatedsortable' => 0,
    'timemodified' => 0,
    'timemodifiedsortable' => 0,
    'authorsearch' => 0,
    'approve' => 0,
    'status' => 0,
    'fieldlist' => json_encode($customfilterfields),
]);
cli_writeln('Created the search form.');

// The ride matching rule.

$ruleid = $DB->insert_record('datalynx_rules', (object) [
    'dataid' => $dataid,
    'type' => 'ridematch',
    'name' => 'Passende Fahrt gefunden',
    'description' => 'Benachrichtigt beide Seiten, sobald ein Angebot und ein Gesuch zusammenpassen.',
    'enabled' => 1,
    'param1' => json_encode(['entry_created', 'entry_updated']),
    'param2' => $fieldids['route'],
    'param3' => $fieldids['fahrttyp'],
    'param4' => $offerlabel,
    'param5' => $requestlabel,
    'param6' => 10,
    'param7' => 24,
]);
cli_writeln("Created the ride matching rule ($ruleid).");

// Activity level CSS and JS: the detour field becomes a slider.

$js = <<<'JS'
// Ride sharing: turn the detour number field into a slider with a live readout.
(function() {
    var LABEL = ' km';

    var enhance = function(input) {
        if (input.dataset.rideSlider) {
            return;
        }
        input.dataset.rideSlider = '1';

        var slider = document.createElement('input');
        slider.type = 'range';
        slider.min = '0';
        slider.max = '50';
        slider.step = '1';
        slider.className = 'form-range flex-grow-1';
        slider.value = input.value === '' ? '5' : input.value;

        var readout = document.createElement('output');
        readout.className = 'badge bg-primary align-self-center';
        readout.textContent = slider.value + LABEL;

        var wrapper = document.createElement('div');
        wrapper.className = 'd-flex gap-3 align-items-center';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(slider);
        wrapper.appendChild(readout);

        // The number input keeps the value; hiding rather than replacing it means the
        // form still submits exactly what datalynx expects.
        input.type = 'hidden';
        input.value = slider.value;
        wrapper.appendChild(input);

        slider.addEventListener('input', function() {
            input.value = slider.value;
            readout.textContent = slider.value + LABEL;
        });
    };

    var init = function() {
        var labels = document.querySelectorAll('.datalynx-ride-field label.form-label');
        Array.prototype.forEach.call(labels, function(label) {
            if (label.textContent.indexOf('Umweg') !== 0) {
                return;
            }
            var input = label.parentNode.querySelector('input[type="text"], input[type="number"]');
            if (input) {
                enhance(input);
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
JS;

$css = <<<'CSS'
/* Ride sharing activity */
.datalynx-ride-field .form-text {
    font-size: 0.85rem;
}
.datalynx-ride-field .datalynx-itinerary-picker {
    margin-top: 0.25rem;
}
.datalynx-ride-field label.form-label {
    margin-bottom: 0.25rem;
}
CSS;

$DB->update_record('datalynx', (object) [
    'id' => $dataid,
    'js' => $js,
    'css' => $css,
    'timemodified' => time(),
]);

rebuild_course_cache($course->id, true);
purge_all_caches();

cli_writeln('');
cli_writeln('Done. Open: ' . (new moodle_url('/mod/datalynx/view.php', ['id' => $cmid]))->out(false));
cli_writeln("Instance id: $dataid");
