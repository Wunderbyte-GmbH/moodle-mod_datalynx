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

namespace mod_datalynx;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use MoodleQuickForm;

/**
 * The filter and behavior forms pass a saved list value JSON-encoded to render_search_mode();
 * the search elements are preset with the saved values (mod_datalynx#318).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\field\datalynxfield_renderer::decode_saved_search_value
 */
final class search_value_defaults_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Number, time, team member, tag and multiple choice search elements show the saved values.
     */
    public function test_saved_list_values_are_preset(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $ids = [];
        $types = [
            'number' => [], 'time' => [], 'teammemberselect' => ['param2' => '[1,2,3,4]'], 'tag' => [],
            'multiselect' => ['param1' => "A\nB\nC"],
        ];
        foreach ($types as $type => $params) {
            $ids[$type] = $this->add_mobile_field($dlx, $type, ucfirst($type), $params);
        }
        $dlx = new datalynx($dlx->id());
        $render = function (string $type, array $saved) use ($dlx, $ids): MoodleQuickForm {
            $mform = new MoodleQuickForm('filter', 'post', '');
            [$elements] = $dlx->get_field_from_id($ids[$type])->renderer()->render_search_mode($mform, 0, json_encode($saved));
            foreach ($elements as $element) {
                $mform->addElement($element);
            }
            return $mform;
        };

        $number = $render('number', ['5', '10']);
        $this->assertSame('5', $number->_defaultValues["f_0_{$ids['number']}[0]"] ?? null);
        $this->assertSame('10', $number->_defaultValues["f_0_{$ids['number']}[1]"] ?? null);

        $time = $render('time', [1700000000, 1700086400]);
        $this->assertSame(1700000000, $time->_defaultValues["f_0_{$ids['time']}_from"] ?? null);
        $this->assertSame(1700086400, $time->_defaultValues["f_0_{$ids['time']}_to"] ?? null);

        $team = $render('teammemberselect', [2]);
        $this->assertSame([2], $team->_defaultValues["f_0_{$ids['teammemberselect']}"] ?? null);

        $tag = $render('tag', ['red', 'blue']);
        $this->assertSame(['red', 'blue'], $tag->getElement("f_0_{$ids['tag']}")->getValue());

        $multi = $render('multiselect', ['1', '3']);
        $this->assertSame(['1', '3'], $multi->_defaultValues["f_0_{$ids['multiselect']}"] ?? null);
    }
}
