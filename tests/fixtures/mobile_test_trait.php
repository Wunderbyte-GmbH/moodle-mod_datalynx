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
 * Shared fixture builders for the Moodle App tests.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_datalynx;

use stdClass;

/**
 * Builds datalynx instances, fields, views, filters and entries directly in the database.
 */
trait mobile_test_trait {
    /**
     * Create a course with a datalynx instance.
     *
     * @param array $record extra instance settings
     * @return array [$course, $dlx]
     */
    private function create_mobile_instance(array $record = []): array {
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('datalynx', ['course' => $course->id] + $record);

        return [$course, new datalynx($instance->id)];
    }

    /**
     * Add a field.
     *
     * @param datalynx $dlx
     * @param string $type field type
     * @param string $name
     * @param array $params param1..param10
     * @return int field id
     */
    private function add_mobile_field(datalynx $dlx, string $type, string $name, array $params = []): int {
        global $DB;

        $field = ['dataid' => $dlx->id(), 'type' => $type, 'name' => $name, 'description' => ''];
        for ($i = 1; $i <= 10; $i++) {
            $field["param$i"] = $params["param$i"] ?? '';
        }
        $id = (int) $DB->insert_record('datalynx_fields', (object) $field);
        $dlx->get_fields(null, false, true);

        return $id;
    }

    /**
     * Add a view.
     *
     * @param datalynx $dlx
     * @param string $type view type
     * @param string $name
     * @param string $entrytemplate the entry template (param2)
     * @param array $settings other columns of datalynx_views
     * @return int view id
     */
    private function add_mobile_view(
        datalynx $dlx,
        string $type,
        string $name,
        string $entrytemplate = '',
        array $settings = []
    ): int {
        global $DB;

        $view = $settings + [
            'dataid' => $dlx->id(),
            'type' => $type,
            'name' => $name,
            'description' => '',
            'visible' => 15,
            'filter' => 0,
            'perpage' => 0,
            'groupby' => '',
            'param5' => 0,
            'param10' => 0,
            'section' => '##entries##',
            'param2' => $entrytemplate,
        ];

        return (int) $DB->insert_record('datalynx_views', (object) $view);
    }

    /**
     * Add a saved filter.
     *
     * @param datalynx $dlx
     * @param string $name
     * @param array $settings other columns of datalynx_filters
     * @return int filter id
     */
    private function add_mobile_filter(datalynx $dlx, string $name, array $settings = []): int {
        global $DB;

        return (int) $DB->insert_record('datalynx_filters', (object) ($settings + [
            'dataid' => $dlx->id(),
            'name' => $name,
            'description' => '',
            'visible' => 1,
            'perpage' => 0,
            'selection' => 0,
        ]));
    }

    /**
     * Add an approved entry.
     *
     * @param datalynx $dlx
     * @param int $userid
     * @param array $contents field id => content
     * @param int $time time created and modified
     * @return int entry id
     */
    private function add_mobile_entry(datalynx $dlx, int $userid, array $contents, int $time = 0): int {
        global $DB;

        $time = $time ?: time();
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) [
            'dataid' => $dlx->id(),
            'userid' => $userid,
            'groupid' => 0,
            'approved' => 1,
            'status' => 0,
            'timecreated' => $time,
            'timemodified' => $time,
        ]);
        foreach ($contents as $fieldid => $content) {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $fieldid,
                'entryid' => $entryid,
                'lineid' => 0,
                'content' => $content,
            ]);
        }

        return $entryid;
    }

    /**
     * Make a view the default view of the instance.
     *
     * @param datalynx $dlx
     * @param int $viewid
     * @return datalynx the instance, reloaded
     */
    private function set_mobile_default_view(datalynx $dlx, int $viewid): datalynx {
        global $DB;

        $DB->set_field('datalynx', 'defaultview', $viewid, ['id' => $dlx->id()]);

        return new datalynx($dlx->id());
    }

    /**
     * The ids of the entries of a payload, in order.
     *
     * @param array $payload
     * @return int[]
     */
    private function mobile_entry_ids(array $payload): array {
        return array_map(fn(array $entry) => (int) $entry['id'], $payload['entries']);
    }

    /**
     * A user enrolled as student.
     *
     * @param stdClass $course
     * @return stdClass
     */
    private function create_mobile_student(stdClass $course): stdClass {
        return $this->getDataGenerator()->create_and_enrol($course, 'student');
    }
}
