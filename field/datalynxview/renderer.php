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
 * @subpackage datadformview
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/filter/filter_class.php");
require_once("$CFG->dirroot/mod/datalynx/field/renderer.php");
require_once("$CFG->dirroot/mod/datalynx/field/entryauthor/field_class.php");
require_once("$CFG->dirroot/mod/datalynx/field/entrygroup/field_class.php");

/**
 */
class datalynxfield_datalynxview_renderer extends datalynxfield_renderer {

    /*
     * Rendering this field in display mode
     * called by the replacement-function of datalynxfield_renderer
     * stdClass @entry   represents the entry which includes this instance of the field
     * array @params     the type of the display, "" for default
     * @returns          the function which displays this field-instance
     */
    public function render_display_mode(stdClass $entry, array $options): string {

        // We don't export dlview to csv.
        if (optional_param('exportcsv', '', PARAM_ALPHA)) {
            return '';
        }

        $type = "";
        return $this->display_browse($entry, $type);
    }

    /**
     * Check, which type of display, and call the right display-function
     * stdClass @entry  the entry object which holds this field
     * string @type     the type of display
     */
    protected function display_browse($entry, $type = null) {
        $field = $this->_field;

        if (empty($field->refdatalynx) || empty($field->refview)) {
            return '';
        }

        // Inline.
        if (empty($type)) {
            // TODO Including controls seems to mess up the hosting view controls.
            $voptions = array('controls' => false);
            return $this->get_view_display_content($entry, $voptions);
        }

        return '';
    }

    /**
     * The default display-method, just inline html
     * stdClass @entry          the entry object this field belongs to
     * array string @options    array of options for the display-method of the view
     *
     * @returns                 the display-method of the view
     */
    protected function get_view_display_content($entry, array $options = array()) {
        $field = $this->_field;

        $refdatalynx = $field->refdatalynx;
        $refview = $refdatalynx->get_view_from_id($field->refview);

        // Options for setting the filter.
        $foptions = array();

        // Search filter by entry author or group.
        $foptions = $this->get_filter_by_options($foptions, $entry);

        if (!isset($foptions['eids']) && !isset($foptions['users'])) {
            return "";
        }

        $refview->set_filter($foptions, true);

        // Set the ref datalynx.
        $params = array('js' => true, 'css' => true, 'modjs' => true, 'completion' => true,
                'comments' => true);

        // Ref datalynx page type defaults to external.
        $refpagetype = !empty($options['pagetype']) ? $options['pagetype'] : 'external';
        $pageoutput = $refdatalynx->set_page('external', $params, true);

        $refview->set_content(array('filter' => $refview->get_filter()));
        // Set to return html.
        $options['tohtml'] = true;
        $options['fieldview'] = true;
        $options['entryactions'] = false;
        return $refview->display($options);
    }

    /**
     * Get the filter options for this field instance
     * and add it to the options array
     * Should the entries be filtered:
     * - by the author of this entry object
     * - by the group to which this entry object belongs to
     * - by a value, which is stored in datalynx_contents for this field(fieldid) and this entry(entryid)
     */
    protected function get_filter_by_options(array $options, $entry, $urlquery = false) {
        global $DB;

        $field = $this->_field;

        if (!empty($field->field->param6)) { // Param6: author,group.
            list($filterauthor, $filtergroup) = explode(',', $field->field->param6);
            // Entry author.
            if ($filterauthor) {
                if ($entry->id != -1) {
                    $users = $urlquery ? $entry->userid : array($entry->userid);
                    $options['users'] = $users;
                } else {
                    global $USER;
                    $users = $urlquery ? $USER->id : array($USER->id);
                    $options['users'] = $users;
                }
            }
            // Entry group.
            if ($filtergroup) {
                if ($entry->id != -1) {
                    $groups = $urlquery ? $entry->groupid : array($entry->groupid);
                    $options['groups'] = $groups;
                } else {
                    $allgroups = groups_get_user_groups($this->_df->course);
                    $groups = $urlquery ? implode(',', $allgroups[0]) : $allgroups[0];
                    $options['groups'] = $groups;
                }
            }
        }

        // With the ID of this datalynxview_field and the ID of the parent-entry we retrieve.
        // The content to search for.
        // Then we get the entry-IDs of the entries (eids) which match this content-value.
        // End add them to the options array.
        if ($fieldid = $field->field->param7) {  // Field-ID of the external field.
            if ($contents = $DB->get_fieldset_select('datalynx_contents', 'content',
                    'entryid = :entryid and fieldid = :fieldid',
                    array('entryid' => $entry->id, 'fieldid' => $field->field->id))
            ) {
                $contentsarr = explode(",", $contents[0]);
                list($insql, $params) = $DB->get_in_or_equal($contentsarr, SQL_PARAMS_NAMED);
                $params['fieldid'] = $fieldid;
                $sql = 'SELECT  c.entryid
                        FROM    {datalynx_contents} c
                        WHERE   c.fieldid = :fieldid
                        AND     c.content ' . $insql;
                if ($eids = $DB->get_fieldset_sql($sql, $params)) {
                    $options['eids'] = implode(",", $eids);
                }

            }
        }

        return $options;
    }

    /**
     */
    protected function get_sort_options() {
        $field = $this->_field;

        $refdatalynx = $field->refdatalynx;
        $refview = $field->df->get_view_from_id($field->refview);

        $soptions = array();
        // Custom sort (ref-field-patten,ASC/DESC).
        if (!empty($field->field->param4)) {
            foreach (explode("\n", $field->field->param4) as $key => $sorty) {
                list($pattern, $dir) = explode(',', $sorty);
                // Get the field id from pattern.
                if (!$rfieldid = $refview->get_pattern_fieldid($pattern)) {
                    continue;
                }
                // Convert direction to 0/1.
                $dir = $dir == 'DESC' ? 1 : 0;
                $soptions[$rfieldid] = $dir;
            }
        }
        return $soptions;
    }

    /**
     *
     * @param unknown $entry
     * @return unknown[]
     */
    protected function get_search_options($entry) {
        $field = $this->_field;
        $soptions = array();

        // Custom search (AND/OR,ref-field-patten,[NOT],OPT,local-field-pattern/value.
        if (empty($field->field->param5)) {
            return $soptions;
        }

        if (!$refdatalynx = $field->refdatalynx || !$refview = $field->refview || !$localview = $field->localview
        ) {
            return $soptions;
        }

        foreach (explode("\n", $field->field->param5) as $key => $searchy) {
            list($andor, $refpattern, $not, $operator, $localpattern) = explode(',', $searchy);
            // And/or.
            if (empty($andor) || !in_array($andor, array('AND', 'OR'))) {
                continue;
            }
            // Get the ref field id from pattern.
            $refviewview = $field->df->get_view_from_id($refview);
            if (!$rfieldid = $refviewview->get_pattern_fieldid($refpattern)) {
                continue;
            }
            // Get value for local pattern or use as value.
            $value = '';
            $localviewview = $field->df->get_view_from_id($localview);
            if (!$localfieldid = $localviewview->get_pattern_fieldid($localpattern)) {
                $value = $localpattern;
            } else {
                if ($localfield = $field->df->get_field_from_id($localfieldid)) {
                    // Get the array of values for the patterns.
                    if ($replacements = $localfield->renderer()->replacements(array($localpattern), $entry)) {
                        // Take the first: array('html', value).
                        $first = reset($replacements);
                        // Extract the value part.
                        $value = $first[1];
                        // Make sure this is the search value.
                        // (select fields search by key).
                        $value = $localfield->get_search_value($value);
                    }
                }
            }

            // Add to the search options.
            if (empty($soptions[$rfieldid])) {
                $soptions[$rfieldid] = array('AND' => array(), 'OR' => array());
            }
            $soptions[$rfieldid][$andor][] = array($not, $operator, $value);
        }

        return $soptions;
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::patterns()
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);

        return $patterns;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see datalynxfield_renderer::render_edit_mode()
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options = null) {

        // Variable $field datalynxfield_datalynxview.
        $field = $this->_field;
        // Do not show in edit mode, when nothing can be selected.
        if ($field->refdatalynx !== null && !empty($field->field->param7)) {

            $fieldid = $field->id();
            $entryid = $entry->id;
            $fieldname = "field_{$fieldid}_$entryid";
            $classname = "datalynxview_{$fieldid}_{$entryid}";
            $required = !empty($options['required']);

            $selected = !empty($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : array();
            // A hidden field is added to autocomplete fields by parent Quickform element.
            // The value of the hidden field must be added as option in order to process an empty autocomplete field.
            $menu = array("_qf__force_multiselect_submission" => "...");
            $menu = array_merge($menu, $field->refdatalynx->get_distinct_textfieldvalues_by_id($field->field->param7));

            $mform->addElement('autocomplete', $fieldname, null, $menu, array(
                    "class" => "datalynxfield_datalynxview $classname",
                    "multiple" => "true"
            ));
            $mform->setType($fieldname, PARAM_NOTAGS);
            $mform->setDefault($fieldname, $selected);
            if ($required) {
                $mform->addRule($fieldname, null, 'required');
            }
        }
    }

    /**
     *
     * {@inheritDoc}
     * @see datalynxfield_renderer::validate()
     */
    public function validate($entryid, $tags, $formdata) {
        $fieldid = $this->_field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";
        $required = true;

        $errors = array();
        foreach ($tags as $tag) {
            list(, $behavior, ) = $this->process_tag($tag);
            // Variable $behavior datalynx_field_behavior.
            if ($behavior->is_required() && (!isset($formdata->$formfieldname))) {
                $errors[$formfieldname] = get_string('fieldrequired', 'datalynx');
            }
        }

        return $errors;
    }
}
