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
 * Tests for internal email views and notification template rendering.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use advanced_testcase;
use datalynxrule_eventnotification\rule as eventnotification_rule;
use moodle_url;
use ReflectionMethod;
use stdClass;

/**
 * Tests for the internal email view integration.
 */
final class email_view_test extends advanced_testcase {
    /**
     * Set up the test fixture.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create a datalynx instance for testing.
     *
     * @return datalynx
     */
    private function create_test_datalynx(): datalynx {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);

        return new datalynx($instance->id);
    }

    /**
     * Insert a view record for the supplied datalynx instance.
     *
     * @param datalynx $dlx
     * @param string $type
     * @param string $name
     * @param string $section
     * @param string $param2
     * @param int $visible
     * @return stdClass
     */
    private function create_view_record(
        datalynx $dlx,
        string $type,
        string $name,
        string $section = '',
        string $param2 = '',
        int $visible = 7
    ): stdClass {
        global $DB;

        $view = (object) [
            'dataid' => $dlx->id(),
            'type' => $type,
            'name' => $name,
            'description' => '',
            'visible' => $visible,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => $section,
            'param2' => $param2,
        ];
        $view->id = $DB->insert_record('datalynx_views', $view);

        return $view;
    }

    /**
     * Create a minimal entry that can be rendered through the email view.
     *
     * @param datalynx $dlx
     * @return int
     */
    private function create_entry(datalynx $dlx): int {
        global $DB, $USER;

        return (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $USER->id,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Build a minimal eventnotification rule record for testing helpers.
     *
     * @param datalynx $dlx
     * @param int $templateviewid
     * @param array $recipients
     * @return eventnotification_rule
     */
    private function create_notification_rule(
        datalynx $dlx,
        int $templateviewid = 0,
        array $recipients = []
    ): eventnotification_rule {
        $rule = (object) [
            'id' => 1,
            'dataid' => $dlx->id(),
            'type' => 'eventnotification',
            'name' => 'Notification',
            'description' => '',
            'enabled' => 1,
            'param1' => serialize(['entry_created']),
            'param2' => eventnotification_rule::FROM_CURRENT_USER,
            'param3' => serialize($recipients),
            'param4' => serialize([]),
            'param5' => 0,
            'param6' => '',
            'param7' => json_encode([]),
            'param8' => $templateviewid,
            'param9' => null,
            'param10' => null,
        ];

        return new eventnotification_rule($dlx, $rule);
    }

    /**
     * Create a Datalynx fixture with one teacher and one student.
     *
     * @return array{0: datalynx, 1: stdClass, 2: stdClass}
     */
    private function create_recipient_fixture(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $instance = $generator->create_module('datalynx', ['course' => $course->id]);
        $context = \context_module::instance($instance->cmid);
        $teacherroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        $studentroleid = (int) $DB->get_field('role', 'id', ['shortname' => 'student']);

        $coursecontext = \context_course::instance($course->id);
        role_assign($teacherroleid, $teacher->id, $coursecontext->id);
        role_assign($studentroleid, $student->id, $coursecontext->id);
        assign_capability('mod/datalynx:viewprivilegeteacher', CAP_ALLOW, $teacherroleid, $context->id, true);
        assign_capability('mod/datalynx:viewprivilegestudent', CAP_PREVENT, $teacherroleid, $context->id, true);
        assign_capability('mod/datalynx:viewprivilegestudent', CAP_ALLOW, $studentroleid, $context->id, true);
        assign_capability('mod/datalynx:viewprivilegeteacher', CAP_PREVENT, $studentroleid, $context->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        return [new datalynx($instance->id), $teacher, $student];
    }

    /**
     * Internal email views must stay out of the browse view lists.
     *
     * @covers \mod_datalynx\datalynx::get_view_records
     * @covers \mod_datalynx\datalynx::get_views_editable_by_user
     * @covers \mod_datalynx\datalynx::get_current_view_from_id
     */
    public function test_email_views_are_excluded_from_browse_records(): void {
        $dlx = $this->create_test_datalynx();
        $tabularview = $this->create_view_record($dlx, 'tabular', 'Browse view');
        $emailview = $this->create_view_record($dlx, 'email', 'Email view', '##entries##', '<p>##entryid##</p>', 1);

        $browsable = $dlx->get_view_records(true);
        $editable = $dlx->get_views_editable_by_user('');

        $this->assertArrayHasKey($tabularview->id, $browsable);
        $this->assertArrayNotHasKey($emailview->id, $browsable);
        $this->assertArrayHasKey($tabularview->id, $editable);
        $this->assertArrayHasKey($emailview->id, $editable);
        $this->assertFalse($dlx->get_current_view_from_id($emailview->id));
    }

    /**
     * Email view placeholders should resolve with the notification link data passed by the rule.
     *
     * @covers \datalynxview_email\view_patterns::get_replacements
     */
    public function test_email_view_notification_placeholders_resolve(): void {
        $dlx = $this->create_test_datalynx();
        $viewrecord = $this->create_view_record(
            $dlx,
            'email',
            'Email view',
            '##entries##',
            '<p>##notificationentrylink##</p><p>##notificationdatalynxurl##</p>',
            1
        );

        $view = $dlx->get_view($viewrecord->type, $viewrecord);
        $patternclass = $view->patternclass();
        $tagentry = '##notificationentrylink##';
        $tagdatalynx = '##notificationdatalynxurl##';
        $replacements = $patternclass->get_replacements([$tagentry, $tagdatalynx], null, [
            'notificationentrylink' => '<a href="https://example.invalid/entry">Entry</a>',
            'notificationdatalynxurl' => 'https://example.invalid/datalynx',
        ]);

        $this->assertSame('<a href="https://example.invalid/entry">Entry</a>', $replacements[$tagentry]);
        $this->assertSame('https://example.invalid/datalynx', $replacements[$tagdatalynx]);
    }

    /**
     * Missing email templates should fall back to the legacy event notification body.
     *
     * @covers \datalynxrule_eventnotification\rule::build_message_body
     */
    public function test_eventnotification_falls_back_when_email_template_is_missing(): void {
        $dlx = $this->create_test_datalynx();
        $rule = $this->create_notification_rule($dlx, 99999);

        $method = new ReflectionMethod($rule, 'build_message_body');
        $method->setAccessible(true);
        $messagedata = (object) [
            'fullname' => 'Template Tester',
            'senderprofilelink' => 'Teacher',
            'viewlink' => 'Entry link',
            'datalynxlink' => 'Datalynx link',
            'messagecontent' => '',
        ];

        [$plain, $html] = $method->invoke(
            $rule,
            'entry_created',
            123,
            new moodle_url('/mod/datalynx/view.php', ['d' => $dlx->id(), 'eids' => 123]),
            new moodle_url('/mod/datalynx/view.php', ['d' => $dlx->id()]),
            $messagedata,
            get_admin()
        );

        $this->assertStringContainsString('Template Tester', $plain);
        $this->assertStringContainsString('Entry link', $html);
        $this->assertStringContainsString('Datalynx link', $html);
    }

    /**
     * Selected email templates should render the configured entry body with notification links.
     *
     * @covers \datalynxrule_eventnotification\rule::render_email_template
     */
    public function test_eventnotification_renders_selected_email_template(): void {
        $dlx = $this->create_test_datalynx();
        $emailview = $this->create_view_record(
            $dlx,
            'email',
            'Email view',
            '##entries##',
            '<p>##notificationentrylink##</p><p>##entryid##</p>',
            1
        );
        $entryid = $this->create_entry($dlx);
        $rule = $this->create_notification_rule($dlx, (int) $emailview->id);

        $method = new ReflectionMethod($rule, 'render_email_template');
        $method->setAccessible(true);
        $html = $method->invoke(
            $rule,
            $entryid,
            new moodle_url('/mod/datalynx/view.php', ['d' => $dlx->id(), 'eids' => $entryid]),
            new moodle_url('/mod/datalynx/view.php', ['d' => $dlx->id()]),
            get_admin()
        );

        $this->assertIsString($html);
        $this->assertStringContainsString((string) $entryid, $html);
        $this->assertStringContainsString('href=', $html);
        $this->assertStringContainsString('Link to entry', $html);
    }

    /**
     * Selected email templates should only render the triggering entry.
     *
     * @covers \datalynxrule_eventnotification\rule::render_email_template
     */
    public function test_eventnotification_renders_only_the_requested_entry(): void {
        $dlx = $this->create_test_datalynx();
        $emailview = $this->create_view_record(
            $dlx,
            'email',
            'Email view',
            '##entries##',
            '<p>##entryid##</p>',
            1
        );
        $entryid = $this->create_entry($dlx);
        $otherentryid = $this->create_entry($dlx);
        $rule = $this->create_notification_rule($dlx, (int) $emailview->id);

        $method = new ReflectionMethod($rule, 'render_email_template');
        $method->setAccessible(true);
        $html = $method->invoke(
            $rule,
            $entryid,
            new moodle_url('/mod/datalynx/view.php', ['d' => $dlx->id(), 'eids' => $entryid]),
            new moodle_url('/mod/datalynx/view.php', ['d' => $dlx->id()]),
            get_admin()
        );

        $this->assertIsString($html);
        $this->assertStringContainsString('<p>' . $entryid . '</p>', $html);
        $this->assertStringNotContainsString('<p>' . $otherentryid . '</p>', $html);
    }

    /**
     * Student recipient selection must not include teacher-only users.
     *
     * @covers \datalynxrule_eventnotification\rule::get_recipients
     */
    public function test_eventnotification_student_recipients_exclude_teacher_only_users(): void {
        [$dlx, $teacher, $student] = $this->create_recipient_fixture();
        $rule = $this->create_notification_rule($dlx, 0, [
            'roles' => [datalynx::PERMISSION_STUDENT],
        ]);

        $method = new ReflectionMethod($rule, 'get_recipients');
        $method->setAccessible(true);
        $recipientids = $method->invoke($rule);
        sort($recipientids);

        $this->assertSame([(int) $student->id], $recipientids);
        $this->assertNotContains($teacher->id, $recipientids);
    }

    /**
     * Teacher recipient selection must not include student-only users.
     *
     * @covers \datalynxrule_eventnotification\rule::get_recipients
     */
    public function test_eventnotification_teacher_recipients_exclude_student_only_users(): void {
        [$dlx, $teacher, $student] = $this->create_recipient_fixture();
        $rule = $this->create_notification_rule($dlx, 0, [
            'roles' => [datalynx::PERMISSION_TEACHER],
        ]);

        $method = new ReflectionMethod($rule, 'get_recipients');
        $method->setAccessible(true);
        $recipientids = $method->invoke($rule);
        sort($recipientids);

        $this->assertSame([(int) $teacher->id], $recipientids);
        $this->assertNotContains($student->id, $recipientids);
    }
}
