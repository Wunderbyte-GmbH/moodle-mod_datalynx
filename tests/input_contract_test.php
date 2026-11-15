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
require_once($CFG->libdir . '/formslib.php');

use advanced_testcase;
use HTML_QuickForm_element;
use HTML_QuickForm_group;
use MoodleQuickForm;

/**
 * The input contract of a field names every input its edit form has, with the form's types (#266).
 *
 * The entry submission service drops what a contract does not name, for the form as for the web
 * services, so an edit element missing from the contract would silently not be saved.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\field\datalynxfield_base::get_input_contract
 * @covers     \mod_datalynx\local\field\datalynxfield_renderer::set_input_types
 */
final class input_contract_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Field types with an edit form, and the settings they need to render one.
     *
     * @return array
     */
    public static function field_type_provider(): array {
        $options = ['param1' => "One\nTwo\nThree"];
        return [
            'text' => ['text', []],
            'text numeric' => ['text', ['param4' => 'numeric']],
            'textarea' => ['textarea', []],
            'number' => ['number', []],
            'editor' => ['editor', []],
            'url' => ['url', []],
            'youtube' => ['youtube', []],
            'time' => ['time', []],
            'time masked' => ['time', ['param5' => 1]],
            'duration' => ['duration', []],
            'select' => ['select', $options],
            'radiobutton' => ['radiobutton', $options],
            'multiselect' => ['multiselect', $options],
            'checkbox' => ['checkbox', $options],
            'tag' => ['tag', []],
            'file' => ['file', []],
            'picture' => ['picture', []],
            'location' => ['location', []],
            'itinerary' => ['itinerary', []],
            'schedule' => ['schedule', []],
            'teammemberselect' => ['teammemberselect', ['param1' => 5, 'param2' => '[5]']],
            'coursegroup' => ['coursegroup', []],
        ];
    }

    /**
     * Every input element of the edit form is an input of the contract, typed as the contract says.
     *
     * @dataProvider field_type_provider
     * @param string $type
     * @param array $params
     */
    public function test_contract_names_every_edit_input(string $type, array $params): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $fieldid = $this->add_mobile_field($dlx, $type, 'Field', $params);
        $field = (new datalynx($dlx->id()))->get_field_from_id($fieldid);

        $tag = '[[Field]]';
        $replacements = $field->renderer()->replacements([$tag], (object) ['id' => -1], ['edit' => true, 'manage' => true]);
        [$callback, $args] = $replacements[$tag][1];
        $mform = new MoodleQuickForm('mod_datalynx_form_test', 'post', '');
        call_user_func_array($callback, array_merge([&$mform], $args));

        $prefix = "field_{$fieldid}_-1";
        $contract = $field->get_input_contract($args[1] ?? []);
        $inputs = $this->input_names($mform->_elements, $prefix);
        $this->assertNotEmpty($inputs, 'The edit form has inputs');
        foreach ($inputs as $name) {
            [$contentname, $key] = $this->split_name($name, $prefix);
            $this->assertArrayHasKey($contentname, $contract, "$name is an input of the contract");
            $expected = $contract[$contentname];
            if (is_array($expected) && isset($expected['fields'])) {
                if ($key === null) {
                    // One element posting a structure (an editor): each part is typed.
                    foreach ($expected['fields'] as $part => $parttype) {
                        $this->assertSame($parttype, $mform->_types["{$name}[{$part}]"] ?? null, "{$name}[{$part}] is typed");
                    }
                    continue;
                }
                $this->assertArrayHasKey($key, $expected['fields'], "$name is a part of the contract's structure");
                $this->assertSame($expected['fields'][$key], $mform->_types[$name] ?? null, "$name has the contract's type");
                continue;
            }
            $expected = is_array($expected) ? $expected['list'] : $expected;
            $element = $key === null ? $name : substr($name, 0, strrpos($name, '['));
            $this->assertSame($expected, $mform->_types[$element] ?? null, "$name has the contract's type");
        }
    }

    /**
     * The names of the input elements of a field, groups opened up.
     *
     * @param HTML_QuickForm_element[] $elements
     * @param string $prefix the field's element name, field_{fieldid}_{entryid}
     * @return string[]
     */
    private function input_names(array $elements, string $prefix): array {
        $names = [];
        foreach ($elements as $element) {
            $name = (string) $element->getName();
            $isinput = $name === $prefix || str_starts_with($name, $prefix . '_') || str_starts_with($name, $prefix . '[');
            if ($element instanceof HTML_QuickForm_group) {
                $inner = $this->input_names($element->getElements(), $prefix);
                // A group whose parts are not named after the field posts under its own name:
                // a date or duration selector, a group of checkboxes named by option.
                $names = array_merge($names, $inner ?: ($isinput ? [$name] : []));
                continue;
            }
            if (!in_array($element->getType(), ['html', 'static', 'header'], true) && $isinput) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The content name of an input, and the key within it for an input like name[day].
     *
     * @param string $name
     * @param string $prefix
     * @return array [content name, key or null]
     */
    private function split_name(string $name, string $prefix): array {
        $rest = substr($name, strlen($prefix));
        $key = null;
        if (preg_match('/^(.*)\[([^\]]*)\]$/', $rest, $matches)) {
            [, $rest, $key] = $matches;
        }

        return [ltrim($rest, '_'), $key];
    }
}
