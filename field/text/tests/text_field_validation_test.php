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

namespace datalynxfield_text;

use advanced_testcase;
use stdClass;
use mod_datalynx\datalynx;

/**
 * Tests for datalynx text field validation.
 *
 * @package    datalynxfield_text
 * @category   test
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \datalynxfield_text\renderer::validate
 */
final class text_field_validation_test extends advanced_testcase {
    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create datalynx instance and a text field.
     *
     * @param string $format The validation format to configure (param4).
     * @return array [datalynx, field object, field database record]
     */
    private function create_text_field_fixture(string $format): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $field = new stdClass();
        $field->dataid = $dlx->id();
        $field->name = 'TextField';
        $field->type = 'text';
        $field->description = '';
        $field->param1 = '';
        $field->param2 = '';
        $field->param3 = '';
        $field->param4 = $format;
        $field->param5 = '';
        $field->param6 = '';
        $field->param7 = '';
        $field->param8 = '';
        $field->param9 = '';
        $field->param10 = '';

        $field->id = $DB->insert_record('datalynx_fields', $field);

        // Fetch field object via datalynx to ensure it instantiates the correct subclass.
        $fields = $dlx->get_fields(null, false, true);
        $fieldobj = $fields[$field->id];

        return [$dlx, $fieldobj, $field];
    }

    /**
     * Test email format validation.
     */
    public function test_email_validation(): void {
        [$dlx, $fieldobj, $field] = $this->create_text_field_fixture('email');
        $renderer = $fieldobj->renderer();
        $tags = ["[[{$field->name}]]"];

        // Valid emails.
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'test@example.com']));
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'user.name+tag@sub.domain.co.uk']));

        // Invalid emails.
        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'invalid-email']);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey("field_{$field->id}_1", $errors);

        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'test@example']);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test IBAN format validation.
     */
    public function test_iban_validation(): void {
        [$dlx, $fieldobj, $field] = $this->create_text_field_fixture('iban');
        $renderer = $fieldobj->renderer();
        $tags = ["[[{$field->name}]]"];

        // Valid IBANs.
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'DE89 3704 0044 0532 0130 00']));
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'DE75512108001245126199']));

        // Invalid IBANs (wrong check digits or format).
        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'DE89 3704 0044 0532 0130 01']);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey("field_{$field->id}_1", $errors);

        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'INVALIDIBAN']);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test BIC/SWIFT format validation.
     */
    public function test_bicswift_validation(): void {
        [$dlx, $fieldobj, $field] = $this->create_text_field_fixture('bicswift');
        $renderer = $fieldobj->renderer();
        $tags = ["[[{$field->name}]]"];

        // Valid BIC/SWIFTs.
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'AAAAESMMXXX']));
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'AAAAESMM']));

        // Invalid BIC/SWIFTs.
        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'AAAAESM']);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey("field_{$field->id}_1", $errors);

        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => 'AAAAESMMXX123']);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test IPv4 format validation.
     */
    public function test_ipv4_validation(): void {
        [$dlx, $fieldobj, $field] = $this->create_text_field_fixture('ipv4');
        $renderer = $fieldobj->renderer();
        $tags = ["[[{$field->name}]]"];

        // Valid IPv4s.
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '127.0.0.1']));
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '255.255.255.255']));

        // Invalid IPv4s.
        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '256.0.0.1']);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey("field_{$field->id}_1", $errors);

        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '1.2.3.4.5']);
        $this->assertNotEmpty($errors);
    }

    /**
     * Test Phone format validation.
     */
    public function test_phone_validation(): void {
        [$dlx, $fieldobj, $field] = $this->create_text_field_fixture('phone');
        $renderer = $fieldobj->renderer();
        $tags = ["[[{$field->name}]]"];

        // Valid Phones.
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '+43 650 234234']));
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '+1-800-555-0199']));
        $this->assertEmpty($renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '+49(0)1234567']));

        // Invalid Phones.
        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '0650 234234']);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey("field_{$field->id}_1", $errors);

        $errors = $renderer->validate(1, $tags, (object) ["field_{$field->id}_1" => '+43']);
        $this->assertNotEmpty($errors);
    }
}
