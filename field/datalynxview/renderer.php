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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package datalynxfield
 * @subpackage datadformview
 * @copyright 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once ("$CFG->dirroot/mod/datalynx/filter/filter_class.php");
require_once ("$CFG->dirroot/mod/datalynx/field/renderer.php");
require_once ("$CFG->dirroot/mod/datalynx/field/entryauthor/field_class.php");
require_once ("$CFG->dirroot/mod/datalynx/field/entrygroup/field_class.php");


/**
 */
class datalynxfield_datalynxview_renderer extends datalynxfield_renderer {


    /*
     * Rendering this field in display mode
     * called by the replacement-function of datalynxfield_renderer
     * stdClass @entry   represents the entry which includes this instance of the field
     * array @params     the type of the display, "embedded" or "overlay" or "" for default
     * @returns          the function which displays this field-instance
     */
	public function render_display_mode(stdClass $entry, array $params) {
	    if(isset($params['embedded'])) {
	        $type = "embedded";
        } else if(isset($params['overlay'])) {
            $type = "overlay";
        } else {
            $type = "";
        }
		return $this->display_browse($entry, $type);
	}
	
    /**
     * Check, which type of display, and call the right display-function
     * stdClass @entry  the entry object which holds this field
     * string @type     the type of display
     */
    protected function display_browse($entry, $type = null) {
        $field = $this->_field;

        if (empty($field->refdatalynx) or empty($field->refview)) {
            return '';
        }

        // Inline
        if (empty($type)) {
            // TODO Including controls seems to mess up the hosting view controls
            $voptions = array('controls' => false);
            return $this->get_view_display_content($entry, $voptions);
        }

        // Overlay
        if ($type == 'overlay') {
            $this->add_overlay_support();
            $voptions = array('controls' => false);
            $widgetbody = html_writer::tag('div',
                    $this->get_view_display_content($entry, $voptions),
                    array('class' => "yui3-widget-bd"));
            $panel = html_writer::tag('div', $widgetbody, array('class' => 'panelContent hide'));
            $button = html_writer::tag('button',
                    get_string('viewbutton', 'datalynxfield_datalynxview'));
            $wrapper = html_writer::tag('div', $button . $panel,
                    array('class' => 'datalynxfield-datalynxview overlay'));
            return $wrapper;
        }

        // Embedded
        if ($type == 'embedded') {
            return $this->get_view_display_embedded($entry);
        }

        // Embedded Overlay
        if ($type == 'embeddedoverlay') {
            $this->add_overlay_support();

            $widgetbody = html_writer::tag('div', $this->get_view_display_embedded($entry),
                    array('class' => "yui3-widget-bd"));
            $panel = html_writer::tag('div', $widgetbody, array('class' => 'panelContent hide'));
            $button = html_writer::tag('button',
                    get_string('viewbutton', 'datalynxfield_datalynxview'));
            $wrapper = html_writer::tag('div', $button . $panel,
                    array('class' => 'datalynxfield-datalynxview embedded overlay'));
            return $wrapper;
        }

        return '';
    }

    /**
     * The default display-method, just inline html
     * stdClass @entry          the entry object this field belongs to
     * array string @options    array of options for the display-method of the view
     * @returns                 the display-method of the view
     */
    protected function get_view_display_content($entry, array $options = array()) {
        $field = $this->_field;

        $refdatalynx = $field->refdatalynx;
        $refview = $field->refview;

        // Options for setting the filter
        $foptions = array();

        // Search filter by entry author or group
        $foptions = $this->get_filter_by_options($foptions, $entry);

        if(!isset($foptions['eids'])) {
            return "";
        }

        $refview->set_filter($foptions, true);

        // Set the ref datalynx
        $params = array('js' => true, 'css' => true, 'modjs' => true, 'completion' => true,
            'comments' => true);

        // Ref datalynx page type defaults to external
        $refpagetype = !empty($options['pagetype']) ? $options['pagetype'] : 'external';
        $pageoutput = $refdatalynx->set_page('external', $params, true);

        $refview->set_content(array( 'filter' =>  $refview->get_filter()));
        // Set to return html
        $options['tohtml'] = true;
        $options['fieldview'] = true;
        $options['entryactions'] = false;
        return $refview->display($options);
    }

    /**
     * This display-method builds an iframe which holds a page with the view
     * stdClass @entry          the entry object this field belongs to
     * @returns                 the moodle display-method for an iframe
     */
    protected function get_view_display_embedded($entry) {
        $field = $this->_field;
        $fieldname = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $field->name()));

        // Construct the src url
        $params = array('d' => $field->refdatalynx->id(), 'view' => $field->refview->id());

        // Search filter by entry author or group or value
        $params = $this->get_filter_by_options($params, $entry, true);

        if(!isset($params['eids'])) {
            return "";
        }

        $srcurl = new moodle_url('/mod/datalynx/embed.php', $params);

        // Frame
        $froptions = array('src' => $srcurl, 'width' => '100%', 'height' => '100%',
            'style' => 'border:0;');
        $iframe = html_writer::tag('iframe', null, $froptions);
        return html_writer::tag('div', $iframe,
                array('class' => "datalynxfield-datalynxview-$fieldname embedded"));
    }

    /**
     */
    protected function add_overlay_support() {
        global $PAGE;

        static $added = false;

        if (!$added) {
            $module = array('name' => 'M.datalynxfield_datalynxview_overlay',
                'fullpath' => '/mod/datalynx/field/datalynxview/datalynxview.js',
                'requires' => array('base', 'node')
            );

            $PAGE->requires->js_init_call('M.datalynxfield_datalynxview_overlay.init', null, false,
                    $module);
        }
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

        if (!empty($field->field->param6)) { // param6: author,group
            list($filterauthor, $filtergroup) = explode(',', $field->field->param6);
            // Entry author
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
            // Entry group
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

        // With the ID of this datalynxview_field and the ID of the parent-entry we retrieve
        // the content to search for.
        // Then we get the entry-IDs of the entries (eids) which match this content-value
        // end add them to the options array
        if($fieldid = $field->field->param7) {  // field-ID of the external field
            if($content = $DB->get_field('datalynx_contents','content',
                array('entryid' => $entry->id, 'fieldid' => $field->field->id))) {
                list($insql, $params) = $DB->get_in_or_equal($content, SQL_PARAMS_NAMED);
                $params['fieldid'] = $fieldid;
                $sql = 'SELECT  c.entryid 
                        FROM    {datalynx_contents} c
                        WHERE   c.fieldid = :fieldid 
                        AND     c.content ' . $insql ;
                if($eids = $DB->get_fieldset_sql($sql,$params)) {
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
        $refview = $field->refview;

        $soptions = array();
        // Custom sort (ref-field-patten,ASC/DESC)
        if (!empty($field->field->param4)) {
            foreach (explode("\n", $field->field->param4) as $key => $sorty) {
                list($pattern, $dir) = explode(',', $sorty);
                // Get the field id from pattern
                if (!$rfieldid = $refview->get_pattern_fieldid($pattern)) {
                    continue;
                }
                // Convert direction to 0/1
                $dir = $dir == 'DESC' ? 1 : 0;
                $soptions[$rfieldid] = $dir;
            }
        }
        return $soptions;
    }

    /**
     */
    protected function get_search_options($entry) {
        $field = $this->_field;
        $soptions = array();

        // Custom search (AND/OR,ref-field-patten,[NOT],OPT,local-field-pattern/value
        if (empty($field->field->param5)) {
            return $soptions;
        }

        if (!$refdatalynx = $field->refdatalynx or !$refview = $field->refview or
                 !$localview = $field->localview) {
            return $soptions;
        }

        foreach (explode("\n", $field->field->param5) as $key => $searchy) {
            list($andor, $refpattern, $not, $operator, $localpattern) = explode(',', $searchy);
            // And/or
            if (empty($andor) or !in_array($andor, array('AND', 'OR'))) {
                continue;
            }
            // Get the ref field id from pattern
            if (!$rfieldid = $refview->get_pattern_fieldid($refpattern)) {
                continue;
            }
            // Get value for local pattern or use as value
            $value = '';
            if (!$localfieldid = $localview->get_pattern_fieldid($localpattern)) {
                $value = $localpattern;
            } else if ($localfield = $field->df->get_field_from_id($localfieldid)) {
                // Get the array of values for the patterns
                if ($replacements = $localfield->renderer()->replacements(array($localpattern), $entry)) {
                    // Take the first: array('html', value)
                    $first = reset($replacements);
                    // extract the value part
                    $value = $first[1];
                    // Make sure this is the search value
                    // (select fields search by key)
                    $value = $localfield->get_search_value($value);
                }
            }

            // Add to the search options
            if (empty($soptions[$rfieldid])) {
                $soptions[$rfieldid] = array('AND' => array(), 'OR' => array());
            }
            $soptions[$rfieldid][$andor][] = array($not, $operator, $value);
        }

        return $soptions;
    }

    /**
     * Array of patterns this field supports
     */
    protected function patterns() {
        $fieldname = $this->_field->name();

        $patterns = parent::patterns();
        $patterns["[[$fieldname]]"] = array(true);
        $patterns["[[$fieldname:overlay]]"] = array(true);
        $patterns["[[$fieldname:embedded]]"] = array(false);
        $patterns["[[$fieldname:embeddedoverlay]]"] = array(false);

        return $patterns;
    }

    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, array $options = null) {
        global $PAGE, $USER;

        /* @var $field datalynxfield_datalynxview */
        $field = $this->_field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $classname = "datalynxview_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);

        $selected = !empty($entry->{"c{$fieldid}_content"}) ? $entry->{"c{$fieldid}_content"} : 0;

        $authorid = isset($entry->userid) ? $entry->userid : $USER->id;

        // TODO replace with textfield-values
        if ($field->refdatalynx !== null && !empty($field->field->param7)) {
            $menu = array('' => get_string('choose')) +
                $field->refdatalynx->get_distinct_textfieldvalues_by_id($field->field->param7);
        }

        $mform->addElement('autocomplete', $fieldname, null, $menu, array('class' => "datalynxfield_datalynxview $classname"));
        $mform->setType($fieldname, PARAM_NOTAGS);
        $mform->setDefault($fieldname, $selected);
        if ($required) {
            $mform->addRule("{$fieldname}", '', 'required', null, 0, 'client');
        }
    }
}
