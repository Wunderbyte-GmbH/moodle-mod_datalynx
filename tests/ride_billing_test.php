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

namespace mod_datalynx;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/stub_router.php');

use advanced_testcase;
use mod_datalynx\local\field\datalynxfield_behavior;
use mod_datalynx\local\map\router_factory;
use mod_datalynx\local\ride\board_builder;
use mod_datalynx\local\ride\schedule;

/**
 * The Fahrtenschein as a view of the ride (mod_datalynx#343) with photo and declaration (#331).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\ride\board_builder
 * @covers     \datalynxfield_schedule\field
 * @covers     \datalynxfield_entryauthor\renderer
 * @covers     \datalynxfield_itinerary\field
 * @covers     \datalynxfield_picture\field
 */
final class ride_billing_test extends advanced_testcase {
    /** @var board_builder */
    protected board_builder $builder;

    /** @var int[] */
    protected array $ids;

    /** @var \stdClass */
    protected \stdClass $course;

    /** @var \stdClass */
    protected \stdClass $driver;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        router_factory::set_router_for_testing(new stub_router());
        \datalynxfield_itinerary\field::reset_server_routes();
        $this->course = $this->getDataGenerator()->create_course();
        $this->builder = new board_builder($this->course, board_builder::VARIANT_BILLING);
        $this->builder->build();
        $this->ids = $this->builder->get_fieldids_by_name();
        $this->driver = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    protected function tearDown(): void {
        router_factory::set_router_for_testing(null);
        parent::tearDown();
    }

    /**
     * A ride of the driver on a day relative to today.
     *
     * @param int $days
     * @param array $contents more contents by field name
     * @param int $start departure, minutes after midnight
     * @return int
     */
    protected function ride(int $days, array $contents = [], int $start = 480): int {
        global $DB;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) ['dataid' => $this->builder->get_dataid(),
            'userid' => $this->driver->id, 'groupid' => 0, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        $dlx = new datalynx($this->builder->get_dataid());
        $termin = $dlx->get_field_from_id($this->ids['Termin'], true);
        $termin->update_content((object) ['id' => $entryid], ['schedule' => (new schedule(
            schedule::MODE_ONCE,
            date('Y-m-d', strtotime("$days days")),
            [],
            $start
        ))->to_json()]);
        $route = $dlx->get_field_from_id($this->ids['Route'], true);
        $route->update_content((object) ['id' => $entryid], ['waypoints' => json_encode([
            ['address' => 'Wien', 'lat' => 48.2, 'lng' => 16.37], ['address' => 'Graz', 'lat' => 47.07, 'lng' => 15.44]])]);
        foreach (['Fahrttyp' => '1'] + $contents as $name => $content) {
            $DB->insert_record('datalynx_contents', (object) ['fieldid' => $this->ids[$name], 'entryid' => $entryid,
                'content' => $content]);
        }
        return $entryid;
    }

    /**
     * The board: billing fields, their behaviours, the billing view and the IBAN profile field.
     */
    public function test_board(): void {
        global $DB;
        foreach (
            ['Anlass', 'Rückfahrt', 'Beantragte Kilometer', 'Begründung', 'Dienstfahrzeug', 'Parkfoto',
                'Eigenerklärung'] as $name
        ) {
            $this->assertArrayHasKey($name, $this->ids);
        }
        $this->assertTrue($DB->record_exists('user_info_field', ['shortname' => 'iban']));
        $route = json_decode($DB->get_field('datalynx_fields', 'param9', ['id' => $this->ids['Route']]), true);
        $this->assertSame(['billing' => 1, 'returnfield' => $this->ids['Rückfahrt'],
            'requestedfield' => $this->ids['Beantragte Kilometer'], 'justificationfield' => $this->ids['Begründung']], $route);
        $photo = $DB->get_record('datalynx_fields', ['id' => $this->ids['Parkfoto']]);
        $this->assertSame(['1600', '1600'], [$photo->param7, $photo->param8]);

        $view = $DB->get_field('datalynx_views', 'param2', ['dataid' => $this->builder->get_dataid(), 'name' => 'Fahrt abrechnen']);
        foreach (
            ['[[Anlass|abrechnungpflicht|', '[[Parkfoto|abrechnungpflicht|', '[[Eigenerklärung|abrechnungpflicht|',
                '##author:iban##', '##status##', '[[Route:billeddistance|abrechnunglesen]]'] as $tag
        ) {
            $this->assertStringContainsString($tag, $view);
        }
    }

    /**
     * Billing fields are seen by the driver and managers, not by other users.
     */
    public function test_billing_fields_are_private(): void {
        $dlx = new datalynx($this->builder->get_dataid());
        $behavior = datalynxfield_behavior::from_name('abrechnung', $dlx->id(), $dlx);
        $ride = (object) ['id' => $this->ride(-1), 'userid' => $this->driver->id];
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $this->setUser($this->driver);
        $this->assertTrue($behavior->is_visible_to_user($ride));
        $this->setUser($other);
        $this->assertFalse($behavior->is_visible_to_user($ride));
        $this->setAdminUser();
        $this->assertTrue($behavior->is_visible_to_user($ride));
    }

    /**
     * Past and upcoming rides, as the billing filter and "Meine Fahrten" see them.
     */
    public function test_past_and_upcoming(): void {
        global $DB;
        $past = $this->ride(-2);
        $future = $this->ride(2);
        $expected = ['PAST' => [$past], 'UPCOMING' => [$future]];
        // Today: departed an hour ago, or leaving in an hour (not around midnight, where that is another day).
        $minutes = (int) date('G') * 60 + (int) date('i');
        if ($minutes >= 90 && $minutes <= 22 * 60) {
            $expected['PAST'][] = $this->ride(0, [], $minutes - 60);
            $expected['UPCOMING'][] = $this->ride(0, [], $minutes + 60);
        }
        $field = (new datalynx($this->builder->get_dataid()))->get_field_from_id($this->ids['Termin'], true);
        foreach ($expected as $operator => $ids) {
            [$sql, $params] = $field->get_search_sql(['', $operator, '']);
            $found = $DB->get_fieldset_sql(
                "SELECT e.id FROM {datalynx_entries} e WHERE e.dataid = :dataid AND $sql ORDER BY e.id",
                ['dataid' => $this->builder->get_dataid()] + $params
            );
            $this->assertSame($ids, array_map('intval', $found), $operator);
        }
    }

    /**
     * More kilometres asked for than the billed route, without a reason, are refused also when only
     * the number is submitted; the IBAN is checked.
     */
    public function test_requested_km_and_iban(): void {
        $ride = $this->ride(-1);
        $dlx = new datalynx($this->builder->get_dataid());
        $number = $dlx->get_field_from_id($this->ids['Beantragte Kilometer'], true);
        $requested = "field_{$this->ids['Beantragte Kilometer']}_{$ride}";
        $reason = "field_{$this->ids['Begründung']}_{$ride}";
        $this->assertArrayHasKey($reason, $number->renderer()->validate($ride, [], (object) [$requested => '150']));
        $this->assertSame([], $number->renderer()->validate($ride, [], (object) [$requested => '150', $reason => 'Umleitung']));
        $this->assertSame([], $number->renderer()->validate($ride, [], (object) [$requested => '90']));

        $this->assertTrue(\datalynxfield_text\renderer::is_valid_iban('AT61 1904 3002 3457 3201'));
        $this->assertFalse(\datalynxfield_text\renderer::is_valid_iban('AT61 1904 3002 3457 3202'));
    }

    /**
     * A submitted Fahrtenschein is locked for the driver, including delete; a manager can still change it.
     */
    public function test_locked_after_submission(): void {
        global $DB;
        $ride = $this->ride(-1);
        $DB->set_field('datalynx_entries', 'status', 2, ['id' => $ride]);
        $entry = $DB->get_record('datalynx_entries', ['id' => $ride]);
        $dlx = new datalynx($this->builder->get_dataid());

        $this->setUser($this->driver);
        $this->assertFalse($dlx->user_can_manage_entry($entry));
        $this->assertFalse($dlx->user_can_delete_entry($entry));
        $this->setAdminUser();
        $this->assertTrue($dlx->user_can_manage_entry($entry));
    }

    /**
     * ##pdfdownload## links a ride to its PDF; the PDF view makes the PDF of a submitted Fahrtenschein.
     */
    public function test_pdf(): void {
        global $DB;
        $ride = $this->ride(-1, ['Anlass' => 'Schulung Erste Hilfe']);
        $DB->set_field('datalynx_entries', 'status', 2, ['id' => $ride]);
        $dlx = new datalynx($this->builder->get_dataid());
        $billing = $dlx->get_view_from_id($DB->get_field('datalynx_views', 'id', ['dataid' => $dlx->id(),
            'name' => 'Fahrt abrechnen']));
        $entry = $DB->get_record('datalynx_entries', ['id' => $ride]);
        $tag = '##pdfdownload:Fahrtenschein PDF;PDF herunterladen;btn##';
        $link = $billing->patternclass()->get_replacements([$tag], $entry)[$tag];
        $this->assertStringContainsString('pdfexportentry=' . $ride, $link);
        $this->assertStringContainsString('PDF herunterladen', $link);
        // A draft is not in the PDF view (submitted ones only): no link to an empty PDF.
        $DB->set_field('datalynx_entries', 'status', 1, ['id' => $ride]);
        $draft = $DB->get_record('datalynx_entries', ['id' => $ride]);
        $this->assertSame('', $billing->patternclass()->get_replacements([$tag], $draft)[$tag]);
        $DB->set_field('datalynx_entries', 'status', 2, ['id' => $ride]);
        $nopdf = '##pdfdownload:Fahrt abrechnen##';
        $this->assertSame('', $billing->patternclass()->get_replacements([$nopdf], $entry)[$nopdf]);

        $pdfview = $dlx->get_view_from_id($DB->get_field('datalynx_views', 'id', ['dataid' => $dlx->id(),
            'name' => 'Fahrtenschein PDF']));
        // The file name of the PDF names the Fahrtenschein and the driver (the export itself ends the request).
        $filter = (new \ReflectionProperty($pdfview, 'filter'))->getValue($pdfview);
        $filter->eids = $ride;
        $pdfview->set_content();
        $name = (new \ReflectionMethod($pdfview, 'get_documentname'))->invoke(
            $pdfview,
            'Fahrtenschein_##entryid##_##author:lastname##'
        );
        $this->assertSame(clean_param('Fahrtenschein_' . $ride . '_' . $this->driver->lastname, PARAM_FILE) . '.pdf', $name);
    }

    /**
     * Anyone may open the PDF view with another entry's id: the billing data stay the driver's.
     */
    public function test_pdf_hides_billing_data_from_others(): void {
        global $DB;
        $ride = $this->ride(-1, ['Anlass' => 'Schulung Erste Hilfe']);
        $DB->set_field('datalynx_entries', 'status', 2, ['id' => $ride]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $render = function (\stdClass $user) use ($DB, $ride): string {
            $this->setUser($user);
            $dlx = new datalynx($this->builder->get_dataid());
            $view = $dlx->get_view_from_id($DB->get_field('datalynx_views', 'id', ['dataid' => $dlx->id(),
                'name' => 'Fahrtenschein PDF']));
            (new \ReflectionProperty($view, 'filter'))->getValue($view)->eids = $ride;
            $view->set_content();
            return $view->display(['export' => true, 'tohtml' => true, 'controls' => false, 'entryactions' => false]);
        };
        $this->assertStringContainsString('Schulung Erste Hilfe', $render($this->driver));
        $this->assertStringNotContainsString('Schulung Erste Hilfe', $render($other));
    }

    /**
     * Cancelling a ride tells its passengers so, in the words of the board's message view.
     */
    public function test_cancel_notifies_passengers(): void {
        global $DB;
        $passenger = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $ride = $this->ride(3, ['Passagiere' => json_encode([(int) $passenger->id]), 'Fahrtstatus' => '1']);
        $this->setUser($this->driver);
        $sink = $this->redirectMessages();
        $entries = new local\datalynx_entries(new datalynx($this->builder->get_dataid()));
        $entries->process_entries('update', (string) $ride, (object) ["field_{$this->ids['Fahrtstatus']}_{$ride}" => 2], true);
        ob_start();
        $this->run_all_adhoc_tasks();
        ob_end_clean();
        $messages = array_values(array_filter($sink->get_messages(), fn($m) => (int) $m->useridto === (int) $passenger->id));
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertSame('Mitfahrbörse: eine Fahrt wurde abgesagt', $messages[0]->subject);
        $this->assertStringContainsString('hat die folgende Fahrt abgesagt', $messages[0]->fullmessagehtml);
        $this->assertSame('2', $DB->get_field('datalynx_contents', 'content', ['entryid' => $ride,
            'fieldid' => $this->ids['Fahrtstatus']]));
    }

    /**
     * The parking photo is scaled down to 1600 px on the long edge.
     */
    public function test_photo_downscaled(): void {
        global $USER;
        $ride = $this->ride(-1);
        $image = imagecreatetruecolor(3000, 2000);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        $draftid = file_get_unused_draft_itemid();
        $fs = get_file_storage();
        $fs->create_file_from_string(['contextid' => \context_user::instance($USER->id)->id, 'component' => 'user',
            'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/', 'filename' => 'parked.png'], $png);

        $dlx = new datalynx($this->builder->get_dataid());
        $photo = $dlx->get_field_from_id($this->ids['Parkfoto'], true);
        $photo->update_content((object) ['id' => $ride], ['filemanager' => $draftid]);

        $files = array_filter(
            $fs->get_area_files($dlx->context->id, 'mod_datalynx', 'content', false, 'id', false),
            fn($file) => $file->get_filename() === 'parked.png'
        );
        $this->assertCount(1, $files);
        $info = reset($files)->get_imageinfo();
        $this->assertLessThanOrEqual(1600, max($info['width'], $info['height']));
    }
}
