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

namespace mod_datalynx\local\field;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\external\submit_entry;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\entry\entry_submission_service;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\mobile_test_trait;
use stdClass;

/**
 * The edit control of each field type (mod_datalynx#271).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\field\datalynxfield_renderer::get_edit_control
 */
final class edit_control_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by name */
    private array $ids = [];

    /** @var int */
    private int $viewid;

    /** @var int an entry with a value in every field */
    private int $entryid;

    #[\Override]
    protected function setUp(): void {
        global $DB, $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        datalynxfield_behavior::reset_condition_cache();

        [, $dlx] = $this->create_mobile_instance();
        $fields = [
            'Title' => ['text', []],
            'Mail' => ['text', ['param4' => 'email']],
            'Count' => ['number', []],
            'Notes' => ['textarea', []],
            'Link' => ['url', ['param5' => 1]],
            'Colour' => ['select', ['param1' => "Red\nGreen\nBlue"]],
            'Size' => ['radiobutton', ['param1' => "S\nM\nL"]],
            'Tags' => ['multiselect', ['param1' => "Big\nSmall\nOld"]],
            'Flags' => ['checkbox', ['param1' => "One\nTwo"]],
            'When' => ['time', []],
            'Length' => ['duration', []],
            'Code' => ['identifier', []],
        ];
        $tags = [];
        foreach ($fields as $name => [$type, $params]) {
            $this->ids[$name] = $this->add_mobile_field($dlx, $type, $name, $params);
            $tags[] = "[[$name]]";
        }
        $this->viewid = $this->add_mobile_view($dlx, 'grid', 'Edit', implode(' ', $tags) . ' ##status## ##edit##',
            ['section' => '##addnewentry## ##entries##']);

        $this->entryid = $this->add_mobile_entry($dlx, $USER->id, [
            $this->ids['Title'] => 'Apple',
            $this->ids['Mail'] => 'a@example.com',
            $this->ids['Count'] => '3.5',
            $this->ids['Notes'] => "Line one\nLine two",
            $this->ids['Link'] => 'https://example.com/apple',
            $this->ids['Colour'] => '2',
            $this->ids['Size'] => '3',
            $this->ids['Tags'] => '#1#,#3#',
            $this->ids['Flags'] => '#2#',
            $this->ids['When'] => '1768042800',
            $this->ids['Length'] => '5400',
            $this->ids['Code'] => 'ABC123',
        ]);
        $DB->set_field('datalynx_contents', 'content1', 'Apple site',
            ['entryid' => $this->entryid, 'fieldid' => $this->ids['Link']]);
        $DB->set_field('datalynx_entries', 'status', 1, ['id' => $this->entryid]);
        $this->dlx = new datalynx($dlx->id());
    }

    /**
     * The entry with its contents, as the entry form loads it.
     *
     * @param int $entryid
     * @return stdClass
     */
    private function load_entry(int $entryid): stdClass {
        if ($entryid < 0) {
            return (object) ['id' => $entryid, 'userid' => 0, 'status' => 0];
        }
        $filter = new datalynx_filter((object) ['dataid' => $this->dlx->id(), 'eids' => (string) $entryid]);
        $filter->contentfields = array_keys(array_filter($this->dlx->get_fields(), fn($field) => !$field::is_internal()));
        $entries = (new datalynx_entries($this->dlx, $filter))->get_entries(['filter' => $filter])->entries;

        return reset($entries);
    }

    /**
     * The controls of an entry, by field name, as the edit form will build them: the render
     * options of each field from the submission service.
     *
     * @param int $entryid
     * @return array field name => control or null
     */
    private function controls(int $entryid): array {
        $view = $this->dlx->get_view('grid', $this->viewid, false);
        $states = (new entry_submission_service($view))->get_field_states($entryid);
        $entry = $this->load_entry($entryid);
        $names = array_flip($this->ids) + ['status' => 'status'];
        $controls = [];
        foreach ($states as $fieldid => $state) {
            if (isset($names[$fieldid]) && $state['editable']) {
                $controls[$names[$fieldid]] = $state['field']->renderer()->get_edit_control($entry, $state['options']);
            }
        }

        return $controls;
    }

    /**
     * Each type describes its control, its inputs named after the input contract, its value and
     * its choices.
     */
    public function test_controls_per_type(): void {
        $c = $this->controls($this->entryid);
        $value = fn(string $name, string $input = '') => array_column($c[$name]['inputs'], 'value', 'name')[$input];

        $this->assertSame('text', $c['Title']['control']);
        $this->assertSame('Apple', $value('Title'));
        $this->assertSame('email', $c['Mail']['inputmode']);
        $this->assertSame(['number', '3.5'], [$c['Count']['control'], $value('Count')]);
        $this->assertSame(['textarea', "Line one\nLine two"], [$c['Notes']['control'], $value('Notes')]);
        $this->assertSame(['url', 'https://example.com/apple', 'Apple site'],
            [$c['Link']['control'], $value('Link', 'url'), $value('Link', 'alt')]);

        $this->assertSame(['select', '2'], [$c['Colour']['control'], $value('Colour')]);
        $this->assertSame(['Red', 'Green', 'Blue'], array_column($c['Colour']['choices'], 'label'));
        $this->assertSame(['radio', '3'], [$c['Size']['control'], $value('Size')]);
        $this->assertSame(['multiselect', ['1', '3'], true], [$c['Tags']['control'], $value('Tags'), $c['Tags']['multiple']]);
        $this->assertTrue($c['Tags']['inputs'][0]['list']);
        $this->assertSame(['checkbox', ['2']], [$c['Flags']['control'], $value('Flags')]);

        $this->assertSame(['datetime', '1768042800'], [$c['When']['control'], $value('When')]);
        $this->assertSame(['duration', '5400'], [$c['Length']['control'], $value('Length')]);
        // An identifier is there: nothing to send.
        $this->assertSame(['hidden', []], [$c['Code']['control'], $c['Code']['inputs']]);
        $this->assertSame(['toggle', '1'], [$c['status']['control'], $value('status')]);
        $this->assertSame(['1', '2'], array_column($c['status']['choices'], 'value'));
    }

    /**
     * A new entry: empty values, and the identifier flag that has it generated.
     */
    public function test_new_entry(): void {
        $c = $this->controls(-1);

        $this->assertSame('', $c['Title']['inputs'][0]['value']);
        $this->assertSame([], $c['Tags']['inputs'][0]['value']);
        $this->assertSame([['name' => '', 'list' => false, 'value' => '1']], $c['Code']['inputs']);
        $this->assertSame('1', $c['status']['inputs'][0]['value']);
    }

    /**
     * Sent back unchanged through submit_entry, the values of the controls change nothing.
     */
    public function test_round_trip_changes_nothing(): void {
        global $DB;

        $before = $DB->get_records('datalynx_contents', ['entryid' => $this->entryid], 'fieldid', 'fieldid, content, content1');
        $data = [];
        foreach ($this->controls($this->entryid) as $control) {
            foreach ($control['inputs'] as $input) {
                $data[] = [
                    'fieldid' => (string) $control['fieldid'],
                    'name' => $input['name'],
                    'value' => $input['list'] ? json_encode($input['value']) : $input['value'],
                ];
            }
        }

        $sink = $this->redirectEvents();
        $result = submit_entry::execute($this->dlx->id(), $this->viewid, $this->entryid, $data);
        $changed = [];
        foreach ($sink->get_events() as $event) {
            if ($event instanceof \mod_datalynx\event\entry_updated) {
                $changed = array_merge($changed, $event->other['changed_field_ids'] ?? []);
            }
        }
        $sink->close();

        $this->assertTrue($result['status'], json_encode($result));
        $this->assertSame([], $changed);
        $this->assertEquals($before,
            $DB->get_records('datalynx_contents', ['entryid' => $this->entryid], 'fieldid', 'fieldid, content, content1'));
        $this->assertEquals(1, $DB->get_field('datalynx_entries', 'status', ['id' => $this->entryid]));
    }

    /**
     * A new entry saved from its controls gets its values and its identifier.
     */
    public function test_new_entry_saves(): void {
        global $DB;

        $data = [];
        $set = ['Title' => 'Pear', 'Colour' => '1', 'Tags' => ['2']];
        foreach ($this->controls(-1) as $name => $control) {
            foreach ($control['inputs'] as $input) {
                $value = $set[$name] ?? $input['value'];
                $data[] = ['fieldid' => (string) $control['fieldid'], 'name' => $input['name'],
                    'value' => $input['list'] ? json_encode($value) : $value];
            }
        }

        $result = submit_entry::execute($this->dlx->id(), $this->viewid, 0, $data);

        $this->assertTrue($result['status'], json_encode($result));
        $contents = $DB->get_records_menu('datalynx_contents', ['entryid' => $result['entryid']], '', 'fieldid, content');
        $this->assertSame('Pear', $contents[$this->ids['Title']]);
        $this->assertSame('#2#', $contents[$this->ids['Tags']]);
        $this->assertNotEmpty($contents[$this->ids['Code']]);
    }

    /**
     * No control where the app has none yet: text autocomplete, multiple choice that adds options,
     * masked time, editor. Select options used up are disabled unless chosen.
     */
    public function test_no_control_and_disabled_choices(): void {
        global $USER;

        $field = fn(string $type, array $params = []) => (new datalynx($this->dlx->id()))
            ->get_field_from_id($this->add_mobile_field($this->dlx, $type, $type . count($this->ids), $params));
        $new = (object) ['id' => -1];

        $this->assertNull($field('text', ['param9' => 1])->renderer()->get_edit_control($new, []));
        $this->assertNull($field('multiselect', ['param1' => "A\nB"])->renderer()->get_edit_control($new, ['addnew' => 1]));
        $this->assertNull($field('time', ['param5' => 1])->renderer()->get_edit_control($new, []));
        $this->assertNull($field('editor')->renderer()->get_edit_control($new, []));

        $limited = $field('select', ['param1' => "Once\nOften", 'param5' => 1]);
        $this->add_mobile_entry($this->dlx, $USER->id, [$limited->field->id => '1']);
        $choices = array_column($limited->renderer()->get_edit_control($new, [])['choices'], 'disabled', 'value');
        $this->assertSame(['1' => true, '2' => false], $choices);
    }
}
