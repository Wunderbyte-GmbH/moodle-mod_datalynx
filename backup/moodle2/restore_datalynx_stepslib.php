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
 * @package mod-datalynx
 * @copyright 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_datalynx_activity_task
 */

/**
 * Structure step to restore one datalynx activity
 */
class restore_datalynx_activity_structure_step extends restore_activity_structure_step {

    protected $groupmode = 0;
    
    /**
     *
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo'); // restore content and user info (requires the backup users)

        
        $paths[] = new restore_path_element('datalynx', '/activity/datalynx');
        $paths[] = new restore_path_element('datalynx_module', '/activity/datalynx/module');
        $paths[] = new restore_path_element('datalynx_field', '/activity/datalynx/fields/field');
        $paths[] = new restore_path_element('datalynx_filter', '/activity/datalynx/filters/filter');
        $paths[] = new restore_path_element('datalynx_view', '/activity/datalynx/views/view');
        $paths[] = new restore_path_element('datalynx_rule', '/activity/datalynx/rules/rule');
        $paths[] = new restore_path_element('datalynx_behavior', '/activity/datalynx/behaviors/behavior');
        $paths[] = new restore_path_element('datalynx_renderer', '/activity/datalynx/renderers/renderer');

        if ($userinfo) {
            $paths[] = new restore_path_element('datalynx_entry', '/activity/datalynx/entries/entry');
            $paths[] = new restore_path_element('datalynx_content', '/activity/datalynx/entries/entry/contents/content');
            $paths[] = new restore_path_element('datalynx_rating', '/activity/datalynx/entries/entry/ratings/rating');
            $paths[] = new restore_path_element('datalynx_grade', '/activity/datalynx/grades/grade');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    /**
     *
     */
    protected function process_datalynx($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timeavailable = $this->apply_date_offset($data->timeavailable);
        $data->timedue = $this->apply_date_offset($data->timedue);

        if ($data->grade < 0) { // scale found, get mapping
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        if ($data->rating < 0) { // scale found, get mapping
            $data->rating = -($this->get_mappingid('scale', abs($data->rating)));
        }

        $newitemid = $this->task->get_activityid();
        
        if ($newitemid) { 
            $data->id = $newitemid;
            $DB->update_record('datalynx', $data);
        } else {
            // insert the datalynx record
            $newitemid = $DB->insert_record('datalynx', $data);
        }
        $this->apply_activity_instance($newitemid);
    }

    /**
     * This must be invoked immediately after creating/updating the "module" activity record
     * and will adjust the new activity id (the instance) in various places
     * Overriding the parent method to handle restoring into the activity
     */
    protected function apply_activity_instance($newitemid) {
        global $DB;

        if ($newitemid == $this->task->get_activityid()) {
            // remap task module id
            $this->set_mapping('course_module', $this->task->get_old_moduleid(), $this->task->get_moduleid());
            // remap task context id
            $this->set_mapping('context', $this->task->get_old_contextid(), $this->task->get_contextid());
        } else {
            // Save activity id in task
            $this->task->set_activityid($newitemid); 
            // Apply the id to course_modules->instance
            $DB->set_field('course_modules', 'instance', $newitemid, array('id' => $this->task->get_moduleid()));
        }
        // Do the mapping for modulename, preparing it for files by oldcontext
        $oldid = $this->task->get_old_activityid();
        $this->set_mapping('datalynx', $oldid, $newitemid, true);
    }

    /**
     *
     */
    protected function process_datalynx_module($data) {
        global $DB;

        $data = (object)$data;
        // Adjust groupmode in course_modules->groupmode
        if (isset($data->groupmode)) {
            $DB->set_field('course_modules', 'groupmode', $data->groupmode, array('id' => $this->task->get_moduleid()));
        }
    }

    /**
     *
     */
    protected function process_datalynx_field($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->dataid = $this->get_new_parentid('datalynx');

        // restore view reference for datalynxview field type
        if ($data->type == 'datalynxview') {
            $data->param1 = $this->get_mappingid('datalynx', $data->param1);
            $data->param2 = $this->get_mappingid('datalynx_views', $data->param2);

            $course = isset($data->targetcourse) ? $data->targetcourse : 'NULL';
            $instance = isset($data->targetinstance) ? $data->targetinstance : 'NULL';
            $view = isset($data->targetview) ? $data->targetview : 'NULL';
            $filter = isset($data->targetfilter) ? $data->targetfilter : 'NULL';

            $this->log("WARNING! 'datalynxview' field type cannot be restored if referencing instances are not included in the backup!", backup::LOG_WARNING);
            $this->log("* Please verify the references of the field:", backup::LOG_WARNING);
            $this->log("* Field '$data->name' originally referenced: course '$course', instance '$instance', view '$view', filter '$filter'", backup::LOG_WARNING);
        }

        // insert the datalynx_fields record
        $newitemid = $DB->insert_record('datalynx_fields', $data);
        $this->set_mapping('datalynx_field', $oldid, $newitemid, true); // files by this item id
    }

    /**
     *
     */
    protected function process_datalynx_filter($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->dataid = $this->get_new_parentid('datalynx');

        // adjust groupby field id
        if ($data->groupby > 0) {
            $data->groupby = $this->get_mappingid('datalynx_field', $data->groupby);
        }
                    
        // adjust customsort field ids
        if ($data->customsort) {
            $customsort = unserialize($data->customsort);
            $sortfields = array();
            foreach ($customsort as $sortfield => $sortdir) {
                if ($sortfield > 0) {
                    $sortfields[$this->get_mappingid('datalynx_field', $sortfield)] = $sortdir;
                } else {
                    $sortfields[$sortfield] = $sortdir;
                }
            }
            $data->customsort = serialize($sortfields);
        }
                        
        // adjust customsearch field ids
        if ($data->customsearch) {
            $customsearch = unserialize($data->customsearch);
            $searchfields = array();
            foreach ($customsearch as $searchfield => $options) {
                if ($searchfield > 0) {
                    $searchfields[$this->get_mappingid('datalynx_field', $searchfield)] = $options;
                } else {
                    $searchfields[$searchfield] = $options;
                }
            }
            $data->customsearch = serialize($searchfields);
        }
        
        // insert the datalynx_filters record
        $newitemid = $DB->insert_record('datalynx_filters', $data);
        $this->set_mapping('datalynx_filter', $oldid, $newitemid, false); // no files associated
    }

    /**
     *
     */
    protected function process_datalynx_view($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->dataid = $this->get_new_parentid('datalynx');

        // adjust groupby field id
        if ($data->groupby > 0) {
            $data->groupby = $this->get_mappingid('datalynx_field', $data->groupby);
        }

        // adjust view filter id
        if ($data->filter) {
            $data->filter = $this->get_mappingid('datalynx_filter', $data->filter);
        }

        // adjust pattern field ids
        if ($data->patterns) {
            $patterns = unserialize($data->patterns);
            $newpatterns = array('view' => $patterns['view'], 'field' => array());
            foreach ($patterns['field'] as $fieldid => $tags) {
                if ($fieldid > 0) {
                    $newpatterns['field'][$this->get_mappingid('datalynx_field', $fieldid)] = $tags;
                } else {
                    $newpatterns['field'][$fieldid] = $tags;
                }
            }
            $data->patterns = serialize($newpatterns);
        }
        
        // insert the datalynx_views record
        $newitemid = $DB->insert_record('datalynx_views', $data);
        $this->set_mapping('datalynx_view', $oldid, $newitemid, true); // files by this item id
    }

    /**
     *
     */
    protected function process_datalynx_rule($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->dataid = $this->get_new_parentid('datalynx');

        // insert the datalynx_fields record
        $newitemid = $DB->insert_record('datalynx_rules', $data);
        $this->set_mapping('datalynx_rule', $oldid, $newitemid, false); // no files
    }

    /**
     *
     */
    protected function process_datalynx_entry($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->dataid = $this->get_new_parentid('datalynx');

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if ($userid = $this->task->get_ownerid()) {
            $data->userid = $userid;
        } else {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }
        $data->groupid = $this->get_mappingid('group', $data->groupid);

        // insert the datalynx_entries record
        $newitemid = $DB->insert_record('datalynx_entries', $data);
        $this->set_mapping('datalynx_entry', $oldid, $newitemid, false); // no files associated
    }

    /**
     *
     */
    protected function process_datalynx_content($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->fieldid = $this->get_mappingid('datalynx_field', $data->fieldid);
        $data->entryid = $this->get_new_parentid('datalynx_entry');

        // insert the data_content record
        $newitemid = $DB->insert_record('datalynx_contents', $data);
        $this->set_mapping('datalynx_content', $oldid, $newitemid, true); // files by this item id
    }

    /**
     *
     */
    protected function process_datalynx_rating($data) {
        $data = (object)$data;
        $data->itemid = $this->get_new_parentid('datalynx_entry');
        $this->process_this_rating($data);        
    }

    /**
     *
     */
    protected function process_datalynx_grade($data) {
        $data = (object)$data;
        $data->itemid = $this->get_mappingid('user', $data->itemid);
        $this->process_this_rating($data);        
    }

    /**
     *
     */
    protected function process_datalynx_behavior($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->dataid = $this->get_new_parentid('datalynx');

        // insert the datalynx_fields record
        $newitemid = $DB->insert_record('datalynx_behaviors', $data);
        $this->set_mapping('datalynx_behavior', $oldid, $newitemid, false); // no files
    }

    /**
     *
     */
    protected function process_datalynx_renderer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->dataid = $this->get_new_parentid('datalynx');

        // insert the datalynx_fields record
        $newitemid = $DB->insert_record('datalynx_renderers', $data);
        $this->set_mapping('datalynx_renderer', $oldid, $newitemid, false); // no files
    }

    /**
     *
     */
    protected function process_this_rating($data) {
        global $DB;
        $data = (object)$data;

        $data->contextid = $this->task->get_contextid();
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->rating = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('rating', $data);
    }

    /**
     *
     */
    protected function after_execute() {
        global $DB;

        // Add data related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_datalynx', 'intro', null);

        // Add content related files, matching by item id (datalynx_content)
        $this->add_related_files('mod_datalynx', 'content', 'datalynx_content');

        // Add content related files, matching by item id (datalynx_view)
        // TODO it's not quite item id; need to add folders there
        $this->add_related_files('mod_datalynx', 'view', 'datalynx_view');

        // TODO Add preset related files, matching by itemname (data_content)
        //$this->add_related_files('mod_datalynx', 'course_presets', 'datalynx');

        // Add view template related files, matching by item id (datalynx_view)
        $this->add_related_files('mod_datalynx', 'viewsection', 'datalynx_view');

        // Add entry template related files, matching by item id (datalynx_view)
        for ($i = 2; $i <= 9; $i++) {
            $this->add_related_files('mod_datalynx', "viewparam{$i}", 'datalynx_view');
        }


        $datalynxnewid = $this->get_new_parentid('datalynx');

        // default view
        if ($defaultview = $DB->get_field('datalynx', 'defaultview', array('id' => $datalynxnewid))) {
            if ($defaultview = $this->get_mappingid('datalynx_view', $defaultview)) {
                $DB->set_field('datalynx', 'defaultview', $defaultview, array('id' => $datalynxnewid));
            }
        }

        // default filter
        if ($defaultfilter = $DB->get_field('datalynx', 'defaultfilter', array('id' => $datalynxnewid))) {
            if ($defaultfilter = $this->get_mappingid('datalynx_filter', $defaultfilter)) {
                $DB->set_field('datalynx', 'defaultfilter', $defaultfilter, array('id' => $datalynxnewid));
            }
        }

        // single edit view
        if ($singleedit = $DB->get_field('datalynx', 'singleedit', array('id' => $datalynxnewid))) {
            if ($singleedit = $this->get_mappingid('datalynx_view', $singleedit)) {
                $DB->set_field('datalynx', 'singleedit', $singleedit, array('id' => $datalynxnewid));
            }
        }

        // single view
        if ($singleview = $DB->get_field('datalynx', 'singleview', array('id' => $datalynxnewid))) {
            if ($singleview = $this->get_mappingid('datalynx_view', $singleview)) {
                $DB->set_field('datalynx', 'singleview', $singleview, array('id' => $datalynxnewid));
            }
        }

        // Update group mode if the original was set to internal mode

        // Update teammmemberselect user ids
        $sqllike = $DB->sql_like('df.type', ':type', false);
        $sql = "SELECT dc.id, dc.content
                  FROM {datalynx_contents} dc
            INNER JOIN {datalynx_fields} df ON dc.fieldid = df.id
                 WHERE $sqllike
                   AND df.dataid = :dataid";
        $results = $DB->get_records_sql_menu($sql, array('type' => 'teammemberselect', 'dataid' => $datalynxnewid));
        foreach ($results as $id => $content) {
            $users = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE && is_array($users)) {
                $newusers = array();
                foreach($users as $user) {
                    if ($user) {
                        $newuser = $this->get_mappingid('user', $user);
                        if ($newuser) {
                            $newusers[] = $newuser;
                        } else {
                            $newusers[] = $user; // WARNING: hack for restoring into same instance w/o course data
                        }
                    }
                }
                $newcontent = json_encode($newusers);
                $DB->set_field('datalynx_contents', 'content', $newcontent, array('id' => $id));
            }
        }

        // Update teammmemberselect reference field ids
        $sqllike = $DB->sql_like('df.type', ':type', false);
        $sql = "SELECT df.id, df.param5
                  FROM {datalynx_fields} df
                 WHERE $sqllike
                   AND df.dataid = :dataid
                   AND " . $DB->sql_cast_char2int('df.param5') . " NOT IN (0, -1)";
        $results = $DB->get_records_sql_menu($sql, array('type' => 'teammemberselect', 'dataid' => $datalynxnewid));
        foreach ($results as $id => $referencefieldid) {
            $newreferencefieldid = $this->get_mappingid('datalynx_field', $referencefieldid);
            if ($newreferencefieldid) {
                $DB->set_field('datalynx_fields', 'param5', $newreferencefieldid, array('id' => $id));
            }
        }

        // Update redirect on submit ids
        $sql = "SELECT dv.id, dv.param10
                  FROM {datalynx_views} dv
                 WHERE dv.dataid = :dataid";
        $results = $DB->get_records_sql_menu($sql, array('dataid' => $datalynxnewid));
        foreach ($results as $id => $redirectid) {
            $newredirectid = $this->get_mappingid('datalynx_view', $redirectid);
            if ($newredirectid) {
                $DB->set_field('datalynx_views', 'param10', $newredirectid, array('id' => $id));
            }
        }

        // Update id of userinfo fields if needed
        // TODO can we condition this on restore to new site?
        if ($userinfofields = $DB->get_records('datalynx_fields', array('dataid' => $datalynxnewid, 'type' => 'userinfo'), '', 'id,param1,param2')) {
            foreach ($userinfofields as $fieldid => $uifield) {
                $infoid = $DB->get_field('user_info_field', 'id', array('shortname' => $uifield->param2));
                if ($infoid != (int) $uifield->param1) {
                    $DB->set_field('datalynx_fields', 'param1', $infoid, array('id' => $fieldid));
                }
            }
        }

    }
}
