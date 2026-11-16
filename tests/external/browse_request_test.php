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

namespace mod_datalynx\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use core_external\external_api;
use externallib_advanced_testcase;
use invalid_parameter_exception;
use mod_datalynx\datalynx;
use mod_datalynx\local\view\manager\tabular_view_manager;
use mod_datalynx\mobile_test_trait;

/**
 * The desktop browse services and the mobile service share one request path (#263, #264).
 *
 * @package    mod_datalynx
 * @category   external
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\external\browse_request
 * @covers     \mod_datalynx\local\view\manager\view_manager
 */
final class browse_request_test extends externallib_advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    /** @var int */
    private int $titleid;

    /** @var int[] entry ids by title */
    private array $entryids = [];

    #[\Override]
    protected function setUp(): void {
        global $USER;

        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        [, $this->dlx] = $this->create_mobile_instance();
        $this->titleid = $this->add_mobile_field($this->dlx, 'text', 'Title');
        $time = time() - 100;
        foreach (['Apple', 'Banana', 'Cherry'] as $i => $title) {
            $this->entryids[$title] = $this->add_mobile_entry($this->dlx, $USER->id, [$this->titleid => $title], $time + $i);
        }
    }

    /**
     * Call the desktop tabular service and clean the result against its returns.
     *
     * @param int $viewid
     * @param array $params named parameters of the service besides d and view
     * @return array
     */
    private function call_tabular(int $viewid, array $params = []): array {
        $result = get_tabular_view_data::execute(...(['d' => $this->dlx->id(), 'view' => $viewid] + $params));

        return external_api::clean_returnvalue(get_tabular_view_data::execute_returns(), $result);
    }

    /**
     * Call the mobile service and clean the result against its returns.
     *
     * @param array $params
     * @return array
     */
    private function call_mobile(array $params): array {
        $result = get_mobile_entries::execute(...($params + ['d' => $this->dlx->id()]));

        return external_api::clean_returnvalue(get_mobile_entries::execute_returns(), $result);
    }

    /**
     * The entry ids of a desktop tabular payload, in order.
     *
     * @param array $payload
     * @return int[]
     */
    private function tabular_entry_ids(array $payload): array {
        return array_map(fn(array $row) => (int) $row['id'], $payload['groups'][0]['rows'] ?? []);
    }

    /**
     * The same criteria list the same entries in the same order on the desktop and in the app.
     */
    public function test_desktop_and_mobile_list_the_same_entries(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'tabular', 'Table');
        $criteria = json_encode([$this->titleid => ['OR' => [['', '=', 'Apple'], ['', '=', 'Cherry']]]]);
        $sort = json_encode([$this->titleid => 1]);

        $desktop = $this->call_tabular($viewid, ['customsearch' => $criteria, 'customsort' => $sort]);
        $mobile = $this->call_mobile(['viewid' => $viewid, 'criteria' => $criteria, 'sort' => $sort]);

        $expected = [$this->entryids['Cherry'], $this->entryids['Apple']];
        $this->assertSame($expected, $this->tabular_entry_ids($desktop));
        $this->assertSame($expected, $this->mobile_entry_ids($mobile));
        $this->assertSame($desktop['entriesfiltercount'], $mobile['entriesfiltercount']);
    }

    /**
     * The desktop services take criteria only through search_criteria: unknown fields are refused.
     */
    public function test_desktop_refuses_criteria_for_unknown_fields(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'tabular', 'Table');

        $this->expectException(invalid_parameter_exception::class);
        $this->call_tabular($viewid, ['customsearch' => json_encode([999999 => ['AND' => [['', '=', 'x']]]])]);
    }

    /**
     * The desktop services refuse operators a field does not take.
     */
    public function test_desktop_refuses_unsupported_operators(): void {
        $viewid = $this->add_mobile_view($this->dlx, 'tabular', 'Table');

        $this->expectException(invalid_parameter_exception::class);
        $this->call_tabular($viewid, ['customsearch' => json_encode([$this->titleid => ['AND' => [['', 'DROP', 'x']]]])]);
    }

    /**
     * A view that forces its filter ignores a desktop client's filter id, search and criteria,
     * as the mobile service always did.
     */
    public function test_desktop_forced_filter_ignores_client_filtering(): void {
        $apples = $this->add_mobile_filter($this->dlx, 'Apples', ['search' => 'Apple']);
        $bananas = $this->add_mobile_filter($this->dlx, 'Bananas', ['search' => 'Banana']);
        $forced = $this->add_mobile_view($this->dlx, 'tabular', 'Forced', '', ['filter' => $apples]);

        $result = $this->call_tabular($forced, [
            'filterid' => $bananas,
            'search' => 'Banana',
            'customsearch' => json_encode([$this->titleid => ['AND' => [['', '=', 'Banana']]]]),
        ]);

        $this->assertSame([$this->entryids['Apple']], $this->tabular_entry_ids($result));
    }

    /**
     * A view that does not force its filter applies the client's filter id and search.
     */
    public function test_desktop_open_view_applies_client_filtering(): void {
        $bananas = $this->add_mobile_filter($this->dlx, 'Bananas', ['search' => 'Banana']);
        $open = $this->add_mobile_view($this->dlx, 'tabular', 'Open', '', ['param5' => 1]);

        $result = $this->call_tabular($open, ['filterid' => $bananas]);
        $this->assertSame([$this->entryids['Banana']], $this->tabular_entry_ids($result));
        $result = $this->call_tabular($open, ['search' => 'Cher']);
        $this->assertSame([$this->entryids['Cherry']], $this->tabular_entry_ids($result));
    }

    /**
     * Criteria a custom filter builds are criteria the desktop services accept when the browser
     * sends them back, and they list the same entries as the filter itself.
     *
     * @dataProvider customfilter_toggle_provider
     * @param int $andor the custom filter's any/all toggle: 0 any of, -2 all of
     * @param string[] $expected titles of the entries the filter finds
     */
    public function test_customfilter_criteria_pass_the_desktop_services(int $andor, array $expected): void {
        global $USER;
        $tagsid = $this->add_mobile_field($this->dlx, 'multiselect', 'Tags', ['param1' => "Red\nGreen\nBlue"]);
        $ids = [
            'Red' => $this->add_mobile_entry($this->dlx, $USER->id, [$this->titleid => 'Red', $tagsid => '#1#']),
            'Red and green' => $this->add_mobile_entry(
                $this->dlx,
                $USER->id,
                [$this->titleid => 'Red and green', $tagsid => '#1#,#2#']
            ),
            'Blue' => $this->add_mobile_entry($this->dlx, $USER->id, [$this->titleid => 'Blue', $tagsid => '#3#']),
        ];
        $customfilter = (object) [
            'id' => 1,
            'dataid' => $this->dlx->id(),
            'fieldlist' => json_encode([$tagsid => ['name' => 'Tags', 'sortable' => 0]]),
        ];
        // As the frontend form submits it: the options and the any/all toggle (customfilter_controls).
        $formdata = (object) [
            "cf_{$tagsid}" => ['1', '2'],
            "cf_{$tagsid}_mode" => $andor === -2 ? 'all' : 'any',
        ];

        $filter = new \mod_datalynx\local\filter\datalynx_filter((object) ['id' => 0, 'dataid' => $this->dlx->id()]);
        $filter = $this->dlx->get_filter_manager()->get_filter_from_customfilterform($filter, $formdata, $customfilter);
        $criteria = json_encode(unserialize($filter->customsearch, ['allowed_classes' => false]));

        $viewid = $this->add_mobile_view($this->dlx, 'tabular', 'Table');
        $result = $this->call_tabular($viewid, ['customsearch' => $criteria]);

        $this->assertEqualsCanonicalizing(
            array_map(fn($title) => $ids[$title], $expected),
            $this->tabular_entry_ids($result)
        );
    }

    /**
     * The custom filter's any/all toggle and what it finds for "Red, Green".
     *
     * @return array
     */
    public static function customfilter_toggle_provider(): array {
        return [
            'any of' => [0, ['Red', 'Red and green']],
            'all of' => [-2, ['Red and green']],
        ];
    }

    /**
     * Entry ids are always set from the parameters, so the request itself is never read for them.
     */
    public function test_filter_options_always_carry_entry_ids(): void {
        $options = browse_request::filter_options(['filterid' => 3, 'page' => -2], $this->dlx);

        $this->assertSame(['filterid' => 3, 'page' => 0, 'eids' => ''], $options);
    }

    /**
     * A field value of "0" is rendered, not dropped as empty.
     */
    public function test_zero_value_is_rendered(): void {
        global $USER;
        $countid = $this->add_mobile_field($this->dlx, 'number', 'Count');
        $entryid = $this->add_mobile_entry($this->dlx, $USER->id, [$this->titleid => 'Zero', $countid => '0']);
        $viewid = $this->add_mobile_view($this->dlx, 'tabular', 'Table');

        $payload = (new tabular_view_manager())->get_browse_payload($this->dlx, $viewid, ['eids' => (string) $entryid]);

        $cells = array_column($payload['groups'][0]['rows'][0]['cells'], 'valuehtml');
        $this->assertContains('0', array_map(fn($html) => trim(strip_tags($html)), $cells));
    }
}
