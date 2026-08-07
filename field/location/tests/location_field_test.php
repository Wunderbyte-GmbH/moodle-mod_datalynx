<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
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

namespace datalynxfield_location;

use advanced_testcase;
use stdClass;
use mod_datalynx\datalynx;

/**
 * Unit tests for location field.
 *
 * @package    datalynxfield_location
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_location\field
 */
final class location_field_test extends advanced_testcase {

    /**
     * Set up test environment.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Helper to create a datalynx instance and location field fixture.
     *
     * @return array [datalynx, field object, field record]
     */
    private function create_location_field_fixture(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($instance->id);

        $field = new stdClass();
        $field->dataid = $dlx->id();
        $field->name = 'LocationField';
        $field->type = 'location';
        $field->description = 'Test location field';
        $field->param1 = 'osm';
        $field->param2 = 'https://nominatim.openstreetmap.org';
        $field->param3 = '';
        $field->param4 = '13';
        $field->param5 = '5';
        $field->param6 = 'map_mini';
        $field->param7 = 'de,at,ch';
        $field->param8 = '';
        $field->param9 = '';
        $field->param10 = '';

        $field->id = $DB->insert_record('datalynx_fields', $field);

        $fields = $dlx->get_fields(null, false, true);
        $fieldobj = $fields[$field->id];

        return [$dlx, $fieldobj, $field];
    }

    /**
     * Test location field content formatting and update logic.
     */
    public function test_format_content(): void {
        [$dlx, $fieldobj, $field] = $this->create_location_field_fixture();

        $entry = new stdClass();
        $entry->id = 1;

        $values = [
            'address' => 'Alexanderplatz 1, 10178 Berlin',
            'lat'     => '52.521918',
            'lng'     => '13.413215',
        ];

        // Access protected format_content method.
        $ref = new \ReflectionMethod($fieldobj, 'format_content');
        $ref->setAccessible(true);
        [$contents, $oldcontents] = $ref->invoke($fieldobj, $entry, $values);

        $this->assertCount(3, $contents);
        $this->assertEquals('Alexanderplatz 1, 10178 Berlin', $contents[0]);
        $this->assertEquals('52.521918', $contents[1]);
        $this->assertEquals('13.413215', $contents[2]);
    }

    /**
     * Test search parameter parsing.
     */
    public function test_parse_search(): void {
        [$dlx, $fieldobj, $field] = $this->create_location_field_fixture();

        $fieldid = $field->id;
        $i = 0;

        $formdata = new stdClass();
        $formdata->{"f_{$i}_{$fieldid}_address"} = 'Berlin';
        $formdata->{"f_{$i}_{$fieldid}_lat"} = '52.52';
        $formdata->{"f_{$i}_{$fieldid}_lng"} = '13.40';
        $formdata->{"f_{$i}_{$fieldid}_radius"} = '10';

        $parsed = $fieldobj->parse_search($formdata, $i);

        $this->assertIsArray($parsed);
        $this->assertEquals('Berlin', $parsed['address']);
        $this->assertEquals(52.52, $parsed['lat']);
        $this->assertEquals(13.40, $parsed['lng']);
        $this->assertEquals(10.0, $parsed['radius']);
    }

    /**
     * Test Haversine SQL query generation for distance search.
     */
    public function test_get_search_sql_haversine(): void {
        [$dlx, $fieldobj, $field] = $this->create_location_field_fixture();

        $searchdata = [
            'address' => 'Stephansplatz, Wien',
            'lat'     => 48.20849,
            'lng'     => 16.37313,
            'radius'  => 5.0,
        ];

        $search = [false, 'LIKE', $searchdata];
        [$sql, $params, $usecontent] = $fieldobj->get_search_sql($search);

        $this->assertTrue($usecontent);
        $this->assertStringContainsString('6371 * ACOS', $sql);
        $this->assertStringContainsString('RADIANS', $sql);
        $this->assertCount(3, $params);
    }

    /**
     * Test actual DB persistence with update_content().
     */
    public function test_update_content_persistence(): void {
        global $DB;
        [$dlx, $fieldobj, $field] = $this->create_location_field_fixture();

        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => 2,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $entry = $DB->get_record('datalynx_entries', ['id' => $entryid]);

        $values = [
            'address' => 'Marienplatz 1, Muenchen',
            'lat'     => '48.137154',
            'lng'     => '11.576124',
        ];

        $contentid = $fieldobj->update_content($entry, $values);
        $this->assertIsInt($contentid);

        $saved = $DB->get_record('datalynx_contents', ['id' => $contentid]);
        $this->assertEquals('Marienplatz 1, Muenchen', $saved->content);
        $this->assertEquals('48.137154', $saved->content1);
        $this->assertEquals('11.576124', $saved->content2);
    }
}
