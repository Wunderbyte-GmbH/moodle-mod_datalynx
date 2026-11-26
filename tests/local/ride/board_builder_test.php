<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx\local\ride;

use advanced_testcase;

/**
 * The ride sharing board builder: both variants, their references, and a restored copy.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\ride\board_builder
 * @covers     \mod_datalynx\local\ride\board_seeder
 */
final class board_builder_test extends advanced_testcase {
    /**
     * The structure of a board by names: every id that points at another part is replaced by its name.
     *
     * @param int $dataid
     * @return array
     */
    public static function structure(int $dataid): array {
        global $DB;
        $fields = $DB->get_records_menu('datalynx_fields', ['dataid' => $dataid], 'id', 'id, name');
        $views = $DB->get_records_menu('datalynx_views', ['dataid' => $dataid], 'id', 'id, name');
        $filters = $DB->get_records_menu('datalynx_filters', ['dataid' => $dataid], 'id', 'id, name');
        $field = fn($id) => $fields[$id] ?? "missing field $id";
        $filternames = array_values($filters);
        sort($filternames);
        $structure = ['fields' => [], 'views' => [], 'filters' => $filternames];
        foreach ($DB->get_records('datalynx_fields', ['dataid' => $dataid], 'id') as $record) {
            $structure['fields'][$record->name] = $record->type;
        }
        foreach ($DB->get_records('datalynx_views', ['dataid' => $dataid]) as $record) {
            $structure['views'][$record->name] = [
                'type' => $record->type,
                'filter' => $record->filter ? ($filters[$record->filter] ?? 'missing filter') : '',
                'next' => $record->param10 ? ($views[$record->param10] ?? 'missing view') : '',
            ];
        }
        foreach ($DB->get_records('datalynx_behaviors', ['dataid' => $dataid]) as $record) {
            $conditions = json_decode((string) $record->conditions, true)['rules'] ?? [];
            $structure['behaviors'][$record->name] = array_map(fn($rule) => $field($rule['sourcefieldid']), $conditions);
        }
        foreach ($DB->get_records('datalynx_rules', ['dataid' => $dataid]) as $record) {
            $params = json_decode((string) $record->param9, true) ?: [];
            $criteria = array_map(fn($c) => $field($c['fieldid']), $params['_matchcriteria']['criteria'] ?? []);
            $structure['rules'][$record->name] = [
                'type' => $record->type,
                'email' => $record->type === 'eventnotification' ? ($views[$record->param8] ?? 'missing view') : '',
                'conditions' => array_map($field, array_filter(array_keys($params), 'is_numeric')),
                'criteria' => $criteria,
            ];
        }
        foreach ($DB->get_records('datalynx_customfilters', ['dataid' => $dataid]) as $record) {
            $structure['customfilters'][$record->name] = array_map($field, array_keys(json_decode($record->fieldlist, true)));
        }
        $structure['layouts'] = $DB->get_fieldset_select('datalynx_renderers', 'name', 'dataid = ? ORDER BY name', [$dataid]);
        $structure['default'] = $views[$DB->get_field('datalynx', 'defaultview', ['id' => $dataid])] ?? '';
        return $structure;
    }

    /**
     * Build a board of the given variant in a new course.
     *
     * @param string $variant
     * @return board_builder
     */
    protected function build(string $variant): board_builder {
        $course = $this->getDataGenerator()->create_course();
        $builder = new board_builder($course, $variant);
        $builder->build();
        return $builder;
    }

    /**
     * The basic board: parts, references, and that every view a template links to exists.
     */
    public function test_basic(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $builder = $this->build(board_builder::VARIANT_BASIC);
        $structure = self::structure($builder->get_dataid());

        $this->assertSame([
            'Fahrttyp' => 'radiobutton', 'Route' => 'itinerary', 'Umweg' => 'number', 'Termin' => 'schedule',
            'Marke' => 'select', 'Modell' => 'text', 'Kennzeichen' => 'text', 'Freie Plätze' => 'select',
            'Gepäck' => 'select', 'Mitfahrende' => 'select', 'Fahrkosten' => 'select', 'Fixbetrag' => 'number',
            'Bemerkungen' => 'textarea',
        ], $structure['fields']);
        $this->assertEqualsCanonicalizing(['Meine Einträge', 'Nur Angebote', 'Nur Gesuche'], $structure['filters']);
        $this->assertEqualsCanonicalizing(
            array_values(array_diff_key(board_builder::VIEWS, ['manage' => 1])),
            array_keys($structure['views']),
            'basic has no view to manage single rides'
        );
        $this->assertSame('Mitfahrbörse', $structure['default']);

        // The wizard chain and the filters of the views.
        $views = $structure['views'];
        $this->assertSame(board_builder::VIEWS['step1'], $views['Mitfahrbörse']['next']);
        $this->assertSame(board_builder::VIEWS['step2'], $views[board_builder::VIEWS['step1']]['next']);
        $this->assertSame('Meine Fahrten', $views[board_builder::VIEWS['step5']]['next']);
        $this->assertSame('Meine Fahrten', $views['Mitfahrgesuch anlegen']['next']);
        $this->assertSame('Meine Einträge', $views[board_builder::VIEWS['step3']]['filter']);
        $this->assertSame('Nur Angebote', $views['Fahrt suchen']['filter']);
        $this->assertSame('email', $views['Benachrichtigung: Passende Fahrt']['type']);

        // Rules and behaviours point at the right fields and views.
        $this->assertSame(['Fahrttyp'], $structure['behaviors']['angebotpflicht']);
        $this->assertSame(['Fahrkosten'], $structure['behaviors']['festbetrag']);
        $match = $structure['rules']['Passende Fahrt gefunden (1)'];
        $this->assertSame('Benachrichtigung: Passende Fahrt', $match['email']);
        $this->assertSame(['Fahrttyp', 'Route', 'Termin'], $match['criteria']);
        $this->assertSame('updatefield', $structure['rules']['Mitfahrgesuch: Fahrttyp setzen']['type']);
        $this->assertSame(['Fahrttyp', 'Route', 'Termin'], $structure['customfilters']['Fahrt suchen']);
        $this->assertCount(13, $structure['layouts']);

        // Every view and field a template names exists.
        $names = array_keys($structure['views']);
        foreach ($DB->get_records('datalynx_views', ['dataid' => $builder->get_dataid()]) as $view) {
            preg_match_all('/##view(?:sess)?link:([^;#]+);/u', $view->section . $view->param2, $links);
            foreach ($links[1] as $name) {
                $this->assertContains($name, $names, "view {$view->name} links to a view that does not exist");
            }
            preg_match_all('/\[\[([^|:\]]+)/u', $view->section . $view->param2, $tags);
            foreach ($tags[1] as $name) {
                $this->assertArrayHasKey($name, $structure['fields'], "view {$view->name} names a field that does not exist");
            }
        }
    }

    /**
     * The billing variant differs from basic where it should.
     */
    public function test_billing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $builder = $this->build(board_builder::VARIANT_BILLING);
        $structure = self::structure($builder->get_dataid());
        $this->assertArrayNotHasKey('Fahrkosten', $structure['fields']);
        $this->assertArrayNotHasKey('Fixbetrag', $structure['fields']);
        $this->assertArrayNotHasKey('festbetrag', $structure['behaviors']);
        $this->assertArrayHasKey('Termin', $structure['fields']);

        // Passengers request a seat, the driver approves; at most as many as the seats (#326, #341).
        global $DB;
        $this->assertSame('teammemberselect', $structure['fields']['Passagiere']);
        $team = $DB->get_record('datalynx_fields', ['dataid' => $builder->get_dataid(), 'name' => 'Passagiere']);
        $seats = $DB->get_field('datalynx_fields', 'id', ['dataid' => $builder->get_dataid(), 'name' => 'Freie Plätze']);
        $route = $DB->get_field('datalynx_fields', 'id', ['dataid' => $builder->get_dataid(), 'name' => 'Route']);
        $detour = $DB->get_field('datalynx_fields', 'id', ['dataid' => $builder->get_dataid(), 'name' => 'Umweg']);
        $this->assertSame(['limitfield' => (int) $seats, 'approval' => 1, 'protectmembers' => 1, 'pickupfield' => (int) $route,
            'detourfield' => (int) $detour], json_decode($team->param10, true));
        foreach (['Mitfahrt beantragt', 'Mitfahrt angenommen', 'Mitfahrt abgelehnt', 'Mitfahrt zurückgezogen'] as $rule) {
            $this->assertArrayHasKey($rule, $structure['rules']);
        }
        $card = $DB->get_field('datalynx_views', 'param2', ['dataid' => $builder->get_dataid(), 'name' => 'Fahrt suchen']);
        $this->assertStringContainsString('[[Passagiere:mitfahren||l_passagiere]]', $card);
    }

    /**
     * A copy made through backup and restore (as a preset is applied) keeps every reference.
     */
    public function test_restored_copy(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $builder = $this->build(board_builder::VARIANT_BASIC);
        $cm = get_coursemodule_from_id('datalynx', $builder->get_cmid(), 0, false, MUST_EXIST);
        $copy = duplicate_module(get_course($cm->course), $cm);

        $this->assertEquals(self::structure($builder->get_dataid()), self::structure((int) $copy->instance));
    }

    /**
     * The board as a preset, applied to a new, empty activity, keeps every reference.
     */
    public function test_preset(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/datalynx/preset/preset_manager.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $builder = $this->build(board_builder::VARIANT_BILLING);
        $source = new \mod_datalynx\datalynx($builder->get_dataid());
        $this->assertTrue((new \datalynx_preset_manager($source))->create_preset_from_backup('nodata'));
        $files = get_file_storage()->get_area_files(
            \context_course::instance($source->course->id)->id,
            'mod_datalynx',
            \datalynx_preset_manager::PRESET_COURSEAREA,
            false,
            'id DESC',
            false
        );
        $preset = reset($files);

        $empty = $this->getDataGenerator()->create_module('datalynx', ['course' => $source->course->id]);
        try {
            (new \datalynx_preset_manager(new \mod_datalynx\datalynx($empty->id)))->apply_preset($preset->get_id());
        } catch (\moodle_exception $e) {
            // Applying ends with a redirect to the activity.
            $this->assertStringContainsString('redirect', $e->getMessage() . $e->errorcode);
        }

        $expected = self::structure($builder->get_dataid());
        $applied = self::structure((int) $empty->id);
        // A new activity comes with a default view of its own; only the board's parts are compared.
        foreach ($expected as $part => $value) {
            if (is_array($value) && $part === 'views') {
                $this->assertEquals($value, array_intersect_key($applied[$part], $value));
            } else {
                $this->assertEquals($value, $applied[$part], $part);
            }
        }
    }

    /**
     * The seeder fills a board with offers and requests.
     */
    public function test_seed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $builder = $this->build(board_builder::VARIANT_BASIC);
        $courseid = $DB->get_field('datalynx', 'course', ['id' => $builder->get_dataid()]);
        for ($i = 0; $i < 4; $i++) {
            $this->getDataGenerator()->create_and_enrol(get_course($courseid), 'student');
        }
        $created = (new board_seeder($builder->get_dataid()))->seed(false);

        $this->assertCount(8, $created['offers']);
        $this->assertCount(4, $created['requests']);
        $ids = $builder->get_fieldids_by_name();
        $schedule = schedule::from_json($DB->get_field(
            'datalynx_contents',
            'content',
            ['entryid' => $created['offers'][0], 'fieldid' => $ids['Termin']]
        ));
        $this->assertSame(schedule::MODE_WEEKLY, $schedule->mode());
        $this->assertSame([1, 3, 5], $schedule->days());
        // Three seats: the fourth option of the seats (0 to 8).
        $this->assertSame('4', $DB->get_field(
            'datalynx_contents',
            'content',
            ['entryid' => $created['offers'][0], 'fieldid' => $ids['Freie Plätze']]
        ));
    }
}
