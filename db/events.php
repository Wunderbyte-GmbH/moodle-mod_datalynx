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
 * Event observer definition.
 *
 * @package mod_datalynx
 * @copyright 2015 Ivan Šakić <ivan.sakic3@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

$observers = array(
        array('eventname' => 'mod_datalynx\event\comment_created',
                'callback' => 'datalynx_rule_manager::trigger_rules',
                'includefile' => 'mod/datalynx/rule/rule_manager.php'
        ),
        array('eventname' => 'mod_datalynx\event\entry_created',
                'callback' => 'datalynx_rule_manager::trigger_rules',
                'includefile' => 'mod/datalynx/rule/rule_manager.php'
        ),
        array('eventname' => 'mod_datalynx\event\entry_updated',
                'callback' => 'datalynx_rule_manager::trigger_rules',
                'includefile' => 'mod/datalynx/rule/rule_manager.php'
        ),
        array('eventname' => 'mod_datalynx\event\entry_deleted',
                'callback' => 'datalynx_rule_manager::trigger_rules',
                'includefile' => 'mod/datalynx/rule/rule_manager.php'
        ),
        array('eventname' => 'mod_datalynx\event\rating_added',
                'callback' => 'datalynx_rule_manager::trigger_rules',
                'includefile' => 'mod/datalynx/rule/rule_manager.php'
        ),
        array('eventname' => 'mod_datalynx\event\rating_updated',
                'callback' => 'datalynx_rule_manager::trigger_rules',
                'includefile' => 'mod/datalynx/rule/rule_manager.php'
        ),
        array('eventname' => 'mod_datalynx\event\team_updated',
                'callback' => 'datalynx_rule_manager::trigger_rules',
                'includefile' => 'mod/datalynx/rule/rule_manager.php'
        )
);
