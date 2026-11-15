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

namespace datalynxfield_file;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');
require_once($CFG->dirroot . '/mod/datalynx/db/upgrade.php');
require_once($CFG->libdir . '/formslib.php');

use advanced_testcase;
use mod_datalynx\datalynx;
use mod_datalynx\local\datalynx_entries;
use mod_datalynx\local\filter\datalynx_filter;
use mod_datalynx\local\filter\search_criteria;
use mod_datalynx\mobile_test_trait;
use MoodleQuickForm;

/**
 * Searching file and picture fields follows the standard [not, operator, value] pattern (#303).
 *
 * @package    datalynxfield_file
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_file\field::get_search_sql
 * @covers     \datalynxfield_file\renderer::render_search_mode
 * @covers     \mod_datalynx\local\filter\datalynx_filter_manager::get_filter_from_customfilterform
 * @covers     ::mod_datalynx_upgrade_file_search_criteria
 */
final class search_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int[] field ids by type */
    private array $fieldids = [];

    /** @var int[] entry ids: the file and picture field filled ('both'), neither ('none'), file removed ('removed') */
    private array $entryids = [];

    #[\Override]
    protected function setUp(): void {
        global $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $this->dlx] = $this->create_mobile_instance();
        foreach (['file', 'picture'] as $type) {
            $this->fieldids[$type] = $this->add_mobile_field($this->dlx, $type, ucfirst($type));
        }
        // A file field holds '1' with a file and '0' once its file is removed.
        $this->entryids['both'] = $this->add_mobile_entry($this->dlx, $USER->id, [
            $this->fieldids['file'] => '1',
            $this->fieldids['picture'] => '1',
        ]);
        $this->entryids['none'] = $this->add_mobile_entry($this->dlx, $USER->id, []);
        $this->entryids['removed'] = $this->add_mobile_entry($this->dlx, $USER->id, [
            $this->fieldids['file'] => '0',
            $this->fieldids['picture'] => '0',
        ]);
    }

    /**
     * The entries a customsearch finds.
     *
     * @param array $customsearch
     * @return int[]
     */
    private function find(array $customsearch): array {
        $filter = new datalynx_filter((object) [
            'id' => 0,
            'dataid' => $this->dlx->id(),
            'customsearch' => serialize($customsearch),
        ]);
        $entries = new datalynx_entries($this->dlx, $filter);
        $entries->set_content();

        return array_map('intval', array_keys($entries->entries() ?: []));
    }

    /**
     * The custom filter choice "exists" / "missing" becomes "not empty" / "empty" and finds the
     * entries with and without a file, for file and picture fields alike.
     *
     * @dataProvider customfilter_provider
     * @param string $type file or picture
     * @param string $choice the selector value: 0 exists, 1 missing
     * @param array $criterion the criterion the custom filter writes
     * @param string[] $expected keys of the entries found
     */
    public function test_customfilter_choice(string $type, string $choice, array $criterion, array $expected): void {
        $fieldid = $this->fieldids[$type];
        $customfilter = (object) [
            'id' => 1,
            'dataid' => $this->dlx->id(),
            'fieldlist' => json_encode([$fieldid => ['name' => ucfirst($type), 'sortable' => 0]]),
        ];
        $formdata = (object) ["f_0_{$fieldid}" => $choice];

        $filter = new datalynx_filter((object) ['id' => 0, 'dataid' => $this->dlx->id()]);
        $filter = $this->dlx->get_filter_manager()->get_filter_from_customfilterform($filter, $formdata, $customfilter);
        $search = unserialize($filter->customsearch, ['allowed_classes' => false]);

        $this->assertSame([$fieldid => ['AND' => [$criterion]]], $search);
        // The criterion passes the strict contract, so a browser can send it back to the services.
        $this->assertSame($search, search_criteria::decode_search(json_encode($search), $this->dlx));
        $this->assertEqualsCanonicalizing(
            array_map(fn($key) => $this->entryids[$key], $expected),
            $this->find($search)
        );
    }

    /**
     * Custom filter choices per field type.
     *
     * @return array
     */
    public static function customfilter_provider(): array {
        return [
            'file exists' => ['file', '0', ['NOT', '', ''], ['both']],
            'file missing' => ['file', '1', ['', '', ''], ['none', 'removed']],
            'picture exists' => ['picture', '0', ['NOT', '', ''], ['both']],
            'picture missing' => ['picture', '1', ['', '', ''], ['none', 'removed']],
        ];
    }

    /**
     * Choosing nothing in a custom filter adds no criterion.
     */
    public function test_customfilter_without_choice_adds_nothing(): void {
        $fieldid = $this->fieldids['file'];
        $customfilter = (object) ['id' => 1, 'dataid' => $this->dlx->id(),
            'fieldlist' => json_encode([$fieldid => ['name' => 'File', 'sortable' => 0]])];

        $filter = new datalynx_filter((object) ['id' => 0, 'dataid' => $this->dlx->id()]);
        $filter = $this->dlx->get_filter_manager()
            ->get_filter_from_customfilterform($filter, (object) ["f_0_{$fieldid}" => '-1'], $customfilter);

        $this->assertEmpty($filter->customsearch);
    }

    /**
     * A view filter row offers no value to enter, a custom filter the exists/missing choice.
     */
    public function test_search_elements(): void {
        $renderer = $this->dlx->get_field_from_id($this->fieldids['file'])->renderer();

        $mform = new MoodleQuickForm('mod_datalynx_form_datalynx_filter_form', 'post', '');
        [$elements] = $renderer->render_search_mode($mform, 0, '');
        $this->assertSame('static', $elements[0]->getType());

        $mform = new MoodleQuickForm('mod_datalynx_form_datalynx_customfilter_frontend_form', 'post', '');
        [$elements] = $renderer->render_search_mode($mform, 0, '');
        $this->assertSame('select', $elements[0]->getType());
    }

    /**
     * The upgrade rewrites stored file criteria, which only their value used to drive, so that
     * each saved filter and rule keeps finding what it found before.
     */
    public function test_upgrade_converts_stored_criteria(): void {
        global $DB;
        $fileid = $this->fieldids['file'];
        $textid = $this->add_mobile_field($this->dlx, 'text', 'Title');
        $text = [['', 'LIKE', 'x']];
        // Old view filter rows: is/not and operator disabled, the selector in the value.
        $hasfile = $this->add_mobile_filter($this->dlx, 'Has file', [
            'customsearch' => serialize([$fileid => ['AND' => [['', '', '0']]], $textid => ['AND' => $text]]),
        ]);
        $nofile = $this->add_mobile_filter($this->dlx, 'No file', [
            'customsearch' => serialize([$fileid => ['OR' => [['', '', 1]]]]),
        ]);
        // Old custom filter output: the negation was the wrong way round and ignored.
        $customnofile = $this->add_mobile_filter($this->dlx, 'Custom missing', [
            'customsearch' => serialize([$fileid => ['AND' => [['NOT', '', false]]]]),
        ]);
        $other = $this->add_mobile_filter($this->dlx, 'Other', ['customsearch' => serialize([$textid => ['AND' => $text]])]);
        $rule = $DB->insert_record('datalynx_rules', (object) ['dataid' => $this->dlx->id(), 'type' => 'eventnotification',
            'name' => 'Rule', 'description' => '', 'param9' => json_encode([
                $fileid => ['AND' => [['', '', '1']]], '_onlyonchangefields' => [$textid],
            ])]);
        $legacy = $DB->insert_record('datalynx_rules', (object) ['dataid' => $this->dlx->id(), 'type' => 'eventnotification',
            'name' => 'Legacy', 'description' => '', 'param5' => (string) $fileid, 'param10' => '0']);

        mod_datalynx_upgrade_file_search_criteria();

        $search = fn($id) => unserialize($DB->get_field('datalynx_filters', 'customsearch', ['id' => $id]));
        $this->assertSame([$fileid => ['AND' => [['NOT', '', '']]], $textid => ['AND' => $text]], $search($hasfile));
        $this->assertSame([$fileid => ['OR' => [['', '', '']]]], $search($nofile));
        $this->assertSame([$fileid => ['AND' => [['NOT', '', '']]]], $search($customnofile));
        $this->assertSame([$textid => ['AND' => $text]], $search($other));
        $this->assertSame(
            [$fileid => ['AND' => [['', '', '']]], '_onlyonchangefields' => [$textid]],
            json_decode($DB->get_field('datalynx_rules', 'param9', ['id' => $rule]), true)
        );
        $this->assertSame(
            [$fileid => ['AND' => [['NOT', '', '']]]],
            json_decode($DB->get_field('datalynx_rules', 'param9', ['id' => $legacy]), true)
        );

        // And the converted filters find what the old reading found.
        $this->assertSame([$this->entryids['both']], $this->find([$fileid => ['AND' => [['NOT', '', '']]]]));
    }
}
