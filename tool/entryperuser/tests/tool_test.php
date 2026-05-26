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
 * Tests for the entryperuser tool.
 *
 * @package    datalynxtool_entryperuser
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxtool_entryperuser;

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use stdClass;

/**
 * PHPUnit test class for the entryperuser tool.
 *
 * @coversDefaultClass \datalynxtool_entryperuser\tool
 */
final class tool_test extends advanced_testcase {
    /**
     * Set up the fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a test datalynx instance.
     */
    private function create_test_datalynx(): datalynx {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);
        return new datalynx($instance->id);
    }

    /**
     * Test filtering views containing the ##edit## tag.
     *
     * @covers ::run
     */
    public function test_filter_views_with_edit_tag(): void {
        global $DB;

        $dlx = $this->create_test_datalynx();

        // 1. Create a view with ##edit## tag.
        $view1 = (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'View with edit tag',
            'description' => '',
            'param2' => '##edit##',
            'eparam2' => '',
            'visible' => 7,
            'param5' => 0,
            'param10' => 0,
        ];
        $view1->id = $DB->insert_record('datalynx_views', $view1);

        // 2. Create another view with ##edit## tag in eparam2.
        $view2 = (object) [
            'dataid' => $dlx->id(),
            'type' => 'grid',
            'name' => 'View with edit tag in eparam',
            'description' => '',
            'param2' => '##edit##',
            'visible' => 7,
            'param5' => 0,
            'param10' => 0,
        ];
        $view2->id = $DB->insert_record('datalynx_views', $view2);

        // 3. Create a view without ##edit## tag.
        $view3 = (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'View without edit tag',
            'description' => '',
            'param2' => 'Some other content',
            'eparam2' => '',
            'visible' => 7,
            'param5' => 0,
            'param10' => 0,
        ];
        $view3->id = $DB->insert_record('datalynx_views', $view3);

        // Verify view properties search logic.
        $views = $dlx->get_views([], true);
        $filteredviews = [];
        foreach ($views as $viewid => $v) {
            $hasedit = false;
            foreach ((array) $v->view as $val) {
                if (is_string($val) && strpos($val, '##edit##') !== false) {
                    $hasedit = true;
                    break;
                }
            }
            if ($hasedit) {
                $filteredviews[$viewid] = $v->view->name;
            }
        }

        $this->assertArrayHasKey($view1->id, $filteredviews);
        $this->assertArrayHasKey($view2->id, $filteredviews);
        $this->assertArrayNotHasKey($view3->id, $filteredviews);
    }

    /**
     * Test entry generation and ownership assignment.
     *
     * @covers ::run
     */
    public function test_generate_entries_per_user_ownership(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $dlxinstance = $generator->create_module('datalynx', ['course' => $course->id]);
        $dlx = new datalynx($dlxinstance->id);

        // Create course users.
        $user1 = $generator->create_and_enrol($course, 'student');
        $user2 = $generator->create_and_enrol($course, 'student');

        // Verify users are returned as gradebook users.
        $users = $dlx->get_gradebook_users();
        $this->assertDebuggingCalled();
        $this->assertCount(2, $users);
        $this->assertArrayHasKey($user1->id, $users);
        $this->assertArrayHasKey($user2->id, $users);

        // Add a text field to the datalynx instance.
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'testfield',
            'description' => '',
        ];
        $fieldid = $DB->insert_record('datalynx_fields', $fieldrecord);

        // Simulate submitting Step 2 of the form with default values.
        $formdata = new stdClass();
        $formdata->{"field_{$fieldid}_-1"} = 'MyDefaultText';
        $formdata->viewid = 1;
        $formdata->step = 2;

        // Construct entries data payload (simulating tool.php logic).
        $entriesdata = (object) ['eids' => []];
        $entryid = -1;

        foreach (array_keys($users) as $userid) {
            $entriesdata->eids[$entryid] = $entryid;
            // Set the owner/userid of the entry to the target user.
            $entriesdata->{"field_userid_{$entryid}"} = $userid;

            // Copy submitted default values.
            foreach ($formdata as $key => $value) {
                if (strpos($key, 'field_') === 0 && strpos($key, '_-1') !== false) {
                    $newkey = str_replace('_-1', "_{$entryid}", $key);
                    $entriesdata->$newkey = $value;
                }
            }
            $entryid--;
        }

        // Process and save the entries.
        $em = new datalynx_entries($dlx);
        $processed = $em->process_entries('update', $entriesdata->eids, $entriesdata, true);

        $this->assertIsArray($processed);
        [$strnotify, $processedeids] = $processed;
        $this->assertCount(2, $processedeids);

        // Verify created entries in database.
        $entryrecords = $DB->get_records('datalynx_entries', ['dataid' => $dlx->id()]);
        $this->assertCount(2, $entryrecords);

        // Ensure owners (userid) are correctly set to user1 and user2.
        $userids = array_map(function ($e) {
            return $e->userid;
        }, $entryrecords);
        $this->assertContains($user1->id, $userids);
        $this->assertContains($user2->id, $userids);

        // Ensure the default text field content is correctly populated.
        $contents = $DB->get_records('datalynx_contents', ['fieldid' => $fieldid]);
        $this->assertCount(2, $contents);
        foreach ($contents as $content) {
            $this->assertEquals('MyDefaultText', $content->content);
        }
    }

    /**
     * Test required fields detection in step 2 form definition.
     *
     * @covers \datalynxtool_entryperuser\form\entryperuser_form
     */
    public function test_form_definition_step_2_detects_required_fields(): void {
        global $DB;

        $dlx = $this->create_test_datalynx();

        // Create a view.
        $view = (object) [
            'dataid' => $dlx->id(),
            'type' => 'tabular',
            'name' => 'View with required fields',
            'description' => '',
            'param2' => '[[testfield|req]]', // Field name with required behavior.
            'eparam2' => '',
            'visible' => 7,
            'param5' => 0,
            'param10' => 0,
        ];
        $view->id = $DB->insert_record('datalynx_views', $view);

        // Add a text field.
        $fieldrecord = (object) [
            'dataid' => $dlx->id(),
            'type' => 'text',
            'name' => 'testfield',
            'description' => '',
        ];
        $fieldid = $DB->insert_record('datalynx_fields', $fieldrecord);

        // Add a required behavior.
        $behaviorrecord = (object) [
            'dataid' => $dlx->id(),
            'name' => 'req',
            'description' => '',
            'visibleto' => serialize([]),
            'editableby' => serialize([]),
            'required' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('datalynx_behaviors', $behaviorrecord);

        // Instantiate the form. This will run the definition and definition_step_2.
        // It shouldn't crash now.
        $form = new \datalynxtool_entryperuser\form\entryperuser_form(null, [
            'dlx' => $dlx,
            'step' => 2,
            'selectedviewid' => $view->id,
        ], 'post', '', null, true);

        $this->assertInstanceOf(\datalynxtool_entryperuser\form\entryperuser_form::class, $form);
    }
}
