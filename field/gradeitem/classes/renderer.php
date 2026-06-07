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
 * @package datalynxfield_gradeitem
 * @subpackage gradeitem
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace datalynxfield_gradeitem;

use mod_datalynx\local\field\datalynxfield_renderer;
use MoodleQuickForm;
use stdClass;

/**
 * Datalynx gradeitem field renderer class.
 */
class renderer extends datalynxfield_renderer {
    /**
     * Render the field in display mode.
     *
     * @param stdClass $entry The entry object.
     * @param array $options Additional options.
     * @return string The rendered content.
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $field = $this->field;
        $fieldid = $field->id();

        if (!isset($entry->{"c{$fieldid}_content"})) {
            return '';
        }

        $number = (float) $entry->{"c{$fieldid}_content"};
        $fieldformat = $options['field_format'] ?? null;
        if ($fieldformat) {
            $decimals = $fieldformat->get_setting('decimals');
        } else {
            $decimals = 2;
        }
        if ($decimals !== null && $decimals !== '') {
            $decimals = (int) $decimals;
            $str = sprintf("%4.{$decimals}f", $number);
        } else {
            $str = (float) $number;
        }
        return $str;
    }

    /**
     * Render the field in edit mode.
     *
     * @param MoodleQuickForm $mform The form object.
     * @param stdClass $entry The entry object.
     * @param array $options Additional options.
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        // Not editable.
    }
}
