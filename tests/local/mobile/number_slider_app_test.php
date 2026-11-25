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
use mod_datalynx\datalynx;
use mod_datalynx\local\field_format\manager;
use mod_datalynx\mobile_test_trait;
use mod_datalynx\output\mobile;

/**
 * A number field with a slider format is a slider in the Moodle App, over the web slider's positions.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_number\renderer::get_edit_control
 * @covers     \mod_datalynx\output\mobile::mobile_edit_entry
 */
final class number_slider_app_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * The slider's positions and labels, its start for a new and a stored value, and the value saved.
     */
    public function test_slider_in_the_app(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$course, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'number', 'Umweg', ['param1' => 0]);
        manager::save_format((object) ['dataid' => $dlx->id(), 'name' => 'slider', 'fieldtype' => 'number',
            'settings' => json_encode(['inputtype' => 'slider', 'sliderstart' => '0', 'sliderend' => '20',
                'sliderscale' => 'linear', 'sliderstep' => '5', 'sliderunit' => ' km'])]);
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Angebot', '[[Umweg:slider]] [[Plain]] ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $plainid = $this->add_mobile_field($dlx, 'number', 'Plain');
        $dlx = $this->set_mobile_default_view($dlx, $viewid);
        $open = fn(int $entryid = 0, array $args = []): array => json_decode(mobile::mobile_edit_entry(array_map('strval', $args + [
            'cmid' => $dlx->cm->id, 'courseid' => $course->id, 'viewid' => $viewid, 'entryid' => $entryid,
        ]))['otherdata']['state'], true);

        $field = fn(array $state, int $id): array => array_column($state['fields'], null, 'fieldid')[$id];
        $state = $open();
        $slider = $field($state, $fieldid);
        $this->assertSame('slider', $slider['control']);
        $this->assertSame(['0', '5', '10', '15', '20'], array_column($slider['choices'], 'value'));
        $this->assertSame(['0 km', '5 km', '10 km', '15 km', '20 km'], array_column($slider['choices'], 'label'));
        $this->assertSame('0', $slider['inputs'][0]['value'], 'a new entry starts at the first position, as on the web');
        $this->assertSame(0, $slider['index']);
        $this->assertSame('number', $field($state, $plainid)['control'], 'without the format a number stays a number');

        // A stored value off the scale snaps to the closest position.
        $entryid = $this->add_mobile_entry($dlx, get_admin()->id, [$fieldid => '12']);
        $slider = $field($open($entryid), $fieldid);
        $this->assertSame('10', $slider['inputs'][0]['value']);
        $this->assertSame(2, $slider['index']);

        // The app saves the value of the chosen position.
        $open($entryid, ['save' => 1, $slider['inputs'][0]['formname'] => '15']);
        $this->assertSame('15', $DB->get_field('datalynx_contents', 'content', ['entryid' => $entryid, 'fieldid' => $fieldid]));
    }
}
