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
 * @package mod-datalynx
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work  by 2011 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

/**
 * Define all the backup steps that will be used by the backup_datalynx_activity_task
 */

/**
 * Define the complete data structure for backup, with file and id annotations
 */
class backup_datalynx_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {
        global $DB;
        global $CFG;

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $datalynx = new backup_nested_element('datalynx', array('id'),
                array('name', 'intro', 'introformat', 'timemodified', 'timeavailable', 'timedue',
                        'timeinterval', 'intervalcount', 'allowlate', 'grade', 'grademethod',
                        'anonymous', 'notification', 'notificationformat', 'entriesrequired',
                        'entriestoview', 'maxentries', 'timelimit', 'approval', 'grouped', 'rating',
                        'comments', 'locks', 'singleedit', 'singleview', 'rssarticles', 'rss', 'css',
                        'cssincludes', 'js', 'jsincludes', 'defaultview', 'defaultfilter',
                        'completionentries'
                ));

        $module = new backup_nested_element('module', array('id'), array('groupmode'));

        $fields = new backup_nested_element('fields');
        $field = new backup_nested_element('field', array('id'),
                array('type', 'name', 'description', 'visible', 'edits', 'label', 'param1',
                        'param2', 'param3', 'param4', 'param5', 'param6', 'param7', 'param8', 'param9',
                        'param10', 'targetcourse', 'targetinstance', 'targetview', 'targetfilter'
                ));

        $filters = new backup_nested_element('filters');
        $filter = new backup_nested_element('filter', array('id'),
                array('name', 'description', 'visible', 'perpage', 'selection', 'groupby', 'search',
                        'customsort', 'customsearch'
                ));

        $views = new backup_nested_element('views');
        $view = new backup_nested_element('view', array('id'),
                array('type', 'name', 'description', 'visible', 'perpage', 'groupby', 'filter',
                        'patterns', 'section', 'sectionpos', 'param1', 'param2', 'param3', 'param4',
                        'param5', 'param6', 'param7', 'param8', 'param9', 'param10'
                ));

        $rules = new backup_nested_element('rules');
        $rule = new backup_nested_element('rule', array('id'),
                array('type', 'name', 'description', 'enabled', 'param1', 'param2', 'param3',
                        'param4', 'param5', 'param6', 'param7', 'param8', 'param9', 'param10'
                ));

        $entries = new backup_nested_element('entries');
        $entry = new backup_nested_element('entry', array('id'),
                array('userid', 'groupid', 'timecreated', 'timemodified', 'approved', 'status'
                ));

        $contents = new backup_nested_element('contents');
        $content = new backup_nested_element('content', array('id'),
                array('fieldid', 'content', 'content1', 'content2', 'content3', 'content4'));

        $tags = new backup_nested_element('tags');
        $tag = new backup_nested_element('tag', array('id'), array('rawname'));

        $ratings = new backup_nested_element('ratings');
        $rating = new backup_nested_element('rating', array('id'),
                array('component', 'ratingarea', 'scaleid', 'value', 'userid', 'timecreated',
                        'timemodified'
                ));

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', array('id'),
                array('component', 'ratingarea', 'scaleid', 'value', 'userid', 'timecreated',
                        'timemodified'
                ));

        $behaviors = new backup_nested_element('behaviors');
        $behavior = new backup_nested_element('behavior', array('id'),
                array('dataid', 'name', 'description', 'visibleto', 'editableby', 'required'
                ));

        $renderers = new backup_nested_element('renderers');
        $renderer = new backup_nested_element('renderer', array('id'),
                array('dataid', 'type', 'name', 'description', 'notvisibletemplate',
                        'displaytemplate', 'novaluetemplate', 'edittemplate', 'noteditabletemplate'
                ));

        // Build the tree.
        $datalynx->add_child($module);

        $datalynx->add_child($fields);
        $fields->add_child($field);

        $datalynx->add_child($filters);
        $filters->add_child($filter);

        $datalynx->add_child($views);
        $views->add_child($view);

        $datalynx->add_child($rules);
        $rules->add_child($rule);

        $datalynx->add_child($entries);
        $entries->add_child($entry);

        $entry->add_child($contents);
        $contents->add_child($content);

        $content->add_child($tags);
        $tags->add_child($tag);

        $entry->add_child($ratings);
        $ratings->add_child($rating);

        $datalynx->add_child($grades);
        $grades->add_child($grade);

        $datalynx->add_child($behaviors);
        $behaviors->add_child($behavior);

        $datalynx->add_child($renderers);
        $renderers->add_child($renderer);

        // Define sources.
        $datalynx->set_source_table('datalynx', array('id' => backup::VAR_ACTIVITYID));
        $module->set_source_table('course_modules', array('id' => backup::VAR_MODID));

        // Tags.
        $tag->set_source_sql('SELECT t.id, t.rawname
                        FROM {tag} t
                        JOIN {tag_instance} ti ON ti.tagid = t.id
                       WHERE ti.itemtype = ?
                         AND ti.component = ?
                         AND ti.itemid = ?', array(
                backup_helper::is_sqlparam('datalynx_contents'),
                backup_helper::is_sqlparam('mod_datalynx'),
                backup::VAR_PARENTID));

            // TODO: fix sql, this is just a temporary fix and does not provide same functionality for postgresql.
            // SQL for mysql provides id mapping of the field datalynx view, whereas there is no id mapping for postgresql.
            // Possible DBs: 'pgsql', 'mariadb', 'mysqli', 'mssql', 'sqlsrv' or 'oci'
            // The cases are weird, they are formated differently in mssql, postgresql, mysql, ... fix that.
            // else if ($CFG->dbtype == 'pgsql') {}
            /* SELECT *, case when rev=1 then 'blabla' end FROM docs works on mysql, mssql, postgresql */
        if ($CFG->dbtype == 'mysqli' || $CFG->dbtype == 'mysql' || $CFG->dbtype == 'mariadb') {
            $field->set_source_sql(
                    "SELECT f.*,
                        CASE f.type WHEN 'datalynxview' THEN MAX(c.fullname) ELSE NULL END AS targetcourse,
                        CASE f.type WHEN 'datalynxview' THEN MAX(d.name) ELSE NULL END AS targetinstance,
                        CASE f.type WHEN 'datalynxview' THEN MAX(v.name) ELSE NULL END AS targetview,
                        CASE f.type WHEN 'datalynxview' THEN MAX(fil.name) ELSE NULL END AS targetfilter
                   FROM {datalynx_fields} f
              LEFT JOIN {datalynx} d ON " . $DB->sql_cast_char2int('f.param1') . " = d.id
              LEFT JOIN {course_modules} cm ON cm.instance = d.id
              LEFT JOIN {course} c ON cm.course = c.id
              LEFT JOIN {datalynx_views} v ON " . $DB->sql_cast_char2int('f.param2') . " = v.id
              LEFT JOIN {datalynx_filters} fil ON " . $DB->sql_cast_char2int('f.param3') . " = fil.id
                  WHERE f.dataid = :dataid
               GROUP BY f.id", array('dataid' => backup::VAR_PARENTID));
        } else {
            $field->set_source_sql(
                    "SELECT f.*
               FROM {datalynx_fields} f
              WHERE f.dataid = :dataid
                AND f.type != 'datalynxview'", array('dataid' => backup::VAR_PARENTID));
        }

        $filter->set_source_table('datalynx_filters', array('dataid' => backup::VAR_PARENTID));
        $view->set_source_table('datalynx_views', array('dataid' => backup::VAR_PARENTID));
        $rule->set_source_table('datalynx_rules', array('dataid' => backup::VAR_PARENTID));
        $behavior->set_source_table('datalynx_behaviors', array('dataid' => backup::VAR_PARENTID));
        $renderer->set_source_table('datalynx_renderers', array('dataid' => backup::VAR_PARENTID));

        // All the rest of elements only happen if we are including user info.
        if ($userinfo) {
            $entry->set_source_table('datalynx_entries', array('dataid' => backup::VAR_PARENTID));
            $content->set_source_table('datalynx_contents',
                    array('entryid' => backup::VAR_PARENTID));

            // Entry ratings.
            $rating->set_source_table('rating',
                    array('contextid' => backup::VAR_CONTEXTID,
                            'itemid' => backup::VAR_PARENTID,
                            'component' => backup_helper::is_sqlparam('mod_datalynx'),
                            'ratingarea' => backup_helper::is_sqlparam('entry')
                    ));
            $rating->set_source_alias('rating', 'value');

            // Activity grade.
            $grade->set_source_table('rating',
                    array('contextid' => backup::VAR_CONTEXTID,
                            'component' => backup_helper::is_sqlparam('mod_datalynx'),
                            'ratingarea' => backup_helper::is_sqlparam('activity')
                    ));
            $grade->set_source_alias('rating', 'value');
        }

        // Define id annotations.
        $datalynx->annotate_ids('scale', 'grade');
        $datalynx->annotate_ids('scale', 'rating');

        $entry->annotate_ids('user', 'userid');
        $entry->annotate_ids('group', 'groupid');

        $rating->annotate_ids('scale', 'scaleid');
        $rating->annotate_ids('user', 'userid');

        // Define file annotations.
        $datalynx->annotate_files('mod_datalynx', 'intro', null); // This file area hasn't itemid.
        $view->annotate_files('mod_datalynx', 'viewsection', 'id'); // By view->id.
        for ($i = 2; $i <= 9; $i++) {
            $view->annotate_files('mod_datalynx', "viewparam{$i}", 'id'); // By view->id.
        }
        $content->annotate_files('mod_datalynx', 'content', 'id'); // By content->id.

        // Return the root element (data), wrapped into standard activity structure.
        return $this->prepare_activity_structure($datalynx);
    }
}
