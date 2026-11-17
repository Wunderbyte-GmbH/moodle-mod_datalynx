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

namespace mod_datalynx\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\mobile_test_trait;

/**
 * The entry form of the Moodle App shows the entry template of its view around the inputs.
 *
 * Built like the first step of the DocSchool application form: heading and info texts in the
 * entry template, the question of a field in its field layout before #input.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\entry\entry_edit_form::parts
 * @covers     \mod_datalynx\output\mobile::mobile_edit_entry
 */
final class mobile_edit_template_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var \stdClass */
    private \stdClass $course;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by name */
    private array $ids = [];

    /** @var int */
    private int $viewid;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        [$this->course, $this->dlx] = $this->create_mobile_instance();
        $this->ids['Format'] = $this->add_mobile_field($this->dlx, 'select', 'Format', ['param1' => "Event Grant\nTravel Grant"]);
        $this->ids['Purpose'] = $this->add_mobile_field($this->dlx, 'text', 'Purpose');
        $DB->insert_record('datalynx_renderers', (object) [
            'dataid' => $this->dlx->id(),
            'name' => 'r_format',
            'description' => '',
            'notvisibletemplate' => '___0___',
            'displaytemplate' => '<div class="row"><b>Funding format</b> #value</div>',
            'novaluetemplate' => '___0___',
            'edittemplate' => '<div class="mb-4"><label>What type of funding?</label><div>#input</div>'
                . '<small>Locked after saving.</small></div>',
            'noteditabletemplate' => '___2___',
        ]);
        $this->viewid = $this->add_mobile_view(
            $this->dlx,
            'grid',
            'Step 1',
            '<div class="card"><h3>Choose application type</h3><p class="text-muted" style="min-width:230px">First select.</p>'
                . '[[Format||r_format]]<div class="alert alert-info">Info box</div><p>Purpose: [[Purpose]]</p>'
                . '<p>Again: [[Purpose]]</p>##edit##</div>',
            ['section' => '##addnewentry## ##entries##']
        );
        $this->dlx = new datalynx($this->dlx->id());
        $this->setUser($this->create_mobile_student($this->course));
    }

    /**
     * Open the edit screen as the app does.
     *
     * @param int $entryid
     * @return array the page state
     */
    private function page(int $entryid = 0): array {
        $result = mobile::mobile_edit_entry(array_map('strval', [
            'cmid' => $this->dlx->cm->id, 'courseid' => $this->course->id, 'viewid' => $this->viewid, 'entryid' => $entryid,
        ]));

        return json_decode($result['otherdata']['state'], true);
    }

    /**
     * The parts in order, each as a short label: the HTML text, or field:<name> for an input.
     *
     * @param array $state
     * @return string[]
     */
    private static function outline(array $state): array {
        $outline = [];
        foreach ($state['parts'] as $part) {
            $outline[] = $part['isfield']
                ? 'field:' . $state['fields'][$part['field']]['fieldid']
                : trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('<', ' <', $part['html']))));
        }
        return $outline;
    }

    /**
     * A new entry: the template texts around the inputs, each input once, the layout's question as its label.
     */
    public function test_new_entry_shows_entry_template(): void {
        $state = $this->page();

        $this->assertSame([
            'Choose application type First select.',
            'field:' . $this->ids['Format'],
            'Locked after saving. Info box Purpose:',
            'field:' . $this->ids['Purpose'],
            'Again:',
        ], self::outline($state));

        $names = array_column($state['fields'], 'name', 'fieldid');
        $this->assertSame('What type of funding?', $names[$this->ids['Format']]);
        $this->assertSame('Purpose', $names[$this->ids['Purpose']]);

        $html = implode('', array_column($state['parts'], 'html'));
        $this->assertStringNotContainsString('@@DLXAPPFIELD', $html);
        $this->assertStringNotContainsString('[[', $html);
        $this->assertStringNotContainsString('min-width', $html);
        $this->assertStringContainsString('class="alert alert-info"', $html);
    }

    /**
     * Editing an entry shows the same template; the inputs carry the stored values.
     */
    public function test_edit_entry_shows_entry_template(): void {
        global $USER;
        $entryid = $this->add_mobile_entry($this->dlx, (int) $USER->id, [$this->ids['Purpose'] => 'Workshop']);

        $state = $this->page($entryid);

        $this->assertTrue($state['allowed']);
        $this->assertContains('Locked after saving. Info box Purpose:', self::outline($state));
        $purpose = array_values(array_filter($state['fields'], fn($f) => (int) $f['fieldid'] === $this->ids['Purpose']))[0];
        $this->assertSame('Workshop', $purpose['inputs'][0]['value']);
    }
}
