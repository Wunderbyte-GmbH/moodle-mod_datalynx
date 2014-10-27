<?php
// This file is part of Moodle - http://moodle.org/.
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
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * @package datalynxfield
 * @subpackage text
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");

/**
 *
 */
class datalynxfield_text_renderer extends datalynxfield_renderer {

    /**
     *
     */
    protected function replacements(array $tags = null, $entry = null, array $options = null) {
        $field = $this->_field;
        $fieldname = $field->name();
        $edit = !empty($options['edit']) ? $options['edit'] : false;

        $replacements = array();
        $tags = $this->add_clean_pattern_keys($tags);
        foreach ($tags as $tag => $cleantag) {
            $replacements[$tag] = '';
            if ($edit) {
                $replacements[$tag] = array('', array(array($this,'display_edit'), array($entry, array('required' => $options['required']))));
            } else {
                $replacements[$tag] = array('html', $this->display_browse($entry));
            }
        }

        return $replacements;
    }

    /**
     *
     */
    public function display_edit(&$mform, $entry, array $options = null) {
        $mform->addElement('html', '<div data-field-type="' . $this->_field->type . '" data-field-name="' . $this->_field->field->name . '">');
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;

        $content = '';
        if ($entryid > 0 and !empty($entry->{"c{$fieldid}_content"})){
            $content = $entry->{"c{$fieldid}_content"};
        }

        $fieldattr = array();

        if ($field->get('param2')) {
            $fieldattr['style'] = 'width:'. s($field->get('param2')). s($field->get('param3')). ';';
        }

        if ($field->get('param4')) {
            $fieldattr['class'] = s($field->get('param4'));
        }

        $fieldname = "field_{$fieldid}_{$entryid}";
        $mform->addElement('text', $fieldname, null, $fieldattr);
        $mform->setType($fieldname, PARAM_TEXT);
        $mform->setDefault($fieldname, $content);
        $required = !empty($options['required']);
        if ($required) {
            $mform->addRule($fieldname, null, 'required', null, 'client');
        }
        // format rule
        if ($format = $field->get('param4')) {
            $mform->addRule($fieldname, null, $format, null, 'client');
            // Adjust type
            switch($format) {
                case 'alphanumeric': $mform->setType($fieldname, PARAM_ALPHANUM); break;
                case 'lettersonly': $mform->setType($fieldname, PARAM_ALPHA); break;
                case 'numeric': $mform->setType($fieldname, PARAM_INT); break;
                case 'email': $mform->setType($fieldname, PARAM_EMAIL); break;            
            }
        }
        // length rule
        if ($length = $field->get('param5')) {
            ($min = $field->get('param6')) or ($min = 0);
            ($max = $field->get('param7')) or ($max = 64);
            
            switch ($length) {
                case 'minlength': $val = $min; break;
                case 'maxlength': $val = $max; break;
                case 'rangelength': $val = array($min, $max); break;
            }                
            $mform->addRule($fieldname, null, $length, $val, 'client');
        }

        $mform->addElement('html', '</div>');
    }

    /**
     *
     */
    protected function display_browse($entry, $params = null) {
        $field = $this->_field;
        $fieldid = $field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};

            $options = new object();
            $options->para = false;

            $format = FORMAT_PLAIN;
            if ($field->get('param1') == '1') {  // We are autolinking this field, so disable linking within us
                $content = '<span class="nolink">'. $content .'</span>';
                $format = FORMAT_PLAIN;
                $options->filter=false;
            }

            $str = format_text($content, $format, $options);
        } else {
            $str = '';
        }

        return $str;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);

       return $patterns;
    }

    /**
     * Array of patterns this field supports
     */
    protected function supports_rules() {
        return array(
            self::RULE_REQUIRED,
            self::RULE_NOEDIT,
        );
    }
}
