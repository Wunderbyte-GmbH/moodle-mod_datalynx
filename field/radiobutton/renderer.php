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
 * @subpackage radiobutton
 * @copyright 2014 Ivan Šakić
 * @copyright 2016 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . "/../select/renderer.php");

/**
 * Class datalynxfield_radiobutton_renderer Renderer for radiobutton field type
 */
class datalynxfield_radiobutton_renderer extends datalynxfield_select_renderer {

    /**
     *
     * @var datalynxfield_radiobutton
     */
    protected $_field = null;

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $menuoptions = $field->options_menu();
        $fieldname = "field_{$fieldid}_$entryid";
        $required = !empty($options['required']);
        $selected = !empty($entry->{"c{$fieldid}_content"}) ? (int) $entry->{"c{$fieldid}_content"} : 0;

        // Check for default value.
        if (!$selected && $defaultval = $field->get('param2')) {
            $selected = (int) array_search($defaultval, $menuoptions);
        }

        $separator = $field->separators[0]['chr'];
        $param2 = (int) $field->get('param2');
        if (isset($param2) && array_key_exists($param2, $field->separators)) {
            $separator = $field->separators[$param2]['chr'];
        }

        $elemgrp = array();
        foreach ($menuoptions as $id => $option) {
            $radio = &$mform->createElement('radio', $fieldname, '', $option, $id);
            if ($id == $selected) {
                $radio->setChecked(true);
            }
            $elemgrp[] = $radio;
        }

        $mform->addGroup($elemgrp, "{$fieldname}_group", null, $separator, false);

        $mform->setDefaults(array($fieldname => (int) $selected));

        if ($required) {
            $mform->addRule("{$fieldname}_group", null, 'required', null, 'client');
        }
    }
}
