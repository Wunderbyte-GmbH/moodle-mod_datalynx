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
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests that the field search builder only ever emits whitelisted SQL operators.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;

/**
 * Guards against SQL injection through the search operator (see issue #240).
 *
 * The operator reaches {@see \mod_datalynx\local\field\datalynxfield_base::get_search_sql()}
 * from the usearch request parameter. It must be validated against an allowlist and never
 * concatenated into SQL verbatim.
 *
 * @coversDefaultClass \mod_datalynx\local\field\datalynxfield_base
 */
final class field_search_operator_test extends advanced_testcase {
    /**
     * Create a text field (which uses the base get_search_sql) and return the field object.
     *
     * @return \mod_datalynx\local\field\datalynxfield_base
     */
    private function create_text_field() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $record = (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'TextField',
            'description' => '',
            'required' => 0,
            'visibleto' => 0,
            'editableby' => 0,
        ];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = '';
        }
        $DB->insert_record('datalynx_fields', $record);
        $dlx->get_fields(null, false, true);

        foreach ($dlx->get_fields() as $field) {
            if ($field->name() === 'TextField') {
                return $field;
            }
        }
        $this->fail('TextField not found');
    }

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A legitimate relational operator is emitted with the value bound as a placeholder.
     *
     * @covers ::get_search_sql
     * @dataProvider allowed_operator_provider
     * @param string $operator
     */
    public function test_allowed_operator_is_used(string $operator): void {
        $field = $this->create_text_field();

        [$sql, $params, $usecontent] = $field->get_search_sql(['', $operator, '42']);

        $this->assertNotSame('', $sql, "operator $operator should produce SQL");
        $this->assertStringContainsString(" $operator :", $sql);
        // The value is bound, never concatenated into the SQL text.
        $this->assertStringNotContainsString('42', $sql);
        $this->assertContains('42', array_values($params));
    }

    /**
     * Data provider of the operators that legitimately reach the base builder.
     *
     * @return array
     */
    public static function allowed_operator_provider(): array {
        return [['<'], ['<='], ['>'], ['>='], ['!='], ['<>']];
    }

    /**
     * A non-whitelisted / injection operator yields an empty (skipped) criterion, never SQL.
     *
     * @covers ::get_search_sql
     * @dataProvider injection_operator_provider
     * @param string $operator
     */
    public function test_injection_operator_is_rejected(string $operator): void {
        $field = $this->create_text_field();

        $result = $field->get_search_sql(['', $operator, 'x']);

        // Fail closed: empty fragment, no params, so the caller drops the criterion entirely.
        $this->assertSame(['', [], false], $result);
    }

    /**
     * Data provider of malicious / unsupported operator strings.
     *
     * @return array
     */
    public static function injection_operator_provider(): array {
        return [
            'or tautology' => ['= 1 OR 1=1'],
            'comment out' => ["= '' --"],
            'stacked query' => ['; DROP TABLE {datalynx_contents}; --'],
            'union' => ['UNION SELECT password FROM {user}'],
            'subquery' => ['= (SELECT 1)'],
            'unsupported keyword' => ['IS'],
            'empty-ish' => ['=='],
        ];
    }
}
