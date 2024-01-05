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
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/mod/datalynx/backup/moodle2/restore_datalynx_stepslib.php");

/**
 * datalynx restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_datalynx_activity_task extends restore_activity_task {

    protected $ownerid = 0;
    // User id of designated owner of content.

    /**
     */
    public function get_old_moduleid() {
        return $this->oldmoduleid;
    }

    /**
     */
    public function set_ownerid($ownerid) {
        $this->ownerid = $ownerid;
    }

    /**
     */
    public function get_ownerid() {
        return $this->ownerid;
    }

    /**
     * @param string $commentarea
     * @return string
     */
    public function get_comment_mapping_itemname($commentarea) {
        if ($commentarea == 'entry') {
            return 'datalynx_entry';
        } else {
            if ($commentarea == 'activity') {
                return 'user';
            }
        }
    }

    /**
     * Override to remove the course module step if restoring a preset
     */
    public function build() {

        // If restoring into a given activity remove the module_info step b/c there
        // is no need to create a module instance.
        if ($this->get_activityid()) {

            // Here we add all the common steps for any activity and, in the point of interest.
            // We call to define_my_steps() in order to get the particular ones inserted in place.
            $this->define_my_steps();

            // Roles (optionally role assignments and always role overrides).
            $this->add_step(
                    new restore_ras_and_caps_structure_step('course_ras_and_caps', 'roles.xml'));

            // Filters (conditionally).
            if ($this->get_setting_value('filters')) {
                $this->add_step(
                        new restore_filters_structure_step('activity_filters', 'filters.xml'));
            }

            // Comments (conditionally).
            if ($this->get_setting_value('comments')) {
                $this->add_step(
                        new restore_comments_structure_step('activity_comments', 'comments.xml'));
            }

            // Grades (module-related, rest of gradebook is restored later if possible: cats, Calculations...).
            $this->add_step(
                    new restore_activity_grades_structure_step('activity_grades', 'grades.xml'));

            // Advanced grading methods attached to the module.
            $this->add_step(
                    new restore_activity_grading_structure_step('activity_grading', 'grading.xml'));

            // Userscompletion (conditionally).
            if ($this->get_setting_value('userscompletion')) {
                $this->add_step(
                        new restore_userscompletion_structure_step('activity_userscompletion',
                                'completion.xml'));
            }

            // Logs (conditionally).
            if ($this->get_setting_value('logs')) {
                $this->add_step(
                        new restore_activity_logs_structure_step('activity_logs', 'logs.xml'));
            }

            // At the end, mark it as built.
            $this->built = true;
        } else {
            parent::build();
        }
    }

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Datalynx only has one structure step.
        $this->add_step(
                new restore_datalynx_activity_structure_step('datalynx_structure', 'datalynx.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    public static function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('datalynx', array('intro'), 'datalynx');
        $contents[] = new restore_decode_content('datalynx_fields',
                array('description', 'param1', 'param2', 'param3', 'param4', 'param5', 'param6',
                        'param7', 'param8', 'param9', 'param10'), 'datalynx_field');
        $contents[] = new restore_decode_content('datalynx_views',
                array('description', 'section', 'param1', 'param2', 'param3', 'param4', 'param5',
                        'param6', 'param7', 'param8', 'param9', 'param10'), 'datalynx_view');
        $contents[] = new restore_decode_content('datalynx_contents',
                array('content', 'content1', 'content2', 'content3', 'content4'), 'datalynx_content');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    public static function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('DFINDEX', '/mod/datalynx/index.php?id=$1', 'course');

        $rules[] = new restore_decode_rule('DFVIEWBYID', '/mod/datalynx/view.php?id=$1',
                'course_module');
        $rules[] = new restore_decode_rule('DFEMBEDBYID', '/mod/datalynx/embed.php?id=$1',
                'course_module');

        $rules[] = new restore_decode_rule('DFVIEWBYD', '/mod/datalynx/view.php?d=$1', 'datalynx');
        $rules[] = new restore_decode_rule('DFEMBEDBYD', '/mod/datalynx/embed.php?d=$1', 'datalynx');

        $rules[] = new restore_decode_rule('DFVIEWVIEW', '/mod/datalynx/view.php?d=$1&amp;view=$2',
                array('datalynx', 'datalynx_view'));
        $rules[] = new restore_decode_rule('DFEMBEDVIEW', '/mod/datalynx/embed.php?d=$1&amp;view=$2',
                array('datalynx', 'datalynx_view'));

        $rules[] = new restore_decode_rule('DFVIEWVIEWFILTER',
                '/mod/datalynx/view.php?d=$1&amp;view=$2&amp;filter=$3',
                array('datalynx', 'datalynx_view', 'datalynx_filter'));
        $rules[] = new restore_decode_rule('DFEMBEDVIEWFILTER',
                '/mod/datalynx/embed.php?d=$1&amp;view=$2&amp;filter=$3',
                array('datalynx', 'datalynx_view', 'datalynx_filter'));

        $rules[] = new restore_decode_rule('DFVIEWENTRY', '/mod/datalynx/view.php?d=$1&amp;eid=$2',
                array('datalynx', 'datalynx_entry'));
        $rules[] = new restore_decode_rule('DFEMBEDENTRY', '/mod/datalynx/embed.php?d=$1&amp;eid=$2',
                array('datalynx', 'datalynx_entry'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * data logs.
     * It must return one array
     * of {@link restore_log_rule} objects
     */
    public static function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('datalynx', 'add',
                'view.php?d={datalynx}&eid={datalynx_entry}', '{datalynx}');
        $rules[] = new restore_log_rule('datalynx', 'update',
                'view.php?d={datalynx}&eid={datalynx_entry}', '{datalynx}');
        $rules[] = new restore_log_rule('datalynx', 'view', 'view.php?id={course_module}',
                '{datalynx}');
        $rules[] = new restore_log_rule('datalynx', 'entry delete', 'view.php?id={course_module}',
                '{datalynx}');
        $rules[] = new restore_log_rule('datalynx', 'fields add',
                'field/index.php?d={datalynx}&fid={datalynx_field}', '{datalynx_field}');
        $rules[] = new restore_log_rule('datalynx', 'fields update',
                'field/index.php?d={datalynx}&fid={datalynx_field}', '{datalynx_field}');
        $rules[] = new restore_log_rule('datalynx', 'fields delete', 'field/index.php?d={datalynx}',
                '[name]');
        $rules[] = new restore_log_rule('datalynx', 'views add',
                'view/index.php?d={datalynx}&vid={datalynx_view}', '{datalynx_view}');
        $rules[] = new restore_log_rule('datalynx', 'views update',
                'view/index.php?d={datalynx}&vid={datalynx_view}', '{datalynx_view}');
        $rules[] = new restore_log_rule('datalynx', 'views delete', 'view/index.php?d={datalynx}',
                '[name]');
        $rules[] = new restore_log_rule('datalynx', 'filters add',
                'filter/index.php?d={datalynx}&fid={datalynx_filter}', '{datalynx_filter}');
        $rules[] = new restore_log_rule('datalynx', 'filters update',
                'filter/index.php?d={datalynx}&fid={datalynx_filter}', '{datalynx_filter}');
        $rules[] = new restore_log_rule('datalynx', 'filters delete',
                'filter/index.php?d={datalynx}', '[name]');
        $rules[] = new restore_log_rule('datalynx', 'rules add',
                'rule/index.php?d={datalynx}&rid={datalynx_rule}', '{datalynx_rule}');
        $rules[] = new restore_log_rule('datalynx', 'rules update',
                'rule/index.php?d={datalynx}&rid={datalynx_rule}', '{datalynx_rule}');
        $rules[] = new restore_log_rule('datalynx', 'rules delete', 'rule/index.php?d={datalynx}',
                '[name]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs.
     * It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('datalynx', 'view all', 'index.php?id={course}', null);

        return $rules;
    }
}
