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
 * Tests for ##viewlink## and ##viewsesslink## tag parsing in mod_datalynx.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\view\datalynxview_patterns
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxview_tabular;
use mod_datalynx\datalynx;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the ##viewlink:...## and ##viewsesslink:...## tag patterns.
 */
final class viewlink_pattern_test extends advanced_testcase {

    /**
     * Set up the test.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Test that the DB migration regex correctly converts old #{{viewlink:...}}# tags
     * to the new ##viewlink:...## format.
     *
     * This mirrors the regex used in db/upgrade.php for the 2026041000 upgrade step.
     */
    public function test_migration_regex_converts_viewlink_tags(): void {
        $cases = [
            '#{{viewlink:myview;Click here;;}}#'                    => '##viewlink:myview;Click here;;##',
            '#{{viewlink:My View;Read More;param=1;btn-primary}}#'  => '##viewlink:My View;Read More;param=1;btn-primary##',
            '#{{viewsesslink:myview;Add entry;new=1;btn}}#'         => '##viewsesslink:myview;Add entry;new=1;btn##',
            // Strings without old tags must remain unchanged.
            '##other:tag##'                                         => '##other:tag##',
            'plain text'                                            => 'plain text',
        ];

        foreach ($cases as $input => $expected) {
            $result = preg_replace('/#\{\{(viewlink:[^}]+)\}\}#/', '##$1##', $input);
            $result = preg_replace('/#\{\{(viewsesslink:[^}]+)\}\}#/', '##$1##', $result);
            $this->assertEquals($expected, $result, "Migration regex failed for input: $input");
        }
    }

    /**
     * Create a minimal datalynx instance with two tabular views and return both.
     *
     * @return array{0: datalynx, 1: datalynxview_tabular, 2: datalynxview_tabular}
     *   [df, targetView, templateView]
     */
    private function create_test_views(string $taginsection): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/datalynx/view/tabular/view_class.php');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $datalynxrecord = $generator->create_module('datalynx', ['course' => $course->id]);
        $df = new datalynx($datalynxrecord->id);

        // Insert a visible target view so it appears in get_views_menu().
        $targetview = new stdClass();
        $targetview->dataid  = $datalynxrecord->id;
        $targetview->type    = 'tabular';
        $targetview->name    = 'myview';
        $targetview->description = '';
        $targetview->visible = 7;
        $targetview->filter  = 0;
        $targetview->perpage = 0;
        $targetview->groupby = '';
        $targetview->param5  = 0;
        $targetview->param10 = 0;
        $targetview->section = '';
        $targetview->id = $DB->insert_record('datalynx_views', $targetview);

        // Insert a template view whose section contains the tag under test.
        $templateview = clone $targetview;
        $templateview->name    = 'templateview';
        $templateview->section = $taginsection;
        unset($templateview->id);
        $templateview->id = $DB->insert_record('datalynx_views', $templateview);

        // Instantiate view objects (filteroptions=false avoids URL param processing).
        $targetobj   = new datalynxview_tabular($df, $targetview, false);
        $templateobj = new datalynxview_tabular($df, $templateview, false);

        return [$df, $targetobj, $templateobj];
    }

    /**
     * Test that ##viewlink:...## is recognized as a regexp pattern.
     */
    public function test_is_regexp_pattern_recognises_new_viewlink_format(): void {
        [, , $templateobj] = $this->create_test_views('##viewlink:myview;Click here;;btn##');

        $patternclass = $templateobj->patternclass();

        $this->assertTrue(
            $patternclass->is_regexp_pattern('##viewlink:myview;Click here;;btn##'),
            '##viewlink:...## should be recognised as a regexp pattern'
        );
    }

    /**
     * Test that ##viewsesslink:...## is recognized as a regexp pattern.
     */
    public function test_is_regexp_pattern_recognises_new_viewsesslink_format(): void {
        [, , $templateobj] = $this->create_test_views('##viewsesslink:myview;Add entry;new=1;btn##');

        $patternclass = $templateobj->patternclass();

        $this->assertTrue(
            $patternclass->is_regexp_pattern('##viewsesslink:myview;Add entry;new=1;btn##'),
            '##viewsesslink:...## should be recognised as a regexp pattern'
        );
    }

    /**
     * Test that the old #{{viewlink:...}}# format is no longer recognised after migration.
     */
    public function test_is_regexp_pattern_rejects_old_viewlink_format(): void {
        [, , $templateobj] = $this->create_test_views('##viewlink:myview;Click here;;##');

        $patternclass = $templateobj->patternclass();

        $this->assertFalse(
            $patternclass->is_regexp_pattern('#{{viewlink:myview;Click here;;}}#'),
            'Old #{{viewlink:...}}# format should no longer be recognised as a regexp pattern'
        );
    }

    /**
     * Test that search() finds ##viewlink:...## tags in template text.
     */
    public function test_search_finds_new_viewlink_tag(): void {
        $tag = '##viewlink:myview;Click here;;btn##';
        [, , $templateobj] = $this->create_test_views($tag);

        $patternclass = $templateobj->patternclass();
        $found = $patternclass->search("Some content $tag more content", false);

        $this->assertContains($tag, $found, 'search() should find ##viewlink:...## tag in text');
    }

    /**
     * Test that search() finds ##viewsesslink:...## tags in template text.
     */
    public function test_search_finds_new_viewsesslink_tag(): void {
        $tag = '##viewsesslink:myview;Add entry;new=1;btn##';
        [, , $templateobj] = $this->create_test_views($tag);

        $patternclass = $templateobj->patternclass();
        $found = $patternclass->search("Header $tag footer", false);

        $this->assertContains($tag, $found, 'search() should find ##viewsesslink:...## tag in text');
    }
}
