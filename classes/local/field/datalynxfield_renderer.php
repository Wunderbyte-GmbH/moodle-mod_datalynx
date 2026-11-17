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
 * @package mod_datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx\local\field;

use MoodleQuickForm;
use stdClass;

/**
 * Base class for field patterns
 */
abstract class datalynxfield_renderer {
    /** @var int Pattern visibility in menu. */
    const PATTERN_SHOW_IN_MENU = 0;

    /** @var int Pattern category. */
    const PATTERN_CATEGORY = 1;

    /** @var array Default options for rendering. */
    protected static $defaultoptions = ['manage' => false, 'visible' => false, 'edit' => false,
            'editable' => false, 'disabled' => false, 'required' => false, 'internal' => false,
    ];

    /** @var datalynxfield_base The field object. */
    protected $field = null; // phpcs:ignore

    /** @var \WeakMap|null Form elements already named by a layout label, e.g. subfields inside a fieldgroup. */
    protected static $namedelements = null;

    /**
     * Constructor
     *
     * @param datalynxfield_base $field The field instance.
     */
    public function __construct(&$field) {
        $this->field = $field;
    }

    /**
     * Search and collate field patterns that occur in given text
     *
     * @param string $text text that may contain field patterns
     * @return array Field patterns found in the text
     */
    public function search($text): array {
        $found = [];

        $fieldname = $this->field->name();
        $regex = \mod_datalynx\local\view\base::get_specific_field_tag_regex($fieldname);
        if (preg_match_all($regex, $text, $matches)) {
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
                $strippedpattern = str_replace(
                    [\mod_datalynx\local\view\base::FIELD_TAG_OPEN, \mod_datalynx\local\view\base::FIELD_TAG_CLOSE],
                    ['', ''],
                    $pattern
                );
                $regex = \mod_datalynx\local\view\base::get_specific_field_tag_regex($strippedpattern);
                if (preg_match_all($regex, $text, $matches)) {
                    $found = array_merge($found, $matches[0]);
                }
            }
        }

        return array_unique($found);
    }

    /**
     * What a field tag means for an entry: whether the field is visible, editable and required,
     * and the behavior and field layout the tag names.
     *
     * This is the one place that decides it, from the behavior alone (its permissions, availability
     * conditions and required flag), the final-submission lock and the single-choice lock. The HTML
     * rendering ({@see replacements()}) and the entry submission service
     * ({@see \mod_datalynx\local\entry\entry_submission_service}) both read it, so what a user may
     * see or change cannot differ between the web form and a web service.
     *
     * @param string $tag the field tag, e.g. [[name|behavior|layout]] or [[name:format]]
     * @param stdClass|null $entry the entry; an object without id for a new entry
     * @param array|null $options render options to start from, e.g. ['edit' => true]
     * @return array [$options, $behavior, $layout]: the options with visible, editable, required and
     *               internal set, the datalynxfield_behavior and the datalynxfield_layout of the tag
     */
    public function resolve_render_options(string $tag, ?stdClass $entry, ?array $options = null): array {
        $options = array_merge(self::$defaultoptions, $options ?? []);
        $entry ??= new stdClass();
        [$fieldname, $behavior, $layout] = $this->process_tag($tag);

        $splitfieldname = explode(':', (string) $fieldname);
        if (isset($splitfieldname[1])) {
            $format = \mod_datalynx\local\field_format\manager::get_format_by_name(
                $this->field->dlx()->id(),
                $splitfieldname[1]
            );
            if ($format && $format->get_fieldtype() === $this->field->type) {
                $options['field_format'] = $format;
            } else {
                $options[$splitfieldname[1]] = true;
            }
        }

        // Availability conditions (based on other fields' values) gate visibility and editability:
        // when not met the field is hidden in both view and edit mode, and cannot be required.
        $conditionsmet = $behavior->passes_conditions($entry);
        $options['visible'] = $behavior->is_visible_to_user($entry) && $conditionsmet;
        $options['editable'] = $this->behavior_allows_edit($behavior, $entry, $conditionsmet);
        $options['required'] = $behavior->is_required() && $conditionsmet;
        $options['internal'] = $this->field->is_internal();

        return [$options, $behavior, $layout];
    }

    /**
     * Returns array of replacements for the field patterns
     * The label pattern should always be first where applicable
     * so that it is processed first in view templates
     * so that in turn patterns it may contain could be processed.
     *
     * @param ?array $tags
     * @param ?stdClass $entry
     * @param ?array $options
     * @return array|null
     */
    public function replacements(?array $tags = null, ?stdClass $entry = null, ?array $options = null): ?array {
        $replacements = [];
        foreach ($tags ?? [] as $tag) {
            [$currentoptions, , $renderer] = $this->resolve_render_options($tag, $entry, $options);

            if (!$currentoptions['visible']) {
                // NOT VISIBLE ===.
                if ($renderer->get_not_visible_template() === $renderer::NOT_VISIBLE_SHOW_NOTHING) {
                    $replacements[$tag] = ['html', ''];
                } else {
                    // No value is shown when not visible, so strip the #value placeholder.
                    $replacements[$tag] = ['html', $this->replace_renderer_template_tags(
                        $this->filter_layout_template($renderer->get_not_visible_template()),
                        ''
                    )];
                }
            } else {
                // VISIBLE ===.
                if ($currentoptions['edit']) {
                    // EDIT MODE ===.
                    if (!$currentoptions['editable']) {
                        // NOT EDITABLE ===.
                        $noteditabletemplate = $renderer->get_not_editable_template();
                        if (
                            $noteditabletemplate === $renderer::NOT_EDITABLE_SHOW_NOTHING
                        ) {
                            $replacements[$tag] = ['html', ''];
                        } else if (
                            $noteditabletemplate === $renderer::NOT_EDITABLE_SHOW_DISABLED
                        ) {
                            // Show the field input rendered as disabled.
                            $currentoptions['editable'] = true;
                            $currentoptions['disabled'] = true;
                            $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                        } else if (
                            $noteditabletemplate === $renderer::NOT_EDITABLE_SHOW_AS_DISPLAY_MODE
                        ) {
                            $currentoptions['template'] = $this->filter_layout_template($renderer->get_display_template());
                            $currentoptions['value'] = $this->render_display_mode($entry, $currentoptions);
                            $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                        } else if (
                            $noteditabletemplate === $renderer::NOT_EDITABLE_SHOW_DEPENDENT
                        ) {
                            $displayvalue = $this->render_display_mode($entry, $currentoptions);
                            if ($displayvalue === '') {
                                $replacements[$tag] = ['html', ''];
                            } else {
                                $currentoptions['template'] = $this->filter_layout_template(
                                    $renderer->get_display_template()
                                );
                                $currentoptions['value'] = $displayvalue;
                                $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                            }
                        } else {
                            $replacements[$tag] = ['html', $this->filter_layout_template($noteditabletemplate)];
                        }
                    } else {
                        // EDITABLE ===.
                        if ($renderer->get_edit_template() === $renderer::EDIT_MODE_TEMPLATE_NONE) {
                            $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                        } else {
                            $currentoptions['template'] = $this->filter_layout_template($renderer->get_edit_template());
                            $replacements[$tag] = ['', [[$this, 'prerender_edit_mode'], [$entry, $currentoptions]]];
                        }
                    }
                } else {
                    // DISPLAY MODE ===.
                    $replacement = $this->render_display_mode($entry, $currentoptions);
                    if ($replacement === '') {
                        // NO VALUE ===.
                        if ($renderer->get_no_value_template() === $renderer::NO_VALUE_SHOW_NOTHING) {
                            $replacements[$tag] = ['html', ''];
                        } else {
                            if (
                                $renderer->get_no_value_template() === $renderer::NO_VALUE_SHOW_DISPLAY_MODE_TEMPLATE
                            ) {
                                $replacements[$tag] = ['html', $this->replace_renderer_template_tags(
                                    $this->filter_layout_template($renderer->get_display_template()),
                                    ''
                                )];
                            } else {
                                // No value here, so strip the #value placeholder from the custom template.
                                $replacements[$tag] = ['html', $this->replace_renderer_template_tags(
                                    $this->filter_layout_template($renderer->get_no_value_template()),
                                    ''
                                )];
                            }
                        }
                    } else {
                        // HAS VALUE ===.
                        if (
                            $renderer->get_display_template() === $renderer::DISPLAY_MODE_TEMPLATE_NONE
                        ) {
                            $replacements[$tag] = ['html', $replacement];
                        } else {
                            $replacements[$tag] = ['html',
                                    $this->replace_renderer_template_tags(
                                        $this->filter_layout_template($renderer->get_display_template()),
                                        $replacement
                                    ),
                            ];
                        }
                    }
                }
            }
        }

        return $replacements;
    }

    /**
     * The HTML a tag's field layout puts before and after the input in edit mode, e.g. the label of
     * the question; empty strings where the layout has no edit template with #input.
     *
     * @param string $tag the field tag, e.g. [[name|behavior|layout]]
     * @return string[] [prefix, suffix]
     */
    public function get_edit_layout(string $tag): array {
        [, , $layout] = $this->process_tag($tag);
        $template = $layout ? $layout->get_edit_template() : null;
        if (!is_string($template) || strpos($template, '#input') === false) {
            return ['', ''];
        }
        [$prefix, $suffix] = explode('#input', (string) $this->filter_layout_template($template), 2);

        return [$prefix, $suffix];
    }

    /**
     * Run a custom field layout template through the content filters (multilang, ...).
     *
     * View templates are filtered before their tags are substituted, so a layout template that is
     * inserted afterwards would otherwise show filter markup such as {mlang} raw. Only the static
     * template is filtered: the field value is formatted by render_display_mode() and the form
     * element is added to the form separately, so neither is filtered twice. Sentinel values
     * (___0___ etc.) select a built-in mode and are returned unchanged.
     *
     * @param int|string|null $template The layout template or sentinel.
     * @return int|string|null
     */
    protected function filter_layout_template(int|string|null $template): int|string|null {
        if (!is_string($template) || $template === '' || preg_match('/^___\d+___$/', $template)) {
            return $template;
        }
        return format_text($template, FORMAT_HTML, [
            'trusted' => true,
            'noclean' => true,
            'filter' => true,
            'para' => false,
            'context' => $this->field->dlx()->context,
        ]);
    }

    /**
     * TODO: add hash escaping option!
     *
     * @param string $template
     * @param mixed $value
     * @return string|null
     */
    private function replace_renderer_template_tags($template, $value): ?string {
        return preg_replace('/#value/', $value, $template);
    }

    /**
     * Whether the field can be edited in an entry under the given behavior.
     *
     * This is the single place that decides editability for the edit form ({@see replacements()})
     * and for {@see is_tag_editable()}, so both always agree.
     *
     * @param datalynxfield_behavior $behavior Behavior of the field tag.
     * @param stdClass $entry The entry.
     * @param bool $conditionsmet Whether the behavior's availability conditions are met for the entry.
     * @return bool
     */
    protected function behavior_allows_edit(datalynxfield_behavior $behavior, stdClass $entry, bool $conditionsmet): bool {
        global $USER;
        // Whether the current user counts as the entry's author. A new entry - rendered and
        // submitted with a negative id (-1, -2, ...), or none - is being filled in by the current
        // user, who becomes its author. (A manager may choose another author through an author
        // format set to "edit"; that does not change who fills in the form, and managers edit by
        // their own permission anyway.) For an existing entry compare its author with the user.
        $isnew = empty($entry->id) || (is_numeric($entry->id) && (int) $entry->id < 0);
        $isentryauthor = $isnew || (isset($entry->userid) && (string) $entry->userid === (string) $USER->id);
        $editable = $behavior->is_editable_by_user(null, $isentryauthor) && $conditionsmet;
        // Once an entry reaches final submission, only fields whose behavior explicitly allows it
        // stay editable for users without editfinalsubmission. The status field itself is never excepted,
        // so the entry cannot be reverted to draft to bypass the lock.
        if (
            $editable && isset($entry->status)
            && (int) $entry->status === \datalynxfield_status\field::STATUS_FINAL_SUBMISSION
            && !has_capability('mod/datalynx:editfinalsubmission', $this->field->dlx()->context)
        ) {
            $editable = $behavior->is_editable_after_final() && $this->field->type !== 'status';
        }
        // A single-choice field set to lock after first save is read-only for everyone once the
        // entry has a saved value. The value can only be changed by resetting the entry (##reset##).
        if ($editable && $this->field->is_locked_for_entry($entry)) {
            $editable = false;
        }
        return $editable;
    }

    /**
     * Whether a field tag takes a submitted value for the entry, as the entry submission service
     * asks it of internal fields (status, approval, author, group), which have no behaviors.
     *
     * By default the same as {@see is_tag_editable()}. Internal fields whose tags are inputs
     * override it; is_tag_editable() keeps deciding the edit links, unchanged.
     *
     * @param string $tag The field tag as found in the view template.
     * @param stdClass $entry The entry.
     * @return bool
     */
    public function accepts_input(string $tag, stdClass $entry): bool {
        return $this->is_tag_editable($tag, $entry);
    }

    /**
     * Whether a field tag of a view would render as an editable input for the entry.
     *
     * Used to decide whether an edit link to a view is worth showing, see
     * {@see \mod_datalynx\local\view\base::entry_has_editable_fields()}.
     * Only [[field|behavior|layout]] tags are considered here; field types with ##...## tags override this
     * method if their tags can be edited.
     *
     * @param string $tag The field tag as found in the view template.
     * @param stdClass $entry The entry.
     * @return bool
     */
    public function is_tag_editable(string $tag, stdClass $entry): bool {
        if (strpos(trim($tag, '@'), '[[') !== 0) {
            return false;
        }
        [, $behavior] = $this->process_tag($tag);
        $conditionsmet = $behavior->passes_conditions($entry);
        if (!$behavior->is_visible_to_user($entry) || !$conditionsmet) {
            return false;
        }
        return $this->behavior_allows_edit($behavior, $entry, $conditionsmet);
    }

    /**
     * Processes a tag string and returns all relevant info: the field name, and the referenced
     * behavior and renderer
     *
     * @param string $tag
     * @return array
     */
    protected function process_tag($tag): array {
        $pattern = '/\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]/';
        $matches = [];

        $fieldname = $this->field->name();
        $behavior = datalynxfield_behavior::get_default_behavior($this->field->dlx());
        $renderer = datalynxfield_layout::get_default_renderer($this->field->dlx());

        if (preg_match($pattern, $tag, $matches)) {
            $fieldname = isset($matches[1]) ? $matches[1] : false;

            $behaviorname = isset($matches[2]) ? $matches[2] : false;
            if ($behaviorname) {
                $behavior = $this->field->dlx()->get_behavior_by_name($behaviorname);
            }

            $renderername = isset($matches[3]) ? $matches[3] : false;
            if ($renderername) {
                $renderer = $this->field->dlx()->get_layout_by_name($renderername);
            }
        }

        return [$fieldname, $behavior, $renderer];
    }

    /**
     * Run a menu of user defined option labels through format_string().
     *
     * Option labels are authored text (the lines of a field's param1) and are inserted into the
     * entry html and the filter widgets after the surrounding template has already been filtered,
     * so this is where filter markup in them gets resolved.
     *
     * @param array $menu key => raw label
     * @param bool $escape False when the consumer (a form element, a Mustache placeholder) escapes
     *                     the label itself; leaving it true there would double encode ampersands.
     * @return array key => formatted label
     */
    protected function format_option_labels(array $menu, bool $escape = false): array {
        $context = $this->field->dlx()->context;

        return array_map(static function ($label) use ($context, $escape) {
            return is_string($label)
                ? format_string($label, true, ['context' => $context, 'escape' => $escape])
                : $label;
        }, $menu);
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
        $fieldid = $this->field->id();

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
     * render_edit_mode. Cannot be overridden, but {@see render_edit_mode()} function can and
     * should be.
     *
     * @param MoodleQuickForm $mform form object used to render field input elements
     * @param stdClass $entry object containing the entry data being rendered
     * @param array $options rendering options
     * @see datalynxfield_renderer::render_edit_mode
     */
    final public function prerender_edit_mode(
        MoodleQuickForm &$mform,
        stdClass $entry,
        array $options
    ): void {
        if ($options['editable']) {
            $hascustomtemplate = isset($options['template'])
                && strpos($options['template'], '#input') !== false;
            if ($hascustomtemplate) {
                $splittemplate = explode('#input', $options['template']);
                $options['prefix'] = $splittemplate[0];
                $options['suffix'] = $splittemplate[1];
            }

            $wrapperclass = 'datalynx-field-wrapper'
                . ($hascustomtemplate ? ' datalynx-no-fitem-wrapper' : '');
            $mform->addElement(
                'html',
                '<div class="' . $wrapperclass . '" data-field-type="' . $this->field->type .
                '" data-field-name="' . $this->field->field->name . '">'
            );
            $prefixelement = null;
            if (isset($options['prefix'])) {
                $prefixelement = $mform->addElement('html', $options['prefix']);
            }
            $firstelement = count($mform->_elements);
            $this->render_edit_mode($mform, $entry, $options);
            $this->set_input_types($mform, "field_{$this->field->id()}_{$entry->id}", $options);
            $this->name_edit_elements(array_slice($mform->_elements, $firstelement), $prefixelement);
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

            $mform->addElement(
                'html',
                '<div class="datalynx-field-wrapper" data-field-type="' . $this->field->type .
                '" data-field-name="' . $this->field->field->name . '">'
            );
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
     * Gives every form element this field added an accessible name.
     *
     * Custom layouts put the visible label into the template (`<label>Purpose</label>#input`) and the
     * element itself is added without a label, so screen readers could not tell which field an input
     * belongs to. The first unnamed element gets the template's first `<label>` attached with `for`;
     * any further unnamed element gets an aria-label with the same text, or with the field description
     * or name when the template has no label. Elements inside groups (url and link text, the day, month
     * and year menus of a time field) are named the same way; when a field has several unnamed elements
     * the aria-label adds the element's placeholder or mask option ("Report: Link text", "Date: Month").
     *
     * @param array $elements form elements added by {@see render_edit_mode()}
     * @param \HTML_QuickForm_static|null $prefixelement the html element holding the layout prefix
     */
    protected function name_edit_elements(array $elements, ?\HTML_QuickForm_static $prefixelement): void {
        $unnamed = array_values(array_filter($this->expand_groups($elements), [$this, 'element_needs_name']));
        $addhint = count($unnamed) > 1;
        if (!$unnamed) {
            return;
        }

        $labeltext = '';
        if ($prefixelement !== null) {
            $prefix = $prefixelement->toHtml();
            if (preg_match('~<label\b([^>]*)>(.*?)</label>~is', $prefix, $match, PREG_OFFSET_CAPTURE)) {
                $labeltext = trim(strip_tags($match[2][0]));
                if (!preg_match('~\bfor\s*=~i', $match[1][0])) {
                    $element = array_shift($unnamed);
                    self::named_elements()[$element] = true;
                    $element->_generateId();
                    $labeltag = '<label for="' . s($element->getAttribute('id')) . '"' . $match[1][0] . '>';
                    $labelstart = $match[0][1];
                    $prefixelement->setText(substr_replace(
                        $prefix,
                        $labeltag,
                        $labelstart,
                        $match[2][1] - $labelstart
                    ));
                }
            }
        }
        if (!$unnamed) {
            return;
        }

        $context = $this->field->dlx()->context;
        $labeltext = format_string($labeltext, true, ['context' => $context, 'escape' => false]);
        if ($labeltext === '') {
            $description = trim(strip_tags((string) ($this->field->field->description ?? '')));
            $labeltext = format_string(
                $description !== '' ? $description : $this->field->field->name,
                true,
                ['context' => $context, 'escape' => false]
            );
        }
        foreach ($unnamed as $element) {
            $hint = $addhint ? $this->element_hint($element) : '';
            $element->updateAttributes(['aria-label' => $hint !== '' ? "$labeltext: $hint" : $labeltext]);
            self::named_elements()[$element] = true;
        }
    }

    /**
     * Elements named by an earlier pass. A fieldgroup renders its subfields with their own layouts first;
     * their inputs must keep that label and not get the fieldgroup's label on top.
     *
     * @return \WeakMap
     */
    protected static function named_elements(): \WeakMap {
        if (self::$namedelements === null) {
            self::$namedelements = new \WeakMap();
        }
        return self::$namedelements;
    }

    /**
     * Replaces each group by its elements, so that the parts of a group can be named.
     *
     * @param array $elements form elements
     * @return array
     */
    protected function expand_groups(array $elements): array {
        $expanded = [];
        foreach ($elements as $element) {
            if ($element instanceof \HTML_QuickForm_group) {
                array_push($expanded, ...$this->expand_groups($element->getElements()));
            } else {
                $expanded[] = $element;
            }
        }
        return $expanded;
    }

    /**
     * What tells one element of a field apart from the others: its placeholder, or the text of the
     * empty mask option of a menu ("Month").
     *
     * @param \HTML_QuickForm_element $element
     * @return string
     */
    protected function element_hint(\HTML_QuickForm_element $element): string {
        $placeholder = trim((string) $element->getAttribute('placeholder'));
        if ($placeholder !== '') {
            return $placeholder;
        }
        if ($element instanceof \HTML_QuickForm_select) {
            foreach ($element->_options as $option) {
                $value = (string) ($option['attr']['value'] ?? '');
                if ($value === '0' || $value === '') {
                    return trim(strip_tags((string) $option['text']));
                }
            }
        }
        return '';
    }

    /**
     * Whether a form element would be announced without a name.
     *
     * Checkboxes and radio buttons carry their own text, the parts of date selectors have labels, and
     * layout, hidden and button elements need no name. Groups are expanded by {@see expand_groups()}.
     *
     * @param mixed $element a QuickForm element
     * @return bool
     */
    protected function element_needs_name($element): bool {
        if (!$element instanceof \HTML_QuickForm_element || $element instanceof \HTML_QuickForm_group) {
            return false;
        }
        $skip = ['html', 'static', 'hidden', 'header', 'submit', 'button', 'cancel', 'reset'];
        if (in_array($element->getType(), $skip, true)) {
            return false;
        }
        $named = isset(self::named_elements()[$element])
            || (string) $element->getLabel() !== ''
            || $element->getAttribute('aria-label')
            || $element->getAttribute('aria-labelledby');
        if ($named) {
            return false;
        }
        if (method_exists($element, 'getText') && trim((string) $element->getText()) !== '') {
            return false;
        }
        return true;
    }

    /**
     * TODO: make abstract once all field types have been updated
     * Adds appropriate input elements to the entry form.
     * Called by {@see prerender_edit_mode()}.
     *
     * @param MoodleQuickForm $mform form object used to render field input elements
     * @param stdClass $entry object containing the entry data being rendered
     * @param array $options rendering options
     * @see datalynxfield_renderer::prerender_edit_mode
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options): void {
        $fieldid = $this->field->id();
        // The element name every field uses and process_entries() reads back.
        $fieldname = "field_{$fieldid}_{$entry->id}";

        $content = '';
        if ($entry->id > 0 && (string) ($entry->{"c{$fieldid}_content"} ?? '') !== '') {
            $content = $entry->{"c{$fieldid}_content"};
        }

        $attributes = ['size' => '32'];
        if (!empty($options['disabled'])) {
            $attributes['disabled'] = 'disabled';
        }
        $mform->addElement('text', $fieldname, null, $attributes);
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $content);
    }

    /**
     * The stored value of a search criterion as render_search_mode() gets it.
     *
     * The filter and behavior forms pass the value as a string; a value that is a list (bounds of a
     * range, chosen options, users, tags) arrives JSON-encoded and is decoded here.
     *
     * @param string $value
     * @return array|string the decoded list, or the value itself
     */
    protected static function decode_saved_search_value(string $value): array|string {
        if ($value === '' || !in_array($value[0], ['[', '{'], true)) {
            return $value;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * TODO: make abstract once all field types have been updated
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array|bool
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = ''): array|bool {
        $fieldid = $this->field->id();
        $fieldname = "f_{$i}_$fieldid";

        $arr = [];
        $arr[] = &$mform->createElement('text', $fieldname, null, ['size' => '32']);
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $value);
        $mform->disabledIf($fieldname, "searchoperator$i", 'eq', '');

        return [$arr, null];
    }

    /**
     * The control that edits this field outside the web's entry form, e.g. in the Moodle App
     * (mod_datalynx#271).
     *
     * It only describes the control. What the user may see and change in an entry is decided by
     * entry_submission_service::get_field_states(); the inputs are named and typed by the field's
     * input contract, so their values go back through the submission service (submit_entry)
     * unchanged.
     *
     * @param stdClass $entry the entry with its contents (c{fieldid}_content, ...); id < 0 for a new one
     * @param array $options the resolved render options of the tag the field is edited under
     * @return array|null see edit_control(); null when there is no control (the field is edited on the web)
     */
    public function get_edit_control(stdClass $entry, array $options): ?array {
        return null;
    }

    /**
     * A field shown, not edited: its display as in browse mode.
     *
     * @param stdClass $entry
     * @param array $options
     * @return array see edit_control()
     */
    public function get_static_control(stdClass $entry, array $options): array {
        $html = $entry->id > 0 ? $this->render_display_mode($entry, ['edit' => false] + $options) : '';

        return $this->edit_control('static', [], $options, ['html' => $html]);
    }

    /**
     * Describe a control; each input must be one the field's input contract names.
     *
     * @param string $control text, number, textarea, url, select, radio, multiselect, checkbox,
     *                        datetime, duration, toggle, hidden or static
     * @param array $values content name, as the input contract names it ('' for the value itself) =>
     *                      the current value: text, or a list of texts for a list input
     * @param array $options the render options the input contract is read with
     * @param array $extra further keys of the control: choices, multiple, inputmode, html, ...
     * @return array ['fieldid', 'control', 'inputs' => [['name', 'list', 'value']], 'choices' =>
     *               [['value', 'label', 'disabled']], 'multiple', ...]
     * @throws \coding_exception for an input outside the contract
     */
    protected function edit_control(string $control, array $values, array $options, array $extra = []): array {
        $contract = $this->field->get_input_contract($options);
        $inputs = [];
        foreach ($values as $name => $value) {
            $name = (string) $name;
            if (!array_key_exists($name, $contract)) {
                throw new \coding_exception("'$name' is no input of field {$this->field->id()}.");
            }
            $list = is_array($contract[$name]) && array_key_exists('list', $contract[$name]);
            $inputs[] = [
                'name' => $name,
                'list' => $list,
                'value' => $list ? array_values(array_map('strval', (array) $value)) : (string) $value,
            ];
        }

        return $extra + [
            'fieldid' => $this->field->id(),
            'control' => $control,
            'inputs' => $inputs,
            'choices' => [],
            'multiple' => false,
        ];
    }

    /**
     * The choices of a control from a menu.
     *
     * @param array $menu value => label
     * @param array $disabled values that cannot be chosen
     * @return array [['value', 'label', 'disabled']]
     */
    protected static function edit_choices(array $menu, array $disabled = []): array {
        $choices = [];
        foreach ($menu as $value => $label) {
            $choices[] = [
                'value' => (string) $value,
                'label' => (string) $label,
                'disabled' => in_array((string) $value, array_map('strval', $disabled), true),
            ];
        }

        return $choices;
    }

    /**
     * The control the Moodle App shows for this field in a custom filter (mod_datalynx#270).
     *
     * One of text, range, daterange, select, multiselect, presence, approve, status: the web and
     * the app build the field's custom filter input from it (customfilter_controls). Unsupported
     * when no control expresses the field's search: the web then shows the field's own search
     * elements (render_search_mode()), the app sends the user to the web.
     *
     * @return string
     */
    public function get_customfilter_control(): string {
        return 'unsupported';
    }

    /**
     * The choices of a select, multiselect or status control: value => label.
     *
     * @return array
     */
    public function get_customfilter_options(): array {
        return [];
    }

    /**
     * Returns the menu for the field.
     *
     * @param bool $showall Whether to show all patterns.
     * @return array
     */
    final public function get_menu($showall = false): array {
        // The default menu category for fields.
        $patternsmenu = [];
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
                    $patternsmenu[$cat] = [$cat => []];
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
     * @param int $entryid
     * @param array $tags
     * @param array $data
     * @return array
     */
    public function validate($entryid, $tags, $data): array {
        return [];
    }

    /**
     * Set the types of the field's form elements from its input contract.
     *
     * The form then cleans the submission by the same types as the entry submission service, which
     * cleans by the contract again for every submission, through the form or a web service.
     *
     * @param MoodleQuickForm $mform
     * @param string $fieldname the element name of the value, field_{fieldid}_{entryid}
     * @param array $options the render options the field is edited under
     * @return void
     */
    protected function set_input_types(MoodleQuickForm $mform, string $fieldname, array $options): void {
        foreach ($this->field->get_input_contract($options) as $contentname => $type) {
            $element = $contentname === '' ? $fieldname : "{$fieldname}_{$contentname}";
            if (is_array($type) && isset($type['fields'])) {
                foreach ($type['fields'] as $key => $itemtype) {
                    $mform->setType("{$element}[{$key}]", $itemtype);
                }
            } else {
                $mform->setType($element, is_array($type) ? $type['list'] : $type);
            }
        }
    }

    /**
     * The form element that shows an error about a field value.
     *
     * Errors are keyed by element name, and the form shows an error only at an element it has.
     * A field whose input is a group, or is named with a suffix, says which element that is.
     *
     * @param string $name the element name of the value: field_{fieldid}_{entryid}[...]
     * @return string
     */
    public function get_error_element(string $name): string {
        return $name;
    }

    /**
     * The message shown when a required field is left empty.
     *
     * @return string
     */
    public function get_required_message(): string {
        return get_string('fieldrequired', 'datalynx');
    }

    /**
     * Whether this field's availability conditions are met for the given entry.
     *
     * Conditionally-hidden fields render no input and must not be validated (e.g. a hidden but
     * "required" field should not block submission). The governing behavior is taken from the field
     * pattern(s), mirroring how replacements() resolves it.
     *
     * @param array $tags The field pattern tags being rendered.
     * @param int|string $entryid The entry id (negative/non-numeric for new or fieldgroup entries).
     * @return bool True if the field is available (so it should be validated).
     */
    public function conditions_met($tags, $entryid): bool {
        $entry = (object) ['id' => is_numeric($entryid) ? (int) $entryid : 0];
        foreach ((array) $tags as $tag) {
            [, $behavior, ] = $this->process_tag($tag);
            if ($behavior && !$behavior->passes_conditions($entry)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Array of patterns this field supports
     * The label pattern should always be first where applicable
     * so that it is processed first in view templates
     * so that in turn patterns it may contain could be processed.
     *
     * @return array pattern => array(visible in menu, category)
     */
    protected function patterns(): array {
        $fieldname = $this->field->name();

        $patterns = [];
        $patterns["[[$fieldname]]"] = [true];
        $patterns += $this->format_patterns();

        return $patterns;
    }

    /**
     * Patterns generated from the user-created field formats for this field's type.
     *
     * Field formats (Manage > Fields > Field Formats) are the single, discoverable way to apply
     * display modifiers to a field: each format defined for this datalynx instance and this field's
     * type becomes a [[fieldname:formatname]] tag that is shown in the field-tags menu and recognised
     * by {@see self::search()} for replacement. Renderers that build their own pattern list (e.g. the
     * legacy ##field:suffix## internal fields) can call this to opt in. Renderers that still carry
     * hardcoded legacy suffixes should call parent::patterns() (which already merges these) and then
     * add their legacy keys as [false] so the suffixes keep rendering in old templates without being
     * advertised in the menu.
     *
     * WARNING: Hardcoded legacy modifiers (e.g. [[fieldname:suffix]]) are deprecated. All new field
     * modifiers MUST be implemented as Field Formats instead. Developers and AI agents must NOT
     * introduce new hardcoded/legacy modifiers or suffixes.
     *
     * @return array pattern => array(visible in menu)
     */
    protected function format_patterns(): array {
        $patterns = [];
        $fieldname = $this->field->name();
        $formats = \mod_datalynx\local\field_format\manager::get_formats_for_instance(
            $this->field->dlx()->id(),
            $this->field->type
        );
        foreach ($formats as $format) {
            $patterns["[[{$fieldname}:{$format->get_name()}]]"] = [true];
        }
        return $patterns;
    }

    /**
     * Add the field's hardcoded legacy [[fieldname:suffix]] modifiers to a pattern list as hidden
     * ([false]) entries.
     *
     * The suffixes stay recognised by {@see self::search()} so existing templates keep rendering, but
     * they are no longer advertised in the field-tags menu — field formats are the surfaced mechanism.
     * A legacy suffix is only added when a user-created format of the same name has not already claimed
     * the tag (so a format named e.g. "url" keeps its visible [true] entry instead of being hidden).
     *
     * @deprecated Since Moodle 4.5. Hardcoded legacy modifiers are deprecated. All new modifiers
     *             MUST be implemented as Field Formats. Developers and AI agents must NOT introduce
     *             new hardcoded/legacy modifiers or suffixes in patterns or replacements.
     *
     * @param array $patterns Existing pattern list (typically from parent::patterns()).
     * @param array $suffixes Legacy suffix names, e.g. ['url', 'size', 'download'].
     * @return array
     */
    protected function add_legacy_suffix_patterns(array $patterns, array $suffixes): array {
        $fieldname = $this->field->name();
        foreach ($suffixes as $suffix) {
            $key = "[[{$fieldname}:{$suffix}]]";
            if (!isset($patterns[$key])) {
                $patterns[$key] = [false];
            }
        }
        return $patterns;
    }

    /**
     * Returns the patterns for pluginfile.
     *
     * @return array
     */
    public function pluginfile_patterns(): array {
        return [];
    }
}
