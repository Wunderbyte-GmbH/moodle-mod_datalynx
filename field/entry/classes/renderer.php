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
 * @package datalynxfield_entry
 * @subpackage _entry
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_entry;

use datalynxfield_status\field as datalynxfield_status;
use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use moodle_url;

// phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
/**
 * Renderer for the internal entry reference field patterns.
 *
 * @package mod_datalynx
 * @subpackage _entry
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends datalynxfield_renderer {
    /**
     * Returns tag replacement pairs for the entry field patterns.
     *
     * @param ?array  $tags    The list of tag patterns to replace.
     * @param ?object $entry   The current entry object.
     * @param ?array  $options Rendering options.
     * @return array
     */
    public function replacements(?array $tags = null, $entry = null, ?array $options = null) {
        $manageable = !empty($options['manage']) ? $options['manage'] : false;
        $manageable = $manageable && ((isset($entry->status) &&
                                $entry->status != datalynxfield_status::STATUS_FINAL_SUBMISSION) ||
                        has_capability('mod/datalynx:manageentries', $this->field->df->context));

        // No edit mode.
        $replacements = [];
        foreach ($tags as $tag) {
            // New entry displays nothing.
            if ($entry->id < 0) {
                $replacements[$tag] = '';

                // No edit mode for this field so just return html.
            } else {
                switch (trim($tag, '@')) {
                    // Reference.
                    case '##entryid##':
                        $str = $entry->id;
                        break;
                    case '##entryidzerofill##':
                        $str = str_pad($entry->id, 4, 0, STR_PAD_LEFT);
                        break;
                    case '##coursevisible##':
                        $str = $this->field->df->course->visible ? get_string('coursevisible', 'datalynx') : '';
                        break;
                    case '##more##':
                        $str = $this->display_more($entry);
                        break;
                    case '##anchor##':
                        $str = html_writer::tag('a', '', ['name' => $entry->id]);
                        break;
                    // Actions.
                    case '##select##':
                        $str = html_writer::checkbox('entryselector', $entry->id, false);
                        break;
                    case '##edit##':
                        $str = $manageable ? $this->display_edit($entry) : '';
                        break;
                    case '##delete##':
                        $str = $manageable ? $this->display_delete($entry) : '';
                        break;
                    case '##export##':
                        $str = $this->display_export($entry);
                        break;
                    case '##duplicate##':
                        $str = $manageable ? $this->display_duplicate($entry) : '';
                        break;
                    default:
                        $str = '';
                }
                $replacements[$tag] = ['html', $str];
            }
        }
        return $replacements;
    }

    /**
     * Renders the "more" (single-entry detail) link for an entry.
     *
     * @param object $entry The current entry.
     * @param bool   $href  If true returns the URL string instead of a link element.
     * @return string
     */
    protected function display_more($entry, $href = false) {
        global $OUTPUT;

        $field = $this->field;
        $params = ['eids' => $entry->id];
        $url = new moodle_url($entry->baseurl, $params);
        if ($field->df()->data->singleview) {
            $url->param('ret', $url->param('view'));
            $url->param('view', $field->df()->data->singleview);
        }
        $str = get_string('more', 'datalynx');
        if (!$href) {
            return html_writer::link($url->out(false), $OUTPUT->pix_icon('i/search', $str));
        } else {
            return $url->out(false);
        }
    }

    /**
     * Renders the edit action link/button for an entry.
     *
     * @param object $entry The current entry.
     * @return string
     */
    protected function display_edit($entry) {
        global $OUTPUT;

        $field = $this->field;
        $params = ['editentries' => $entry->id, 'sesskey' => sesskey(),
                'sourceview' => $this->field->df()->get_current_view()->id()];
        $url = new moodle_url($entry->baseurl, $params);
        if ($field->df()->data->singleedit) {
            $url->param('view', $field->df()->data->singleedit);
            $url->param('eids', $entry->id);
        }
        $str = get_string('edit');

        // In case we serve the app show a nice button for mobile devices.
        if (WS_SERVER) {
            $cmid = $field->df()->cm->id;
            $courseid = $field->df()->cm->course;
            $args = "[args]='{entry: $entry->id, action: \"edit\", cmid: $cmid, courseid: $courseid }'";
            return "<button ion-button core-site-plugins-new-content title='editbutton'
                component='mod_datalynx' method='mobile_course_view' $args>$str</button>";
        }

        return html_writer::link($url->out(false), $OUTPUT->pix_icon('t/edit', $str));
    }

    /**
     * Renders the duplicate action link for an entry.
     *
     * @param object $entry The current entry.
     * @return string
     */
    protected function display_duplicate($entry) {
        global $OUTPUT;

        $field = $this->field;
        $params = ['duplicate' => $entry->id, 'sesskey' => sesskey(),
                'sourceview' => $this->field->df()->get_current_view()->id()];
        $url = new moodle_url($entry->baseurl, $params);
        if ($field->df()->data->singleedit) {
            $url->param('view', $field->df()->data->singleedit);
        }
        $str = get_string('duplicate');
        return html_writer::link($url->out(false), $str . ' ' . $OUTPUT->pix_icon('t/copy', $str));
    }

    /**
     * Renders the delete action link/button for an entry.
     *
     * @param object $entry The current entry.
     * @return string
     */
    protected function display_delete($entry) {
        global $OUTPUT;

        $field = $this->field;
        $params = ['delete' => $entry->id, 'sesskey' => sesskey(),
                'sourceview' => $this->field->df()->get_current_view()->id()];
        $url = new moodle_url($entry->baseurl, $params);
        $str = get_string('delete');

        // In case we serve the app show a nice button for mobile devices.
        if (WS_SERVER) {
            $cmid = $field->df()->cm->id;
            $courseid = $field->df()->cm->course;
            $args = "[args]='{entry: $entry->id, action: \"delete\", cmid: $cmid, courseid: $courseid }'";
            return "<button ion-button core-site-plugins-new-content title='deletebutton'
                component='mod_datalynx' method='mobile_course_view' $args>$str</button>";
        }

        return html_writer::link($url->out(false), $OUTPUT->pix_icon('t/delete', $str));
    }

    /**
     * Renders the portfolio export link for an entry.
     *
     * @param object $entry The current entry.
     * @return string
     */
    protected function display_export($entry) {
        global $OUTPUT, $CFG;

        if (!$CFG->enableportfolios) {
            return '';
        }

        $str = '';
        $canexportentry = $this->field->df()->user_can_export_entry($entry);
        if ($canexportentry) {
            $url = new moodle_url(
                $entry->baseurl,
                ['export' => $entry->id, 'sesskey' => sesskey()]
            );
            $strexport = get_string('export', 'datalynx');
            return html_writer::link($url, $OUTPUT->pix_icon('t/portfolioadd', $strexport));
        }
        return $str;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $patterns = [];

        // Actions.
        $actions = get_string('actions', 'datalynx');
        $patterns["##edit##"] = [true, $actions];
        $patterns["##delete##"] = [true, $actions];
        $patterns["##select##"] = [true, $actions];
        $patterns["##export##"] = [true, $actions];
        $patterns["##duplicate##"] = [true, $actions];

        // Reference.
        $reference = get_string('reference', 'datalynx');
        $patterns["##anchor##"] = [true, $reference];
        $patterns["##more##"] = [true, $reference];

        // Entryinfo.
        $entryinfo = get_string('entryinfo', 'datalynx');
        $patterns["##entryid##"] = [true, $entryinfo];
        // Entry id with prepended zeroes.
        $patterns["##entryidzerofill##"] = [true, $entryinfo];
        // Display if course is visible to students.
        $patterns["##coursevisible##"] = [true, $entryinfo];

        return $patterns;
    }
}
// phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal
