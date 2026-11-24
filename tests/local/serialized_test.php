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

namespace mod_datalynx\local;

use stdClass;

/**
 * The reader of the values datalynx stores serialized: the stored format reads as before.
 *
 * @package    mod_datalynx
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\serialized
 */
final class serialized_test extends \basic_testcase {
    /**
     * Stored filters, behaviours and settings read back exactly as they were written.
     */
    public function test_stored_values_read_as_written(): void {
        $customsearch = [12 => ['AND' => [['', '=', 'a'], ['NOT', 'ANY_OF', [1, 2]]]], 'approve' => ['OR' => [['', '', 1]]]];
        $customsort = [12 => 0, 'timecreated' => 1];
        $visibleto = ['permissions' => [1, 2, 3], 'users' => [], 'teammember' => []];
        foreach ([$customsearch, $customsort, $visibleto] as $value) {
            $this->assertSame($value, serialized::to_array(serialize($value)));
            $this->assertSame($value, serialized::decode(serialize($value)));
        }

        $settings = (object) ['orientation' => 'P', 'header' => (object) ['text' => 'x', 'marginleft' => 15]];
        $this->assertEquals($settings, serialized::decode(serialize($settings)));
        $this->assertInstanceOf(stdClass::class, serialized::decode(serialize($settings))->header);
    }

    /**
     * Arrays already decoded pass; empty, broken and non-array values give an empty array.
     */
    public function test_to_array_fallbacks(): void {
        $this->assertSame([1 => 0], serialized::to_array([1 => 0]));
        foreach ([null, '', false, 'N;', 'b:0;', 's:1:"a";', 'a:1:{i:0;s:5:"ab";}', 'not serialized'] as $value) {
            $this->assertSame([], serialized::to_array($value), var_export($value, true));
        }
        $this->assertFalse(serialized::decode(''));
        $this->assertFalse(serialized::decode(null));
    }

    /**
     * No class besides stdClass and the ones named is instantiated: no object injection.
     */
    public function test_other_classes_are_not_instantiated(): void {
        $message = new \core\message\message();
        $message->subject = 'Hello';
        $message->userto = (object) ['id' => 5];
        $stored = serialize([$message]);

        $plain = serialized::decode($stored);
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $plain[0]);

        // The message queue names the message class: tasks queued before an upgrade still send.
        $queued = serialized::decode($stored, [\core\message\message::class]);
        $this->assertInstanceOf(\core\message\message::class, $queued[0]);
        $this->assertSame('Hello', $queued[0]->subject);
        $this->assertSame(5, $queued[0]->userto->id);
    }
}
