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
 * Tests for the ifeditable flag of ##viewlink## / ##viewsesslink## tags.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxfield_status\field as datalynxfield_status;
use datalynxview_grid\view as grid_view;
use mod_datalynx\local\field\datalynxfield_behavior;
use mod_datalynx\local\field_format\manager;
use mod_datalynx\local\view\datalynxview_patterns;
use stdClass;

/**
 * An edit link with the ifeditable flag is only shown while the target view has a field the
 * current user can edit in the entry. See mod_datalynx#285.
 *
 * @covers \mod_datalynx\local\view\datalynxview_patterns::get_regexp_replacements
 * @covers \mod_datalynx\local\view\base::entry_has_editable_fields
 * @covers \mod_datalynx\local\field\datalynxfield_renderer::is_tag_editable
 * @covers \datalynxfield_entryauthor\renderer::is_tag_editable
 */
final class viewlink_ifeditable_test extends advanced_testcase {
    /** @var stdClass Datalynx instance record. */
    private $instance;

    /** @var datalynx */
    private $dlx;

    /** @var stdClass The student who authors the entry. */
    private $student;

    /** @var int Id of the select field used in conditions. */
    private $selectid;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        datalynx::reset_static_caches();
        datalynxview_patterns::reset_static_caches();
        datalynxfield_behavior::reset_condition_cache();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->student = $generator->create_user();
        $generator->enrol_user($this->student->id, $course->id, 'student');
        $this->instance = $generator->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($this->instance->id);

        $this->create_field('text', 'title');
        $this->selectid = $this->create_field('select', 'phase', ['param1' => "open\nclosed"]);

        $permissions = [datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER, datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR];
        $this->create_behavior('b_locked', $permissions, false);
        $this->create_behavior('b_afterfinal', $permissions, true);
        $this->create_behavior('b_open', $permissions, true, [
            'match' => 'all',
            'rules' => [['sourcefieldid' => $this->selectid, 'not' => '', 'operator' => 'ANY_OF', 'value' => ['1']]],
        ]);
        $this->create_behavior('b_readonly', [], false);
    }

    /**
     * Without the flag the link is always rendered (current behaviour).
     */
    public function test_link_without_flag_is_always_shown(): void {
        $link = $this->render_link('[[title|b_locked]]', datalynxfield_status::STATUS_FINAL_SUBMISSION, false);
        $this->assertStringContainsString('editentries=', $link);
    }

    /**
     * Draft: the field is editable, so the link is shown. Final submission locks it, so the link disappears.
     */
    public function test_link_hidden_after_final_submission(): void {
        $draft = $this->render_link('[[title|b_locked]]', datalynxfield_status::STATUS_DRAFT);
        $this->assertStringContainsString('editentries=', $draft);
        $this->assertSame('', $this->render_link('[[title|b_locked]]', datalynxfield_status::STATUS_FINAL_SUBMISSION));
    }

    /**
     * A field whose behavior stays editable after final submission keeps the link.
     */
    public function test_link_kept_when_field_editable_after_final(): void {
        $template = '[[title|b_locked]][[title|b_afterfinal]]';
        $link = $this->render_link($template, datalynxfield_status::STATUS_FINAL_SUBMISSION);
        $this->assertStringContainsString('editentries=', $link);
    }

    /**
     * Conditions decide as in the edit form: editable only while the select has the first option.
     */
    public function test_link_follows_behavior_conditions(): void {
        $template = '[[title|b_open]]';
        $this->assertStringContainsString(
            'editentries=',
            $this->render_link($template, datalynxfield_status::STATUS_FINAL_SUBMISSION, true, '1')
        );
        $this->assertSame('', $this->render_link($template, datalynxfield_status::STATUS_FINAL_SUBMISSION, true, '2'));
    }

    /**
     * A field the user may only see, not edit, does not count.
     */
    public function test_read_only_field_does_not_count(): void {
        $this->assertSame('', $this->render_link('[[title|b_readonly]]', datalynxfield_status::STATUS_DRAFT));
    }

    /**
     * A select locked after its first save does not count once it has a value.
     */
    public function test_select_locked_after_save_does_not_count(): void {
        global $DB;
        $DB->set_field('datalynx_fields', 'param7', 1, ['id' => $this->selectid]);
        $this->dlx->get_fields(null, false, true);
        $this->assertSame('', $this->render_link('[[phase|b_afterfinal]]', datalynxfield_status::STATUS_DRAFT, true, '1'));
    }

    /**
     * Author profile fields edited inline count as editable, even after final submission.
     */
    public function test_author_profile_field_counts_as_editable(): void {
        $this->getDataGenerator()->create_custom_profile_field([
            'shortname' => 'street', 'name' => 'Street', 'datatype' => 'text',
        ]);
        manager::save_format((object) [
            'dataid' => $this->instance->id,
            'name' => 'Street',
            'fieldtype' => 'entryauthor',
            'settings' => json_encode(['option' => 'street', 'editable' => 1, 'mandatory' => 0]),
        ]);
        $this->dlx->get_fields(null, false, true);
        $link = $this->render_link('[[title|b_locked]]##author:Street##', datalynxfield_status::STATUS_FINAL_SUBMISSION);
        $this->assertStringContainsString('editentries=', $link);
    }

    /**
     * Render an edit link to a target view holding the given template, for the student's entry.
     *
     * @param string $template Entry template of the target view.
     * @param int $status Entry status.
     * @param bool $ifeditable Whether the link carries the ifeditable flag.
     * @param string|null $phase Stored value of the select field, if any.
     * @return string
     */
    private function render_link(string $template, int $status, bool $ifeditable = true, ?string $phase = null): string {
        global $DB;
        $target = $this->create_view('target', $template);
        $flag = $ifeditable ? ';ifeditable' : '';
        $tag = "##viewsesslink:target;Edit;editentries=##entryid##;btn{$flag}##";
        $list = $this->create_view('list', $tag);

        $entry = (object) [
            'dataid' => $this->dlx->id(), 'userid' => $this->student->id, 'groupid' => 0, 'approved' => 1,
            'status' => $status, 'timecreated' => time(), 'timemodified' => time(),
        ];
        $entry->id = $DB->insert_record('datalynx_entries', $entry);
        if ($phase !== null) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $this->selectid, 'entryid' => $entry->id, 'lineid' => 0, 'content' => $phase,
            ]);
            $entry->{"c{$this->selectid}_content"} = $phase;
        }

        $this->setUser($this->student);
        datalynx::reset_static_caches();
        datalynxview_patterns::reset_static_caches();
        datalynxfield_behavior::reset_condition_cache();
        $dlx = new datalynx($this->instance->id);
        $listview = new grid_view($dlx, $list, false);
        $replacements = $listview->patternclass()->get_replacements([$tag], $entry, []);
        $this->setAdminUser();

        // Each call builds its own views, so remove them again for the next call.
        $DB->delete_records('datalynx_views', ['id' => $target->id]);
        $DB->delete_records('datalynx_views', ['id' => $list->id]);
        return (string) ($replacements[$tag] ?? '');
    }

    /**
     * Insert a grid view with the given entry template.
     *
     * @param string $name
     * @param string $template
     * @return stdClass
     */
    private function create_view(string $name, string $template): stdClass {
        global $DB;
        $view = (object) [
            'dataid' => $this->instance->id, 'type' => 'grid', 'name' => $name, 'description' => '',
            'visible' => 7, 'filter' => 0, 'perpage' => 0, 'groupby' => '', 'param5' => 0, 'param10' => 0,
            'section' => '##entries##', 'param2' => $template,
        ];
        $view->id = $DB->insert_record('datalynx_views', $view);
        return $view;
    }

    /**
     * Create a field and return its id.
     *
     * @param string $type
     * @param string $name
     * @param array $params
     * @return int
     */
    private function create_field(string $type, string $name, array $params = []): int {
        global $DB;
        $record = (object) ['dataid' => $this->instance->id, 'type' => $type, 'name' => $name, 'description' => ''];
        for ($i = 1; $i <= 10; $i++) {
            $record->{"param$i"} = $params["param$i"] ?? '';
        }
        $id = (int) $DB->insert_record('datalynx_fields', $record);
        $this->dlx->get_fields(null, false, true);
        return $id;
    }

    /**
     * Create a behavior.
     *
     * @param string $name
     * @param int[] $editableby Permissions that may edit.
     * @param bool $afterfinal Editable after final submission.
     * @param array|null $conditions Availability conditions.
     */
    private function create_behavior(string $name, array $editableby, bool $afterfinal, ?array $conditions = null): void {
        global $DB;
        $DB->insert_record('datalynx_behaviors', (object) [
            'dataid' => $this->instance->id,
            'name' => $name,
            'description' => '',
            'visibleto' => serialize(['permissions' => [datalynx::PERMISSION_MANAGER, datalynx::PERMISSION_TEACHER,
                datalynx::PERMISSION_STUDENT, datalynx::PERMISSION_AUTHOR], 'users' => [], 'teammember' => []]),
            'editableby' => serialize($editableby),
            'required' => 0,
            'conditions' => $conditions ? json_encode($conditions) : null,
            'editableafterfinal' => $afterfinal ? 1 : 0,
        ]);
    }
}
