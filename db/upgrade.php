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
 * This file keeps track of upgrades to
 * the datalynx module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 */

function xmldb_datalynx_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // Moodle v2.1.0 release upgrade line
    if ($oldversion < 2012032100) {
        // add field selection to datalynx_filters
        $table = new xmldb_table('datalynx_filters');
        $field = new xmldb_field('selection', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'perpage');

        // Launch add field selection
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012032100, 'datalynx');
    
    
    }

    if ($oldversion < 2012040600) {
        // add field edits to datalynx_fields
        $table = new xmldb_table('datalynx_fields');
        $field = new xmldb_field('edits', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '-1', 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012040600, 'datalynx');
    }

    if ($oldversion < 2012050500) {
        // drop field comments from datalynx
        $table = new xmldb_table('datalynx');
        $field = new xmldb_field('comments');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        // drop field locks
        $field = new xmldb_field('locks');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        // add field rules
        $field = new xmldb_field('rules', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'rating');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012050500, 'datalynx');
    }

    if ($oldversion < 2012051600) {
        // drop field grading from entries
        $table = new xmldb_table('datalynx_entries');
        $field = new xmldb_field('grading');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012051600, 'datalynx');
    }

    if ($oldversion < 2012053100) {
        $table = new xmldb_table('datalynx');

        // add field cssincludes
        $field = new xmldb_field('cssincludes', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'css');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // add field jsincludes
        $field = new xmldb_field('jsincludes', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'js');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012053100, 'datalynx');
    }

    if ($oldversion < 2012060101) {
        // changed stored content of view editors from serialized to formatted string
        // Assumed at this point that serialized content in param fields in the
        // view table is editor content which needs to be unserialized to 
        // $text, $format, $trust and restored as "ft:{$format}tr:{$trust}ct:$text" 
        
        // Get all views
        if ($views = $DB->get_records('datalynx_views')) {
            foreach ($views as $view) {
                $update = false;
                // section field
                if (!empty($view->section)) {
                    $editordata = @unserialize($view->section);
                    if ($editordata !== false) {
                        list($text, $format, $trust) = $editordata;
                        $view->section = "ft:{$format}tr:{$trust}ct:$text";
                        $update = true;
                    }
                }                
                // 10 param fields
                for ($i = 1; $i <= 10; ++$i) {
                    $param = "param$i";
                    if (!empty($view->$param)) {
                        $editordata = @unserialize($view->$param);
                        if ($editordata !== false) {
                            list($text, $format, $trust) = $editordata;
                            $view->$param = "ft:{$format}tr:{$trust}ct:$text";
                            $update = true;
                        }
                    }
                } 
                if ($update) {
                    $DB->update_record('datalynx_views', $view);
                }
            }
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012060101, 'datalynx');
    }

    if ($oldversion < 2012061700) {
        // Remove version record of datalynx views and fields from config_plugin
        $DB->delete_records_select('config_plugins', $DB->sql_like('plugin', '?'), array('datalynx%'));
        // Change type of view block/blockext to matrix/matrixext
        $DB->set_field('datalynx_views', 'type', 'matrix', array('type' => 'block'));
        $DB->set_field('datalynx_views', 'type', 'matrixext', array('type' => 'blockext'));
        
        // Move content of matrixext param1 -> param4 and param3 -> param5 
       if ($views = $DB->get_records('datalynx_views', array('type' => 'matrixext'))) {
            foreach ($views as $view) {
                if (!empty($view->param1) or !empty($view->param3)) {
                    $view->param4 = $view->param1;
                    $view->param5 = $view->param3;
                    $view->param1 = null;
                    $view->param3 = null;
                    $DB->update_record('datalynx_views', $view);
                }
            }
        }
        
        // Move content of editon param3 -> param7 
       if ($views = $DB->get_records('datalynx_views', array('type' => 'editon'))) {
            foreach ($views as $view) {
                if (!empty($view->param3)) {
                    $view->param7 = $view->param3;
                    $view->param1 = null;
                    $view->param3 = null;
                    $DB->update_record('datalynx_views', $view);
                }
            }
        }
        
        // Move content of tabular param1 -> param3 
       if ($views = $DB->get_records('datalynx_views', array('type' => 'tabular'))) {
            foreach ($views as $view) {
                $view->param3 = $view->param1;
                $view->param1 = null;
                $DB->update_record('datalynx_views', $view);
            }
        }
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012061700, 'datalynx');
    }

    if ($oldversion < 2012070601) {
        // add field default filter to datalynx
        $table = new xmldb_table('datalynx');
        $field = new xmldb_field('defaultfilter', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'defaultview');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Move content of datalynx->defaultsort to a new default filter
       if ($datalynxs = $DB->get_records('datalynx')) {
            $strdefault = get_string('default');
            foreach ($datalynxs as $dfid => $datalynx) {
                if (!empty($datalynx->defaultsort)) {
                    // Add a new 'Default filter' filter
                    $filter = new object;
                    $filter->dataid = $dfid;
                    $filter->name = $strdefault. '_0';
                    $filter->description = '';
                    $filter->visible = 0;
                    $filter->customsort = $datalynx->defaultsort;

                    if ($filterid = $DB->insert_record('datalynx_filters', $filter)) {
                        $DB->set_field('datalynx', 'defaultfilter', $filterid, array('id' => $dfid));
                    }
                }
            }
        }
        
        // drop datalynx field defaultsort
        $field = new xmldb_field('defaultsort');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012070601, 'datalynx');
    }

    if ($oldversion < 2012081801) {
        // add field visible to datalynx_fields
        $table = new xmldb_table('datalynx_fields');
        $field = new xmldb_field('visible', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '2', 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012081801, 'datalynx');
    }

    if ($oldversion < 2012082600) {
        // Change timelimit field to signed, default -1
        $table = new xmldb_table('datalynx_fields');
        $field = new xmldb_field('timelimit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '-1', 'maxentries');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_unsigned($table, $field);
            $dbman->change_field_default($table, $field);
        }
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012082600, 'datalynx');
    }

    if ($oldversion < 2012082900) {
        $fs = get_file_storage();
        // Move presets from course_packages to course_presets
        if ($datalynxs = $DB->get_records('datalynx')) {
            foreach ($datalynxs as $df) {
                $context = context_course::instance($df->course);
                if ($presets = $fs->get_area_files($context->id, 'mod_datalynx', 'course_packages')) {
                
                    $filerecord = new object;
                    $filerecord->contextid = $context->id;
                    $filerecord->component = 'mod_datalynx';
                    $filerecord->filearea = 'course_presets';
                    $filerecord->filepath = '/';

                    foreach ($presets as $preset) {
                        if (!$preset->is_directory()) {
                            $fs->create_file_from_storedfile($filerecord, $preset);
                        }
                    }
                    $fs->delete_area_files($context->id, 'mod_datalynx', 'course_packages');
                }
            }
        }

        // Move presets from site_packages to site_presets
        $filerecord = new object;
        $filerecord->contextid = SYSCONTEXTID;
        $filerecord->component = 'mod_datalynx';
        $filerecord->filearea = 'site_presets';
        $filerecord->filepath = '/';

        if ($presets = $fs->get_area_files(SYSCONTEXTID, 'mod_datalynx', 'course_packages')) {
            foreach ($presets as $preset) {
                if (!$preset->is_directory()) {
                    $fs->create_file_from_storedfile($filerecord, $preset);
                }
            }
        }
        $fs->delete_area_files(SYSCONTEXTID, 'mod_datalynx', 'site_packages');

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012082900, 'datalynx');
    }

    if ($oldversion < 2012092002) {
        // Add rules table
        $table = new xmldb_table('datalynx_rules');
        if (!$dbman->table_exists($table)) {
            $filepath = "$CFG->dirroot/mod/datalynx/db/install.xml";
            $dbman->install_one_table_from_xmldb_file($filepath, 'datalynx_rules');
        }
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012092002, 'datalynx');
    }

    if ($oldversion < 2012092207) {
        // Change type of view matrix/matrixext to grid/gridext
        $DB->set_field('datalynx_views', 'type', 'grid', array('type' => 'matrix'));
        $DB->set_field('datalynx_views', 'type', 'gridext', array('type' => 'matrixext'));
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012092207, 'datalynx');
    }

    if ($oldversion < 2012121600) {
        // Convert internal field ids whereever they are cached or referenced
        $newfieldids = array(
            -1 => 'entry',
            -2 => 'timecreated',
            -3 => 'timemodified',
            -4 => 'approve',
            -5 => 'group',
            -6 => 'userid',
            -7 => 'username',
            -8 => 'userfirstname',
            -9 => 'userlastname',
            -10 => 'userusername',
            -11 => 'useridnumber',
            -12 => 'userpicture',
            -13 => 'comment',
            -14 => 'rating',
            -141 => 'ratingavg',
            -142 => 'ratingcount',
            -143 => 'ratingmax',
            -144 => 'ratingmin',
            -145 => 'ratingsum',
        );
        
        // View patterns
        if ($views = $DB->get_records('datalynx_views')) {
            foreach ($views as $view) {
                $update = false;
                if ($view->patterns) {
                    $patterns = unserialize($view->patterns);
                    $newpatterns = array('view' => $patterns['view'], 'field' => array());
                    foreach ($patterns['field'] as $fieldid => $tags) {
                        if ($fieldid < 0 and !empty($newfieldids[$fieldid])) {
                            $newpatterns['field'][$newfieldids[$fieldid]] = $tags;
                            $update = true;
                        } else {
                            $newpatterns['field'][$fieldid] = $tags;
                        }
                    }
                    $view->patterns = serialize($newpatterns);
                }
                if ($update) {
                    $DB->update_record('datalynx_views', $view);
                }
            }
        }
        // Filter customsort and customsearch
        if ($filters = $DB->get_records('datalynx_filters')) {
            foreach ($filters as $filter) {
                $update = false;

                // adjust customsort field ids
                if ($filter->customsort) {
                    $customsort = unserialize($filter->customsort);
                    $sortfields = array();
                    foreach ($customsort as $fieldid => $sortdir) {
                        if ($fieldid < 0 and !empty($newfieldids[$fieldid])) {
                            $sortfields[$newfieldids[$fieldid]] = $sortdir;
                            $update = true;
                        } else {
                            $sortfields[$fieldid] = $sortdir;
                        }
                    }
                    $filter->customsort = serialize($sortfields);
                }
                                
                // adjust customsearch field ids
                if ($filter->customsearch) {
                    $customsearch = unserialize($filter->customsearch);
                    $searchfields = array();
                    foreach ($customsearch as $fieldid => $options) {
                        if ($fieldid < 0 and !empty($newfieldids[$fieldid])) {
                            $searchfields[$newfieldids[$fieldid]] = $options;
                            $update = true;
                        } else {
                            $searchfields[$fieldid] = $options;
                        }
                    }
                    $filter->customsearch = serialize($searchfields);
                }
                if ($update) {
                    $DB->update_record('datalynx_filters', $filter);
                }
            }
        }        
        
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012121600, 'datalynx');
    }

    if ($oldversion < 2012121900) {

        // Changing type of field groupby on table datalynx_views to char.
        $table = new xmldb_table('datalynx_views');
        $field = new xmldb_field('groupby', XMLDB_TYPE_CHAR, '64', null, null, null, '', 'perpage');
        $dbman->change_field_type($table, $field);

        // Changing type of field groupby on table datalynx_filters to char.
        $table = new xmldb_table('datalynx_filters');
        $field = new xmldb_field('groupby', XMLDB_TYPE_CHAR, '64', null, null, null, '', 'selection');
        $dbman->change_field_type($table, $field);

        // Change groupby 0 to null in existing views and filters
        $DB->set_field('datalynx_views', 'groupby', null, array('groupby' => 0));
        $DB->set_field('datalynx_filters', 'groupby', null, array('groupby' => 0));

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2012121900, 'datalynx');
    }

    if ($oldversion < 2013051101) {
        // Add notification format column to datalynx
        $table = new xmldb_table('datalynx');
        $field = new xmldb_field('notificationformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'notification');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add label column to datalynx fields
        $table = new xmldb_table('datalynx_fields');
        $field = new xmldb_field('label', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'edits');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2013051101, 'datalynx');
    }

    if ($oldversion < 2013051102) {
        // add field selection to datalynx_entries
        $table = new xmldb_table('datalynx_entries');
        $field = new xmldb_field('status', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'approved');

        // Launch add field selection
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2013051102, 'datalynx');


    }

    if ($oldversion < 2013051103) {
        // add field selection to datalynx_entries
        $table = new xmldb_table('datalynx');
        $field = new xmldb_field('completionentries', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'defaultfilter');

        // Launch add field selection
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2013051103, 'datalynx');


    }

    if ($oldversion < 2013082800) {

        // Changing precision of field visible on table datalynx_views to (4).
        $table = new xmldb_table('datalynx_views');
        $field = new xmldb_field('visible', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'description');

        // Launch change of precision for field visible.
        $dbman->change_field_precision($table, $field);

        $DB->set_field('datalynx_views', 'visible', '15', array('visible' => '2'));
        $DB->set_field('datalynx_views', 'visible', '1', array('visible' => '1'));

        // Datalynx savepoint reached.
        upgrade_mod_savepoint(true, 2013082800, 'datalynx');
    }

    if ($oldversion < 2014010700) {
        $query = "UPDATE {datalynx_views} dv
                     SET dv.param10 = dv.param4
                   WHERE dv.param10 IS NULL
                     AND dv.param4 IS NOT NULL
                     AND dv.param4 <> '0'";
        $DB->execute($query);

        // Datalynx savepoint reached.
        upgrade_mod_savepoint(true, 2014010700, 'datalynx');
    }

    if ($oldversion < 2014010701) {
        $query = "UPDATE {datalynx_views} dv
                     SET dv.type = 'grid',
                         dv.section = CASE
                                          WHEN dv.param4 IS NULL AND dv.param5 IS NULL THEN CONCAT(dv.section, '##entries##')
                                          WHEN dv.param4 IS NOT NULL AND dv.param5 IS NULL THEN CONCAT(dv.section, dv.param4, '##entries##')
                                          WHEN dv.param4 IS NULL AND dv.param5 IS NOT NULL THEN CONCAT(dv.section, '##entries##', dv.param5)
                                          ELSE CONCAT(dv.section, dv.param4, '##entries##', dv.param5)
                                      END,
                         dv.param4 = NULL,
                         dv.param5 = NULL
                   WHERE dv.type = 'gridext'";
        $DB->execute($query);

        // Datalynx savepoint reached.
        upgrade_mod_savepoint(true, 2014010701, 'datalynx');
    }

    if ($oldversion < 2014031401) {
        $query = "UPDATE {datalynx_contents} c
                     SET c.content = CONCAT('#', c.content, '#')
                   WHERE c.fieldid IN (SELECT id
                                       FROM {datalynx_fields} f
                                       WHERE f.type = 'checkbox' OR f.type = 'multiselect')";
        $DB->execute($query);

        // Datalynx savepoint reached.
        upgrade_mod_savepoint(true, 2014031401, 'datalynx');
    }

    if ($oldversion < 2014102101) {
        $table = new xmldb_table('datalynx_behaviors');
        if (!$dbman->table_exists($table)) {
            $filepath = "$CFG->dirroot/mod/datalynx/db/install.xml";
            $dbman->install_one_table_from_xmldb_file($filepath, 'datalynx_behaviors');
        }
        mod_datalynx_replace_field_rules();
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2014102101, 'datalynx');
    }

    if ($oldversion < 2014111501) {
        $table = new xmldb_table('datalynx_renderers');
        if (!$dbman->table_exists($table)) {
            $filepath = "$CFG->dirroot/mod/datalynx/db/install.xml";
            $dbman->install_one_table_from_xmldb_file($filepath, 'datalynx_renderers');
        }
        mod_datalynx_replace_field_labels();
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2014111501, 'datalynx');
    }

    if ($oldversion < 2015011101) {
        $views = $DB->get_records('datalynx_views');
        foreach ($views as $view) {
            if (strpos($view->section, '##entries##') === false) {
                $view->section .= '##entries##';
                $DB->update_record('datalynx_views', $view);
            }
        }
        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015011101, 'datalynx');
    }

    if ($oldversion < 2015011802) {
        $teamfields = $DB->get_records_sql("SELECT f.* FROM {datalynx_fields} f WHERE f.type = 'teammemberselect'");

        $map = [
            1 => 1,
            2 => 1,
            3 => 2,
            4 => 2,
            5 => 4,
            6 => 8,
            7 => 8,
            8 => 8
        ];

        foreach ($teamfields as $teamfield) {
            $perms = [];
            $roles = json_decode($teamfield->param2, true);
            foreach ($roles as $roleid) {
                if (isset($map[$roleid])) {
                    $perms[] = $map[$roleid];
                } else {
                    $perms[] = 8;
                }
            }
            $teamfield->param2 = json_encode(array_unique($perms));
            $DB->update_record('datalynx_fields', $teamfield);
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015011802, 'datalynx');
    }

    if ($oldversion < 2015030801) {
        $sql = "SELECT c.id, c.content
                  FROM {datalynx_contents} c
            INNER JOIN {datalynx_fields} f ON c.fieldid = f.id
                 WHERE f.type = 'checkbox'";

        $contents = $DB->get_records_sql_menu($sql);
        foreach ($contents as $id => $content) {
            if (preg_match('/^#+$/', $content)) {
                $DB->delete_records('datalynx_contents', array('id' => $id));
            }
        }

        $sql = "SELECT c.id, c.content
                  FROM {datalynx_contents} c
            INNER JOIN {datalynx_fields} f ON c.fieldid = f.id
                 WHERE f.type = 'teammemberselect'";

        $contents = $DB->get_records_sql_menu($sql);
        foreach ($contents as $id => $content) {
            if (preg_match('/^\[(?:\"0\",?)*\]$/', $content)) {
                $DB->delete_records('datalynx_contents', array('id' => $id));
            }
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015030801, 'datalynx');
    }

    if ($oldversion < 2015030901) {
        $sql = "SELECT c.id, c.content
                  FROM {datalynx_contents} c
            INNER JOIN {datalynx_fields} f ON c.fieldid = f.id
                 WHERE f.type = 'select'";

        $contents = $DB->get_records_sql_menu($sql);
        foreach ($contents as $id => $content) {
            if (!$content) {
                $DB->delete_records('datalynx_contents', array('id' => $id));
            }
        }

        $sql = "SELECT c.id, c.content
                  FROM {datalynx_contents} c
            INNER JOIN {datalynx_fields} f ON c.fieldid = f.id
                 WHERE f.type = 'time'";

        $contents = $DB->get_records_sql_menu($sql);
        foreach ($contents as $id => $content) {
            if (!$content) {
                $DB->delete_records('datalynx_contents', array('id' => $id));
            }
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015030901, 'datalynx');
    }

    if ($oldversion < 2015030902) {
        $sql = "SELECT c.id, c.content
                  FROM {datalynx_contents} c
            INNER JOIN {datalynx_fields} f ON c.fieldid = f.id
                 WHERE f.type = 'duration'";

        $contents = $DB->get_records_sql_menu($sql);
        foreach ($contents as $id => $content) {
            if (!$content && "$content" !== "0") {
                $DB->delete_records('datalynx_contents', array('id' => $id));
            }
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015030902, 'datalynx');
    }

    if ($oldversion < 2015032204) {
        $views = $DB->get_records('datalynx_views');
        foreach ($views as $view) {
            if (strpos($view->param2, ':addnew]]') !== false) {
                $view->param2 = str_replace(':addnew]]', ']]', $view->param2);
                $DB->update_record('datalynx_views', $view);
            }
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015032204, 'datalynx');
    }

    if ($oldversion < 2015032207) {

        $sqllike = $DB->sql_like('f.type', ':type');
        $sql = "SELECT f.* FROM {datalynx_fields} f WHERE $sqllike";

        $checkboxfields = $DB->get_fieldset_sql($sql, ['type' => 'checkbox']);
        $radiofields = $DB->get_fieldset_sql($sql, ['type' => 'radiobutton']);

        $filtersearchfields = $DB->get_records_sql_menu("SELECT id, customsearch FROM {datalynx_filters} WHERE 1");
        foreach ($filtersearchfields as $filterid => $serializedcustomsearch) {
            $customsearch = unserialize($serializedcustomsearch);
            $newcustomsearch = (array)(object)$customsearch;
            if (is_array($customsearch)){
                foreach ($customsearch as $fieldid => $queries) {
                    if (in_array($fieldid, $checkboxfields)) {
                        foreach ($queries as $subid => $sub) {
                            foreach ($sub as $queryid => $query) {
                                if ($query[1] !== '' && $query[1] !== 'ANY_OF' && $query[1] !== 'ALL_OF' && $query[1] !== 'EXACTLY') {
                                    $newcustomsearch[$fieldid][$subid][$queryid][1] = 'EXACTLY';
                                }
                                if (isset($query[2]['selected'])) {
                                    $newcustomsearch[$fieldid][$subid][$queryid][2] = $query[2]['selected'];
                                }
                            }
                        }
                    } else if (in_array($fieldid, $radiofields)) {
                        foreach ($queries as $subid => $sub) {
                            foreach ($sub as $queryid => $query) {
                                if ($query[1] !== '' && $query[1] !== 'ANY_OF') {
                                    $newcustomsearch[$fieldid][$subid][$queryid][1] = 'EXACTLY';
                                }
                            }
                        }
                    }
                }    
            }
            $serializedcustomsearch = serialize($newcustomsearch);
            $DB->set_field('datalynx_filters', 'customsearch', $serializedcustomsearch, array('id' => $filterid));
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015032207, 'datalynx');
    }

    if ($oldversion < 2015032208) {
        $instances = $DB->get_records('datalynx');
        foreach ($instances as $instance) {
            $views = $DB->get_records('datalynx_views', array('dataid' => $instance->id));
            $moreview = $DB->get_record('datalynx_views', array('id' => $instance->singleview));
            if ($moreview) {
                foreach ($views as $view) {
                    $view->section = preg_replace('/\<a.*##moreurl##[^>]*\>(.+)\<\/a\>/', "#{{viewlink:{$moreview->name};$1;;}}#", $view->section);
                    $view->param2 = preg_replace('/\<a.*##moreurl##[^>]*\>(.+)\<\/a\>/', "#{{viewlink:{$moreview->name};$1;;}}#", $view->param2);
                    $DB->update_record('datalynx_views', $view);
                }
            }
        }

        // datalynx savepoint reached
        upgrade_mod_savepoint(true, 2015032208, 'datalynx');
    }

    return true;
}

function mod_datalynx_replace_field_rules() {
    require_once(dirname(__FILE__) . '/../behavior/behavior.php');
    global $DB;

    $defaultbehavior = (object) array(
        'name' => '',
        'description' => '',
        'visibleto' => array(
            datalynx::PERMISSION_MANAGER,
            datalynx::PERMISSION_TEACHER,
            datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR,),
        'editableby' => array(
            datalynx::PERMISSION_MANAGER,
            datalynx::PERMISSION_TEACHER,
            datalynx::PERMISSION_STUDENT,
            datalynx::PERMISSION_AUTHOR,),
        'required' => false,
    );
    $dataids = $DB->get_fieldset_select('datalynx', 'id', "id IS NOT NULL");
    foreach ($dataids as $dataid) {
        $views = $DB->get_records('datalynx_views', array('dataid' => $dataid), '', 'id, param2');
        foreach ($views as $view) {
            $changed = false;

            $regex = '/\[\[\*([^\]]+)\]\]/';
            $matches = array();
            if (preg_match_all($regex, $view->param2, $matches, PREG_SET_ORDER)) {
                $behavior = $defaultbehavior;
                $behavior->name = get_string('required', 'datalynx');
                $behavior->required = true;
                $behavior->d = $dataid;
                datalynx_field_behavior::insert_behavior($behavior);

                foreach ($matches as $match) {
                    $view->param2 = str_replace($match[0], "[[{$match[1]}|{$behavior->name}]]", $view->param2);
                }

                $changed = true;
            }

            $regex = '/\[\[\^([^\]]+)\]\]/';
            $matches = array();
            if (preg_match_all($regex, $view->param2, $matches, PREG_SET_ORDER)) {
                $behavior = $defaultbehavior;
                $behavior->name = get_string('hidden', 'datalynx');
                $behavior->visibleto = array();
                $behavior->d = $dataid;
                datalynx_field_behavior::insert_behavior($behavior);

                foreach ($matches as $match) {
                    $view->param2 = str_replace($match[0], "[[{$match[1]}|{$behavior->name}]]", $view->param2);
                }

                $changed = true;
            }

            $regex = '/\[\[\!([^\]]+)\]\]/';
            $matches = array();
            if (preg_match_all($regex, $view->param2, $matches, PREG_SET_ORDER)) {
                $behavior = $defaultbehavior;
                $behavior->name = get_string('noedit', 'datalynx');
                $behavior->editableby = array();
                $behavior->d = $dataid;
                datalynx_field_behavior::insert_behavior($behavior);

                foreach ($matches as $match) {
                    $view->param2 = str_replace($match[0], "[[{$match[1]}|{$behavior->name}]]", $view->param2);
                }

                $changed = true;
            }

            if ($changed) {
                $DB->update_record('datalynx_views', $view);
            }
        }
    }
}

function mod_datalynx_replace_field_labels() {
    require_once(dirname(__FILE__) . '/../renderer/renderer.php');
    global $DB;

    $defaultrenderer = (object) array(
        'id' => 0,
        'name' => '',
        'description' => '',
        'notvisibletemplate' => datalynx_field_renderer::NOT_VISIBLE_SHOW_NOTHING,
        'displaytemplate' => datalynx_field_renderer::DISPLAY_MODE_TEMPLATE_NONE,
        'novaluetemplate' => datalynx_field_renderer::NO_VALUE_SHOW_NOTHING,
        'edittemplate' => datalynx_field_renderer::EDIT_MODE_TEMPLATE_NONE,
        'noteditabletemplate' => datalynx_field_renderer::NOT_EDITABLE_SHOW_NOTHING,
    );

    $dataids = $DB->get_fieldset_select('datalynx', 'id', "id IS NOT NULL");
    foreach ($dataids as $dataid) {
        $views = $DB->get_records('datalynx_views', array('dataid' => $dataid), '', 'id, param2');
        foreach ($views as $view) {
            $fieldtags = array();
            $fieldlabels = $DB->get_records_menu('datalynx_fields', array('dataid' => $dataid), '', 'name, label');
            $regex = '/\[\[([^\]]+)\@\]\]/';
            $matches = array();
            if (preg_match_all($regex, $view->param2, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $oldtag = $match[0];
                    $fieldname = $match[1];
                    if (!isset($fieldtags[$fieldname])) {
                        $renderer = $defaultrenderer;
                        $renderer->name = $fieldname . '_' . get_string('label', 'datalynx');
                        $renderer->d = $dataid;
                        $renderer->displaytemplate = str_replace('[[' . $fieldname . ']]', '#value', $fieldlabels[$fieldname]);
                        $renderer->edittemplate = str_replace('[[' . $fieldname . ']]', '#input', $fieldlabels[$fieldname]);
                        datalynx_field_renderer::insert_renderer($renderer);
                        $fieldtags[$oldtag] = '[[' . $fieldname . '||' . $renderer->name . ']]';
                    }
                }
            }
            $view->param2 = str_replace(array_keys($fieldtags), array_values($fieldtags), $view->param2);
            $DB->update_record('datalynx_views', $view);
        }
    }
}
