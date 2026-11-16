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

/**
 * Reading the stored settings of a PDF view.
 *
 * @package    mod_datalynx
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \datalynxview_pdf\view
 */
final class pdf_view_settings_test extends advanced_testcase {
    use mobile_test_trait;

    /**
     * Stored settings: base64 (current), plain serialized (older versions) and plain serialized
     * whose string lengths a charset conversion broke.
     *
     * @return array
     */
    public static function stored_settings_provider(): array {
        $template = '<p>Größe: ä</p>';
        $serialized = serialize((object) ['docname' => 'Portfolio', 'toc' => (object) ['template' => $template]]);
        $declared = 's:' . strlen($template) . ':"' . $template;
        $wronglength = str_replace($declared, 's:' . (strlen($template) + 6) . ':"' . $template, $serialized);
        return [
            'base64' => [base64_encode($serialized)],
            'plain' => [$serialized],
            'wrong string lengths' => [$wronglength],
        ];
    }

    /**
     * The settings are read, repaired where only their string lengths are wrong.
     *
     * @dataProvider stored_settings_provider
     * @param string $param1
     */
    public function test_stored_settings_are_read(string $param1): void {
        $view = $this->create_pdf_view($param1);

        $settings = $view->get_pdf_settings();

        $this->assertSame('Portfolio', $settings->docname);
        $this->assertSame('<p>Größe: ä</p>', $settings->toc->template);
    }

    /**
     * Truncated settings fall back to the defaults with a debugging message instead of failing
     * the page.
     */
    public function test_unreadable_settings_fall_back_to_defaults(): void {
        $view = $this->create_pdf_view(substr(serialize((object) ['docname' => 'Portfolio']), 0, -5));

        $this->assertDebuggingCalled();
        $settings = $view->get_pdf_settings();
        $this->assertSame('', $settings->docname);
        $this->assertSame('mm', $settings->unit);
    }

    /**
     * Objects other than stdClass in the stored settings are not instantiated.
     */
    public function test_settings_do_not_instantiate_classes(): void {
        $view = $this->create_pdf_view(base64_encode(serialize((object) ['docname' => new \moodle_url('/x.php')])));

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $view->get_pdf_settings()->docname);
    }

    /**
     * Create a PDF view with the given stored settings.
     *
     * @param string $param1
     * @return \datalynxview_pdf\view
     */
    private function create_pdf_view(string $param1): \datalynxview_pdf\view {
        $this->resetAfterTest();
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $viewid = $this->add_mobile_view($dlx, 'pdf', 'PDF', '', ['param1' => $param1]);

        return $dlx->get_view('pdf', $viewid, false);
    }
}
