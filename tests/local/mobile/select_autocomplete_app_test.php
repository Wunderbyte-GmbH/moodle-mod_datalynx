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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\mobile_test_trait;
use mod_datalynx\output\mobile;

/**
 * A single choice with autocomplete is a search box in the Moodle App.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_select\renderer::get_edit_control
 * @covers     \mod_datalynx\output\mobile::mobile_edit_entry
 */
final class select_autocomplete_app_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * The box shows the label of the stored choice, and the chosen value is saved.
     */
    public function test_autocomplete_in_the_app(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $dlx] = $this->create_mobile_instance();
        $brandid = $this->add_mobile_field($dlx, 'select', 'Marke', ['param1' => "Audi\nBMW\nVolkswagen", 'param6' => 1]);
        $colourid = $this->add_mobile_field($dlx, 'select', 'Farbe', ['param1' => "Rot\nBlau"]);
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Fahrzeug', '[[Marke]] [[Farbe]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $dlx = $this->set_mobile_default_view($dlx, $viewid);
        $open = fn(int $entryid = 0, array $args = []): array => json_decode(mobile::mobile_edit_entry(array_map('strval', $args + [
            'cmid' => $dlx->cm->id, 'courseid' => $course->id, 'viewid' => $viewid, 'entryid' => $entryid,
        ]))['otherdata']['state'], true);
        $field = fn(array $state, int $id): array => array_column($state['fields'], null, 'fieldid')[$id];

        $state = $open();
        $brand = $field($state, $brandid);
        $this->assertTrue($brand['autocomplete']);
        $this->assertSame('', $brand['typed']);
        $this->assertFalse($field($state, $colourid)['autocomplete'], 'a select without autocomplete stays a list');

        $entryid = $this->add_mobile_entry($dlx, get_admin()->id, [$brandid => '3']);
        $brand = $field($open($entryid), $brandid);
        $this->assertSame('Volkswagen', $brand['typed']);

        $open($entryid, ['save' => 1, $brand['inputs'][0]['formname'] => '2']);
        $this->assertSame('2', $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $brandid]));
    }
}
