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

/**
 *
 * @package datalynxfield_file
 * @subpackage file
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_file;

use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use moodle_url;
use MoodleQuickForm;
use stdClass;
use stored_file;

/**
 * Renderer for the file field type.
 */
class renderer extends datalynxfield_renderer {
    /**
     * The file manager element is field_{fieldid}_{entryid}_filemanager.
     *
     * @param string $name
     * @return string
     */
    public function get_error_element(string $name): string {
        return $name . '_filemanager';
    }

    /**
     * Renders the edit mode form elements for this field.
     *
     * @param MoodleQuickForm $mform The Moodle form object.
     * @param stdClass $entry The entry object.
     * @param ?array $options Rendering options.
     * @return void
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, ?array $options = null): void {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        $fieldname = "field_{$fieldid}_{$entryid}";
        $fmoptions = [
                'subdirs' => 0,
                'maxbytes' => $field->get('param1'),
                'maxfiles' => $field->get('param2'),
                'accepted_types' => explode(',', $field->get('param3')),
        ];

        $draftitemid = file_get_submitted_draft_itemid("{$fieldname}_filemanager");

        file_prepare_draft_area(
            $draftitemid,
            $field->dlx()->context->id,
            'mod_datalynx',
            'content',
            $contentid,
            $fmoptions
        );

        // Add file manager element. It is labelled with the field name, unless a custom field
        // layout wraps the input (#input): that layout provides the label, and the internal field
        // name would otherwise appear next to it.
        $hascustomlayout = isset($options['template']) && strpos($options['template'], '#input') !== false;
        $mform->addElement(
            'filemanager',
            "{$fieldname}_filemanager",
            $hascustomlayout ? '' : $field->name(),
            ['aria-label' => format_string($field->name())],
            $fmoptions
        );
        $mform->setDefault("{$fieldname}_filemanager", $draftitemid);

        if (!empty($options['required'])) {
            $mform->addRule("{$fieldname}_filemanager", get_string('required'), 'required', null, 'client');
        }
    }


    /**
     * Render the field of type file.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = $entry->{"c{$fieldid}_content"} ?? null;
        $content2 = $entry->{"c{$fieldid}_content2"} ?? null;
        $contentid = $entry->{"c{$fieldid}_id"} ?? null;

        if (empty($content)) {
            return '';
        }

        // Resolve the requested output mode from either a field format ([[file:myformat]]) or the
        // legacy suffix flag ([[file:downloadcount]]).
        $format = $options['field_format'] ?? null;
        $mode = $format ? ($format->get_settings()['mode'] ?? '') : '';

        if (!empty($options['downloadcount']) || $mode === 'downloadcount') {
            return $content2;
        }

        $fs = get_file_storage();

        // Contentid is stored in itemid for lookup. This is usable with fieldgroups.
        $files = $fs->get_area_files(
            $field->dlx()->context->id,
            'mod_datalynx',
            'content',
            $contentid
        );
        if (!$files || !(count($files) > 1)) {
            return '';
        }

        $strfiles = [];
        foreach ($files as $file) {
            if (!$file->is_directory()) {
                $path = "/{$field->dlx()->context->id}/mod_datalynx/content/$contentid";
                // ToDo: Remove or implement altname.
                $altname = "";
                $strfiles[] = $this->display_file($file, $entryid, $path, $altname, $options);
            }
        }

        // For csv export we simply show link to first file.
        if (optional_param('exportcsv', '', PARAM_ALPHA)) {
            return $this->render_csv($strfiles);
        }

        return implode("<br />\n", $strfiles);
    }

    /**
     * The search element of a file field.
     *
     * In a view filter or a rule condition the criterion is "is empty" or "not empty", set with
     * the is/not control every field row has; there is no value to enter. A custom filter has no
     * is/not control, so it offers both states as one choice, which the filter manager turns
     * into the same criterion.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array [elements, separators]
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = ''): array {
        $fieldname = "f_{$i}_{$this->field->id()}";

        // Files are searched for being there or not by the custom filter (customfilter_controls);
        // other search forms take the operator alone.
        return [[$mform->createElement('static', $fieldname, '', get_string('empty', 'datalynx'))], null];
    }

    /**
     * Render file html.
     *
     * @param stored_file $file
     * @param int $entryid
     * @param string $path
     * @param string $altname
     * @param ?array $params
     * @return moodle_url|string|false
     */
    protected function display_file(
        stored_file $file,
        int $entryid,
        string $path,
        string $altname = '',
        ?array $params = null
    ): moodle_url|string|false {
        $field = $this->field;
        $fieldid = $field->id();
        $fieldname = "field_{$fieldid}_{$entryid}";
        $filename = $file->get_filename();
        $mimetype = $file->get_mimetype();
        $pluginfileurl = '/pluginfile.php';

        $format = $params['field_format'] ?? null;
        $mode = '';
        if ($format) {
            $settings = $format->get_settings();
            $mode = $settings['mode'] ?? '';
        }

        // Check for specific parameters/patterns first.
        if (!empty($params['url']) || $mode === 'url') {
            return moodle_url::make_file_url($pluginfileurl, "$path/$filename");
        } else if (!empty($params['size']) || $mode === 'size') {
            $bsize = $file->get_filesize();
            if ($bsize < 1000000) {
                $size = round($bsize / 1000, 1) . 'KB';
            } else {
                $size = round($bsize / 1000000, 1) . 'MB';
            }
            return $size;
        } else if (!empty($params['content']) || $mode === 'content') {
            return $file->get_content();
        } else if (!empty($params['download']) || $mode === 'download') {
            // A "download" field format must behave like the legacy [[field:download]] tag, i.e. link
            // through download.php to force a download. display_link() keys off $params['download'],
            // so make sure it is set when the format mode (rather than the legacy param) requested it.
            $params['download'] = 1;
            return $this->display_link($file, $path, $altname, $params);
        }

        // Embed PDF if it's a PDF file and no specific pattern was requested.
        if ($mimetype === 'application/pdf') {
            // PDF document.
            $moodleurl = moodle_url::make_file_url($pluginfileurl, "$path/$filename");
            return $this->embed_pdf($moodleurl->out(), $fieldname, $filename);
        }

        // For all other file types, display as link.
        return $this->display_link($file, $path, $altname, $params);
    }

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Returns the pdf embedding html: a download link and the pages rendered as images by pdf.js.
     *
     * The rendering is an image of the pages, so screen readers get an alt text that says so and
     * points to the PDF itself for the content. It fits the column instead of a fixed width.
     *
     * @param string $fullurl
     * @param string $fieldname
     * @param string $filename name of the PDF file
     * @return string html
     */
    protected function embed_pdf(string $fullurl, string $fieldname, string $filename = ''): string {
        global $PAGE;
        $customscale = $this->field->get('param4');
        if (empty($customscale)) {
            $customscale = 1;
        }
        $PAGE->requires->js_call_amd('mod_datalynx/pdfembed', 'init');

        $link = html_writer::link(
            $fullurl,
            get_string('download', 'core_repository') . ' ' . get_string('application/pdf', 'core_mimetypes'),
            ['target' => '_blank', 'class' => 'btn btn-primary']
        );
        return html_writer::div($link) . '<br>' . html_writer::div('', 'datalynx-pdf-embed', [
            'id' => $fieldname,
            'role' => 'img',
            'aria-label' => get_string('pdfpreviewalt', 'datalynxfield_file', $filename),
            'data-pdf-url' => $fullurl,
            'data-custom-scale' => $customscale,
        ]);
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Render a link.
     * @param stored_file $file
     * @param string $path
     * @param string $altname
     * @param ?array $params
     * @return string
     */
    protected function display_link($file, $path, $altname, $params = null): string {
        global $OUTPUT;

        $filename = $file->get_filename();
        $mimetype = $file->get_mimetype();
        $displayname = $altname ?: $filename;
        $fileicon = html_writer::empty_tag(
            'img',
            ['src' => $OUTPUT->image_url(file_mimetype_icon($mimetype)),
            'alt' => $mimetype,
            'height' => 16,
            'width' => 16]
        );

        if (!empty($params['download'])) {
            [, $context, , , $contentid] = explode('/', $path);
            $url = new moodle_url(
                "/mod/datalynx/field/file/download.php",
                ['cid' => $contentid, 'context' => $context, 'file' => $filename]
            );
            return html_writer::link($url, "$fileicon&nbsp;$displayname", ['target' => '_blank']);
        } else {
            $url = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
        }

        return html_writer::link($url, "$fileicon&nbsp;$displayname");
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Returns pluginfile URL patterns supported by this field.
     */
    public function pluginfile_patterns(): array {
        return ["[[{$this->field->name()}]]"];
    }

    /**
     * Array of patterns this field supports.
     *
     * Field formats (Manage > Fields > Field Formats) are the surfaced way to apply display modifiers:
     * parent::patterns() contributes [[fieldname]] plus a visible [[fieldname:formatname]] tag for each
     * file format defined for this instance. The hardcoded legacy [[fieldname:suffix]] modifiers stay
     * recognised at render time (see datalynxfield_renderer::replacements() and self::display_file())
     * for backward compatibility but are hidden from the menu via add_legacy_suffix_patterns().
     */
    protected function patterns(): array {
        $patterns = parent::patterns();

        return $this->add_legacy_suffix_patterns(
            $patterns,
            ['url', 'alt', 'size', 'content', 'download', 'downloadcount']
        );
    }

    /**
     * Returns comma seperated list of urls in this entry.
     *
     * @param array $strfiles Array of HTML strings containing file links.
     * @return string Comma-separated list of URLs.
     */
    public function render_csv($strfiles): string {
        $regex = '/https?\:\/\/[^\" ]+/i';
        $matches = [];
        foreach ($strfiles as $strfile) {
            preg_match($regex, $strfile, $match);
            $matches[] = $match[0];
        }

        return implode(",", $matches);
    }

    /**
     * Files are searched for being there or not.
     *
     * @return string
     */
    public function get_customfilter_control(): string {
        return 'presence';
    }
}
