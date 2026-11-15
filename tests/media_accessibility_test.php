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

/**
 * Pictures get an alt text and the PDF preview is an image with an alt text and no fixed width (#301).
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \datalynxfield_picture\renderer::display_file
 * @covers \datalynxfield_file\renderer::embed_pdf
 */
final class media_accessibility_test extends advanced_testcase {
    /** @var datalynx */
    private datalynx $dlx;

    /**
     * Create a datalynx instance.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id]);
        $this->dlx = new datalynx($instance->id);
    }

    /**
     * Create a field with one stored file and return the field and an entry pointing to it.
     *
     * @param string $type field type (file or picture)
     * @param string $filename file name
     * @param string $content file content
     * @param string $alt link/alt text (content1)
     * @return array [field, entry]
     */
    private function field_with_file(string $type, string $filename, string $content, string $alt = ''): array {
        global $DB;
        $fieldid = (int) $DB->insert_record('datalynx_fields', (object) [
            'dataid' => $this->dlx->id(), 'type' => $type, 'name' => $type . 'field', 'description' => '',
        ]);
        $contentid = (int) $DB->insert_record('datalynx_contents', (object) [
            'fieldid' => $fieldid, 'entryid' => 1, 'content' => 1, 'content1' => $alt,
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $this->dlx->context->id, 'component' => 'mod_datalynx', 'filearea' => 'content',
            'itemid' => $contentid, 'filepath' => '/', 'filename' => $filename,
        ], $content);
        $field = (new datalynx($this->dlx->id()))->get_field_from_id($fieldid, true);
        $entry = (object) [
            'id' => 1, "c{$fieldid}_id" => $contentid, "c{$fieldid}_content" => 1, "c{$fieldid}_content1" => $alt,
        ];
        return [$field, $entry];
    }

    /**
     * A 1x1 png.
     *
     * @return string
     */
    private function png(): string {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }

    /**
     * The picture uses its link text as alt text, and the file name when there is none.
     */
    public function test_picture_has_alt_text(): void {
        [$field, $entry] = $this->field_with_file('picture', 'receipt.png', $this->png(), 'Receipt & invoice');
        $html = $field->renderer()->render_display_mode($entry, []);
        $this->assertMatchesRegularExpression('~<img[^>]+alt="Receipt &amp; invoice"~', $html);

        [$field, $entry] = $this->field_with_file('picture', 'photo_2026.png', $this->png());
        $html = $field->renderer()->render_display_mode($entry, []);
        $this->assertMatchesRegularExpression('~<img[^>]+alt="photo_2026.png"~', $html);
    }

    /**
     * The PDF preview is one image with an alt text that points to the PDF, and has no fixed size.
     */
    public function test_pdf_preview_is_an_image_with_alt_text(): void {
        [$field, $entry] = $this->field_with_file('file', 'report.pdf', "%PDF-1.4\n%%EOF\n");
        $html = $field->renderer()->render_display_mode($entry, []);

        $alt = get_string('pdfpreviewalt', 'datalynxfield_file', 'report.pdf');
        $this->assertMatchesRegularExpression('~class="datalynx-pdf-embed"[^>]+role="img"~', $html);
        $this->assertStringContainsString('aria-label="' . s($alt) . '"', $html);
        $this->assertStringContainsString('report.pdf', $alt);
        $this->assertStringNotContainsString('1800px', $html);
        $this->assertStringNotContainsString('min-height', $html);
        $this->assertMatchesRegularExpression('~<a [^>]*class="btn btn-primary" href="[^"]+report.pdf"~', $html);
    }
}
