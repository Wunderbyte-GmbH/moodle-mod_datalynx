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
 * @package datalynxfield
 * @subpackage picture
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/file/renderer.php");

/**
 */
class datalynxfield_picture_renderer extends datalynxfield_file_renderer {

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options = null) {
        $field = $this->_field;
        $fieldid = $field->id();

        $entryid = $entry->id;

        // If we see a 0 in content there are no files stored. Create new draft area.
        $content = isset($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : null;
        if ($content == 0 || !isset($entry->{"c{$fieldid}_id"})) {
            $contentid = null;
        } else {
            $contentid = $entry->{"c{$fieldid}_id"};
        }

        $fieldname = "field_{$fieldid}_{$entryid}";
        $fmoptions = array('subdirs' => 0, 'maxbytes' => $field->get('param1'),
                'maxfiles' => $field->get('param2'),
                'accepted_types' => explode(',', $field->get('param3')));

        $draftitemid = file_get_submitted_draft_itemid("{$fieldname}_filemanager");
        file_prepare_draft_area($draftitemid, $field->df()->context->id, 'mod_datalynx', 'content',
                $contentid, $fmoptions);

        // For behat testing: Much, much better to use the official step there than a bunch of very volatile js/css lines.
        $label = $field->df->name() == "Datalynx Test Instance" ? "Picture" : "";
        // File manager.
        $mform->addElement('filemanager', "{$fieldname}_filemanager", $label, null, $fmoptions);
        $mform->setDefault("{$fieldname}_filemanager", $draftitemid);
        $required = !empty($options['required']);
        if ($required) {
            $mform->addRule("{$fieldname}_filemanager", null, 'required', null, 'client');
        }
    }

    public function render_display_mode(stdClass $entry, array $params) {
        global $CFG, $PAGE;

        $PAGE->requires->js_call_amd('mod_datalynx/zoomable', 'init');

        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = isset($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : null;
        $content1 = isset($entry->{"c{$fieldid}_content1"}) ? $entry->{"c{$fieldid}_content1"} : null;
        $content2 = isset($entry->{"c{$fieldid}_content2"}) ? $entry->{"c{$fieldid}_content2"} : null;
        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;

        if (empty($content)) {
            return '';
        }

        if (!empty($params['downloadcount'])) {
            return $content2;
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files($field->df()->context->id, 'mod_datalynx', 'content', $contentid);

        // If we see no file attached we are done here.
        if (!$files or !(count($files) > 1)) {
            return '';
        }

        $altname = empty($content1) ? '' : s($content1);

        if (!empty($params['alt'])) {
            return $altname;
        }

        $strfiles = array();
        foreach ($files as $file) {
            if (!$file->is_directory()) {

                $filename = $file->get_filename();
                $filenameinfo = pathinfo($filename);
                $path = "/{$field->df()->context->id}/mod_datalynx/content/$contentid";

                if (strpos($filename, 'thumb_') === false) {
                    $strfiles[] = $this->display_file($file, $path, $altname, $params);
                }
            }
        }

        // For csv export we simply show link to first file.
        if ($exportcsv = optional_param('exportcsv', '', PARAM_ALPHA)) {
            return $this->render_csv($strfiles);
        }

        return implode("<br />\n", $strfiles);
    }

    /**
     */
    public function pluginfile_patterns() {
        $fieldname = $this->_field->name();
        return array("[[{$fieldname}]]", "[[{$fieldname}:thumb]]", "[[{$fieldname}:linked]]",
                "[[{$fieldname}:lightbox]]");
    }

    protected function display_file($file, $path, $altname, $params = null) {
        $field = $this->_field;

        $imgattr = array('style' => array());
        if (isset($params['lightbox']) && $params['lightbox']) {
            $imgattr['class'] = 'zoomable';
        }

        // TODO: This fails when I upload something that looks like an image but is not.
        if ($file->is_valid_image()) {
            $filename = $file->get_filename();
            $pluginfileurl = new moodle_url('/pluginfile.php');
            $imgpath = moodle_url::make_file_url($pluginfileurl, "$path/$filename");

            if (isset($params['thumb'])) {
                $thumbpath = moodle_url::make_file_url($pluginfileurl, "$path/thumb_$filename");
                $thumbpath = str_replace('mod_datalynx/content/', 'mod_datalynx/thumb/', $thumbpath);
                $imgattr['style'] = implode(';', $imgattr['style']);
                $imgattr['src'] = $thumbpath;
                $thumb = html_writer::empty_tag('img', $imgattr);

                if (isset($params['linked'])) {
                    return html_writer::link($imgpath, $thumb);
                } else {
                    return $thumb;
                }
            } else {
                // The picture's display dimension may be set in the field.
                if ($field->get('param4')) {
                    $imgattr['style'][] = 'width:' . s($field->get('param4')) . s($field->get('param6'));
                }
                if ($field->get('param5')) {
                    $imgattr['style'][] = 'height:' . s($field->get('param5')) . s($field->get('param6'));
                }

                $imgattr['src'] = $imgpath;
                $imgattr['style'] = implode(';', $imgattr['style']);
                $img = html_writer::empty_tag('img', $imgattr);
                if (isset($params['linked'])) {
                    return html_writer::link($imgpath, $img, array('target' => '_blank'));
                } else {
                    return $img;
                }
            }
            return '';
        }
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[{$fieldname}:linked]]"] = array(true);
        $patterns["[[{$fieldname}:tn]]"] = array(false);
        $patterns["[[{$fieldname}:thumb]]"] = array(true);
        $patterns["[[{$fieldname}:tn-linked]]"] = array(false);
        $patterns["[[{$fieldname}:lightbox]]"] = array(true);

        return $patterns;
    }
}
