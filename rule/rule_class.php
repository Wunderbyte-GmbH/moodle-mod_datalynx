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
 * @package datalynx_rule
 * @copyright 2014 onwards by edulabs.org and associated programmers
 * @copyright based on the work by 2013 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

require_once(dirname(__FILE__) . "/../classes/datalynx.php");

/**
 * Base class for Datalynx Rule Types
 */
abstract class datalynx_rule_base {

    /**
     * Subclasses must override the type with their name.
     * @var string
     */
    public $type = 'unknown';

    /**
     * The datalynx object that this rule belongs to.
     * @var \mod_datalynx\datalynx|null
     */
    public $df = null;

    /**
     * The rule object itself, if we know it.
     * @var object
     */
    public $rule = null;

    /**
     * datalynx_rule_base constructor.
     *
     * @param int|mod_datalynx\datalynx $df
     * @param int|object $rule
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function __construct($df = 0, $rule = 0) {
        if (empty($df)) {
            throw new coding_exception('Datalynx id or object must be passed to view constructor.');
        } else {
            if ($df instanceof \mod_datalynx\datalynx) {
                $this->df = $df;
            } else { // Datalynx id/object.
                $this->df = new mod_datalynx\datalynx($df);
            }
        }

        if (!empty($rule)) {
            // Variable $rule is the rule record.
            if (is_object($rule)) {
                $this->rule = $rule; // Programmer knows what they are doing, we hope.

                // Variable $rule is a rule id.
            } else {
                if ($ruleobj = $this->df->get_rule_from_id($rule)) {
                    $this->rule = $ruleobj->rule;
                } else {
                    throw new moodle_exception('invalidrule', 'datalynx', null, null, $rule);
                }
            }
        }

        if (empty($this->rule)) { // We need to define some default values.
            $this->set_rule();
        }
    }

    /**
     *
     * @param \core\event\base $event
     * @return bool
     * @throws coding_exception
     */
    public abstract function trigger(\core\event\base $event);

    /**
     * Checks if the rule triggers on the given event
     *
     * @param string $eventname full name of the event (with namespaces)
     * @return bool
     */
    public function is_triggered_by($eventname) {
        $eventname = explode('\\', trim($eventname, '\\'))[2];
        $triggers = array_map(
                function($element) {
                    return explode(':', $element)[0];
                }, unserialize($this->rule->param1));
        return array_search($eventname, $triggers) !== false;
    }

    /**
     * Returns the list of the triggers
     *
     * @return array
     */
    public function get_triggers() {
        static $triggers = array();
        if (empty($triggers)) {
            $triggers = array_map(
                    function($element) {
                        return explode(':', $element)[0];
                    }, unserialize($this->rule->param1));
        }
        return $triggers;
    }

    /**
     * Sets up a rule object
     */
    public function set_rule($forminput = null) {
        $this->rule = new stdClass();
        $this->rule->id = !empty($forminput->id) ? $forminput->id : 0;
        $this->rule->type = $this->type;
        $this->rule->dataid = $this->df->id();
        $this->rule->name = !empty($forminput->name) ? trim($forminput->name) : '';
        $this->rule->description = !empty($forminput->description) ? trim($forminput->description) : '';
        $this->rule->enabled = isset($forminput->enabled) ? $forminput->enabled : 1;
        for ($i = 1; $i <= 10; $i++) {
            $this->rule->{"param$i"} = !empty($forminput->{"param$i"}) ? trim($forminput->{"param$i"}) : null;
        }
    }

    /**
     * Insert a new rule in the database
     * @param string $fromform
     * @return bool|int
     * @throws dml_exception
     */
    public function insert_rule($fromform = null) {
        global $DB, $OUTPUT;

        if (!empty($fromform)) {
            $this->set_rule($fromform);
        }

        if (!$this->rule->id = $DB->insert_record('datalynx_rules', $this->rule)) {
            echo $OUTPUT->notification('Insertion of new rule failed!');
            return false;
        } else {
            return $this->rule->id;
        }
    }

    /**
     * Update a rule in the database
     *
     * @param null $fromform
     * @return bool
     * @throws dml_exception
     */
    public function update_rule($fromform = null) {
        global $DB, $OUTPUT;
        if (!empty($fromform)) {
            $this->set_rule($fromform);
        }

        if (!$DB->update_record('datalynx_rules', $this->rule)) {
            echo $OUTPUT->notification('updating of rule failed!');
            return false;
        }
        return true;
    }

    /**
     * Delete a rule completely
     *
     * @return bool
     * @throws dml_exception
     */
    public function delete_rule() {
        global $DB;

        if (!empty($this->rule->id)) {
            $DB->delete_records('datalynx_rules', array('id' => $this->rule->id));
        }
        return true;
    }

    /**
     * Returns the rule id
     *
     * @return number
     */
    public function get_id() {
        return $this->rule->id;
    }

    /**
     * @return boolean
     */
    public function is_enabled() {
        return $this->rule->enabled;
    }

    /**
     * @return string
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Returns the name of the rule
     * @return string
     */
    public function get_name() {
        return $this->rule->name;
    }

    /**
     * Returns the type name of the rule
     *
     * @return string
     * @throws coding_exception
     */
    public function typename() {
        return get_string('pluginname', "datalynxrule_{$this->type}");
    }

    /**
     * @return \mod_datalynx\datalynx|null
     */
    public function df() {
        return $this->df;
    }

    /**
     * @return mixed
     * @throws moodle_exception
     */
    public function get_form() {
        global $CFG;

        if (file_exists($CFG->dirroot . '/mod/datalynx/rule/' . $this->type . '/rule_form.php')) {
            require_once($CFG->dirroot . '/mod/datalynx/rule/' . $this->type . '/rule_form.php');
            $formclass = 'datalynx_rule_' . $this->type . '_form';
        } else {
            require_once($CFG->dirroot . '/mod/datalynx/rule/rule_form.php');
            $formclass = 'datalynx_rule_form';
        }
        $actionurl = new moodle_url('/mod/datalynx/rule/rule_edit.php',
                array('d' => $this->df->id(), 'rid' => $this->get_id(), 'type' => $this->type));
        return new $formclass($this, $actionurl);
    }

    /**
     * @return object
     */
    public function to_form() {
        return $this->rule;
    }

    /**
     * Get SQL query
     *
     * @return string
     */
    public function get_select_sql() {
        if ($this->rule->id > 0) {
            $id = " c{$this->rule->id}.id AS c{$this->rule->id}_id ";
            $content = $this->get_sql_compare_text() . " AS c{$this->rule->id}_content";
            return " $id , $content ";
        } else {
            return '';
        }
    }

    /**
     * Get sort part for SQL query
     *
     * @param string $paramname
     * @param string $paramcount
     * @return array|null
     */
    public function get_sort_from_sql($paramname = 'sortie', $paramcount = '') {
        $ruleid = $this->rule->id;
        if ($ruleid > 0) {
            $sql = " LEFT JOIN {datalynx_contents} c$ruleid
            ON (c$ruleid.entryid = e.id AND c$ruleid.ruleid = :$paramname$paramcount) ";
            return array($sql, $ruleid);
        } else {
            return null;
        }
    }

    /**
     * Returngs empty string??
     * @return string
     */
    public function get_sort_sql() {
        return '';
    }
}
