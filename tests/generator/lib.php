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
 * @package    mod_datalynx
 * @copyright  2013 onwards edulabs.org and associated programmers
 * @copyright  based on the work by 2012 Itamar Tzadok
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Page module PHPUnit data generator class
 *
 * @package    mod_datalynx
 * @copyright  2014 Ivan Šakić
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_datalynx_generator extends testing_module_generator {
    /**
     * Create new datalynx module instance
     *
     * @param array|stdClass $record
     * @param ?array $options (mostly course_module properties)
     * @return stdClass activity record with extra cmid field
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = ['intro' => null, 'introformat' => 0, 'timemodified' => time(),
                'timeavailable' => 0, 'timedue' => 0, 'timeinterval' => 0, 'intervalcount' => 1,
                'allowlate' => 0, 'grade' => 0, 'grademethod' => 0, 'anonymous' => 0,
                'notification' => 0, 'notificationformat' => 1, 'entriesrequired' => 0,
                'entriestoview' => 0, 'maxentries' => -1, 'timelimit' => -1, 'approval' => 0,
                'grouped' => 0, 'rating' => 0, 'singleedit' => 0, 'singleview' => 0, 'rssarticles' => 0,
                'rss' => 0, 'css' => null, 'cssincludes' => null, 'js' => null, 'jsincludes' => null,
                'defaultview' => 0, 'defaultfilter' => 0, 'completionentries' => 0];

        foreach ($defaults as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }

    /**
     * A ride sharing board built by the board builder (mod_datalynx#336).
     *
     * @param array $record courseid, variant (basic or fahrtenschein, default fahrtenschein), name
     * @return stdClass the datalynx record with cmid
     */
    public function create_ride_board(array $record): stdClass {
        global $DB;
        $builder = new \mod_datalynx\local\ride\board_builder(
            get_course($record['courseid']),
            $record['variant'] ?? \mod_datalynx\local\ride\board_builder::VARIANT_BILLING,
            $record['name'] ?? ''
        );
        $builder->build();
        $instance = $DB->get_record('datalynx', ['id' => $builder->get_dataid()], '*', MUST_EXIST);
        $instance->cmid = $builder->get_cmid();
        return $instance;
    }

    /**
     * A ride offer on a board: a route between two places the test geocoder knows, on a day relative
     * to today, published.
     *
     * @param array $record datalynxid, userid (the driver), from, to (wien, graz, baden, linz), days
     *                      (default 7), time (default 08:00), seats (default 2), detour (default 20),
     *                      photo (1 for a parking photo)
     * @return int the entry id
     */
    public function create_ride(array $record): int {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/stub_geocoder.php');
        $places = \mod_datalynx\stub_geocoder::BEHAT_PLACES;
        $dlx = new \mod_datalynx\datalynx((int) $record['datalynxid']);
        $ids = $DB->get_records_menu('datalynx_fields', ['dataid' => $dlx->id()], '', 'name, id');
        $entryid = (int) $DB->insert_record('datalynx_entries', (object) ['dataid' => $dlx->id(),
            'userid' => (int) $record['userid'], 'groupid' => 0, 'approved' => 1, 'status' => 0,
            'timecreated' => time(), 'timemodified' => time()]);
        [$hours, $minutes] = array_map('intval', explode(':', $record['time'] ?? '08:00'));
        $dlx->get_field_from_id((int) $ids['Termin'], true)->update_content((object) ['id' => $entryid], [
            'schedule' => (new \mod_datalynx\local\ride\schedule(
                \mod_datalynx\local\ride\schedule::MODE_ONCE,
                date('Y-m-d', strtotime(((int) ($record['days'] ?? 7)) . ' days')),
                [],
                $hours * 60 + $minutes
            ))->to_json(),
        ]);
        $stops = [];
        foreach ([$record['from'] ?? 'wien', $record['to'] ?? 'graz'] as $place) {
            [$address, $lat, $lng] = $places[core_text::strtolower($place)];
            $stops[] = ['address' => $address, 'lat' => $lat, 'lng' => $lng];
        }
        $dlx->get_field_from_id((int) $ids['Route'], true)->update_content(
            (object) ['id' => $entryid],
            ['waypoints' => json_encode($stops)]
        );
        $contents = ['Fahrttyp' => '1', 'Freie Plätze' => (string) ($record['seats'] ?? 2),
            'Umweg' => (string) ($record['detour'] ?? 20), 'Veröffentlicht' => '#1#', 'Kennzeichen' => 'W-1234A'];
        foreach ($contents as $name => $content) {
            if (isset($ids[$name])) {
                $DB->insert_record('datalynx_contents', (object) ['fieldid' => $ids[$name], 'entryid' => $entryid,
                    'content' => $content]);
            }
        }
        if (!empty($record['photo']) && isset($ids['Parkfoto'])) {
            $image = imagecreatetruecolor(800, 600);
            ob_start();
            imagejpeg($image);
            $draftid = file_get_unused_draft_itemid();
            get_file_storage()->create_file_from_string(['contextid' => context_user::instance($USER->id)->id,
                'component' => 'user', 'filearea' => 'draft', 'itemid' => $draftid, 'filepath' => '/',
                'filename' => 'parkfoto.jpg'], ob_get_clean());
            $dlx->get_field_from_id((int) $ids['Parkfoto'], true)->update_content(
                (object) ['id' => $entryid],
                ['filemanager' => $draftid]
            );
        }
        return $entryid;
    }
}
