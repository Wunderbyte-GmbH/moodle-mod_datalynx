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
 * @subpackage tag
 * @copyright 2016 David Bogner
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once ("$CFG->dirroot/mod/datalynx/field/field_class.php");


class datalynxfield_tag extends datalynxfield_option_multiple {

    public $type = 'tag';
    
    /**
     * Write tags and and associate them with the id of the contents recordd
     *
     * @see datalynxfield_base::update_content()
     */
    public function update_content($entry, array $values = null) {
        global $DB;
        $entryid = $entry->id;
        $fieldid = $this->field->id;
        $contentid = isset($entry->{"c{$fieldid}_id"}) ? $entry->{"c{$fieldid}_id"} : null;
    
        if (empty($values)) {
            return true;
        }
        
        // $content is an array of tagnames
        $content = reset($values);
        $rec = new stdClass();
        $rec->fieldid = $fieldid;
        $rec->entryid = $entryid;
    
        if (!$rec->id = $contentid) {
            $rec->id = $DB->insert_record('datalynx_contents', $rec);
        }
        core_tag_tag::set_item_tags('mod_datalynx', 'datalynx_contents', $rec->id, $this->df->context, $content);
        $collid = core_tag_area::get_collection('mod_datalynx', 'datalynx_contents');
        if ($this->field->param1) {
        	$tagobjects = core_tag_tag::create_if_missing($collid, $content, true);
			// make standard tags
	        foreach ($tagobjects as $tagobject) {
	            if (!$tagobject->isstandard) {
	                $tagobject->update(array('isstandard' => 1));
	            }
	        }
        }

        if(!empty($content)){
            $content = implode(',',$content);
        }
        $rec->content = $content;
    
        return $DB->update_record('datalynx_contents', $rec);
    }
    
    /**
     * This is exact copy of parent::parent, because I can not access parent::parent::set_field();
     * {@inheritDoc}
     * @see datalynxfield_option::set_field()
     */
    public function set_field($forminput = null){
        $this->field = new stdClass();
        $this->field->id = !empty($forminput->id) ? $forminput->id : 0;
        $this->field->type = $this->type;
        $this->field->dataid = $this->df->id();
        $this->field->name = !empty($forminput->name) ? trim($forminput->name) : '';
        $this->field->description = !empty($forminput->description) ? trim($forminput->description) : '';
        $this->field->visible = isset($forminput->visible) ? $forminput->visible : 2;
        $this->field->edits = isset($forminput->edits) ? $forminput->edits : -1;
        $this->field->label = !empty($forminput->label) ? $forminput->label : '';
        for ($i = 1; $i <= 10; $i++) {
            $this->field->{"param$i"} = !empty($forminput->{"param$i"}) ? trim(
                    $forminput->{"param$i"}) : null;
        }    
    }
    
    
    /**
     * 
     * {@inheritDoc}
     * @see datalynxfield_option_multiple::get_search_sql()
     */
    public function get_search_sql($search) {
        global $DB;
    
        // $not is either empty or "NOT"
        list($not, $operator, $value) = $search;
    
        static $i = 0; // FIXME: might cause problems!
        $i++;
        $fieldid = $this->field->id;
        $name = "df_{$fieldid}_{$i}";
    
        $sql = '';
        $params = [];
        $conditions = [];
        $notinidsequal = false;
        
        $sql = 'SELECT DISTINCT dc.entryid
    	        FROM {datalynx_contents} dc
    	        JOIN {tag_instance} ti ON ti.itemid = dc.id
            	JOIN {tag} t ON ti.tagid = t.id
    	        JOIN {datalynx_entries} de ON de.id = dc.entryid
    	        JOIN {datalynx_fields} df ON df.id = dc.fieldid
    	        WHERE ti.itemtype = :itemtype
    	        AND ti.component = :component';
        $params = array('itemtype' => 'datalynx_contents', 'component' => 'mod_datalynx');
    
   
        if ($operator === 'EXACTLY' && empty($value)) {
            $operator = '';
        }
    
        if ($operator === 'ANY_OF' OR $operator === 'ALL_OF') {
            foreach ($value as $key => $searchstring) {
                $xname = $name . $key;
    
                $conditions[] = "t.rawname = :{$xname}";
                $params[$xname] = $searchstring;
            }
            if ($operator === 'ANY_OF') {
                $sql .= "AND (" . implode(" OR ", $conditions) . ") ";
            } else if ($operator === 'ALL_OF'){
                $sql .= "AND (" . implode(" AND ", $conditions) . ") ";
            }
        } else if ($operator === '') { // EMPTY
            // get all entry ids where tags are present and then add a NOT IN (these entry ids)
            // invert the $not operator in order to get correct results
            if ($not) {
                $not = null;
            } else {
                $not = "NOT";
            }
        }
        
        $entryids = $DB->get_fieldset_sql($sql, $params);
        $entryidsstr = implode(',', $entryids);
    
        return array(" e.id $not IN ($entryidsstr)", array(), false);
    }
    
    public function get_supported_search_operators() {
        return array('ANY_OF' => get_string('anyof', 'datalynx'),
                        'ALL_OF' => get_string('allof', 'datalynx'),
                        '' => get_string('empty', 'datalynx'));
    }
    
}
