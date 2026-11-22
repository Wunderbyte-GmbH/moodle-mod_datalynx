<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_datalynx;

use advanced_testcase;
use mod_datalynx\local\entry\entry_submission_service;
use mod_datalynx\local\view\base;
use mod_datalynx\local\view\datalynxview_entries_form;
use moodle_url;
use ReflectionClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');
require_once($CFG->libdir . '/formslib.php');

/**
 * "Limit choices for users" of single choice fields is validated, with the error shown at the
 * field (mod_datalynx#192).
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxfield_select\renderer::validate
 * @covers     \mod_datalynx\local\field\datalynxfield_option_single::get_disabled_values_for_user
 * @covers     \datalynxfield_radiobutton\renderer::render_edit_mode
 * @covers     \datalynxfield_radiobutton\renderer::get_edit_control
 */
final class limitchoice_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var datalynx */
    private datalynx $dlx;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \mod_datalynx\local\field\datalynxfield_behavior::reset_condition_cache();
        [, $this->dlx] = $this->create_mobile_instance();
    }

    /**
     * A view to add entries with the given entry template.
     *
     * @param string $entrytemplate
     * @return base
     */
    private function view(string $entrytemplate): base {
        global $DB;
        $viewid = $this->add_mobile_view($this->dlx, 'grid', 'Edit', $entrytemplate . ' ##edit##', [
            'section' => '##addnewentry## ##entries##',
        ]);
        $this->dlx = new datalynx($this->dlx->id());

        return $this->dlx->get_view('grid', $DB->get_record('datalynx_views', ['id' => $viewid]));
    }

    /**
     * The new-entry form of the view.
     *
     * @param base $view
     * @return datalynxview_entries_form
     */
    private function new_entry_form(base $view): datalynxview_entries_form {
        $reflection = new ReflectionClass($view);
        $editentries = $reflection->getProperty('editentries');
        $editentries->setAccessible(true);
        $editentries->setValue($view, [-1]);
        $definition = $reflection->getMethod('set_display_definition');
        $definition->setAccessible(true);
        $definition->invoke($view);

        return new datalynxview_entries_form(new moodle_url('/mod/datalynx/view.php'), ['view' => $view, 'update' => '-1']);
    }

    /**
     * The element names of the form that carry an error message where it is rendered.
     *
     * @param datalynxview_entries_form $form
     * @param array $errors
     * @return string[]
     */
    private function errors_shown_at_elements(datalynxview_entries_form $form, array $errors): array {
        $mform = (new ReflectionClass($form))->getProperty('_form');
        $mform->setAccessible(true);
        $quickform = $mform->getValue($form);

        return array_values(array_filter(array_keys($errors), fn($name) => $quickform->elementExists($name)));
    }

    /**
     * Field types with "Limit choices for users".
     *
     * @return array
     */
    public static function single_choice_provider(): array {
        return [
            'radio button' => ['radiobutton', '_group'],
            'select' => ['select', ''],
        ];
    }

    /**
     * Choosing an option a second time when the limit is one is an error at the field.
     *
     * @dataProvider single_choice_provider
     * @param string $type field type
     * @param string $suffix suffix of the element showing the error
     */
    public function test_limit_reached_is_an_error_at_the_field(string $type, string $suffix): void {
        $user = $this->getDataGenerator()->create_and_enrol(get_course($this->dlx->course->id), 'student');
        $this->setUser($user);
        $fieldid = $this->add_mobile_field($this->dlx, $type, 'Choice', ['param1' => "A\nB", 'param5' => '1']);
        $view = $this->view('[[Choice]]');
        $name = "field_{$fieldid}_-1";

        // First entry with A is fine and saved.
        $saved = (new entry_submission_service($view))->save([-1], (object) ['new' => 1, $name => 1]);
        $this->assertSame([], $saved['errors']);
        $this->assertCount(1, $saved['entryids']);

        // Second entry with A: the limit is reached.
        $view = $this->view('[[Choice]]');
        $form = $this->new_entry_form($view);
        $errors = $form->validation(['new' => 1, $name => 1], []);

        $this->assertSame([$name . $suffix], array_keys($errors));
        $this->assertSame(get_string('limitchoiceerror', 'datalynx', 'A'), reset($errors));
        $this->assertSame([$name . $suffix], $this->errors_shown_at_elements($form, $errors));
        $this->assertSame([], $form->get_page_errors($errors));

        // B is still free.
        $this->assertSame([], $form->validation(['new' => 1, $name => 2], []));
    }

    /**
     * Whether the form offers the option as disabled.
     *
     * @param datalynxview_entries_form $form
     * @param string $name element name of the field value
     * @param int $option option id
     * @return bool
     */
    private function option_disabled(datalynxview_entries_form $form, string $name, int $option): bool {
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8"?>' . $form->render());
        $nodes = (new \DOMXPath($dom))->query(
            "//input[@type='radio' and @name='{$name}' and @value='{$option}']"
            . " | //select[@name='{$name}']/option[@value='{$option}']"
        );
        $this->assertSame(1, $nodes->length, "Option {$option} of {$name} is in the form.");

        return $nodes->item(0)->hasAttribute('disabled');
    }

    /**
     * The limit counts the entries of all users: an option another user used up can't be chosen,
     * neither in the entry form nor in the app's edit control. An entry keeps its own choice.
     *
     * @dataProvider single_choice_provider
     * @param string $type field type
     * @param string $suffix suffix of the element showing the error
     */
    public function test_used_up_option_is_disabled_for_everyone(string $type, string $suffix): void {
        $first = $this->getDataGenerator()->create_and_enrol(get_course($this->dlx->course->id), 'student');
        $second = $this->getDataGenerator()->create_and_enrol(get_course($this->dlx->course->id), 'student');
        $fieldid = $this->add_mobile_field($this->dlx, $type, 'Choice', ['param1' => "A\nB", 'param5' => '1']);
        $name = "field_{$fieldid}_-1";

        $this->setUser($first);
        $saved = (new entry_submission_service($this->view('[[Choice]]')))->save([-1], (object) ['new' => 1, $name => 1]);
        $entryid = $saved['entryids'][0];

        // Another user: A is used up.
        $this->setUser($second);
        $view = $this->view('[[Choice]]');
        $form = $this->new_entry_form($view);
        $this->assertTrue($this->option_disabled($form, $name, 1));
        $this->assertFalse($this->option_disabled($form, $name, 2));
        $this->assertSame([$name . $suffix], array_keys($form->validation(['new' => 1, $name => 1], [])));

        $renderer = $this->dlx->get_field_from_id($fieldid)->renderer();
        $choices = array_column($renderer->get_edit_control((object) ['id' => -1], [])['choices'], 'disabled', 'value');
        $this->assertTrue($choices['1']);
        $this->assertFalse($choices['2']);

        // The entry that chose A keeps it choosable when edited.
        $this->setUser($first);
        $entry = (object) ['id' => $entryid, "c{$fieldid}_content" => '1'];
        $choices = array_column($renderer->get_edit_control($entry, [])['choices'], 'disabled', 'value');
        $this->assertFalse($choices['1']);
        $service = new entry_submission_service($this->view('[[Choice]]'));
        $this->assertSame([], $service->validate([$entryid], (object) ["field_{$fieldid}_{$entryid}" => 1]));
    }
}
