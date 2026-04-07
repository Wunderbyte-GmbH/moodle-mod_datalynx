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
 * @package datalynxfield_comment
 * @subpackage _comment
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_comment;

use mod_datalynx\local\field\datalynxfield_renderer;
use stdClass;
use comment;



/**
 * Renderer for the internal comment field patterns.
 *
 * @package mod_datalynx
 * @subpackage _comment
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends datalynxfield_renderer {
    /**
     * Returns tag replacement pairs for the comment field patterns.
     *
     * @param array|null  $tags    The list of tag patterns to replace.
     * @param object|null $entry   The current entry object.
     * @param array|null  $options Rendering options.
     * @return array
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        global $CFG;

        $field = $this->field;

        // No edit mode.
        $replacements = array_fill_keys($tags, '');

        // No edit mode for this field so just return html.
        if ($entry->id > 0 && !empty($CFG->usecomments)) {
            foreach ($tags as $tag) {
                switch (trim($tag, '@')) {
                    case '##comments:count##':
                        $options = ['count' => true];
                        $str = $this->display_browse($entry, $options);
                        break;
                    case '##comments:inline##':
                        $options = ['notoggle' => true, 'autostart' => true];
                        $str = $this->display_browse($entry, $options);
                        break;
                    case '##comments##':
                    case '##comments:add##':
                        $str = $this->display_browse($entry);
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
     * Renders the comment widget HTML for browse mode.
     *
     * @param object $entry   The current entry.
     * @param array  $options Optional display options (e.g. count, notoggle, autostart).
     * @return string
     */
    public function display_browse($entry, $options = []) {
        global $CFG;

        $df = $this->field->df();
        $str = '';

        require_once("$CFG->dirroot/comment/lib.php");
        $cmt = new stdClass();
        $cmt->context = $df->context;
        $cmt->courseid = $df->course->id;
        $cmt->cm = $df->cm;
        $cmt->itemid = $entry->id;
        $cmt->component = 'mod_datalynx';
        $cmt->area = !empty($options['area']) ? $options['area'] : 'entry';
        $cmt->showcount = isset($options['showcount']) ? $options['showcount'] : true;

        if (!empty($options['count'])) {
            $comment = new comment($cmt);
            $str = $comment->count();
        } else {
            foreach ($options as $key => $val) {
                $cmt->$key = $val;
            }
            $comment = new comment($cmt);
            $str = $comment->output(true);
        }

        return $str;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $cat = get_string('comments', 'datalynx');

        $patterns = [];
        $patterns['##comments##'] = [true, $cat];
        $patterns['##comments:count##'] = [true, $cat];
        $patterns['##comments:inline##'] = [true, $cat];
        $patterns['##comments:add##'] = [false];

        return $patterns;
    }
}
