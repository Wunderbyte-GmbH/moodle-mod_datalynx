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
 * mod_datalynx data generator
 *
 * @package mod_datalynx
 * @category phpunit
 * @copyright 2013 onwards edulabs.org and associated programmers
 * @copyright based on the work by 2012 Itamar Tzadok
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') or die();

/**
 * Page module PHPUnit data generator class
 *
 * @package mod_datalynx
 * @category phpunit
 * @copyright 2014 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_datalynx_generator extends testing_module_generator {

    /**
     * Create new datalynx module instance
     *
     * @param array|stdClass $record
     * @param array $options (mostly course_module properties)
     * @return stdClass activity record with extra cmid field
     */
    public function create_instance($record = null, array $options = null) {
        $record = (object) (array) $record;

        $defaults = array('intro' => null, 'introformat' => 0, 'timemodified' => time(),
                'timeavailable' => 0, 'timedue' => 0, 'timeinterval' => 0, 'intervalcount' => 1,
                'allowlate' => 0, 'grade' => 0, 'grademethod' => 0, 'anonymous' => 0,
                'notification' => 0, 'notificationformat' => 1, 'entriesrequired' => 0,
                'entriestoview' => 0, 'maxentries' => 0, 'timelimit' => -1, 'approval' => 0,
                'grouped' => 0, 'rating' => 0, 'singleedit' => 0, 'singleview' => 0, 'rssarticles' => 0,
                'rss' => 0, 'css' => null, 'cssincludes' => null, 'js' => null, 'jsincludes' => null,
                'defaultview' => 0, 'defaultfilter' => 0, 'completionentries' => 0);

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
