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
 * Tests for compiling relations to another entry into ordinary search criteria.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\rule\match_compiler;

/**
 * What an entry stores is not always what a field's search expects to receive, so every field type
 * that can be matched against another entry has to be checked on its own.
 *
 * @group mod_datalynx
 * @covers \mod_datalynx\local\rule\match_compiler
 * @covers \mod_datalynx\local\field\datalynxfield_base::compile_relative_criterion
 */
final class rule_match_compiler_test extends advanced_testcase {
    /** @var datalynx The datalynx instance used by the current test. */
    private datalynx $dlx;

    /**
     * Set up a fresh datalynx instance per test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Insert a field of the given type and return its id.
     *
     * @param string $type
     * @param string $name
     * @param string $param1
     * @return int
     */
    private function make_field(string $type, string $name, string $param1 = ''): int {
        global $DB;
        return (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => $type, 'name' => $name, 'description' => '',
            'required' => 0, 'visibleto' => 0, 'editableby' => 0,
            'param1' => $param1, 'param2' => '', 'param3' => '', 'param4' => '', 'param5' => '',
            'param6' => '', 'param7' => '', 'param8' => '', 'param9' => '', 'param10' => '',
        ]);
    }

    /**
     * Insert an entry with one content value.
     *
     * @param int $fieldid
     * @param string|null $content
     * @return int
     */
    private function make_entry(int $fieldid, ?string $content): int {
        global $DB, $USER;
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $this->dlx->id(), 'userid' => $USER->id, 'groupid' => 0,
            'approved' => 1, 'status' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        if ($content !== null) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
            ]);
        }
        return $entryid;
    }

    /**
     * Compile one criterion against one entry.
     *
     * @param array $criterion
     * @param int $entryid
     * @return array the compile() result
     */
    private function compile(array $criterion, int $entryid): array {
        return (new match_compiler($this->dlx))->compile([$criterion], $entryid);
    }

    /**
     * A text field states equality directly.
     */
    public function test_same_on_text_compiles_to_equality(): void {
        $fieldid = $this->make_field('text', 'Organiser');
        $entryid = $this->make_entry($fieldid, 'Wunderbyte');

        $result = $this->compile(['fieldid' => $fieldid, 'op' => match_compiler::OP_SAME], $entryid);

        $this->assertFalse($result['impossible']);
        $this->assertSame([$fieldid => ['AND' => [['', '=', 'Wunderbyte']]]], $result['customsearch']);
    }

    /**
     * The different-value relation is the same criterion, negated.
     */
    public function test_different_negates_the_criterion(): void {
        $fieldid = $this->make_field('text', 'Organiser');
        $entryid = $this->make_entry($fieldid, 'Wunderbyte');

        $result = $this->compile(['fieldid' => $fieldid, 'op' => match_compiler::OP_DIFFERENT], $entryid);

        $this->assertSame([$fieldid => ['AND' => [['NOT', '=', 'Wunderbyte']]]], $result['customsearch']);
    }

    /**
     * A select field stores the position of the chosen option, which is what ANY_OF searches for.
     */
    public function test_same_on_select_compiles_to_anyof_the_stored_position(): void {
        $fieldid = $this->make_field('select', 'Ride type', "Offer a ride\nLooking for a ride");
        $entryid = $this->make_entry($fieldid, '2');

        $result = $this->compile(['fieldid' => $fieldid, 'op' => match_compiler::OP_SAME], $entryid);

        $this->assertSame([$fieldid => ['AND' => [['', 'ANY_OF', [2]]]]], $result['customsearch']);
    }

    /**
     * A checkbox field stores "#1#,#3#" but EXACTLY wants the bare positions.
     */
    public function test_same_on_checkbox_parses_the_stored_option_list(): void {
        $fieldid = $this->make_field('checkbox', 'Colors', "Red\nGreen\nBlue");
        $entryid = $this->make_entry($fieldid, '#3#,#1#');

        $result = $this->compile(['fieldid' => $fieldid, 'op' => match_compiler::OP_SAME], $entryid);

        $this->assertSame([$fieldid => ['AND' => [['', 'EXACTLY', [1, 3]]]]], $result['customsearch']);
    }

    /**
     * A number field's search values are arrays; a bare string would be read character by character.
     */
    public function test_same_on_number_wraps_the_value_in_an_array(): void {
        $fieldid = $this->make_field('number', 'Seats');
        $entryid = $this->make_entry($fieldid, '12');

        $result = $this->compile(['fieldid' => $fieldid, 'op' => match_compiler::OP_SAME], $entryid);

        $this->assertSame([$fieldid => ['AND' => [['', '=', [12.0]]]]], $result['customsearch']);
    }

    /**
     * A tolerance becomes a range around the other entry's value.
     */
    public function test_within_on_time_compiles_to_a_range_around_the_value(): void {
        $fieldid = $this->make_field('time', 'Departure');
        $departure = 1800000000;
        $entryid = $this->make_entry($fieldid, (string) $departure);

        $result = $this->compile([
            'fieldid' => $fieldid,
            'op' => match_compiler::OP_WITHIN,
            'tolerance' => 2,
            'unit' => HOURSECS,
        ], $entryid);

        [$not, $operator, $value] = $result['customsearch'][$fieldid]['AND'][0];
        $this->assertSame('', $not);
        $this->assertSame('BETWEEN', $operator);
        // The bounds are widened, because BETWEEN excludes at least one end: a counterpart exactly
        // two hours away has to stay inside a two hour window.
        $this->assertLessThan($departure - 2 * HOURSECS, $value[0]);
        $this->assertGreaterThan($departure + 2 * HOURSECS, $value[1]);
    }

    /**
     * A tolerance of zero is not a window, it is a misconfiguration.
     */
    public function test_within_without_a_tolerance_is_impossible(): void {
        $fieldid = $this->make_field('time', 'Departure');
        $entryid = $this->make_entry($fieldid, '1800000000');

        $result = $this->compile([
            'fieldid' => $fieldid,
            'op' => match_compiler::OP_WITHIN,
            'tolerance' => 0,
            'unit' => HOURSECS,
        ], $entryid);

        $this->assertTrue($result['impossible']);
    }

    /**
     * An entry with no value cannot be matched on that field, and must not match everything.
     */
    public function test_absent_value_makes_the_search_impossible(): void {
        $fieldid = $this->make_field('text', 'Organiser');
        $entryid = $this->make_entry($fieldid, null);

        $result = $this->compile(['fieldid' => $fieldid, 'op' => match_compiler::OP_SAME], $entryid);

        $this->assertTrue($result['impossible']);
        $this->assertSame([], $result['customsearch']);
    }

    /**
     * A relation the field cannot express is a misconfiguration, not a criterion to ignore.
     */
    public function test_unsupported_relation_is_impossible(): void {
        $fieldid = $this->make_field('text', 'Organiser');
        $entryid = $this->make_entry($fieldid, 'Wunderbyte');

        $result = $this->compile(['fieldid' => $fieldid, 'op' => match_compiler::OP_ROUTE], $entryid);

        $this->assertTrue($result['impossible']);
    }

    /**
     * Every criterion has to hold, so they are collected in one AND group.
     */
    public function test_several_criteria_are_combined_with_and(): void {
        $organiser = $this->make_field('text', 'Organiser');
        $seats = $this->make_field('number', 'Seats');
        $entryid = $this->make_entry($organiser, 'Wunderbyte');
        $this->make_content($seats, $entryid, '4');

        $result = (new match_compiler($this->dlx))->compile([
            ['fieldid' => $organiser, 'op' => match_compiler::OP_SAME],
            ['fieldid' => $seats, 'op' => match_compiler::OP_DIFFERENT],
        ], $entryid);

        $this->assertFalse($result['impossible']);
        $this->assertCount(2, $result['customsearch']);
        $this->assertSame([['', '=', 'Wunderbyte']], $result['customsearch'][$organiser]['AND']);
        $this->assertSame([['NOT', '=', [4.0]]], $result['customsearch'][$seats]['AND']);
    }

    /**
     * Only fields that can be compared with another entry are offered.
     */
    public function test_eligible_fields_lists_only_comparable_fields(): void {
        $text = $this->make_field('text', 'Organiser');
        $select = $this->make_field('select', 'Ride type', "a\nb");
        $number = $this->make_field('number', 'Seats');
        $this->make_field('file', 'Attachment');
        $this->make_field('picture', 'Photo');

        $eligible = match_compiler::eligible_fields($this->dlx);

        $this->assertEqualsCanonicalizing([$text, $select, $number], array_keys($eligible));
    }

    /**
     * Which relations a field offers depends on what it can be compared for.
     */
    public function test_operators_offered_per_field_type(): void {
        $textid = $this->make_field('text', 'Organiser');
        $timeid = $this->make_field('time', 'Departure');
        $selectid = $this->make_field('select', 'Ride type', "a\nb");
        $fields = $this->dlx->get_fields(null, false, true);

        $this->assertEqualsCanonicalizing(
            [match_compiler::OP_SAME, match_compiler::OP_DIFFERENT],
            array_keys(match_compiler::operators_for($fields[$textid]))
        );
        $this->assertEqualsCanonicalizing(
            [match_compiler::OP_SAME, match_compiler::OP_DIFFERENT, match_compiler::OP_WITHIN],
            array_keys(match_compiler::operators_for($fields[$timeid]))
        );
        $this->assertEqualsCanonicalizing(
            [match_compiler::OP_SAME, match_compiler::OP_DIFFERENT],
            array_keys(match_compiler::operators_for($fields[$selectid]))
        );
    }

    /**
     * Add a content value to an existing entry.
     *
     * @param int $fieldid
     * @param int $entryid
     * @param string $content
     */
    private function make_content(int $fieldid, int $entryid, string $content): void {
        global $DB;
        $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => $entryid, 'content' => $content,
        ]);
    }
}
