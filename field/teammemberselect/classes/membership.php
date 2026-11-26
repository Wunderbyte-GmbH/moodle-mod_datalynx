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

namespace datalynxfield_teammemberselect;

use moodle_exception;
use stdClass;

/**
 * The team of one entry: its members and the requests to join it (mod_datalynx#326, #341).
 *
 * The members are the content of the field (`content`), as always, so everything that reads
 * members counts confirmed members only. Requests are kept beside them in `content1`, a JSON object
 * keyed by user id: `{"17": {"status": "requested", "time": 1764150000, "data": {...}}}`.
 *
 * Every change of a team made by a person (not through the edit form) goes through here: the web
 * service, the buttons of the display and the app. The checks are made here, on the server.
 *
 * @package    datalynxfield_teammemberselect
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class membership {
    /** @var string The user is in the team. */
    const MEMBER = 'member';

    /** @var string The user asked to join and waits for the author. */
    const REQUESTED = 'requested';

    /** @var string The author declined the request. */
    const DECLINED = 'declined';

    /** @var string Nothing yet. */
    const NONE = 'none';

    /** @var field The team member select field. */
    protected field $field;

    /** @var int The entry. */
    protected int $entryid;

    /** @var stdClass|null The content row of the field for the entry. */
    protected ?stdClass $row;

    /**
     * Constructor.
     *
     * @param field $field
     * @param int $entryid
     */
    public function __construct(field $field, int $entryid) {
        global $DB;
        $this->field = $field;
        $this->entryid = $entryid;
        $this->row = $DB->get_record(
            'datalynx_contents',
            ['fieldid' => $field->id(), 'entryid' => $entryid],
            '*',
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * The members.
     *
     * @return int[] user ids
     */
    public function members(): array {
        $members = $this->row && $this->row->content ? json_decode($this->row->content, true) : [];
        return array_values(array_filter(array_map('intval', (array) $members), fn($id) => $id > 0));
    }

    /**
     * The requests, open and declined.
     *
     * @return array user id => ['status' => ..., 'time' => ..., 'data' => [...]]
     */
    public function requests(): array {
        $requests = $this->row && $this->row->content1 ? json_decode($this->row->content1, true) : [];
        $clean = [];
        foreach ((array) $requests as $userid => $request) {
            $known = is_array($request) && in_array($request['status'] ?? '', [self::REQUESTED, self::DECLINED], true);
            if ((int) $userid > 0 && $known) {
                $clean[(int) $userid] = [
                    'status' => $request['status'],
                    'time' => (int) ($request['time'] ?? 0),
                    'data' => is_array($request['data'] ?? null) ? $request['data'] : [],
                ];
            }
        }
        return $clean;
    }

    /**
     * The open requests.
     *
     * @return array user id => request
     */
    public function open_requests(): array {
        return array_filter($this->requests(), fn($request) => $request['status'] === self::REQUESTED);
    }

    /**
     * Where a user stands.
     *
     * @param int $userid
     * @return string one of the constants
     */
    public function status(int $userid): string {
        if (in_array($userid, $this->members(), true)) {
            return self::MEMBER;
        }
        return $this->requests()[$userid]['status'] ?? self::NONE;
    }

    /**
     * Whether another member fits.
     *
     * @return bool
     */
    public function has_room(): bool {
        $limit = $this->field->get_member_limit($this->entryid);
        return $limit === null || count($this->members()) < $limit;
    }

    /**
     * The author of the entry.
     *
     * @return int
     */
    public function author(): int {
        global $DB;
        return (int) $DB->get_field('datalynx_entries', 'userid', ['id' => $this->entryid]);
    }

    /**
     * Whether the user may decide on requests and remove members: the author of the entry, or a
     * user who manages entries.
     *
     * @param int $userid
     * @return bool
     */
    public function can_decide(int $userid): bool {
        return $userid === $this->author()
            || has_capability('mod/datalynx:manageentries', $this->field->dlx()->context, $userid);
    }

    /**
     * Ask to join. With approval off, this joins directly (self-subscription as before).
     *
     * @param int $userid
     * @param array $data extension data of the request, cleaned by the field
     */
    public function request(int $userid, array $data = []): void {
        $status = $this->status($userid);
        if ($status === self::MEMBER || $status === self::REQUESTED) {
            return;
        }
        if ($status === self::DECLINED) {
            throw new moodle_exception('requestdeclined', 'datalynxfield_teammemberselect');
        }
        if (!$this->has_room()) {
            throw new moodle_exception('teamfull', 'datalynxfield_teammemberselect');
        }
        if (!$this->field->needs_approval()) {
            $this->save_members(array_merge($this->members(), [$userid]));
            return;
        }
        $requests = $this->requests();
        $requests[$userid] = [
            'status' => self::REQUESTED,
            'time' => time(),
            'data' => $this->field->clean_request_data($this->entryid, $userid, $data),
        ];
        $this->save_requests($requests);
        $this->event('team_request_created', $userid);
    }

    /**
     * Withdraw a request, or leave the team.
     *
     * @param int $userid
     */
    public function withdraw(int $userid): void {
        $status = $this->status($userid);
        if ($status === self::REQUESTED) {
            $requests = $this->requests();
            unset($requests[$userid]);
            $this->save_requests($requests);
            $this->event('team_request_withdrawn', $userid);
        } else if ($status === self::MEMBER) {
            if (!$this->field->allowunsubscription) {
                throw new moodle_exception('nounsubscription', 'datalynxfield_teammemberselect');
            }
            $this->save_members(array_diff($this->members(), [$userid]));
            $this->field->member_left($this->entryid, $userid);
            $this->event('team_request_withdrawn', $userid);
        }
    }

    /**
     * Approve a request: the user joins the team.
     *
     * @param int $userid
     */
    public function approve(int $userid): void {
        if ($this->status($userid) !== self::REQUESTED) {
            throw new moodle_exception('norequest', 'datalynxfield_teammemberselect');
        }
        if (!$this->has_room()) {
            throw new moodle_exception('teamfull', 'datalynxfield_teammemberselect');
        }
        $requests = $this->requests();
        $request = $requests[$userid];
        unset($requests[$userid]);
        $this->save_requests($requests);
        $this->save_members(array_merge($this->members(), [$userid]));
        $this->field->member_joined($this->entryid, $userid, $request['data']);
        $this->event('team_request_approved', $userid);
    }

    /**
     * Decline a request. The user cannot request this entry again.
     *
     * @param int $userid
     */
    public function decline(int $userid): void {
        if ($this->status($userid) !== self::REQUESTED) {
            throw new moodle_exception('norequest', 'datalynxfield_teammemberselect');
        }
        $requests = $this->requests();
        $requests[$userid]['status'] = self::DECLINED;
        $requests[$userid]['time'] = time();
        $this->save_requests($requests);
        $this->event('team_request_declined', $userid);
    }

    /**
     * Remove a member.
     *
     * @param int $userid
     */
    public function remove(int $userid): void {
        if ($this->status($userid) !== self::MEMBER) {
            throw new moodle_exception('nomember', 'datalynxfield_teammemberselect');
        }
        $this->save_members(array_diff($this->members(), [$userid]));
        $this->field->member_left($this->entryid, $userid);
    }

    /**
     * Store the members through the field, as the edit form does: notifications and the team event.
     *
     * @param int[] $members
     */
    protected function save_members(array $members): void {
        $fieldid = $this->field->id();
        $entry = new stdClass();
        $entry->id = $this->entryid;
        $entry->{"c{$fieldid}_id"} = $this->row->id ?? null;
        $entry->{"c{$fieldid}_content"} = $this->row->content ?? null;
        $this->field->update_content($entry, [array_values(array_unique(array_map('intval', $members)))]);
        $this->reload();
    }

    /**
     * Store the requests in content1.
     *
     * @param array $requests
     */
    protected function save_requests(array $requests): void {
        global $DB;
        $content1 = $requests ? json_encode($requests) : null;
        if ($this->row) {
            $DB->set_field('datalynx_contents', 'content1', $content1, ['id' => $this->row->id]);
        } else {
            $DB->insert_record('datalynx_contents', (object) [
                'fieldid' => $this->field->id(),
                'entryid' => $this->entryid,
                'content' => '[]',
                'content1' => $content1,
            ]);
        }
        $this->reload();
    }

    /**
     * Read the content row again.
     */
    protected function reload(): void {
        global $DB;
        $this->row = $DB->get_record(
            'datalynx_contents',
            ['fieldid' => $this->field->id(), 'entryid' => $this->entryid],
            '*',
            IGNORE_MULTIPLE
        ) ?: null;
    }

    /**
     * Fire an event of a request.
     *
     * @param string $name event class name in mod_datalynx\event
     * @param int $userid the user concerned
     */
    protected function event(string $name, int $userid): void {
        $class = '\\mod_datalynx\\event\\' . $name;
        $class::create([
            'context' => $this->field->dlx()->context,
            'objectid' => $this->entryid,
            'relateduserid' => $userid,
            'other' => ['dataid' => $this->field->dlx()->id(), 'fieldid' => $this->field->id()],
        ])->trigger();
    }
}
