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
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\local\datalynx_entries;

/**
 * Entry actions are usable from a web service: no output, no exit, no request parameters.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\datalynx_entries::process_entries
 * @covers     \mod_datalynx\local\view\base::confirm_view_action
 * @covers     \mod_datalynx\local\view\base::get_entries_manager
 */
final class entry_actions_ws_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Without confirmation a non-interactive delete prints nothing, exits nowhere and deletes nothing.
     */
    public function test_unconfirmed_noninteractive_action_returns_notification(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'text', 'Title');
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => 'Keep me']);

        $entries = new datalynx_entries($dlx);
        $entries->interactive = false;

        $this->expectOutputString('');
        $result = $entries->process_entries('delete', (string) $entryid, null, false);

        $this->assertSame([get_string('confirmationrequired', 'datalynx'), ''], $result);
        $this->assertTrue($DB->record_exists('datalynx_entries', ['id' => $entryid]));
    }

    /**
     * A confirmed non-interactive delete removes the entry and its content, and prints nothing.
     */
    public function test_confirmed_noninteractive_delete_deletes(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'text', 'Title');
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => 'Delete me']);
        $otherid = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => 'Keep me']);

        $entries = new datalynx_entries($dlx);
        $entries->interactive = false;

        $this->expectOutputString('');
        [, $processed] = $entries->process_entries('delete', (string) $entryid, null, true);

        $this->assertSame([$entryid], array_map('intval', $processed));
        $this->assertFalse($DB->record_exists('datalynx_entries', ['id' => $entryid]));
        $this->assertFalse($DB->record_exists('datalynx_contents', ['entryid' => $entryid]));
        $this->assertTrue($DB->record_exists('datalynx_entries', ['id' => $otherid]));
    }

    /**
     * Entry ids are never written into the query: an injected id list affects no entry.
     */
    public function test_injected_eids_affect_no_entries(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'text', 'Title');
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => 'Keep me']);

        $entries = new datalynx_entries($dlx);
        $entries->interactive = false;
        foreach (["$entryid) OR (1=1", "{$entryid}abc", '0) OR (1=1', '-1', "-$entryid", 'abc', ''] as $eids) {
            [$notification, $processed] = $entries->process_entries('delete', $eids, null, true);
            $this->assertEmpty($processed, "eids '$eids' processed entries");
            $this->assertStringContainsString(get_string('entrynoneforaction', 'datalynx'), $notification);
        }

        $this->assertTrue($DB->record_exists('datalynx_entries', ['id' => $entryid]));
    }

    /**
     * Ids of another instance are ignored, mixed in with valid ones or not.
     */
    public function test_eids_of_another_instance_are_ignored(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        [, $other] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, 'text', 'Title');
        $otherfieldid = $this->add_mobile_field($other, 'text', 'Title');
        $mine = $this->add_mobile_entry($dlx, $USER->id, [$fieldid => 'Mine']);
        $theirs = $this->add_mobile_entry($other, $USER->id, [$otherfieldid => 'Theirs']);

        $entries = new datalynx_entries($dlx);
        $entries->interactive = false;
        [, $processed] = $entries->process_entries('delete', [$mine, $theirs], null, true);

        $this->assertSame([$mine], array_map('intval', $processed));
        $this->assertTrue($DB->record_exists('datalynx_entries', ['id' => $theirs]));
    }

    /**
     * The action gate follows the target view's tags, wherever they are, and nothing else.
     */
    public function test_confirm_view_action_follows_the_target_view(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        [, $other] = $this->create_mobile_instance();
        $plain = $this->add_mobile_view($dlx, 'grid', 'Plain', '<p>[[Title]]</p>');
        $intemplate = $this->add_mobile_view($dlx, 'grid', 'Template', '<p>[[Title]] ##delete##</p>');
        $insection = $this->add_mobile_view($dlx, 'grid', 'Section', '', ['section' => '##entries## ##delete##']);
        $editable = $this->add_mobile_view($dlx, 'grid', 'Editable', '##edit##');
        $foreign = $this->add_mobile_view($other, 'grid', 'Foreign', '##delete##');

        $view = $dlx->get_view('grid', $plain, false);

        $this->assertFalse($view->confirm_view_action('delete', $plain));
        $this->assertTrue($view->confirm_view_action('delete', $intemplate));
        $this->assertTrue($view->confirm_view_action('delete', $insection));
        $this->assertFalse($view->confirm_view_action('delete', $editable));
        $this->assertTrue($view->confirm_view_action('edit', $editable));
        $this->assertFalse($view->confirm_view_action('delete', $foreign), 'A view of another instance authorised an action.');
        $this->assertFalse($view->confirm_view_action('delete', 999999));
        // Without a target and without a request parameter, the view itself is the target.
        $this->assertFalse($view->confirm_view_action('delete'));
        $this->assertTrue($dlx->get_view('grid', $intemplate, false)->confirm_view_action('delete'));
    }

    /**
     * A view hidden from the user authorises nothing for them, even with the tag in place.
     */
    public function test_confirm_view_action_requires_a_visible_target(): void {
        $this->resetAfterTest();
        [$course, $dlx] = $this->create_mobile_instance();
        $visible = $this->add_mobile_view($dlx, 'grid', 'Visible', '##delete##');
        $hidden = $this->add_mobile_view($dlx, 'grid', 'Hidden', '##delete##', ['visible' => 1]);
        $this->setUser($this->create_mobile_student($course));

        $view = $dlx->get_view('grid', $visible, false);

        $this->assertTrue($view->confirm_view_action('delete', $visible));
        $this->assertFalse($view->confirm_view_action('delete', $hidden));
    }

    /**
     * A view built without filter options still hands out an entries manager.
     */
    public function test_get_entries_manager(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Plain');

        $view = $dlx->get_view('grid', $viewid, false);
        $manager = $view->get_entries_manager();

        $this->assertInstanceOf(datalynx_entries::class, $manager);
        $this->assertSame($manager, $view->get_entries_manager());
    }
}
