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
 * @subpackage _entry
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield__entry_renderer extends datalynxfield_renderer {

    /**
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        $manageable = !empty($options['manage']) ? $options['manage'] : false;
        $manageable = $manageable && ((isset($entry->status) &&
                                $entry->status != datalynxfield__status::STATUS_FINAL_SUBMISSION) ||
                        has_capability('mod/datalynx:manageentries', $this->_field->df->context));

        // No edit mode.
        $replacements = array();
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
                    case '##more##':
                        $str = $this->display_more($entry);
                        break;
                    case '##anchor##':
                        $str = html_writer::tag('a', '', array('name' => $entry->id));
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
                $replacements[$tag] = array('html', $str);
            }
        }
        return $replacements;
    }

    /**
     */
    protected function display_more($entry, $href = false) {
        global $OUTPUT;

        $field = $this->_field;
        $params = array('eids' => $entry->id);
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
     */
    protected function display_edit($entry) {
        global $OUTPUT;

        $field = $this->_field;
        $params = array('editentries' => $entry->id, 'sesskey' => sesskey(),
                'sourceview' => $this->_field->df()->get_current_view()->id());
        $url = new moodle_url($entry->baseurl, $params);
        if ($field->df()->data->singleedit) {
            $url->param('view', $field->df()->data->singleedit);
            $url->param('eids', $entry->id);
        }
        $str = get_string('edit');
        return html_writer::link($url->out(false), $OUTPUT->pix_icon('t/edit', $str));
    }

    /**
     */
    protected function display_duplicate($entry) {
        global $OUTPUT;

        $field = $this->_field;
        $params = array('duplicate' => $entry->id, 'sesskey' => sesskey(),
                'sourceview' => $this->_field->df()->get_current_view()->id());
        $url = new moodle_url($entry->baseurl, $params);
        if ($field->df()->data->singleedit) {
            $url->param('view', $field->df()->data->singleedit);
        }
        $str = get_string('copy');
        return html_writer::link($url->out(false), $OUTPUT->pix_icon('t/copy', $str));
    }

    /**
     */
    protected function display_delete($entry) {
        global $OUTPUT;

        $field = $this->_field;
        $params = array('delete' => $entry->id, 'sesskey' => sesskey(),
                'sourceview' => $this->_field->df()->get_current_view()->id());
        $url = new moodle_url($entry->baseurl, $params);
        $str = get_string('delete');
        return html_writer::link($url->out(false), $OUTPUT->pix_icon('t/delete', $str));
    }

    /**
     */
    protected function display_export($entry) {
        global $CFG, $OUTPUT;

        if (!$CFG->enableportfolios) {
            return '';
        }

        $str = '';
        $canexportentry = $this->_field->df()->user_can_export_entry($entry);
        if ($canexportentry) {
            $url = new moodle_url($entry->baseurl,
                    array('export' => $entry->id, 'sesskey' => sesskey()));
            $strexport = get_string('export', 'datalynx');
            return html_writer::link($url, $OUTPUT->pix_icon('t/portfolioadd', $strexport));
        }
        return $str;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $patterns = array();

        // Actions.
        $actions = get_string('actions', 'datalynx');
        $patterns["##edit##"] = array(true, $actions);
        $patterns["##delete##"] = array(true, $actions);
        $patterns["##select##"] = array(true, $actions);
        $patterns["##export##"] = array(true, $actions);
        $patterns["##duplicate##"] = array(true, $actions);

        // Reference.
        $reference = get_string('reference', 'datalynx');
        $patterns["##anchor##"] = array(true, $reference);
        $patterns["##more##"] = array(true, $reference);

        // Entryinfo.
        $entryinfo = get_string('entryinfo', 'datalynx');
        $patterns["##entryid##"] = array(true, $entryinfo);

        return $patterns;
    }
}
