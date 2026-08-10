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
 * Fills a ride sharing activity with example offers and requests.
 *
 * Entries are written through the fields' own update_content(), so the derived
 * itinerary columns and the waypoint index are produced exactly as they are when a
 * traveller submits the form. Afterwards the matching sweep is run for every request,
 * which is the same work the ad-hoc task does after a real save.
 *
 * Usage:
 *   php mod/datalynx/cli/seed_ridesharing_entries.php --dataid=123
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
$dlx = new \mod_datalynx\datalynx($dataid);
$courseid = (int) $dlx->course->id;

$fields = [];
foreach ($DB->get_records('datalynx_fields', ['dataid' => $dataid]) as $field) {
    $fields[$field->name] = $dlx->get_fields(null, false, true)[$field->id];
}
foreach (['Fahrttyp', 'Route', 'Fahrtmuster'] as $required) {
    if (!isset($fields[$required])) {
        cli_error("Instance $dataid has no field '$required' - is this a ride sharing activity?");
    }
}

if (!empty($options['clear'])) {
    $entryids = $DB->get_fieldset_select('datalynx_entries', 'id', 'dataid = ?', [$dataid]);
    if ($entryids) {
        [$insql, $params] = $DB->get_in_or_equal($entryids);
        $DB->delete_records_select('datalynx_contents', "entryid $insql", $params);
        $DB->delete_records_select('datalynx_waypoints', "entryid $insql", $params);
        $DB->delete_records_select('datalynx_entries', "id $insql", $params);
        $DB->delete_records_select(
            'datalynx_ride_matches',
            "offerentryid $insql OR requestentryid $insql",
            array_merge($params, $params)
        );
        cli_writeln('Deleted ' . count($entryids) . ' existing entries.');
    }
}

// Real places, so the distances and the corridor matching mean something.
$places = [
    'wien' => ['Karlsplatz, 1010 Wien', 48.2004, 16.3696],
    'wienhbf' => ['Wien Hauptbahnhof, 1100 Wien', 48.1854, 16.3760],
    'wrneustadt' => ['Hauptplatz, 2700 Wiener Neustadt', 47.8149, 16.2372],
    'graz' => ['Hauptplatz, 8010 Graz', 47.0710, 15.4385],
    'stpoelten' => ['Rathausplatz, 3100 St. Pölten', 48.2047, 15.6256],
    'linz' => ['Hauptplatz, 4020 Linz', 48.3064, 14.2861],
    'salzburg' => ['Residenzplatz, 5020 Salzburg', 47.7982, 13.0470],
    'klagenfurt' => ['Neuer Platz, 9020 Klagenfurt', 46.6247, 14.3053],
    'brno' => ['Náměstí Svobody, Brno', 49.1951, 16.6068],
    'innsbruck' => ['Maria-Theresien-Straße, 6020 Innsbruck', 47.2654, 11.3927],
    'eisenstadt' => ['Hauptstraße, 7000 Eisenstadt', 47.8457, 16.5200],
    'villach' => ['Hauptplatz, 9500 Villach', 46.6103, 13.8558],
    'udine' => ['Piazza Libertà, Udine', 46.0637, 13.2350],
    'baden' => ['Hauptplatz, 2500 Baden', 48.0059, 16.2340],
    'moedling' => ['Hauptstraße, 2340 Mödling', 48.0857, 16.2833],
];

// Authors: whoever is enrolled in the course, admin last.
$users = array_values(get_enrolled_users(
    \context_course::instance($courseid),
    'mod/datalynx:writeentry',
    0,
    'u.id, u.firstname, u.lastname',
    'u.lastname ASC',
    0,
    30
));
if (count($users) < 4) {
    cli_error("Course $courseid has too few enrolled users to seed rides.");
}

$day = DAYSECS;
$today = usergetmidnight(time());

/*
 * A ride: who drives it, which stops, and the values of the remaining fields.
 * "times" are offsets in seconds from midnight today, applied to the first and the
 * last stop - that is where a one-off journey carries its departure, and it is what
 * the matcher reads for its time window.
 */
$rides = [
    // Offers.
    [
        'type' => 1,
        'stops' => ['wien', 'wrneustadt', 'graz'],
        'muster' => 2,
        'wochentage' => [1, 3, 5],
        'abfahrt' => 15, // 07:00 in half hour slots, 1 = 00:00.
        'ankunft' => 20, // 09:30.
        'fahrzeugart' => 4, // Kombi.
        'fahrzeug' => 'Škoda Octavia Combi',
        'kennzeichen' => 'W-12345A',
        'kraftstoff' => 2, // Hybrid.
        'plaetze' => 3,
        'gepaeck' => 3, // 1 Koffer.
        'mitfahrende' => 1,
        'kosten' => 1, // Aufteilen.
        'umweg' => 10,
        'bemerkung' => 'Fahre jeden Montag, Mittwoch und Freitag zur Uni nach Graz. '
            . 'Zusteigen in Wiener Neustadt ist kein Problem.',
    ],
    [
        'type' => 1,
        'stops' => ['wienhbf', 'stpoelten', 'linz'],
        'muster' => 1,
        'times' => [3 * $day + 8 * HOURSECS, 3 * $day + 10 * HOURSECS + 30 * MINSECS],
        'fahrzeugart' => 2,
        'fahrzeug' => 'BMW 320d',
        'kennzeichen' => 'W-88112B',
        'kraftstoff' => 1,
        'plaetze' => 2,
        'gepaeck' => 4,
        'mitfahrende' => 1,
        'kosten' => 2, // Festbetrag.
        'fixbetrag' => 15,
        'umweg' => 5,
        'bemerkung' => 'Einmalige Fahrt zur Konferenz, Rückfahrt am selben Abend.',
    ],
    [
        'type' => 1,
        'stops' => ['graz', 'klagenfurt'],
        'muster' => 2,
        'wochentage' => [2, 4],
        'abfahrt' => 33, // 16:00.
        'ankunft' => 37, // 18:00.
        'fahrzeugart' => 5,
        'fahrzeug' => 'Hyundai Tucson',
        'kennzeichen' => 'G-4711C',
        'kraftstoff' => 3, // E-Auto.
        'plaetze' => 4,
        'gepaeck' => 4,
        'mitfahrende' => 1,
        'kosten' => 3, // Kostenlos.
        'umweg' => 15,
        'bemerkung' => 'Dienstag und Donnerstag nach der Vorlesung.',
    ],
    [
        'type' => 1,
        'stops' => ['linz', 'salzburg'],
        'muster' => 1,
        'times' => [5 * $day + 9 * HOURSECS, 5 * $day + 10 * HOURSECS + 45 * MINSECS],
        'fahrzeugart' => 1,
        'fahrzeug' => 'VW Polo',
        'kennzeichen' => 'L-2020D',
        'kraftstoff' => 1,
        'plaetze' => 3,
        'gepaeck' => 2, // Handtasche.
        'mitfahrende' => 3, // Weiblich.
        'kosten' => 1,
        'umweg' => 5,
        'bemerkung' => 'Nichtraucherfahrzeug, nehme gerne Studentinnen mit.',
    ],
    [
        'type' => 1,
        'stops' => ['wien', 'brno'],
        'muster' => 1,
        'times' => [7 * $day + 6 * HOURSECS + 30 * MINSECS, 7 * $day + 8 * HOURSECS + 30 * MINSECS],
        'fahrzeugart' => 3,
        'fahrzeug' => 'Mazda MX-5',
        'kennzeichen' => 'W-77777E',
        'kraftstoff' => 1,
        'plaetze' => 1,
        'gepaeck' => 2,
        'mitfahrende' => 1,
        'kosten' => 2,
        'fixbetrag' => 20,
        'umweg' => 8,
        'bemerkung' => 'Wochenendtrip nach Brünn, Platz für eine Person plus Handgepäck.',
    ],
    [
        'type' => 1,
        'stops' => ['wrneustadt', 'moedling', 'wien'],
        'muster' => 2,
        'wochentage' => [1, 2, 3, 4, 5],
        'abfahrt' => 14, // 06:30.
        'ankunft' => 16, // 07:30.
        'fahrzeugart' => 7, // Kleinbus.
        'fahrzeug' => 'Ford Transit',
        'kennzeichen' => 'WN-9001F',
        'kraftstoff' => 1,
        'plaetze' => 8,
        'gepaeck' => 4,
        'mitfahrende' => 1,
        'kosten' => 1,
        'umweg' => 12,
        'bemerkung' => 'Tägliche Pendelfahrt in die Stadt, Zustieg in Mödling möglich.',
    ],
    [
        'type' => 1,
        'stops' => ['innsbruck', 'salzburg'],
        'muster' => 1,
        'times' => [10 * $day + 13 * HOURSECS, 10 * $day + 15 * HOURSECS + 15 * MINSECS],
        'fahrzeugart' => 6, // Mini Van.
        'fahrzeug' => 'Renault Scenic',
        'kennzeichen' => 'I-3003G',
        'kraftstoff' => 2,
        'plaetze' => 4,
        'gepaeck' => 4,
        'mitfahrende' => 1,
        'kosten' => 1,
        'umweg' => 20,
        'bemerkung' => 'Viel Platz für Ski und Gepäck.',
    ],
    [
        'type' => 1,
        'stops' => ['wien', 'baden', 'eisenstadt'],
        'muster' => 2,
        'wochentage' => [1, 2, 3],
        'abfahrt' => 17, // 08:00.
        'ankunft' => 19, // 09:00.
        'fahrzeugart' => 1,
        'fahrzeug' => 'Toyota Yaris',
        'kennzeichen' => 'W-5150H',
        'kraftstoff' => 2,
        'plaetze' => 2,
        'gepaeck' => 2,
        'mitfahrende' => 1,
        'kosten' => 1,
        'umweg' => 5,
        'bemerkung' => 'Montag bis Mittwoch ins Burgenland.',
    ],

    // Requests.
    [
        'type' => 2,
        'stops' => ['wrneustadt', 'graz'],
        'muster' => 2,
        'wochentage' => [1, 5],
        'abfahrt' => 15,
        'umweg' => 5,
        'bemerkung' => 'Suche eine Mitfahrgelegenheit für Montag und Freitag, gerne auch nur einfach.',
    ],
    [
        'type' => 2,
        'stops' => ['stpoelten', 'linz'],
        'muster' => 1,
        'times' => [3 * $day + 8 * HOURSECS + 30 * MINSECS, null],
        'umweg' => 5,
        'bemerkung' => 'Muss zur selben Konferenz, kann in St. Pölten zusteigen.',
    ],
    [
        'type' => 2,
        'stops' => ['wien', 'graz'],
        'muster' => 2,
        'wochentage' => [3],
        'abfahrt' => 15,
        'umweg' => 10,
        'bemerkung' => 'Mittwochs zur Vorlesung nach Graz.',
    ],
    [
        'type' => 2,
        'stops' => ['villach', 'udine'],
        'muster' => 1,
        'times' => [14 * $day + 7 * HOURSECS, null],
        'umweg' => 5,
        'bemerkung' => 'Fährt zufällig jemand nach Italien?',
    ],
];

$setcontent = function (int $entryid, string $fieldname, $value) use ($DB, $fields, $dataid): void {
    if (!isset($fields[$fieldname]) || $value === null || $value === []) {
        return;
    }
    $field = $fields[$fieldname];
    $fieldid = $field->id();

    $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);
    if ($existing = $DB->get_record('datalynx_contents', ['fieldid' => $fieldid, 'entryid' => $entryid])) {
        $entry->{"c{$fieldid}_id"} = $existing->id;
        $entry->{"c{$fieldid}_content"} = $existing->content;
    }

    $field->update_content($entry, is_array($value) && isset($value['waypoints']) ? $value : [$value]);
};

$router = \mod_datalynx\local\map\router_factory::instance();
if ($router === null) {
    cli_writeln('No routing service configured: rides will show straight-line distance only.');
}

$created = ['offers' => [], 'requests' => []];
foreach ($rides as $index => $ride) {
    $author = $users[$index % count($users)];

    $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
        'dataid' => $dataid,
        'userid' => $author->id,
        'groupid' => 0,
        'approved' => 1,
        'status' => 0,
        'timecreated' => time(),
        'timemodified' => time(),
    ]);

    $waypoints = [];
    $laststop = count($ride['stops']) - 1;
    foreach ($ride['stops'] as $position => $key) {
        [$address, $lat, $lng] = $places[$key];
        $time = null;
        if (!empty($ride['times'])) {
            if ($position === 0 && isset($ride['times'][0])) {
                $time = $today + $ride['times'][0];
            } else if ($position === $laststop && isset($ride['times'][1])) {
                $time = $today + $ride['times'][1];
            }
        }
        $waypoints[] = ['address' => $address, 'lat' => $lat, 'lng' => $lng, 'time' => $time];
    }

    // The entry form has the picker route a journey while it is being edited; here
    // the same routing service is asked directly, so seeded rides show a travel
    // time too rather than only a straight-line distance.
    $route = null;
    if ($router !== null) {
        $route = $router->route(array_map(
            static fn(array $stop): array => [$stop['lat'], $stop['lng']],
            $waypoints
        ));
    }

    $journeyvalues = ['waypoints' => json_encode($waypoints)];
    if ($route !== null) {
        $journeyvalues['route'] = $route->to_json();
    }
    $setcontent($entryid, 'Route', $journeyvalues);
    $setcontent($entryid, 'Fahrttyp', $ride['type']);
    $setcontent($entryid, 'Fahrtmuster', $ride['muster']);
    $setcontent($entryid, 'Umweg', $ride['umweg'] ?? null);
    $setcontent($entryid, 'Wochentage', $ride['wochentage'] ?? null);
    $setcontent($entryid, 'Abfahrtszeit', $ride['abfahrt'] ?? null);
    $setcontent($entryid, 'Ankunftszeit', $ride['ankunft'] ?? null);
    $setcontent($entryid, 'Fahrzeugart', $ride['fahrzeugart'] ?? null);
    $setcontent($entryid, 'Fahrzeug', $ride['fahrzeug'] ?? null);
    $setcontent($entryid, 'Kennzeichen', $ride['kennzeichen'] ?? null);
    $setcontent($entryid, 'Kraftstoff', $ride['kraftstoff'] ?? null);
    $setcontent($entryid, 'Freie Plätze', $ride['plaetze'] ?? null);
    $setcontent($entryid, 'Gepäck', $ride['gepaeck'] ?? null);
    $setcontent($entryid, 'Mitfahrende', $ride['mitfahrende'] ?? null);
    $setcontent($entryid, 'Fahrkosten', $ride['kosten'] ?? null);
    $setcontent($entryid, 'Fixbetrag', $ride['fixbetrag'] ?? null);
    $setcontent($entryid, 'Bemerkungen', $ride['bemerkung'] ?? null);

    $label = implode(' → ', $ride['stops']);
    if ($ride['type'] === 1) {
        $created['offers'][] = $entryid;
        cli_writeln("Angebot   #$entryid  $label  ({$author->firstname} {$author->lastname})");
    } else {
        $created['requests'][] = $entryid;
        cli_writeln("Gesuch    #$entryid  $label  ({$author->firstname} {$author->lastname})");
    }
}

cli_writeln('');
cli_writeln('Created ' . count($created['offers']) . ' offers and ' . count($created['requests']) . ' requests.');

// Run the matching sweep, exactly as the ad-hoc task does after a real save.

if (empty($options['nomatch'])) {
    $rules = $DB->get_records_select(
        'datalynx_rules',
        "dataid = :dataid AND type = 'eventnotification' AND " . $DB->sql_like('param9', ':key'),
        ['dataid' => $dataid, 'key' => '%_matchcriteria%']
    );
    if (!$rules) {
        cli_writeln('No matching rule configured, skipping the sweep.');
    } else {
        $dlx = new \mod_datalynx\datalynx($dataid);
        foreach ($created['requests'] as $entryid) {
            foreach ($rules as $record) {
                $rule = new \datalynxrule_eventnotification\rule($dlx, $record);
                $rule->run_matching([
                    'eventname' => 'entry_created',
                    'entryid' => $entryid,
                    'objectid' => $entryid,
                    'teamfieldid' => 0,
                ]);
            }
        }
        cli_writeln('Ran the matching sweep for ' . count($created['requests']) . ' Gesuche.');
    }
}
