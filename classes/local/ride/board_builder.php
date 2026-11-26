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
use mod_datalynx\local\definition_cache;
use mod_datalynx\local\field\datalynxfield_layout;
use mod_datalynx\local\field_format\manager as format_manager;
use stdClass;

/**
 * Builds a complete ride sharing board (Mitfahrbörse) in a course.
 *
 * One place for the whole board: fields with their layouts, behaviours, filters, the offer
 * wizard and the request page, the search, the match rules and the notification view. The CLI
 * (`cli/create_ridesharing_instance.php`), the tests and the preset export all use it.
 *
 * Two variants:
 * - `basic`: offers and requests, route, schedule, vehicle, search and matching.
 * - `fahrtenschein`: `basic` without the cost fields, plus the billing extension of
 *   mod_datalynx#324. It changes the definitions of `basic` where it differs; it does not copy them.
 *
 * Everything that refers to another part (a field in a condition, a filter of a view, the next
 * view of a step, the email view of a rule) is written by name here and resolved to ids while
 * building.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class board_builder {
    /** @var string The board as it is used today. */
    const VARIANT_BASIC = 'basic';

    /** @var string The board with the billing extension (Fahrtenschein). */
    const VARIANT_BILLING = 'fahrtenschein';

    /** @var string Label of the offer option of the ride type. */
    const OFFER = 'Fahrt anbieten';

    /** @var string Label of the request option of the ride type. */
    const REQUEST = 'Mitfahrt suchen';

    /** @var string[] View names the templates link to. */
    const VIEWS = [
        'portal' => 'Mitfahrbörse',
        'step1' => 'Fahrtangebot · Schritt 1 – Route',
        'step2' => 'Fahrtangebot · Schritt 2 – Termin',
        'step3' => 'Fahrtangebot · Schritt 3 – Fahrzeug',
        'step4' => 'Fahrtangebot · Schritt 4 – Mitnahme',
        'step5' => 'Fahrtangebot · Schritt 5 – Übersicht',
        'request' => 'Mitfahrgesuch anlegen',
        'search' => 'Fahrt suchen',
        'mine' => 'Meine Fahrten',
        'all' => 'Alle Fahrten',
        'email' => 'Benachrichtigung: Passende Fahrt',
        'manage' => 'Meine Fahrten verwalten',
        'billing' => 'Fahrt abrechnen',
        'pdf' => 'Fahrtenschein PDF',
        'tickets' => 'Meine Fahrtenscheine',
        'cancelemail' => 'Benachrichtigung: Fahrt abgesagt',
    ];

    /** @var string Shortname of the user profile field holding the IBAN (#343). */
    const IBAN = 'iban';

    /** @var string[] Titles of the offer steps. */
    const STEPS = [1 => 'Route', 2 => 'Termin', 3 => 'Fahrzeug', 4 => 'Mitnahme', 5 => 'Übersicht'];

    /** @var stdClass The course the board is built in. */
    protected stdClass $course;

    /** @var string One of the VARIANT_ constants. */
    protected string $variant;

    /** @var string Name of the activity. */
    protected string $name;

    /** @var callable|null Receives a line of progress. */
    protected $log;

    /** @var int Datalynx instance id. */
    protected int $dataid = 0;

    /** @var int Course module id. */
    protected int $cmid = 0;

    /** @var datalynx|null The instance. */
    protected ?datalynx $dlx = null;

    /** @var int[] Field ids by key. */
    protected array $fieldids = [];

    /** @var int[] Filter ids by key. */
    protected array $filterids = [];

    /** @var int[] View ids by key. */
    protected array $viewids = [];

    /** @var array Field definitions by key, see fields(). */
    protected array $fielddefs = [];

    /**
     * Constructor.
     *
     * @param stdClass $course
     * @param string $variant self::VARIANT_BASIC or self::VARIANT_BILLING
     * @param string $name activity name; empty for the default name of the variant
     * @param callable|null $log receives each line of progress
     */
    public function __construct(stdClass $course, string $variant = self::VARIANT_BASIC, string $name = '', ?callable $log = null) {
        if (!array_key_exists($variant, self::variants())) {
            throw new \coding_exception("Unknown ride sharing board variant '$variant'");
        }
        $this->course = $course;
        $this->variant = $variant;
        $this->name = $name !== '' ? $name : self::variants()[$variant];
        $this->log = $log;
    }

    /**
     * The variants: key => default activity name.
     *
     * @return string[]
     */
    public static function variants(): array {
        return [
            self::VARIANT_BASIC => 'Mitfahrbörse',
            self::VARIANT_BILLING => 'Mitfahrbörse mit Fahrtenschein',
        ];
    }

    /**
     * Whether this board has the billing extension.
     *
     * @return bool
     */
    public function is_billing(): bool {
        return $this->variant === self::VARIANT_BILLING;
    }

    /**
     * Datalynx instance id of the built board.
     *
     * @return int
     */
    public function get_dataid(): int {
        return $this->dataid;
    }

    /**
     * Course module id of the built board.
     *
     * @return int
     */
    public function get_cmid(): int {
        return $this->cmid;
    }

    /**
     * Field ids by name.
     *
     * @return int[]
     */
    public function get_fieldids_by_name(): array {
        $ids = [];
        foreach ($this->fielddefs as $key => $def) {
            $ids[$def['name']] = $this->fieldids[$key];
        }
        return $ids;
    }

    /**
     * Activities of the given name in the course.
     *
     * @param int $courseid
     * @param string $name
     * @return stdClass[] datalynx records
     */
    public static function find_existing(int $courseid, string $name): array {
        global $DB;
        return $DB->get_records('datalynx', ['course' => $courseid, 'name' => $name]);
    }

    /**
     * Delete the activities of the given name in the course, with everything in them.
     *
     * @param int $courseid
     * @param string $name
     * @return int[] course module ids removed
     */
    public static function remove_existing(int $courseid, string $name): array {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $removed = [];
        foreach (self::find_existing($courseid, $name) as $instance) {
            $cm = get_coursemodule_from_instance('datalynx', $instance->id, $courseid, false, MUST_EXIST);
            // Moodle 5.2 moved course_delete_module() to cmactions::delete() and deprecated it.
            if (method_exists(\core_courseformat\local\cmactions::class, 'delete')) {
                \core_courseformat\formatactions::cm($courseid)->delete($cm->id);
            } else {
                course_delete_module($cm->id);
            }
            $removed[] = (int) $cm->id;
        }
        return $removed;
    }

    /**
     * Build the board.
     *
     * @return int course module id
     */
    public function build(): int {
        if ($this->is_billing()) {
            $this->ensure_iban_field();
        }
        $this->create_activity();
        $this->create_fields();
        $this->create_layouts();
        $this->create_behaviors();
        $this->create_formats();
        $this->create_filters();
        $this->create_views();
        $this->create_customfilters();
        $this->create_rules();
        $this->finish();

        return $this->cmid;
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
     * The user profile field of the IBAN, made when the site has none (billing variant, #343).
     *
     * Kept once per user, private to them; the Fahrtenschein shows and edits it.
     */
    protected function ensure_iban_field(): void {
        global $DB;
        if ($DB->record_exists('user_info_field', ['shortname' => self::IBAN])) {
            return;
        }
        $categoryid = (int) $DB->get_field_sql('SELECT MIN(id) FROM {user_info_category}');
        if (!$categoryid) {
            $categoryid = (int) $DB->insert_record('user_info_category', (object) ['name' => 'Mitfahrbörse', 'sortorder' => 1]);
        }
        $DB->insert_record('user_info_field', (object) [
            'shortname' => self::IBAN,
            'name' => 'IBAN',
            'datatype' => 'text',
            'description' => 'Konto für die Fahrtkostenabrechnung der Mitfahrbörse.',
            'descriptionformat' => FORMAT_HTML,
            'categoryid' => $categoryid,
            'sortorder' => (int) $DB->count_records('user_info_field', ['categoryid' => $categoryid]) + 1,
            'required' => 0,
            'locked' => 0,
            // Visible to the user only.
            'visible' => 1,
            'forceunique' => 0,
            'signup' => 0,
            'defaultdata' => '',
            'defaultdataformat' => FORMAT_PLAIN,
            'param1' => 34,
            'param2' => 34,
            'param3' => 0,
        ]);
        $this->log('Created the user profile field "iban".');
    }

    /**
     * The activity itself.
     */
    protected function create_activity(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $intro = '<p>Fahrgemeinschaften für Fahrten zum Campus und darüber hinaus: Fahrten anbieten, '
            . 'Mitfahrgelegenheiten suchen und automatisch benachrichtigt werden, sobald Angebot und Gesuch '
            . 'zusammenpassen.</p>';
        $moduleinfo = (object) [
            'modulename' => 'datalynx',
            'module' => $DB->get_field('modules', 'id', ['name' => 'datalynx'], MUST_EXIST),
            'name' => $this->name,
            'course' => $this->course->id,
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
        $moduleinfo = add_moduleinfo($moduleinfo, $this->course);
        $this->dataid = (int) $moduleinfo->instance;
        $this->cmid = (int) $moduleinfo->coursemodule;
        $this->dlx = new datalynx($this->dataid);
        $this->log("Created datalynx instance {$this->dataid} (cmid {$this->cmid}) in course {$this->course->id}.");
    }

    /**
     * The fields: key => type, name, label and hint of the form, params.
     *
     * The label and the hint live in the field layout rather than in the view template, so that a
     * field hidden by a condition takes its label with it.
     *
     * @return array
     */
    protected function fields(): array {
        $fields = [
            'fahrttyp' => [
                'type' => 'radiobutton',
                'name' => 'Fahrttyp',
                'label' => 'Ich möchte …',
                'hint' => 'Angebote und Gesuche werden automatisch miteinander abgeglichen.',
                'params' => ['param1' => self::OFFER . "\n" . self::REQUEST, 'param2' => self::OFFER, 'param3' => 1],
            ],
            'route' => [
                'type' => 'itinerary',
                'name' => 'Route',
                'label' => 'Route: von – über – nach',
                'hint' => 'Adresse eintippen und einen Vorschlag auswählen. Zwischenstopps helfen beim Finden '
                    . 'von Mitfahrenden entlang der Strecke.',
                'params' => [
                    'param1' => 8, // Maximum stops.
                    'param2' => 2, // Rows shown initially.
                    'param3' => 'both', // Map and list.
                    'param4' => 8, // Default zoom.
                    'param5' => 5, // Default corridor radius in km.
                    'param6' => 'approximate',
                    'param7' => 'at,de,ch,it,si,sk,cz,hu',
                ],
                'fullwidth' => true,
            ],
            'umweg' => [
                'type' => 'number',
                'name' => 'Umweg',
                'label' => 'Umweg, den ich in Kauf nehme (km)',
                'hint' => 'Wie weit dürfen Mitfahrende von meiner Strecke entfernt wohnen?',
                'params' => ['param1' => 0, 'param4' => 1],
            ],
            'termin' => [
                'type' => 'schedule',
                'name' => 'Termin',
                'description' => 'Wann die Fahrt stattfindet - einmalig oder jede Woche.',
                'label' => 'Termin',
                'hint' => 'Einmalige Fahrt: Datum und Uhrzeit. Regelmäßige Fahrt: Wochentage, Uhrzeit und bis wann '
                    . 'sie gilt.',
                'params' => ['param1' => 30, 'param2' => 1440, 'param3' => 'both', 'param4' => 0, 'param5' => 1],
                'fullwidth' => true,
            ],
            // The brand is picked from the brands sold in Germany, found by typing (autocomplete).
            'marke' => [
                'type' => 'select',
                'name' => 'Marke',
                'label' => 'Marke',
                'hint' => 'Tippen Sie die ersten Buchstaben, zum Beispiel „Volks“ für Volkswagen.',
                'params' => ['param1' => implode("\n", vehicle::BRANDS), 'param6' => 1],
            ],
            'modell' => [
                'type' => 'text',
                'name' => 'Modell',
                'label' => 'Modell',
                'hint' => 'Zum Beispiel: Polo',
                'params' => ['param2' => 50, 'param3' => '%'],
            ],
            'kennzeichen' => [
                'type' => 'text',
                'name' => 'Kennzeichen',
                'label' => 'Kennzeichen',
                'hint' => 'Hilft beim Wiedererkennen am Treffpunkt.',
                'params' => ['param2' => 50, 'param3' => '%'],
            ],
            'sitzplaetze' => [
                'type' => 'select',
                'name' => 'Freie Plätze',
                'label' => 'Freie Plätze',
                'hint' => 'Wie viele Personen kann ich auf dieser Fahrt mitnehmen?',
                'params' => ['param1' => implode("\n", range(0, 8))],
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
        if ($this->is_billing()) {
            // Reimbursement is per kilometre through the Fahrtenschein (mod_datalynx#325).
            unset($fields['fahrkosten'], $fields['fixbetrag']);
            // Seats 0 to 8 as a number: 0 is a solo trip, offered only to be billed (#325).
            $fields['sitzplaetze'] = [
                'type' => 'number',
                'name' => 'Freie Plätze',
                'label' => 'Plätze für Mitfahrende',
                'hint' => '0 = ich nehme niemanden mit (nur Abrechnung)',
                'params' => ['param1' => 0, 'param5' => 0, 'param6' => 8],
            ];
            // The licence plate is needed for the billing: required on offers, checked and stored as printed.
            $fields['kennzeichen']['hint'] = 'Pflichtangabe für die Fahrtkostenabrechnung.';
            $fields['kennzeichen']['params']['param4'] = 'licenceplate';
            // The passengers: they request a seat, the driver approves (mod_datalynx#341); at most as
            // many as the seats of the ride (mod_datalynx#326).
            $fields['passagiere'] = [
                'type' => 'teammemberselect',
                'name' => 'Passagiere',
                'label' => 'Mitfahrende',
                'hint' => '',
                'params' => [
                    'param1' => 8,
                    'param2' => json_encode([datalynx::PERMISSION_STUDENT, datalynx::PERMISSION_TEACHER]),
                    'param3' => 0,
                    'param4' => \datalynxfield_teammemberselect\field::TEAMMEMBERSELECT_FORMAT_UL,
                    'param7' => 1,
                    'param8' => 1,
                ],
                // Resolved once the fields exist. A ride with confirmed passengers is cancelled, not deleted.
                'settings' => ['limitfield' => 'sitzplaetze', 'approval' => 1, 'protectmembers' => 1,
                    // Passengers give their pickup point, within the driver's detour; it joins the route (#327).
                    'pickupfield' => 'route', 'detourfield' => 'umweg'],
                'fullwidth' => true,
            ];
            // Every offer is one ride on one date: a weekly offer is split into single rides when it is
            // published, at most 6 months ahead (#342).
            $fields['termin']['params']['param6'] = 6;
            $fields['veroeffentlicht'] = [
                'type' => 'checkbox',
                'name' => 'Veröffentlicht',
                'label' => 'Veröffentlichen',
                'hint' => 'Erst mit diesem Häkchen ist die Fahrt sichtbar. Eine regelmäßige Fahrt wird dann in '
                    . 'einzelne Fahrten aufgeteilt, die Sie unter „Meine Fahrten verwalten“ einzeln absagen oder löschen.',
                'params' => ['param1' => 'Angaben geprüft – Fahrt veröffentlichen'],
            ];
            // The Fahrtenschein: billing fields on the ride itself (#343, #331), for the driver and managers.
            $fields['anlass'] = [
                'type' => 'text',
                'name' => 'Anlass',
                'label' => 'Anlass / Schulung',
                'hint' => 'Wofür war die Fahrt? Zum Beispiel: Fortbildung, Schulung oder Termin mit Ort.',
                'params' => ['param2' => 100, 'param3' => '%'],
            ];
            $fields['rueckfahrt'] = [
                'type' => 'checkbox',
                'name' => 'Rückfahrt',
                'label' => 'Rückfahrt',
                'hint' => 'Angehakt verdoppelt die Kilometer.',
                'params' => ['param1' => 'Rückfahrt (gleiche Strecke zurück)'],
            ];
            $fields['beantragtekm'] = [
                'type' => 'number',
                'name' => 'Beantragte Kilometer',
                'label' => 'Beantragte Kilometer, falls abweichend von der Routenplanung',
                'hint' => 'Nur ausfüllen, wenn Sie mehr oder weniger Kilometer als berechnet abrechnen möchten.',
                // Not filled in shows nothing, not "0".
                'params' => ['param1' => 1, 'param4' => 1, 'param5' => 0],
            ];
            $fields['begruendung'] = [
                'type' => 'textarea',
                'name' => 'Begründung',
                'label' => 'Begründung für Abweichung',
                'hint' => 'Pflicht, wenn Sie mehr Kilometer beantragen, als die Route ergibt.',
                'params' => ['param2' => 60, 'param3' => 3],
                'fullwidth' => true,
            ];
            $fields['dienstfahrzeug'] = [
                'type' => 'checkbox',
                'name' => 'Dienstfahrzeug',
                'label' => 'Dienstfahrzeug',
                'hint' => '',
                'params' => ['param1' => 'Die Fahrt war mit einem Dienstfahrzeug'],
            ];
            // Proof of the trip (#331): a photo of the parked car at the destination, at most 1600 px.
            $fields['parkfoto'] = [
                'type' => 'picture',
                'name' => 'Parkfoto',
                'label' => 'Parkfoto',
                'hint' => 'Ein Foto des geparkten Fahrzeugs am Zielort (JPEG oder PNG, höchstens 10 MB).',
                'params' => ['param1' => 10485760, 'param2' => 1, 'param3' => 'image', 'param7' => 1600, 'param8' => 1600,
                    'param9' => 320, 'param10' => 320],
                'fullwidth' => true,
            ];
            $fields['eigenerklaerung'] = [
                'type' => 'checkbox',
                'name' => 'Eigenerklärung',
                'label' => 'Eigenerklärung',
                'hint' => '',
                'params' => ['param1' => 'Hiermit bestätige ich wahrheitsgemäß, dass ich die angegebene Fahrt am genannten '
                    . 'Datum selbst als Fahrer/in durchgeführt habe und sowohl die angegebenen Streckendaten als auch die '
                    . 'aufgeführten mitgenommenen Personen den Tatsachen entsprechen.'],
                'fullwidth' => true,
            ];
            // The distance of the route is billing-relevant: computed on the server (#328).
            $fields['route']['billing'] = ['returnfield' => 'rueckfahrt', 'requestedfield' => 'beantragtekm',
                'justificationfield' => 'begruendung'];
            $fields['fahrtstatus'] = [
                'type' => 'select',
                'name' => 'Fahrtstatus',
                'label' => 'Fahrtstatus',
                'hint' => 'Bei „Abgesagt“ werden die Mitfahrenden benachrichtigt; die Fahrt erscheint nicht mehr in der Suche.',
                'params' => ['param1' => "Geplant\nAbgesagt", 'param2' => 'Geplant'],
            ];
        }
        return $fields;
    }

    /**
     * Whether the board has the field of the given key.
     *
     * @param string $key
     * @return bool
     */
    protected function has(string $key): bool {
        return isset($this->fielddefs[$key]);
    }

    /**
     * Create the fields.
     */
    protected function create_fields(): void {
        global $DB;
        $this->fielddefs = $this->fields();
        foreach ($this->fielddefs as $key => $def) {
            $record = (object) array_merge([
                'dataid' => $this->dataid,
                'type' => $def['type'],
                'name' => $def['name'],
                'description' => $def['description'] ?? '',
            ], $def['params']);
            $this->fieldids[$key] = (int) $DB->insert_record('datalynx_fields', $record);
        }
        // The billing settings of a route (param9) name other fields by key.
        foreach ($this->fielddefs as $key => $def) {
            if (!empty($def['billing'])) {
                $billing = ['billing' => 1];
                foreach ($def['billing'] as $name => $fieldkey) {
                    $billing[$name] = $this->fieldids[$fieldkey];
                }
                $DB->set_field('datalynx_fields', 'param9', json_encode($billing), ['id' => $this->fieldids[$key]]);
            }
        }
        // Settings naming another field (param10 of a team member select) by key.
        foreach ($this->fielddefs as $key => $def) {
            if (!empty($def['settings'])) {
                $settings = $def['settings'];
                foreach (['limitfield', 'pickupfield', 'detourfield'] as $name) {
                    if (isset($settings[$name])) {
                        $settings[$name] = $this->fieldids[$settings[$name]];
                    }
                }
                $DB->set_field('datalynx_fields', 'param10', json_encode($settings), ['id' => $this->fieldids[$key]]);
            }
        }
        definition_cache::invalidate($this->dataid);
        $this->log('Created ' . count($this->fieldids) . ' fields.');
    }

    /**
     * Create a field layout per field: label, hint and wrapper markup travel with the field.
     */
    protected function create_layouts(): void {
        global $DB;
        foreach ($this->fielddefs as $key => $def) {
            $label = s($def['label']);
            $hint = $def['hint'] !== '' ? '<div class="form-text">' . s($def['hint']) . '</div>' : '';
            if (!empty($def['fullwidth'])) {
                $display = '<div class="my-3"><div class="small fw-semibold text-muted mb-1">' . $label . '</div>'
                    . '<div>#value</div></div>';
            } else {
                $display = '<div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-1">'
                    . '<span class="small fw-semibold text-nowrap">' . $label . '</span>'
                    . '<span class="text-end">#value</span></div>';
            }
            $DB->insert_record('datalynx_renderers', (object) [
                'dataid' => $this->dataid,
                'type' => $def['type'],
                'name' => 'l_' . $key,
                'description' => 'Layout für ' . $def['name'],
                'notvisibletemplate' => datalynxfield_layout::NOT_VISIBLE_SHOW_NOTHING,
                'displaytemplate' => $display,
                'novaluetemplate' => datalynxfield_layout::NO_VALUE_SHOW_NOTHING,
                'edittemplate' => '<div class="mb-3 datalynx-ride-field"><label class="form-label fw-semibold">' . $label
                    . '</label>#input' . $hint . '</div>',
                // Dependent rather than "as display mode": on the summary step, where every field is
                // read-only, a field without a value then disappears with its label.
                'noteditabletemplate' => datalynxfield_layout::NOT_EDITABLE_SHOW_DEPENDENT,
            ]);
        }
        definition_cache::invalidate($this->dataid);
        $this->log('Created ' . count($this->fielddefs) . ' field layouts.');
    }

    /**
     * A condition on an option field: the field key and the option labels it must have.
     *
     * Option fields store the position of the option, not its label.
     *
     * @param string $key
     * @param string[] $labels
     * @return string JSON of the behaviour conditions
     */
    protected function option_condition(string $key, array $labels): string {
        $options = array_map('trim', explode("\n", (string) $this->fielddefs[$key]['params']['param1']));
        $positions = [];
        foreach ($labels as $label) {
            $position = array_search($label, $options, true);
            if ($position === false) {
                throw new \coding_exception("Field $key has no option '$label'");
            }
            $positions[] = (string) ($position + 1);
        }
        return json_encode([
            'match' => 'all',
            'rules' => [[
                'sourcefieldid' => $this->fieldids[$key],
                'not' => '',
                'operator' => 'ANY_OF',
                'value' => $positions,
            ]],
        ]);
    }

    /**
     * Behaviours: name => required, editable (false = read-only), conditions (JSON).
     *
     * The read-only twins serve the summary step: it arrives in edit mode like every other step,
     * and "nobody may edit" turns the fields there into a recap. Each keeps the condition of its
     * editable twin, so a field that does not apply stays out of the summary as well.
     *
     * @return array
     */
    protected function behaviors(): array {
        $offer = $this->option_condition('fahrttyp', [self::OFFER]);
        $behaviors = [
            'pflicht' => ['required' => 1, 'conditions' => ''],
            'angebot' => ['required' => 0, 'conditions' => $offer],
            'angebotpflicht' => ['required' => 1, 'conditions' => $offer],
            'nurlesen' => ['required' => 0, 'conditions' => '', 'readonly' => true],
            'nurlesenangebot' => ['required' => 0, 'conditions' => $offer, 'readonly' => true],
        ];
        if ($this->is_billing()) {
            // An offer can be changed until its departure, then only by managers (#325).
            foreach (['pflicht', 'angebot', 'angebotpflicht'] as $name) {
                $behaviors[$name]['editableuntil'] = 'termin';
            }
            $behaviors['bisabfahrt'] = ['required' => 0, 'conditions' => '', 'editableuntil' => 'termin'];
            // The Fahrtenschein: visible to the driver and managers only (#343).
            $billing = [datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_AUTHOR];
            $behaviors['abrechnung'] = ['required' => 0, 'conditions' => '', 'visibleto' => $billing];
            $behaviors['abrechnungpflicht'] = ['required' => 1, 'conditions' => '', 'visibleto' => $billing];
            $behaviors['abrechnunglesen'] = ['required' => 0, 'conditions' => '', 'visibleto' => $billing, 'readonly' => true];
        }
        if ($this->has('fahrkosten')) {
            $fixed = $this->option_condition('fahrkosten', ['Festbetrag']);
            $behaviors['festbetrag'] = ['required' => 0, 'conditions' => $fixed];
            $behaviors['nurlesenfestbetrag'] = ['required' => 0, 'conditions' => $fixed, 'readonly' => true];
        }
        return $behaviors;
    }

    /**
     * Create the behaviours.
     */
    protected function create_behaviors(): void {
        global $DB;
        $everyone = [
            datalynx::PERMISSION_MANAGER,
            datalynx::PERMISSION_TEACHER,
            datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR,
            datalynx::PERMISSION_GUEST,
        ];
        $editors = [
            datalynx::PERMISSION_MANAGER,
            datalynx::PERMISSION_TEACHER,
            datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR,
        ];
        $behaviors = $this->behaviors();
        foreach ($behaviors as $name => $def) {
            if (!empty($def['editableuntil'])) {
                $conditions = $def['conditions'] ? json_decode($def['conditions'], true) : [];
                $conditions['editableuntil'] = $this->fieldids[$def['editableuntil']];
                $def['conditions'] = json_encode($conditions);
            }
            $DB->insert_record('datalynx_behaviors', (object) [
                'dataid' => $this->dataid,
                'name' => $name,
                'description' => '',
                'visibleto' => serialize(['permissions' => $def['visibleto'] ?? $everyone]),
                'editableby' => serialize(!empty($def['readonly']) ? [] : ($def['editableby'] ?? $editors)),
                'required' => $def['required'],
                'editableafterfinal' => 0,
                'conditions' => $def['conditions'],
            ]);
        }
        definition_cache::invalidate($this->dataid);
        $this->log('Created ' . count($behaviors) . ' behaviours.');
    }

    /**
     * Field formats: name => field type and settings.
     *
     * @return array
     */
    protected function formats(): array {
        $formats = [
            // The detour is a slider.
            'slider' => ['fieldtype' => 'number', 'settings' => [
                'decimals' => 0, 'inputtype' => 'slider', 'sliderstart' => '1', 'sliderend' => '20',
                'sliderscale' => 'linear', 'sliderstep' => '1', 'sliderunit' => 'km', 'sliderticks' => 1,
            ]],
        ];
        if ($this->has('parkfoto')) {
            // The kilometres of the Fahrtenschein (#328), and the IBAN from the driver's profile (#343).
            $formats['distance'] = ['fieldtype' => 'itinerary', 'settings' => ['show' => 'distance']];
            $formats['billeddistance'] = ['fieldtype' => 'itinerary', 'settings' => ['show' => 'billeddistance']];
            $formats['iban'] = ['fieldtype' => 'entryauthor', 'settings' => ['option' => self::IBAN, 'editable' => 1,
                'mandatory' => 1, 'check' => 'iban']];
        }
        if ($this->has('passagiere')) {
            // The passengers with the button to request a seat.
            $formats['mitfahren'] = ['fieldtype' => 'teammemberselect',
                'settings' => ['subscribe' => 1, 'label' => 'Mitfahrt beantragen']];
        }
        return $formats;
    }

    /**
     * Create the field formats.
     */
    protected function create_formats(): void {
        foreach ($this->formats() as $name => $def) {
            format_manager::save_format((object) [
                'dataid' => $this->dataid,
                'name' => $name,
                'fieldtype' => $def['fieldtype'],
                'settings' => json_encode($def['settings']),
            ]);
        }
        definition_cache::invalidate($this->dataid);
    }

    /**
     * Filters: key => name and customsearch.
     *
     * @return array
     */
    protected function filters(): array {
        $type = function (string $label): array {
            $options = explode("\n", $this->fielddefs['fahrttyp']['params']['param1']);
            $position = (string) (array_search($label, $options, true) + 1);
            return [$this->fieldids['fahrttyp'] => ['AND' => [['', 'ANY_OF', [$position]]]]];
        };
        $offers = $type(self::OFFER) + $this->ride_conditions();
        $filters = [];
        if ($this->has('parkfoto')) {
            // The driver's own past, not cancelled rides: the ones to bill (#343).
            $filters['billable'] = ['name' => 'Abzurechnende Fahrten', 'customsearch' => ['userid' => ['AND' => [['', 'ME', '']]]]
                + $type(self::OFFER) + [
                    $this->fieldids['termin'] => ['AND' => [['', 'PAST', '']]],
                    $this->fieldids['fahrtstatus'] => ['AND' => [['NOT', 'ANY_OF', ['2']]]],
                ]];
            // Submitted Fahrtenscheine (final submission): all of them for the PDF, one's own for the list (#332).
            $filters['submitted'] = ['name' => 'Eingereichte Fahrtenscheine', 'customsearch' => [
                'status' => ['AND' => [['', '=', 2]]],
            ]];
            // One's own Fahrtenscheine, saved as a draft or submitted.
            $filters['mytickets'] = ['name' => 'Meine Fahrtenscheine', 'customsearch' => [
                'userid' => ['AND' => [['', 'ME', '']]],
                'status' => ['OR' => [['', '=', 1], ['', '=', 2]]],
            ]];
        }
        return $filters + [
            'own' => ['name' => 'Meine Einträge', 'customsearch' => ['userid' => ['AND' => [['', 'ME', '']]]]],
            'offers' => ['name' => 'Nur Angebote', 'customsearch' => $offers],
            'requests' => ['name' => 'Nur Gesuche', 'customsearch' => $type(self::REQUEST)],
        ];
    }

    /**
     * Create the filters.
     */
    protected function create_filters(): void {
        global $DB;
        foreach ($this->filters() as $key => $def) {
            $this->filterids[$key] = (int) $DB->insert_record('datalynx_filters', (object) [
                'dataid' => $this->dataid,
                'name' => $def['name'],
                'description' => '',
                'visible' => 1,
                'perpage' => 20,
                'selection' => 0,
                'groupby' => '',
                'search' => '',
                'customsort' => '',
                'customsearch' => serialize($def['customsearch']),
            ]);
        }
        definition_cache::invalidate($this->dataid);
        $this->log('Created ' . count($this->filterids) . ' filters.');
    }

    /**
     * A field tag with its behaviour and its layout.
     *
     * @param string $key
     * @param string $behavior
     * @param string $format field format name, if any
     * @return string empty when the board has no such field
     */
    protected function tag(string $key, string $behavior = '', string $format = ''): string {
        if (!$this->has($key)) {
            return '';
        }
        return '[[' . $this->fielddefs[$key]['name'] . ($format !== '' ? ':' . $format : '') . '|' . $behavior
            . '|l_' . $key . ']]';
    }

    /**
     * The tag of a field without a layout: its bare value.
     *
     * @param string $key
     * @param string $behavior
     * @return string
     */
    protected function plaintag(string $key, string $behavior = ''): string {
        if (!$this->has($key)) {
            return '';
        }
        return '[[' . $this->fielddefs[$key]['name'] . ($behavior !== '' ? '|' . $behavior : '') . ']]';
    }

    /**
     * A row of the PDF table: label and value; nothing for a field the board does not have.
     *
     * @param string $label
     * @param string $value
     * @return string
     */
    protected function pdfrow(string $label, string $value): string {
        if ($value === '') {
            return '';
        }
        return '<tr><td width="35%"><b>' . $label . '</b></td><td width="65%">' . $value . '</td></tr>';
    }

    /**
     * Tags of several fields.
     *
     * @param string[] $keys
     * @param string $behavior
     * @return string
     */
    protected function tags(array $keys, string $behavior = ''): string {
        return implode('', array_map(fn(string $key): string => $this->tag($key, $behavior), $keys));
    }

    /**
     * The badges of the offer steps: completed steps link back to their step with the same entry.
     *
     * @param int $current
     * @return string
     */
    protected function stepper(int $current): string {
        $badges = '';
        foreach (self::STEPS as $number => $title) {
            $caption = $number . '. ' . $title;
            if ($number < $current) {
                $badges .= '##viewsesslink:' . self::VIEWS['step' . $number] . ';' . $caption
                    . ';editentries=##entryid##|eids=##entryid##;badge rounded-pill bg-success p-2 me-2 mb-1##';
            } else if ($number === $current) {
                $badges .= '<span class="badge rounded-pill bg-primary p-2 me-2 mb-1">' . $caption . '</span>';
            } else {
                $badges .= '<span class="badge rounded-pill bg-secondary opacity-50 p-2 me-2 mb-1">' . $caption . '</span>';
            }
        }
        $percent = (int) round($current / count(self::STEPS) * 100);
        return '<div class="d-flex flex-wrap mb-2">' . $badges . '</div>'
            . '<div class="progress mb-4" style="height: 8px;">'
            . '<div class="progress-bar bg-primary" style="width: ' . $percent . '%;">&nbsp;</div></div>';
    }

    /**
     * The card of a form page: overline, heading, stepper, lead text, fields and the save button.
     *
     * @param string $overline
     * @param string $heading
     * @param string $stepper
     * @param string $lead
     * @param string $body
     * @param string $submitlabel label of ##submit## (spaces as underscores)
     * @return string
     */
    protected function card(
        string $overline,
        string $heading,
        string $stepper,
        string $lead,
        string $body,
        string $submitlabel
    ): string {
        return '<div class="card shadow-sm border-0 my-4 mx-auto" style="max-width: 820px;">'
            . '<div class="card-header bg-white border-0 pt-4 px-4 pb-0">'
            . '<div class="text-uppercase text-primary fw-bold small mb-1">' . $overline . '</div>'
            . '<h3 class="fw-bold mb-3">' . $heading . '</h3>'
            . $stepper
            . '</div><div class="card-body px-4 pb-4">'
            . '<p class="text-muted">' . $lead . '</p>'
            . $body
            . '<div class="d-flex justify-content-end gap-2 mt-4">##submit:text=' . $submitlabel . '##</div>'
            . '</div></div>';
    }

    /**
     * The card presenting a ride in the browse and search views.
     *
     * @return string
     */
    protected function ridecard(): string {
        return '<div class="card h-100 shadow-sm border-0"><div class="card-body">'
            . '<div class="d-flex justify-content-between align-items-center mb-2">'
            . '<span class="badge bg-primary">[[Fahrttyp]]</span>'
            . '<span class="small text-muted">##author:name##</span></div>'
            . $this->tags(['route', 'termin'])
            // The vehicle and what the driver takes along belong to an offer: a request shows (and
            // edits, in "Meine Fahrten") none of them. Passengers join by request only.
            . $this->tags(['sitzplaetze', 'marke', 'modell', 'gepaeck', 'mitfahrende', 'fahrkosten', 'fixbetrag'], 'angebot')
            . $this->tag('bemerkungen')
            . $this->tag('passagiere', 'nurlesenangebot', 'mitfahren')
            . '</div><div class="card-footer bg-transparent border-0 d-flex gap-2">##edit####delete##</div></div>';
    }

    /**
     * The start page.
     *
     * @return string
     */
    protected function portal(): string {
        $tile = function (string $title, string $text, string $buttons, string $footerclass = ''): string {
            return '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm text-center p-3"><div class="card-body">'
                . '<h3 class="card-title fw-bold mb-3">' . $title . '</h3>'
                . '<p class="card-text text-secondary">' . $text . '</p></div>'
                . '<div class="card-footer bg-transparent border-0' . $footerclass . '">' . $buttons . '</div></div></div>';
        };
        return '<div class="container py-4">'
            . '<div class="p-5 mb-4 bg-white border rounded-4 shadow-sm text-center">'
            . '<h1 class="display-6 fw-bold text-primary mb-3">Mitfahrbörse</h1>'
            . '<p class="lead text-muted mx-auto" style="max-width: 640px;">Gemeinsam fahren statt allein: Tragen Sie '
            . 'Ihre Fahrt ein oder suchen Sie eine Mitfahrgelegenheit. Passen ein Angebot und ein Gesuch zusammen, '
            . 'werden beide Seiten automatisch benachrichtigt.'
            . ($this->is_billing() ? ' Jede Fahrt, deren Kosten erstattet werden, wird hier eingetragen und abgerechnet – '
                . 'auch allein: „0 Plätze“ heißt, Sie nehmen niemanden mit.' : '')
            . '</p></div>'
            . '<div class="row g-4">'
            . $tile(
                'Fahrt eintragen',
                'Als Fahrer/in ein Angebot mit Route, Termin und Fahrzeug eintragen – oder als Mitfahrer/in ein Gesuch '
                    . 'mit Strecke und Zeit.',
                '##viewsesslink:' . self::VIEWS['step1'] . ';Fahrtangebot anlegen;new=1;btn btn-primary w-100 py-2 fw-bold##'
                    . '##viewsesslink:' . self::VIEWS['request']
                    . ';Mitfahrgesuch anlegen;new=1;btn btn-outline-primary w-100 py-2 fw-bold##',
                ' d-grid gap-2'
            )
            . $tile(
                'Fahrt suchen',
                'Angebote entlang Ihrer Strecke finden – auch dann, wenn Sie nur ein Stück der Route mitfahren.',
                '##viewlink:' . self::VIEWS['search'] . ';Fahrt suchen;;btn btn-primary w-100 py-2 fw-bold##'
            )
            . $tile(
                'Meine Fahrten',
                'Eigene Angebote und Gesuche ansehen, ändern oder löschen.',
                '##viewlink:' . self::VIEWS['mine'] . ';Meine Fahrten;;btn btn-outline-primary w-100 py-2##'
                    . ($this->is_billing() ? '##viewlink:' . self::VIEWS['manage']
                        . ';Fahrten verwalten;;btn btn-link w-100##' : ''),
                $this->is_billing() ? ' d-grid gap-2' : ''
            )
            . ($this->is_billing()
                ? $tile(
                    'Fahrt abrechnen',
                    'Nach der Fahrt den Fahrtenschein ausfüllen: Anlass, Parkfoto und Eigenerklärung. Die Kilometer '
                        . 'berechnet das System aus der Route.',
                    '##viewlink:' . self::VIEWS['billing'] . ';Fahrt abrechnen;;btn btn-primary w-100 py-2 fw-bold##'
                )
                . $tile(
                    'Meine Fahrtenscheine',
                    'Eingereichte Fahrtenscheine ansehen und als PDF herunterladen.',
                    '##viewlink:' . self::VIEWS['tickets'] . ';Meine Fahrtenscheine;;btn btn-outline-primary w-100 py-2##'
                )
                : '')
            . '</div></div>';
    }

    /**
     * The views: key => view type and data for view::add(). 'filter' and 'next' are keys.
     *
     * @return array
     */
    protected function views(): array {
        $entries = '<div class="container py-4">##entries##</div>';
        $step = function (int $number, string $heading, string $lead, string $body, string $submit) use ($entries): array {
            return [
                'type' => 'grid',
                'data' => [
                    'description' => 'Fahrtangebot, Schritt ' . $number . ' von ' . count(self::STEPS),
                    'perpage' => 0,
                    'param9' => $number < count(self::STEPS) ? 1 : 0,
                    'esection' => $entries,
                    'eparam2' => $this->card(
                        'Fahrtangebot · Schritt ' . $number . ' von ' . count(self::STEPS),
                        $heading,
                        $this->stepper($number),
                        $lead,
                        $body,
                        $submit
                    ),
                ],
                'filter' => 'own',
                'permitted' => ['own'],
                'next' => $number < count(self::STEPS) ? 'step' . ($number + 1) : 'mine',
            ];
        };
        // Fields of the offer steps without a behaviour of their own: editable until departure (#325).
        $free = $this->is_billing() ? 'bisabfahrt' : '';
        $list = function (string $heading, string $inner): string {
            return '<div class="container py-4"><h2 class="fw-bold mb-3">' . $heading . '</h2>' . $inner . '</div>';
        };

        return [
            'portal' => [
                'type' => 'grid',
                'data' => ['description' => 'Startseite der Mitfahrbörse', 'perpage' => 0, 'esection' => $this->portal(),
                    'eparam2' => ''],
                'next' => 'step1',
            ],
            'step1' => $step(
                1,
                'Route',
                'Tragen Sie Start, Zwischenstopps und Ziel Ihrer Fahrt ein. Der Fahrttyp steht bereits auf „'
                    . self::OFFER . '" – wer mitfahren möchte, legt stattdessen ein Mitfahrgesuch an.',
                $this->tag('fahrttyp', 'pflicht') . $this->tag('route', 'pflicht') . $this->tag('umweg', $free, 'slider'),
                'Weiter_zum_Termin'
            ),
            'step2' => $step(
                2,
                'Wann fahre ich?',
                'Eine einmalige Fahrt hat ein Datum und eine Uhrzeit. Eine regelmäßige Fahrt beschreiben Sie über '
                    . 'Wochentage, Uhrzeit und den Zeitraum, in dem sie gilt.',
                $this->tag('termin', $free),
                'Weiter_zum_Fahrzeug'
            ),
            'step3' => $step(
                3,
                'Womit fahre ich?',
                'Beschreiben Sie das Fahrzeug, mit dem Sie unterwegs sind.',
                $this->tag('marke', 'angebotpflicht') . $this->tag('modell', 'angebot')
                    . $this->tag('kennzeichen', $this->is_billing() ? 'angebotpflicht' : 'angebot')
                    . $this->tag('sitzplaetze', 'angebotpflicht')
                    . $this->tag('gepaeck', 'angebot'),
                'Weiter_zur_Mitnahme'
            ),
            'step4' => $step(
                4,
                'Wen nehme ich mit?',
                $this->has('fahrkosten')
                    ? 'Legen Sie fest, wen Sie mitnehmen und wie die Fahrtkosten aufgeteilt werden.'
                    : 'Legen Sie fest, wen Sie mitnehmen.',
                $this->tag('mitfahrende', 'angebot') . $this->tag('fahrkosten', 'angebot')
                    . $this->tag('fixbetrag', 'festbetrag') . $this->tag('bemerkungen', $free),
                'Weiter_zur_Übersicht'
            ),
            'step5' => $step(
                5,
                'Übersicht',
                'Bitte prüfen Sie Ihre Angaben. Mit „Fahrt speichern" ist das Angebot veröffentlicht und wird ab sofort '
                    . 'automatisch mit den Mitfahrgesuchen abgeglichen.',
                $this->tags(['fahrttyp', 'route', 'umweg', 'termin'], 'nurlesen')
                    . $this->tags(
                        ['marke', 'modell', 'kennzeichen', 'sitzplaetze', 'gepaeck', 'mitfahrende', 'fahrkosten'],
                        'nurlesenangebot'
                    )
                    . $this->tag('fixbetrag', 'nurlesenfestbetrag') . $this->tag('bemerkungen', 'nurlesen')
                    . $this->tag('veroeffentlicht', 'angebotpflicht'),
                'Fahrt_speichern'
            ),
            'request' => [
                'type' => 'grid',
                'data' => [
                    'description' => 'Mitfahrgesuch in einem Schritt',
                    'perpage' => 0,
                    'param9' => 0,
                    'esection' => $entries,
                    'eparam2' => $this->card(
                        'Mitfahrbörse',
                        'Mitfahrgesuch anlegen',
                        '',
                        'Woher, wohin und wann möchten Sie mitfahren? Eine einmalige Fahrt hat ein Datum, eine '
                            . 'regelmäßige Fahrt Wochentage und einen Zeitraum.',
                        $this->tag('route', 'pflicht') . $this->tag('termin', 'pflicht') . $this->tag('bemerkungen'),
                        'Gesuch_speichern'
                    ),
                ],
                'filter' => 'own',
                'permitted' => ['own'],
                'next' => 'mine',
            ],
            'search' => [
                'type' => 'grid',
                'data' => [
                    'perpage' => 20,
                    // Allow all filters: a view that forces its filter hides the search form, and a
                    // permitted-filters whitelist blocks ad-hoc filters, which is what a custom filter is.
                    // The offers filter still applies until the visitor searches.
                    'param5' => 1,
                    'esection' => $list('Fahrt suchen', '<p class="text-muted">Tragen Sie ein, wo Sie losfahren und wo Sie '
                        . 'hinwollen. Gefunden werden auch Fahrten, bei denen Sie nur ein Stück mitfahren.</p>'
                        . '##customfilter:Fahrt suchen##<div class="mt-3">##entries##</div>'
                        . '<div class="alert alert-info mt-4">Nichts Passendes gefunden? ##viewsesslink:'
                        . self::VIEWS['request'] . ';Tragen Sie ein Gesuch ein;new=1;alert-link## – sobald ein passendes '
                        . 'Angebot eingetragen wird, werden Sie benachrichtigt.</div>'),
                    'eparam2' => $this->ridecard(),
                ],
                'filter' => 'offers',
            ],
            'mine' => [
                'type' => 'grid',
                'data' => [
                    'perpage' => 20,
                    'esection' => $list('Meine Fahrten', '<div class="mb-3">##viewsesslink:' . self::VIEWS['step1']
                        . ';Neue Fahrt eintragen;new=1;btn btn-primary##'
                        . ($this->is_billing() ? ' ##viewlink:' . self::VIEWS['manage']
                            . ';Meine Fahrten verwalten;;btn btn-outline-primary##'
                            . ' ##viewlink:' . self::VIEWS['billing'] . ';Fahrt abrechnen;;btn btn-outline-primary##' : '')
                        . '</div>##entries##'),
                    'eparam2' => $this->ridecard(),
                ],
                'filter' => 'own',
            ],
            'manage' => !$this->is_billing() ? null : [
                'type' => 'grid',
                'data' => [
                    'perpage' => 50,
                    'esection' => $list('Meine Fahrten verwalten', '<p class="text-muted">Ihre einzelnen Fahrten. Fahrten '
                        . 'ohne Mitfahrende können Sie löschen (auch mehrere auf einmal); eine Fahrt mit Mitfahrenden '
                        . 'sagen Sie über „Bearbeiten“ ab – die Mitfahrenden werden benachrichtigt.</p>'
                        . '<div class="mb-3">##selectallnone## ##multidelete##</div>##entries##'),
                    'eparam2' => '<div class="card h-100 shadow-sm border-0"><div class="card-body">'
                        . '<div class="d-flex justify-content-between align-items-center mb-2">##select##'
                        . '<span class="small text-muted">##author:name##</span></div>'
                        . $this->tags(['route', 'termin'], 'nurlesen') . $this->tag('fahrtstatus', 'angebot')
                        // Passengers join by request only: the form would list every participant to pick.
                        . $this->tag('passagiere', 'nurlesen')
                        . '</div><div class="card-footer bg-transparent border-0 d-flex gap-2">##edit####delete##</div></div>',
                ],
                'filter' => 'own',
            ],
            'billing' => !$this->has('parkfoto') ? null : [
                'type' => 'grid',
                'data' => [
                    'perpage' => 20,
                    'esection' => $list('Fahrt abrechnen', '<p class="text-muted">Ihre vergangenen Fahrten. Öffnen Sie eine '
                        . 'Fahrt mit „Bearbeiten“, füllen Sie den Fahrtenschein aus und reichen Sie ihn ein. Danach kann er '
                        . 'nicht mehr geändert werden.</p>##entries##'),
                    'eparam2' => $this->card(
                        'Fahrtenschein',
                        'Fahrt abrechnen',
                        '',
                        'Die Kilometer berechnet der Server aus der Route; mit Rückfahrt zählen sie doppelt.',
                        $this->tags(['route', 'termin', 'kennzeichen', 'passagiere'], 'nurlesen')
                            . '<div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-1 mb-3">'
                            . '<span class="small fw-semibold text-nowrap">Abrechnungs-Kilometer</span>'
                            . '<span class="text-end fw-bold">[[Route:billeddistance|abrechnunglesen]]</span></div>'
                            . $this->tag('anlass', 'abrechnungpflicht') . $this->tag('rueckfahrt', 'abrechnung')
                            . $this->tag('beantragtekm', 'abrechnung') . $this->tag('begruendung', 'abrechnung')
                            . $this->tag('dienstfahrzeug', 'abrechnung')
                            . '<div class="mb-3 datalynx-ride-field"><label class="form-label fw-semibold">IBAN</label>'
                            . '##author:iban##<div class="form-text">Wird in Ihrem Profil gespeichert und für jede Abrechnung '
                            . 'verwendet.</div></div>'
                            . $this->tag('parkfoto', 'abrechnungpflicht') . $this->tag('eigenerklaerung', 'abrechnungpflicht')
                            . '<div class="mb-3 datalynx-ride-field"><label class="form-label fw-semibold">Fahrtenschein '
                            . 'einreichen</label>##status##<div class="form-text">Eingereicht ist der Fahrtenschein gesperrt.'
                            . '</div></div>##edit## ##pdfdownload:' . self::VIEWS['pdf']
                            . ';PDF herunterladen;btn btn-outline-secondary##',
                        'Fahrtenschein_speichern'
                    ),
                ],
                'filter' => 'billable',
                'next' => 'tickets',
            ],
            'tickets' => !$this->has('parkfoto') ? null : [
                'type' => 'grid',
                'data' => [
                    'perpage' => 20,
                    'esection' => $list('Meine Fahrtenscheine', '<p class="text-muted">Ihre Fahrtenscheine: Entwürfe '
                        . 'reichen Sie über „Bearbeiten“ ein; von eingereichten laden Sie das PDF herunter und leiten es an '
                        . 'die Abrechnungsstelle weiter.</p>##entries##'),
                    'eparam2' => '<div class="card h-100 shadow-sm border-0"><div class="card-body">'
                        . '<div class="mb-2"><span class="badge bg-secondary">##status##</span></div>'
                        . $this->tags(['termin', 'anlass', 'rueckfahrt'], 'abrechnunglesen')
                        . '<div class="d-flex justify-content-between align-items-start gap-3 border-bottom py-1">'
                        . '<span class="small fw-semibold text-nowrap">Abrechnungs-Kilometer</span>'
                        . '<span class="text-end fw-bold">[[Route:billeddistance|abrechnunglesen]]</span></div>'
                        . '</div><div class="card-footer bg-transparent border-0 d-flex gap-2 align-items-center">'
                        . '##pdfdownload:' . self::VIEWS['pdf'] . ';PDF herunterladen;btn btn-outline-primary##'
                        // A draft is completed in the billing view; a submitted one is locked (ifeditable).
                        . '##viewsesslink:' . self::VIEWS['billing'] . ';Bearbeiten;editentries=##entryid##|eids=##entryid##;'
                        . 'btn btn-link;ifeditable##</div></div>',
                ],
                'filter' => 'mytickets',
            ],
            // One Fahrtenschein per PDF, A4 portrait, downloaded (#332).
            'pdf' => !$this->has('parkfoto') ? null : [
                'type' => 'pdf',
                'data' => [
                    'visible' => 7,
                    'perpage' => 0,
                    'param1' => base64_encode(serialize((object) [
                        'docname' => 'Fahrtenschein_##entryid##_##author:lastname##',
                        'orientation' => 'P',
                        'unit' => 'mm',
                        'format' => 'A4',
                        'destination' => 'D',
                        'pagebreak' => 'entry',
                    ])),
                    'esection' => '##entries##',
                    // Frame, watermark, signature and certification of the PDF: none.
                    'eparam3' => '', 'eparam4' => '', 'eparam5' => '', 'eparam6' => '', 'eparam7' => '', 'eparam8' => '',
                    'eparam9' => '',
                    // A table with labels of its own and plain values: TCPDF knows neither the flex
                    // layouts of the web views nor their labels.
                    'eparam2' => '<div style="font-family: helvetica; font-size: 10pt;">'
                        . '<h1 style="font-size: 16pt;">Fahrtenschein Nr. ##entryid##</h1>'
                        . '<table cellpadding="4" border="0" width="100%">'
                        . $this->pdfrow('Fahrer/in', '##author:name##')
                        . $this->pdfrow('IBAN', '##author:iban##')
                        . $this->pdfrow('Termin', $this->plaintag('termin'))
                        // The billing data as in the web views: for the driver, teachers and managers only
                        // (anyone may open the PDF view's address with another entry's id).
                        . $this->pdfrow('Anlass / Schulung', $this->plaintag('anlass', 'abrechnunglesen'))
                        . $this->pdfrow('Kennzeichen', $this->plaintag('kennzeichen', 'abrechnunglesen'))
                        . $this->pdfrow('Dienstfahrzeug', $this->plaintag('dienstfahrzeug', 'abrechnunglesen'))
                        . $this->pdfrow('Rückfahrt', $this->plaintag('rueckfahrt', 'abrechnunglesen'))
                        . $this->pdfrow('Strecke einfach', '[[Route:distance]]')
                        . $this->pdfrow('Abrechnungs-Kilometer', '<b>[[Route:billeddistance|abrechnunglesen]]</b>')
                        . $this->pdfrow('Beantragte Kilometer', $this->plaintag('beantragtekm', 'abrechnunglesen'))
                        . $this->pdfrow('Begründung', $this->plaintag('begruendung', 'abrechnunglesen'))
                        . $this->pdfrow('Mitfahrende', $this->plaintag('passagiere'))
                        . '</table>'
                        . '<h2 style="font-size: 12pt;">Route (Start – Stopps – Ziel)</h2>[[Route]]'
                        . '<h2 style="font-size: 12pt;">Eigenerklärung</h2>' . $this->plaintag('eigenerklaerung', 'abrechnunglesen')
                        . '<h2 style="font-size: 12pt;">Parkfoto</h2>' . $this->plaintag('parkfoto', 'abrechnunglesen')
                        . '</div>',
                ],
                'filter' => 'submitted',
            ],
            'all' => [
                'type' => 'grid',
                'data' => ['perpage' => 20, 'esection' => $list('Alle Fahrten', '##entries##'), 'eparam2' => $this->ridecard()],
            ],
            // An email view renders the entry template alone, so param2 is the whole notification body.
            'email' => [
                'type' => datalynx::INTERNAL_VIEW_EMAIL,
                'data' => [
                    'description' => 'Nachrichtentext für den Fahrtenabgleich',
                    'visible' => 0,
                    'perpage' => 0,
                    'eparam2' => '<div style="font-family: sans-serif; font-size: 15px; color: #2d3339;">'
                        . '<p>Guten Tag,</p><p>es gibt eine passende Fahrt zu Ihrem Eintrag in der Mitfahrbörse:</p>'
                        . '<div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 16px; margin: 16px 0;">'
                        . $this->tags(['fahrttyp', 'route', 'termin'])
                        . $this->tags(
                            ['sitzplaetze', 'marke', 'modell', 'gepaeck', 'mitfahrende', 'fahrkosten', 'fixbetrag'],
                            'angebot'
                        )
                        . $this->tag('bemerkungen')
                        . '<div style="margin-top: 8px; font-size: 0.9em; color: #6a737b;">Angelegt von ##author:name##</div>'
                        . '</div><p><strong>##notificationentrylink##</strong></p>'
                        . '<p style="color: #6a737b;">Ihr eigener Eintrag: ##notificationotherentrylink##</p>'
                        . '<p style="color: #6a737b; font-size: 0.9em;">Diese Nachricht kommt aus ##notificationdatalynxlink##.</p>'
                        . '</div>',
                ],
            ],
            // What the passengers of a cancelled ride read.
            'cancelemail' => !$this->has('fahrtstatus') ? null : [
                'type' => datalynx::INTERNAL_VIEW_EMAIL,
                'data' => [
                    'description' => 'Nachrichtentext, wenn eine Fahrt abgesagt wird',
                    'visible' => 0,
                    'perpage' => 0,
                    'eparam2' => '<div style="font-family: sans-serif; font-size: 15px; color: #2d3339;">'
                        . '<p>Guten Tag,</p><p>##author:name## hat die folgende Fahrt abgesagt, bei der Sie als '
                        . 'Mitfahrer/in eingetragen sind. Bitte organisieren Sie sich eine andere Fahrt.</p>'
                        . '<div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 16px; margin: 16px 0;">'
                        . $this->tags(['termin', 'route']) . '</div>'
                        . '<p><strong>##notificationentrylink##</strong></p>'
                        . '<p style="color: #6a737b; font-size: 0.9em;">Diese Nachricht kommt aus ##notificationdatalynxlink##.</p>'
                        . '</div>',
                ],
            ],
        ];
    }

    /**
     * Create the views and wire the step chain.
     */
    protected function create_views(): void {
        global $DB;
        $views = array_filter($this->views());
        $back = '<div class="mb-2">##viewlink:' . self::VIEWS['portal'] . ';← Zurück zur Übersicht;;btn btn-link ps-0##</div>';
        foreach ($views as $key => &$view) {
            if ($view['type'] === 'grid' && $key !== 'portal' && isset($view['data']['esection'])) {
                $view['data']['esection'] = preg_replace(
                    '/^<div class="container py-4">/',
                    '$0' . $back,
                    $view['data']['esection'],
                    1
                );
            }
        }
        unset($view);
        foreach ($views as $key => $def) {
            $data = array_merge([
                'name' => self::VIEWS[$key] ?? $def['name'],
                'description' => '',
                'visible' => 7,
                'filter' => isset($def['filter']) ? $this->filterids[$def['filter']] : 0,
            ], $def['data']);
            if ($def['type'] === 'grid' && !isset($data['param3'])) {
                // The entry wrapper the board was designed with.
                $data['param3'] = 'entry';
            }
            if (isset($def['permitted'])) {
                $data['permittedfilters'] = array_map(fn(string $filter): int => $this->filterids[$filter], $def['permitted']);
            }
            $view = $this->dlx->get_view($def['type'], 0, false);
            $this->viewids[$key] = (int) $view->add((object) $data);
        }
        // The chain: each step points at the next one.
        foreach ($views as $key => $def) {
            if (!empty($def['next'])) {
                $DB->set_field('datalynx_views', 'param10', $this->viewids[$def['next']], ['id' => $this->viewids[$key]]);
            }
        }
        $DB->set_field('datalynx', 'defaultview', $this->viewids['portal'], ['id' => $this->dataid]);
        definition_cache::invalidate($this->dataid);
        $this->log('Created ' . count($this->viewids) . ' views and wired the step chain.');
    }

    /**
     * Custom filters (search forms): name => description and field keys (key => sortable).
     *
     * @return array
     */
    protected function customfilters(): array {
        return [
            'Fahrt suchen' => [
                'description' => 'Suche nach Fahrten entlang einer Strecke',
                'fields' => ['fahrttyp' => 0, 'route' => 0, 'termin' => 1],
            ],
        ];
    }

    /**
     * Create the custom filters.
     */
    protected function create_customfilters(): void {
        global $DB;
        foreach ($this->customfilters() as $name => $def) {
            $fieldlist = [];
            foreach ($def['fields'] as $key => $sortable) {
                $fieldlist[$this->fieldids[$key]] = ['name' => $this->fielddefs[$key]['name'], 'sortable' => $sortable];
            }
            $DB->insert_record('datalynx_customfilters', (object) [
                'dataid' => $this->dataid,
                'name' => $name,
                'description' => $def['description'],
                'visible' => 1,
                'fulltextsearch' => 0,
                'timecreated' => 0,
                'timecreatedsortable' => 0,
                'timemodified' => 0,
                'timemodifiedsortable' => 0,
                'authorsearch' => 0,
                'approve' => 0,
                'status' => 0,
                'fieldlist' => json_encode($fieldlist),
            ]);
        }
        definition_cache::invalidate($this->dataid);
    }

    /**
     * Rules: name => record fields (params already resolved).
     *
     * The two matching rules differ only in which side they fire for and which way round the
     * corridor is tested; they share one ledger, so a pair is introduced once between them.
     *
     * @return array
     */
    protected function rules(): array {
        $types = explode("\n", $this->fielddefs['fahrttyp']['params']['param1']);
        $position = fn(string $label): string => (string) (array_search($label, $types, true) + 1);
        $rules = [];
        foreach ([1 => [self::OFFER, 'reverse'], 2 => [self::REQUEST, 'forward']] as $number => [$side, $direction]) {
            $rules["Passende Fahrt gefunden ($number)"] = [
                'type' => 'eventnotification',
                'description' => 'Benachrichtigt beide Seiten, sobald ein Angebot und ein Gesuch zusammenpassen.',
                'param1' => json_encode(['entry_created', 'entry_updated']),
                'param3' => json_encode(['matchauthors' => 1, 'subjectauthorpermatch' => 1]),
                'param4' => '[]',
                'param6' => 'Mitfahrbörse: passende Fahrt gefunden',
                'param7' => '[]',
                'param8' => $this->viewids['email'],
                'param9' => json_encode($this->match_conditions($side) + [
                    '_matchcriteria' => [
                        'counterpart' => $this->match_counterpart($side),
                        'criteria' => [
                            ['fieldid' => $this->fieldids['fahrttyp'], 'op' => 'different'],
                            ['fieldid' => $this->fieldids['route'], 'op' => 'route', 'radius' => 10, 'direction' => $direction],
                            ['fieldid' => $this->fieldids['termin'], 'op' => 'within', 'tolerance' => 1440, 'unit' => 60],
                        ],
                        'requireapproved' => 1,
                        'dedupe' => 'ridematch' . $this->dataid,
                        'forgetstale' => 1,
                        'maxmatches' => 200,
                    ],
                ]),
            ];
        }
        // The request page carries no ride type; this stamps it. Follow-up events let the
        // matching rules, which select on the ride type, see the entry again.
        $rules['Mitfahrgesuch: Fahrttyp setzen'] = [
            'type' => 'updatefield',
            'description' => 'Setzt den Fahrttyp auf „' . self::REQUEST . '", wenn ein Eintrag ohne Fahrttyp angelegt wird.',
            'param1' => json_encode(['entry_created']),
            'param2' => $this->fieldids['fahrttyp'],
            'param3' => $position(self::REQUEST),
            'param4' => '1',
            'param9' => json_encode([$this->fieldids['fahrttyp'] => ['AND' => [['', '', '']]]]),
        ];
        if ($this->has('veroeffentlicht')) {
            // A published weekly offer becomes single rides (#342).
            $rules['Fahrtangebot: einzelne Fahrten anlegen'] = [
                'type' => 'splitschedule',
                'description' => 'Teilt ein veröffentlichtes regelmäßiges Angebot in einzelne Fahrten auf.',
                'param1' => json_encode(['entry_updated', 'entry_created']),
                'param2' => $this->fieldids['termin'],
                'param3' => 6,
                'param9' => json_encode([
                    $this->fieldids['fahrttyp'] => ['AND' => [['', 'ANY_OF', [$position(self::OFFER)]]]],
                    $this->fieldids['veroeffentlicht'] => ['AND' => [['', 'ANY_OF', ['1']]]],
                ]),
            ];
            // A cancelled ride tells its passengers.
            $rules['Fahrt abgesagt'] = [
                'type' => 'eventnotification',
                'description' => 'Benachrichtigt die Mitfahrenden, wenn eine Fahrt abgesagt wird.',
                'param1' => json_encode(['entry_updated']),
                'param2' => \datalynxrule_eventnotification\rule::FROM_AUTHOR,
                'param3' => json_encode(['teams' => [$this->fieldids['passagiere']]]),
                'param4' => '[]',
                'param6' => 'Mitfahrbörse: eine Fahrt wurde abgesagt',
                'param7' => '[]',
                'param8' => $this->viewids['cancelemail'],
                'param9' => json_encode([
                    $this->fieldids['fahrtstatus'] => ['AND' => [['', 'ANY_OF', ['2']]]],
                    \mod_datalynx\local\rule\base::ONCHANGE_KEY => [$this->fieldids['fahrtstatus']],
                ]),
            ];
        }
        if ($this->has('passagiere')) {
            $team = 'Passagiere';
            $notify = function (string $event, array $recipient, string $subject, string $description) use ($team): array {
                return [
                    'type' => 'eventnotification',
                    'description' => $description,
                    'param1' => json_encode([$event . ':' . $this->fieldids['passagiere']]),
                    'param2' => \datalynxrule_eventnotification\rule::FROM_CURRENT_USER,
                    'param3' => json_encode($recipient),
                    'param4' => '[]',
                    'param6' => $subject,
                    'param7' => '[]',
                ];
            };
            $rules['Mitfahrt beantragt'] = $notify(
                'team_request_created',
                ['author' => 1],
                'Mitfahrbörse: neue Anfrage für Ihre Fahrt',
                'Benachrichtigt den Fahrer, wenn jemand mitfahren möchte.'
            );
            $rules['Mitfahrt angenommen'] = $notify(
                'team_request_approved',
                ['relateduser' => 1],
                'Mitfahrbörse: Ihre Mitfahrt wurde bestätigt',
                'Benachrichtigt die Person, deren Anfrage angenommen wurde.'
            );
            $rules['Mitfahrt abgelehnt'] = $notify(
                'team_request_declined',
                ['relateduser' => 1],
                'Mitfahrbörse: Ihre Anfrage wurde abgelehnt',
                'Benachrichtigt die Person, deren Anfrage abgelehnt wurde.'
            );
            $rules['Mitfahrt zurückgezogen'] = $notify(
                'team_request_withdrawn',
                ['author' => 1],
                'Mitfahrbörse: eine Mitfahrt wurde abgesagt',
                'Benachrichtigt den Fahrer, wenn jemand die Anfrage zurückzieht '
                . 'oder sich austrägt.'
            );
        }
        return $rules;
    }

    /**
     * The trigger conditions of a matching rule: its side of the ride type, and for offers in the
     * billing variant a free seat (#325).
     *
     * @param string $side self::OFFER or self::REQUEST
     * @return array customsearch
     */
    protected function match_conditions(string $side): array {
        $types = explode("\n", $this->fielddefs['fahrttyp']['params']['param1']);
        $position = (string) (array_search($side, $types, true) + 1);
        $conditions = [$this->fieldids['fahrttyp'] => ['AND' => [['', 'ANY_OF', [$position]]]]];
        if ($side === self::OFFER) {
            $conditions += $this->ride_conditions();
        }
        return $conditions;
    }

    /**
     * What makes an offer a ride others can join, in the billing variant: published (#342), not
     * cancelled, with a free seat (#325). None in the basic variant.
     *
     * @return array customsearch
     */
    protected function ride_conditions(): array {
        // A ride that has left is no longer offered.
        $conditions = [$this->fieldids['termin'] => ['AND' => [['', 'UPCOMING', '']]]];
        if ($this->has('veroeffentlicht')) {
            $conditions[$this->fieldids['veroeffentlicht']] = ['AND' => [['', 'ANY_OF', ['1']]]];
        }
        if ($this->has('fahrtstatus')) {
            $conditions[$this->fieldids['fahrtstatus']] = ['AND' => [['NOT', 'ANY_OF', ['2']]]];
        }
        if ($this->has('passagiere')) {
            $conditions[$this->fieldids['passagiere']] = ['AND' => [['', 'FREEPLACES', '']]];
        }
        return $conditions;
    }

    /**
     * What the counterpart of a matching rule must meet: a request finds only offers with a free seat.
     *
     * @param string $side
     * @return array customsearch
     */
    protected function match_counterpart(string $side): array {
        return $side === self::REQUEST ? $this->ride_conditions() : [];
    }

    /**
     * Create the rules.
     */
    protected function create_rules(): void {
        global $DB;
        $rules = $this->rules();
        foreach ($rules as $name => $def) {
            $DB->insert_record('datalynx_rules', (object) array_merge(
                ['dataid' => $this->dataid, 'name' => $name, 'enabled' => 1],
                $def
            ));
        }
        definition_cache::invalidate($this->dataid);
        $this->log('Created ' . count($rules) . ' rules.');
    }

    /**
     * Activity CSS, caches.
     */
    protected function finish(): void {
        global $DB;
        $css = "/* Ride sharing activity */\n"
            . ".datalynx-ride-field .form-text {\n    font-size: 0.85rem;\n}\n"
            . ".datalynx-ride-field .datalynx-itinerary-picker {\n    margin-top: 0.25rem;\n}\n"
            . ".datalynx-ride-field label.form-label {\n    margin-bottom: 0.25rem;\n}\n";
        $DB->update_record('datalynx', (object) ['id' => $this->dataid, 'css' => $css, 'js' => '', 'timemodified' => time()]);
        definition_cache::invalidate($this->dataid);
        rebuild_course_cache($this->course->id, true);
    }
}
