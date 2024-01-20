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
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../behavior/behavior.php');
require_once(dirname(__FILE__) . '/../renderer/renderer.php');

/**
 * Base class for field patterns
 */
abstract class datalynxfield_renderer {

    const PATTERN_SHOW_IN_MENU = 0;

    const PATTERN_CATEGORY = 1;

    protected static $defaultoptions = array('manage' => false, 'visible' => false, 'edit' => false,
            'editable' => false, 'disabled' => false, 'required' => false, 'internal' => false
    );

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
     * @param string $text text that may contain field patterns
     * @return array Field patterns found in the text
     */
    public function search($text) {
        $found = array();

        $matches = array();
        $fieldname = preg_quote($this->_field->name(), '/');
        if (preg_match_all("/\[\[$fieldname(?:\|(?:[^\]]+))?\]\](?:@)?/", $text, $matches)) {
            $found = array_merge($found, $matches[0]);
        }

        $patterns = array_keys($this->patterns());
        foreach ($patterns as $pattern) {
            if (strpos($pattern, '##') === 0) {
                $strippedpattern = preg_quote(str_replace('##', '', $pattern), '/');
                if (preg_match_all("/##$strippedpattern##(?:@)?/", $text, $matches)) {
                    $found = array_merge($found, $matches[0]);
                }
                if (strpos($text, $pattern) !== false) {
                    $found[] = $pattern;
                }
            } else {
                $strippedpattern = preg_quote(str_replace(['[[', ']]'], ['', ''], $pattern), '/');
                if (preg_match_all("/\[\[$strippedpattern(?:\|(?:[^\]]+))?\]\](?:@)?/", $text,
                        $matches)) {
                    $found = array_merge($found, $matches[0]);
                }
            }
        }

        return array_unique($found);
    }

    /**
     * Returns array of replacements for the field patterns
     * The label pattern should always be first where applicable
     * so that it is processed first in view templates
     * so that in turn patterns it may contain could be processed.
     *
     * @param array $tags
     * @param stdClass $entry
     * @param array $options
     * @return array
     */
    public function replacements(array $tags = null, stdClass $entry = null, array $options = null) {
        $replacements = array();
        foreach ($tags as $tag) {
            $currentoptions = array_merge(self::$defaultoptions, $options);
            list($fieldname, $behavior, $renderer) = $this->process_tag($tag);
            // Variable $field datalynxfield_base
            // Variable $behavior datalynx_field_behavior
            // Variable $renderer datalynx_field_renderer.

            $splitfieldname = explode(':', $fieldname);
            if (isset($splitfieldname[1])) {
                $currentoptions[$splitfieldname[1]] = true;
            }

            $currentoptions['visible'] = $behavior->is_visible_to_user($entry);
            $currentoptions['editable'] = $behavior->is_editable_by_user();
            $currentoptions['required'] = $behavior->is_required();
            $currentoptions['internal'] = $this->_field->is_internal();

            if (!$currentoptions['visible']) {
                // NOT VISIBLE ===.
                if ($renderer->get_not_visible_template() === $renderer::NOT_VISIBLE_SHOW_NOTHING) {
                    $replacements[$tag] = array('html', '');
                } else {
                    $replacements[$tag] = array('html', $renderer->get_not_visible_template());
                }
            } else {
                // VISIBLE ===.
                if ($currentoptions['edit']) {
                    // EDIT MODE ===.
                    if (!$currentoptions['editable']) {
                        // NOT EDITABLE ===.
                        if ($renderer->get_not_editable_template() === $renderer::NOT_EDITABLE_SHOW_NOTHING
                        ) {
                            $replacements[$tag] = array('html', '');
                        } else {
                            if ($renderer->get_not_editable_template() === $renderer::NOT_EDITABLE_SHOW_AS_DISPLAY_MODE
                            ) {
                                $currentoptions['template'] = $renderer->get_display_template();
                                $currentoptions['value'] = $this->render_display_mode($entry, $currentoptions);
                                $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                            } else {
                                $replacements[$tag] = array('html', $renderer->get_not_editable_template());
                            }
                        }
                    } else {
                        // EDITABLE ===.
                        if ($renderer->get_edit_template() === $renderer::EDIT_MODE_TEMPLATE_NONE) {
                            $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                        } else {
                            $currentoptions['template'] = $renderer->get_edit_template();
                            $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                        }
                    }
                } else {
                    // DISPLAY MODE ===.
                    $replacement = $this->render_display_mode($entry, $currentoptions);
                    if ($replacement === '') {
                        // NO VALUE ===.
                        if ($renderer->get_no_value_template() === $renderer::NO_VALUE_SHOW_NOTHING) {
                            $replacements[$tag] = array('html', '');
                        } else {
                            if ($renderer->get_no_value_template() === $renderer::NO_VALUE_SHOW_DISPLAY_MODE_TEMPLATE
                            ) {
                                $replacements[$tag] = array('html',
                                        $this->replace_renderer_template_tags($renderer->get_display_template(), ''));
                            } else {
                                $replacements[$tag] = array('html', $renderer->get_no_value_template());
                            }
                        }
                    } else {
                        // HAS VALUE ===.
                        if ($renderer->get_display_template() === $renderer::DISPLAY_MODE_TEMPLATE_NONE
                        ) {
                            $replacements[$tag] = array('html', $replacement);
                        } else {
                            $replacements[$tag] = array('html',
                                    $this->replace_renderer_template_tags(
                                            $renderer->get_display_template(), $replacement)
                            );
                        }
                    }
                }
            }
        }

        return $replacements;
    }

    /**
     * TODO: add hash escaping option!
     *
     * @param $template
     * @param $value
     * @return mixed
     */
    private function replace_renderer_template_tags($template, $value) {
        return preg_replace('/#value/', $value, $template);
    }

    /**
     * Processes a tag string and returns all relevant info: the field name, and the referenced
     * behavior and renderer
     *
     * @param string $tag
     * @return array
     */
    protected function process_tag($tag) {
        $pattern = '/\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]/';
        $matches = array();

        $fieldname = $this->_field->name();
        $behavior = datalynx_field_behavior::get_default_behavior($this->_field->df());
        $renderer = datalynx_field_renderer::get_default_renderer($this->_field->df());

        if (preg_match($pattern, $tag, $matches)) {
            $fieldname = isset($matches[1]) ? $matches[1] : false;

            $behaviorname = isset($matches[2]) ? $matches[2] : false;
            if ($behaviorname) {
                $behavior = datalynx_field_behavior::from_name($behaviorname, $this->_field->df()->id());
            }

            $renderername = isset($matches[3]) ? $matches[3] : false;
            if ($renderername) {
                $renderer = datalynx_field_renderer::get_renderer_by_name($renderername, $this->_field->df()->id());
            }
        }

        return array($fieldname, $behavior, $renderer);
    }

    /**
     * TODO: make abstract once all field types have been updated
     * Outputs the HTML representation of the field and its value
     *
     * @param stdClass $entry object containing the entry data being rendered
     * @param array $options rendering options
     * @return string HTML representation of the field
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        $fieldid = $this->_field->id();

        if (isset($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
            $str = format_text($content, FORMAT_PLAIN, $options);
        } else {
            $str = '';
        }

        return $str;
    }

    /**
     * Callback function.
     * Adds preceding and following HTML formatting for field elements and calls
     * render_edit_mode. Cannot be overridden, but {@link render_edit_mode()} function can and
     * should be.
     *
     * @param MoodleQuickForm $mform form object used to render field input elements
     * @param stdClass $entry object containing the entry data being rendered
     * @param array $options rendering options
     * @see datalynxfield_renderer::render_edit_mode
     */
    final public function prerender_edit_mode(MoodleQuickForm &$mform, stdClass $entry,
            array $options) {
        if ($options['editable']) {
            if (isset($options['template']) && strpos($options['template'], '#input') !== false) {
                $splittemplate = explode('#input', $options['template']);
                $options['prefix'] = $splittemplate[0];
                $options['suffix'] = $splittemplate[1];
            }

            $mform->addElement('html',
                    '<div class="datalynx-field-wrapper" data-field-type="' . $this->_field->type .
                    '" data-field-name="' . $this->_field->field->name . '">');
            if (isset($options['prefix'])) {
                $mform->addElement('html', $options['prefix']);
            }
            $this->render_edit_mode($mform, $entry, $options);
            if (isset($options['suffix'])) {
                $mform->addElement('html', $options['suffix']);
            }
            $mform->addElement('html', '</div>');
        } else {
            if (isset($options['template']) && strpos($options['template'], '#value') !== false) {
                $splittemplate = explode('#value', $options['template']);
                $options['prefix'] = $splittemplate[0];
                $options['suffix'] = $splittemplate[1];
            }

            $mform->addElement('html',
                    '<div class="datalynx-field-wrapper" data-field-type="' . $this->_field->type .
                    '" data-field-name="' . $this->_field->field->name . '">');
            if (isset($options['prefix'])) {
                $mform->addElement('html', $options['prefix']);
            }
            $mform->addElement('html', $options['value']);
            if (isset($options['suffix'])) {
                $mform->addElement('html', $options['suffix']);
            }
            $mform->addElement('html', '</div>');
        }
    }

    /**
     * TODO: make abstract once all field types have been updated
     * Adds appropriate input elements to the entry form.
     * Called by {@link prerender_edit_mode()}.
     *
     * @param MoodleQuickForm $mform form object used to render field input elements
     * @param stdClass $entry object containing the entry data being rendered
     * @param array $options rendering options
     * @see datalynxfield_renderer::prerender_edit_mode
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options) {
        $fieldid = $this->_field->id();

        $fieldname = "f_{$entry->id}_$fieldid";

        $content = '';
        if ($entry->id > 0 && !empty($entry->{"c{$fieldid}_content"})) {
            $content = $entry->{"c{$fieldid}_content"};
        }

        $arr = array();
        $arr[] = &$mform->createElement('text', $fieldname, null,
                array('size' => '32', 'disabled' => ($options['disabled'] ? 'disabled' : null)
                ));
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $content);
    }

    /**
     * TODO: make abstract once all field types have been updated
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = '') {
        $fieldid = $this->_field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = array();
        $arr[] = &$mform->createElement('text', $fieldname, null, array('size' => '32'));
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');

        return array($arr, null);
    }

    /**
     */
    final public function get_menu($showall = false) {
        // The default menu category for fields.
        $patternsmenu = array();
        foreach ($this->patterns() as $tag => $pattern) {
            if ($showall || $pattern[self::PATTERN_SHOW_IN_MENU]) {
                // Which category.
                if (!empty($pattern[self::PATTERN_CATEGORY])) {
                    $cat = $pattern[self::PATTERN_CATEGORY];
                } else {
                    $cat = get_string('fields', 'datalynx');
                }
                // Prepare array.
                if (!isset($patternsmenu[$cat])) {
                    $patternsmenu[$cat] = array($cat => array());
                }
                // Add tag.
                $patternsmenu[$cat][$cat][$tag] = $tag;
            }
        }
        return $patternsmenu;
    }

    /**
     * Validate the form data for this field
     *
     * @param $entryid
     * @param $tags
     * @param $data
     * @return array
     */
    public function validate($entryid, $tags, $data) {
        return array();
    }

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

    /**
     */
    public function pluginfile_patterns() {
        return array();
    }
}
