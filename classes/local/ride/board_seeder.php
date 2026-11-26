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

namespace mod_datalynx\local\ride;

use mod_datalynx\datalynx;
use stdClass;

/**
 * Example rides for a ride sharing board built by {@see board_builder}.
 *
 * Real places, so that distances and the corridor matching mean something. The authors are the
 * users enrolled in the course. After seeding, the matching sweep runs for every request, as the
 * ad-hoc task does after a real save.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class board_seeder {
    /** @var array[] Places: key => [address, lat, lng]. */
    const PLACES = [
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

    /** @var datalynx The board. */
    protected datalynx $dlx;

    /** @var array Field objects by name. */
    protected array $fields = [];

    /** @var callable|null Receives a line of progress. */
    protected $log;

    /**
     * Constructor.
     *
     * @param int $dataid
     * @param callable|null $log
     */
    public function __construct(int $dataid, ?callable $log = null) {
        $this->dlx = new datalynx($dataid);
        foreach ($this->dlx->get_fields(null, false, true) as $field) {
            $this->fields[$field->field->name] = $field;
        }
        $this->log = $log;
    }

    /**
     * A schedule once on the day the given number of days from today.
     *
     * @param int $days
     * @param string $start HH:MM
     * @param string|null $end HH:MM
     * @return string JSON
     */
    protected static function once(int $days, string $start, ?string $end = null): string {
        $date = date('Y-m-d', strtotime("+$days days", usergetmidnight(time())));
        return (new schedule(schedule::MODE_ONCE, $date, [], self::minute($start), $end ? self::minute($end) : null))
            ->to_json();
    }

    /**
     * A weekly schedule from today for some weeks.
     *
     * @param int[] $days ISO weekdays
     * @param string $start HH:MM
     * @param string|null $end HH:MM
     * @param int $weeks how long it runs
     * @return string JSON
     */
    protected static function weekly(array $days, string $start, ?string $end = null, int $weeks = 8): string {
        $today = usergetmidnight(time());
        return (new schedule(
            schedule::MODE_WEEKLY,
            '',
            $days,
            self::minute($start),
            $end ? self::minute($end) : null,
            date('Y-m-d', $today),
            date('Y-m-d', strtotime("+$weeks weeks", $today))
        ))->to_json();
    }

    /**
     * Minutes after midnight of HH:MM.
     *
     * @param string $time
     * @return int
     */
    protected static function minute(string $time): int {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        return $hours * 60 + $minutes;
    }

    /**
     * The example rides: offers first, then requests. Option fields take option labels.
     *
     * @return array[]
     */
    public static function rides(): array {
        return [
            ['type' => board_builder::OFFER, 'stops' => ['wien', 'wrneustadt', 'graz'],
                'termin' => self::weekly([1, 3, 5], '07:00', '09:30'),
                'marke' => 'Skoda', 'modell' => 'Octavia Combi', 'kennzeichen' => 'W-12345A', 'plaetze' => 3,
                'gepaeck' => '1 Koffer', 'kosten' => 'Aufteilen', 'umweg' => 10,
                'bemerkung' => 'Fahre jeden Montag, Mittwoch und Freitag zur Uni nach Graz. Zusteigen in Wiener Neustadt '
                    . 'ist kein Problem.'],
            ['type' => board_builder::OFFER, 'stops' => ['wienhbf', 'stpoelten', 'linz'],
                'termin' => self::once(3, '08:00', '10:30'),
                'marke' => 'BMW', 'modell' => '320d', 'kennzeichen' => 'W-88112B', 'plaetze' => 2, 'gepaeck' => '2 Koffer',
                'kosten' => 'Festbetrag', 'fixbetrag' => 15, 'umweg' => 5,
                'bemerkung' => 'Einmalige Fahrt zur Konferenz, Rückfahrt am selben Abend.'],
            ['type' => board_builder::OFFER, 'stops' => ['graz', 'klagenfurt'], 'termin' => self::weekly([2, 4], '16:00', '18:00'),
                'marke' => 'Hyundai', 'modell' => 'Tucson', 'kennzeichen' => 'G-4711C', 'plaetze' => 4, 'gepaeck' => '2 Koffer',
                'kosten' => 'kostenlos', 'umweg' => 15, 'bemerkung' => 'Dienstag und Donnerstag nach der Vorlesung.'],
            ['type' => board_builder::OFFER, 'stops' => ['linz', 'salzburg'], 'termin' => self::once(5, '09:00', '10:45'),
                'marke' => 'Volkswagen', 'modell' => 'Polo', 'kennzeichen' => 'L-2020D', 'plaetze' => 3,
                'gepaeck' => 'Handtasche', 'mitfahrende' => 'weiblich', 'kosten' => 'Aufteilen', 'umweg' => 5,
                'bemerkung' => 'Nichtraucherfahrzeug, nehme gerne Studentinnen mit.'],
            ['type' => board_builder::OFFER, 'stops' => ['wien', 'brno'], 'termin' => self::once(7, '06:30', '08:30'),
                'marke' => 'Mazda', 'modell' => 'MX-5', 'kennzeichen' => 'W-77777E', 'plaetze' => 1, 'gepaeck' => 'Handtasche',
                'kosten' => 'Festbetrag', 'fixbetrag' => 20, 'umweg' => 8,
                'bemerkung' => 'Wochenendtrip nach Brünn, Platz für eine Person plus Handgepäck.'],
            ['type' => board_builder::OFFER, 'stops' => ['wrneustadt', 'moedling', 'wien'],
                'termin' => self::weekly([1, 2, 3, 4, 5], '06:30', '07:30'), 'marke' => 'Ford', 'modell' => 'Transit',
                'kennzeichen' => 'WN-9001F', 'plaetze' => 8, 'gepaeck' => '2 Koffer', 'kosten' => 'Aufteilen', 'umweg' => 12,
                'bemerkung' => 'Tägliche Pendelfahrt in die Stadt, Zustieg in Mödling möglich.'],
            ['type' => board_builder::OFFER, 'stops' => ['innsbruck', 'salzburg'], 'termin' => self::once(10, '13:00', '15:15'),
                'marke' => 'Renault', 'modell' => 'Scenic', 'kennzeichen' => 'I-3003G', 'plaetze' => 4, 'gepaeck' => '2 Koffer',
                'kosten' => 'Aufteilen', 'umweg' => 20, 'bemerkung' => 'Viel Platz für Ski und Gepäck.'],
            ['type' => board_builder::OFFER, 'stops' => ['wien', 'baden', 'eisenstadt'],
                'termin' => self::weekly([1, 2, 3], '08:00', '09:00'), 'marke' => 'Toyota', 'modell' => 'Yaris',
                'kennzeichen' => 'W-5150H', 'plaetze' => 2, 'gepaeck' => 'Handtasche', 'kosten' => 'Aufteilen', 'umweg' => 5,
                'bemerkung' => 'Montag bis Mittwoch ins Burgenland.'],
            ['type' => board_builder::REQUEST, 'stops' => ['wrneustadt', 'graz'], 'termin' => self::weekly([1, 5], '07:00'),
                'umweg' => 5, 'bemerkung' => 'Suche eine Mitfahrgelegenheit für Montag und Freitag, gerne auch nur einfach.'],
            ['type' => board_builder::REQUEST, 'stops' => ['stpoelten', 'linz'], 'termin' => self::once(3, '08:30'),
                'umweg' => 5, 'bemerkung' => 'Muss zur selben Konferenz, kann in St. Pölten zusteigen.'],
            ['type' => board_builder::REQUEST, 'stops' => ['wien', 'graz'], 'termin' => self::weekly([3], '07:00'),
                'umweg' => 10, 'bemerkung' => 'Mittwochs zur Vorlesung nach Graz.'],
            ['type' => board_builder::REQUEST, 'stops' => ['villach', 'udine'], 'termin' => self::once(14, '07:00'),
                'umweg' => 5, 'bemerkung' => 'Fährt zufällig jemand nach Italien?'],
        ];
    }

    /**
     * Write a line of progress.
     *
     * @param string $line
     */
    protected function log(string $line): void {
        if ($this->log) {
            ($this->log)($line);
        }
    }

    /**
     * The authors: users enrolled in the course who may write entries.
     *
     * @return stdClass[]
     */
    protected function authors(): array {
        return array_values(get_enrolled_users(
            \context_course::instance($this->dlx->course->id),
            'mod/datalynx:writeentry',
            0,
            'u.id, u.firstname, u.lastname',
            'u.lastname ASC',
            0,
            30
        ));
    }

    /**
     * Store a value of a field on an entry, through the field.
     *
     * @param int $entryid
     * @param string $fieldname
     * @param mixed $value an option label for option fields; null to leave it empty
     */
    protected function set(int $entryid, string $fieldname, $value): void {
        global $DB;
        if ($value === null || !isset($this->fields[$fieldname])) {
            return;
        }
        $field = $this->fields[$fieldname];
        if (in_array($field->type, ['select', 'radiobutton'], true) && !is_int($value)) {
            $options = array_map('trim', explode("\n", (string) $field->field->param1));
            $position = array_search((string) $value, $options, true);
            if ($position === false) {
                throw new \coding_exception("Field $fieldname has no option '$value'");
            }
            $value = $position + 1;
        }
        $fieldid = $field->id();
        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid], '*', MUST_EXIST);
        if ($existing = $DB->get_record('datalynx_contents', ['fieldid' => $fieldid, 'entryid' => $entryid])) {
            $entry->{"c{$fieldid}_id"} = $existing->id;
            $entry->{"c{$fieldid}_content"} = $existing->content;
        }
        $field->update_content($entry, is_array($value) ? $value : [$value]);
    }

    /**
     * Create an entry of the given author.
     *
     * @param int $userid
     * @return int entry id
     */
    protected function new_entry(int $userid): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(),
            'userid' => $userid,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Seed the board.
     *
     * @param bool $match run the matching sweep afterwards
     * @return array ['offers' => int[], 'requests' => int[]]
     */
    public function seed(bool $match = true): array {
        $authors = $this->authors();
        if (count($authors) < 4) {
            throw new \moodle_exception('error', 'datalynx', '', null, 'The course has too few enrolled users to seed rides.');
        }
        $router = \mod_datalynx\local\map\router_factory::instance();
        $created = ['offers' => [], 'requests' => []];
        foreach (self::rides() as $index => $ride) {
            $author = $authors[$index % count($authors)];
            $entryid = $this->new_entry((int) $author->id);

            $waypoints = array_map(function (string $key): array {
                [$address, $lat, $lng] = self::PLACES[$key];
                return ['address' => $address, 'lat' => $lat, 'lng' => $lng];
            }, $ride['stops']);
            $route = ['waypoints' => json_encode($waypoints)];
            if ($router !== null) {
                $routed = $router->route(array_map(fn(array $stop): array => [$stop['lat'], $stop['lng']], $waypoints));
                if ($routed !== null) {
                    $route['route'] = $routed->to_json();
                }
            }
            $this->set($entryid, 'Route', $route);
            $this->set($entryid, 'Fahrttyp', $ride['type']);
            $this->set($entryid, 'Termin', ['schedule' => $ride['termin']]);
            $this->set($entryid, 'Umweg', $ride['umweg'] ?? null);
            $this->set($entryid, 'Bemerkungen', $ride['bemerkung'] ?? null);
            if ($ride['type'] === board_builder::OFFER) {
                $this->set($entryid, 'Marke', $ride['marke']);
                $this->set($entryid, 'Modell', $ride['modell']);
                $this->set($entryid, 'Kennzeichen', $ride['kennzeichen']);
                $this->set($entryid, 'Freie Plätze', (string) $ride['plaetze']);
                $this->set($entryid, 'Gepäck', $ride['gepaeck']);
                $this->set($entryid, 'Mitfahrende', $ride['mitfahrende'] ?? 'jedes Geschlecht');
                $this->set($entryid, 'Fahrkosten', $ride['kosten'] ?? null);
                $this->set($entryid, 'Fixbetrag', $ride['fixbetrag'] ?? null);
                // Billing variant: published, so the offer is a ride others see (#342).
                $this->set($entryid, 'Veröffentlicht', [['1']]);
                $this->set($entryid, 'Fahrtstatus', isset($this->fields['Fahrtstatus']) ? 'Geplant' : null);
                $created['offers'][] = $entryid;
            } else {
                $created['requests'][] = $entryid;
            }
            $this->log(sprintf(
                '%s #%d %s (%s %s)',
                $ride['type'] === board_builder::OFFER ? 'Angebot' : 'Gesuch ',
                $entryid,
                implode(' → ', $ride['stops']),
                $author->firstname,
                $author->lastname
            ));
        }
        \mod_datalynx\local\definition_cache::invalidate($this->dlx->id());
        $created['offers'] = array_merge($created['offers'], $this->split($created['offers']));
        if ($match) {
            $this->match($created['requests']);
        }
        return $created;
    }

    /**
     * Split weekly offers into single rides, as the board's split rule does when an offer is published.
     *
     * @param int[] $offerids
     * @return int[] the entries made
     */
    protected function split(array $offerids): array {
        global $DB;
        $made = [];
        foreach ($DB->get_records('datalynx_rules', ['dataid' => $this->dlx->id(), 'type' => 'splitschedule']) as $record) {
            $rule = new \datalynxrule_splitschedule\rule($this->dlx, $record);
            foreach ($offerids as $entryid) {
                $before = (int) $DB->get_field_sql('SELECT MAX(id) FROM {datalynx_entries} WHERE dataid = ?', [$this->dlx->id()]);
                if ($rule->split($entryid)) {
                    $made = array_merge($made, array_map('intval', $DB->get_fieldset_select(
                        'datalynx_entries',
                        'id',
                        'dataid = ? AND id > ?',
                        [$this->dlx->id(), $before]
                    )));
                }
            }
        }
        if ($made) {
            $this->log('Split weekly offers into ' . count($made) . ' more single rides.');
        }
        return $made;
    }

    /**
     * Run the matching sweep for the given entries, as the ad-hoc task does after a real save.
     *
     * @param int[] $entryids
     */
    protected function match(array $entryids): void {
        global $DB;
        $rules = $DB->get_records_select(
            'datalynx_rules',
            "dataid = :dataid AND type = 'eventnotification' AND " . $DB->sql_like('param9', ':key'),
            ['dataid' => $this->dlx->id(), 'key' => '%_matchcriteria%']
        );
        foreach ($entryids as $entryid) {
            foreach ($rules as $record) {
                $rule = new \datalynxrule_eventnotification\rule($this->dlx, $record);
                $rule->run_matching([
                    'eventname' => 'entry_created',
                    'entryid' => $entryid,
                    'objectid' => $entryid,
                    'teamfieldid' => 0,
                ]);
            }
        }
        $this->log('Ran the matching sweep for ' . count($entryids) . ' requests.');
    }
}
