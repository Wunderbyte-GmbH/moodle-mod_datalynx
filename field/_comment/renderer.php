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
 * @subpackage _comment
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 */
class datalynxfield__comment_renderer extends datalynxfield_renderer {

    /**
     */
    public function replacements(array $tags = null, $entry = null, array $options = null) {
        global $CFG;

        $field = $this->_field;

        // No edit mode.
        $replacements = array_fill_keys($tags, '');

        // No edit mode for this field so just return html.
        if ($entry->id > 0 and !empty($CFG->usecomments)) {
            foreach ($tags as $tag) {
                switch (trim($tag, '@')) {
                    case '##comments:count##':
                        $options = array('count' => true);
                        $str = $this->display_browse($entry, $options);
                        break;
                    case '##comments:inline##':
                        $options = array('notoggle' => true, 'autostart' => true);
                        $str = $this->display_browse($entry, $options);
                        break;
                    case '##comments##':
                    case '##comments:add##':
                        $str = $this->display_browse($entry);
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
    public function display_browse($entry, $options = array()) {
        global $CFG;

        $df = $this->_field->df();
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

        $patterns = array();
        $patterns['##comments##'] = array(true, $cat);
        $patterns['##comments:count##'] = array(true, $cat);
        $patterns['##comments:inline##'] = array(true, $cat);
        $patterns['##comments:add##'] = array(false);

        return $patterns;
    }
}
