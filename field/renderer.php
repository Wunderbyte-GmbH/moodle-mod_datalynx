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
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die;

/**
 * Base class for field patterns
 */
abstract class datalynxfield_renderer {

    const PATTERN_SHOW_IN_MENU = 0;
    const PATTERN_CATEGORY = 1;

    const RULE_REQUIRED = '*';
    const RULE_HIDDEN = '^';
    const RULE_NOEDIT = '!';

    /**
     * @var datalynxfield_base
     */
    protected $_field = null;

    /**
     * Constructor
     */
    public function __construct(&$field) {
        $this->_field = $field;
    }

    /**
     * Search and collate field patterns that occur in given text
     *
     * @param string Text that may contain field patterns
     * @return array Field patterns found in the text
     */
    public function search($text) {
        $fieldid = $this->_field->field->id;
        $fieldname = $this->_field->name();

        $found = array();


        $matches = array();
        if (preg_match_all("/\[\[$fieldname(?:\|(?:[^\]]+))?\]\]/", $text, $matches)) {
            $found = array_merge($found, $matches[0]);
        }

        // Legacy code below
        
        // Capture label patterns
        if (strpos($text, "[[$fieldname@]]") !== false and !empty($this->_field->field->label)) {
            $found[] = "[[$fieldname]]";
            $found[] = "[[$fieldname@]]";
            
            $text = str_replace("[[$fieldname@]]", $this->_field->field->label, $text);
        }
        
        // Search and collate field patterns
        $patterns = array_keys($this->patterns());
        $wrapopen = is_numeric($fieldid) && $fieldid > 0 ? '\[\[' : '##';
        $wrapclose = is_numeric($fieldid) && $fieldid > 0 ? '\]\]' : '##';
        $labelpattern = false;
        if ($rules = implode('', $this->supports_rules())) {
            // Patterns may have rule prefix
            foreach ($patterns as $pattern) {
                $pattern = trim($pattern, '[]#');
                $pattern = $wrapopen. "[$rules]*$pattern". $wrapclose;
                preg_match_all("/$pattern/", $text, $matches);
                if (!empty($matches[0])) {
                    $found = array_merge($found, $matches[0]);
                }
            }
        } else {
            foreach ($patterns as $pattern) {
                if (strpos($text, $pattern) !== false) {
                    $found[] = $pattern;
                }
            }
        }

        return array_unique($found);
    }

    /**
     * @return string characters of supported rulesCleans a pattern from auxiliary indicators (e.g. * for required)
     */
    protected function supports_rules() {
        return array();
    }

    /**
     * Cleans a pattern from auxiliary indicators (e.g. * for required)
     */
    public function add_clean_pattern_keys(array $patterns) {
        $keypatterns = array();
        foreach ($patterns as $pattern) {
            $keypatterns[$pattern] = str_replace($this->supports_rules(), '', $pattern);
        }
        return $keypatterns;
    }

    /**
     *
     */
    public function pluginfile_patterns() {
        return array();
    }

    /**
     *
     */
    public function display_search(&$mform, $i = 0, $value = '') {
        /* @var $mform MoodleQuickForm */
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";
        
        $arr = array();
        $arr[] = &$mform->createElement('text', $fieldname, null, array('size'=>'32'));
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');
        
        return array($arr, null);
    }

    /**
     *
     */
    public final function get_menu($showall = false) {
        // the default menu category for fields
        $patternsmenu = array();
        foreach ($this->patterns() as $tag => $pattern) {
            if ($showall or $pattern[self::PATTERN_SHOW_IN_MENU]) {
                // which category
                if (!empty($pattern[self::PATTERN_CATEGORY])) {
                    $cat = $pattern[self::PATTERN_CATEGORY];
                } else {
                    $cat = get_string('fields', 'datalynx');
                }
                // prepare array
                if (!isset($patternsmenu[$cat])) {
                    $patternsmenu[$cat] = array($cat => array());
                }
                // add tag
                $patternsmenu[$cat][$cat][$tag] = $tag;
            }
        }
        return $patternsmenu;
    }

    /**
     *
     */
    public function get_replacements(array $tags, stdClass $entry, array $options) {
        $replacements = $this->replacements($tags, $entry, $options);
        return $this->apply_behavior_options($replacements, $options);
    }

    /**
     * Handles visibility,
     * @param array $replacements
     * @param array $options
     * @return array
     */
    private function apply_behavior_options(array $replacements, array $options) {
        $new = array();
        foreach ($replacements as $tag => $replacement) {
            if ($options['edit'] && !$options['editable']) {
                if (!$this->_field->is_internal()) {
                    $replacement = array('html', '<em>[[field not editable]]</em>');
                } else {
                    $replacement = array('html', '');
                }

            }

            if (!$options['visible'] && !$this->_field->is_internal()) {
                $replacement = array('html', '<em>[[field not visible]]</em>');
            }

            $new[$tag] = $replacement;
        }
        return $new;
    }

    /**
     *
     */
    public function validate_data($entryid, $tags, $data) {
        return array();
    }

    /**
     * Returns array of replacements for the field patterns
     * The label pattern should always be first where applicable
     * so that it is processed first in view templates 
     * so that in turn patterns it may contain could be processed.
     *
     * @return array pattern => array(visible in menu, category) -> WRONG WRONG WRONG
     */
    abstract protected function replacements(array $tags = null, $entry = null, array $options = null);

    /**
     * Array of patterns this field supports
     * The label pattern should always be first where applicable
     * so that it is processed first in view templates 
     * so that in turn patterns it may contain could be processed.
     *
     * @return array pattern => array(visible in menu, category) 
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = array();
        $patterns["[[$fieldname]]"] = array(true);

        return $patterns;
    }
}
