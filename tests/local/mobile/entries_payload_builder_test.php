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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;

/**
 * The entries the app receives carry no action links and no markup the app cannot use (#267).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\mobile\entries_payload_builder
 */
final class entries_payload_builder_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var string[] what must not reach the app: action links, session keys, scripts, handlers */
    private const FORBIDDEN = ['view.php?delete=', 'delete=', 'editentries=', 'duplicate=', 'sesskey=', '<script',
        '<style', '<form', '<input', 'onclick', 'onerror', 'javascript:'];

    /**
     * An entry template full of action tags and active markup comes out as plain content, also
     * for a user who may manage every entry; the field values are cleaned the same way.
     */
    public function test_entry_html_and_fields_hold_no_actions_or_scripts(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $notesid = $this->add_mobile_field($dlx, 'textarea', 'Notes');
        $template = '<div class="card" onclick="steal()">[[Title]] [[Notes]]'
            . ' ##edit## ##delete## ##duplicate## ##select## ##export##'
            . '<style>.card{display:none}</style><script>alert(1)</script>'
            . '<form action="view.php"><input name="x"></form>'
            . '<a href="javascript:alert(2)">x</a><img src="x" onerror="alert(3)"></div>';
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Cards', $template);
        $this->add_mobile_entry($dlx, $USER->id, [
            $titleid => 'Hello',
            $notesid => '<p onmouseover="alert(4)">Notes text</p><script>alert(5)</script>',
        ]);

        $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $viewid]);

        $this->assertCount(1, $payload['entries']);
        $entry = $payload['entries'][0];
        $this->assertStringContainsString('Hello', implode('', array_column($entry['parts'], 'html')));
        $this->assertStringContainsString('Notes text', implode('', array_column($entry['parts'], 'html')));
        $this->assertSame('Hello', $entry['title']);
        $this->assertTrue($entry['canedit']);
        $this->assertTrue($entry['candelete']);
        $texts = array_merge([implode('', array_column($entry['parts'], 'html'))], array_column($entry['fields'], 'valuehtml'));
        foreach ($texts as $html) {
            foreach (self::FORBIDDEN as $needle) {
                $this->assertStringNotContainsStringIgnoringCase($needle, $html);
            }
        }
    }
}
