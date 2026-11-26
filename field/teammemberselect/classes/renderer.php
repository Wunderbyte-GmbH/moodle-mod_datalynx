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
 * @package datalynxfield_teammemberselect
 * @subpackage teammemberselect
 * @copyright 2013 Ivan Šakić
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace datalynxfield_teammemberselect;

use html_writer;
use mod_datalynx\local\field\datalynxfield_renderer;
use moodle_url;
use MoodleQuickForm;
use stdClass;

/**
 * Renderer class for teammemberselect datalynx field
 */
class renderer extends datalynxfield_renderer {
    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Render the display mode for the teammemberselect field.
     *
     * @param stdClass $entry
     * @param array $options
     * @return string
     */
    public function render_display_mode(stdClass $entry, array $options): string {
        global $USER, $PAGE;

        // Variable $field datalynxfield_teammemberselect.
        $field = $this->field;
        $fieldid = $field->id();
        $str = '';

        if (isset($entry->{"c{$fieldid}_content"})) {
            $selected = json_decode($entry->{"c{$fieldid}_content"}, true);
            $selected = $selected ? $selected : [];

            $str = $this->get_user_list((int) $field->dlx()->course->id, $selected);

            switch ($field->listformat) {
                case field::TEAMMEMBERSELECT_FORMAT_NEWLINE:
                    $str = implode('<br>', $str);
                    break;
                case field::TEAMMEMBERSELECT_FORMAT_SPACE:
                    $str = implode(' ', $str);
                    break;
                case field::TEAMMEMBERSELECT_FORMAT_COMMA:
                    $str = implode(',', $str);
                    break;
                case field::TEAMMEMBERSELECT_FORMAT_COMMA_SPACE:
                    $str = implode(', ', $str);
                    break;
                case field::TEAMMEMBERSELECT_FORMAT_UL:
                default:
                    if (count($str) > 0) {
                        $str = '<ul class="team-member-list"><li>' . implode('</li><li>', $str) . '</li></ul>';
                    } else {
                        $str = '';
                    }
                    break;
            }
        }

        $format = $options['field_format'] ?? null;
        $subscribeenabled = false;
        if ($format) {
            $settings = $format->get_settings();
            $subscribeenabled = !empty($settings['subscribe']);
        } else {
            $subscribeenabled = isset($options['subscribe']);
        }
        // Inside a fieldgroup the field is a list of people: nobody subscribes to a line.
        $subscribeenabled = $subscribeenabled && !$field->is_fieldgroup_subfield();
        // Joining needs the author's approval: requests instead of the subscribe link (mod_datalynx#341).
        if ($subscribeenabled && $field->needs_approval() && (int) $entry->id > 0) {
            $label = $format ? trim((string) ($format->get_settings()['label'] ?? '')) : '';
            return $str . $this->render_requests((int) $entry->id, $label);
        }
        $selected = isset($entry->{"c{$fieldid}_content"}) ? json_decode(
            $entry->{"c{$fieldid}_content"},
            true
        ) : [];
        $selected = $selected ? $selected : []; // TODO MDL-000000: Seems obsolete.
        // Full when it holds as many members as the entry allows (team_subscription refuses more): the
        // team size, lowered by the limit field of the entry when one is set (mod_datalynx#326).
        $limit = $field->get_member_limit((int) $entry->id);
        $teamfull = $limit !== null && count($selected) >= $limit;
        $hasadmissiblerole = array_intersect(
            $field->dlx()->get_user_datalynx_permissions($USER->id),
            $field->admissibleroles
        );
        $userismember = in_array($USER->id, $selected);
        $canunsubscribe = $this->field->allowunsubscription;

        if (
            $subscribeenabled && $hasadmissiblerole && (!$teamfull || $userismember) &&
                (!$userismember || $canunsubscribe)
        ) {
            $userurl = new moodle_url(
                '/user/view.php',
                ['course' => $field->dlx()->course->id, 'id' => $USER->id]
            );

            $str .= html_writer::link(
                new moodle_url(
                    '/mod/datalynx/view.php',
                    ['d' => $field->dlx()->id(), 'fieldid' => $fieldid,
                                'entryid' => $entry->id,
                                'userid' => $USER->id,
                                'action' => $userismember ? 'unsubscribe' : 'subscribe',
                        ]
                ),
                get_string($userismember ? 'unsubscribe' : 'subscribe', 'datalynx'),
                [
                    'class' => 'datalynxfield_subscribe' . ($userismember ? ' subscribed' : ''),
                    'data-userurl' => $userurl->out(false),
                    'data-username' => fullname($USER),
                    'data-canunsubscribe' => $canunsubscribe ? 1 : 0,
                    'data-subscribestring' => get_string('subscribe', 'datalynx'),
                    'data-unsubscribestring' => get_string('unsubscribe', 'datalynx'),
                ]
            );

            // Load JS.
            $PAGE->requires->js_call_amd(
                'mod_datalynx/teammemberselect',
                'init',
                [$field->dlx()->id(), $fieldid, $userurl->out(false), fullname($USER), $canunsubscribe]
            );
        } else if ($subscribeenabled && $hasadmissiblerole && $teamfull && !$userismember && $limit > 0) {
            // No room left; an entry without any places (limit 0) shows nothing at all.
            $str .= html_writer::span(
                get_string('teamfull', 'datalynxfield_teammemberselect'),
                'datalynxfield_teamfull badge bg-secondary'
            );
        }

        return $str;
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * The team of an entry whose joining needs the author's approval: what the current user can do
     * (request, withdraw, leave) and, for the author, the open requests to approve or decline.
     *
     * Buttons only; the actions go through the web service mod_datalynx_team_subscription.
     *
     * @param int $entryid
     * @param string $label the label of the request button, empty for the default
     * @return string
     */
    protected function render_requests(int $entryid, string $label): string {
        global $USER, $PAGE;
        $field = $this->field;
        $membership = $field->membership($entryid);
        $button = function (string $action, int $userid, string $text, string $class) use ($field, $entryid): string {
            return html_writer::tag('button', s($text), [
                'type' => 'button',
                'class' => "btn btn-sm $class me-1 mb-1",
                'data-dlx-team-action' => $action,
                'data-d' => $field->dlx()->id(),
                'data-entryid' => $entryid,
                'data-fieldid' => $field->id(),
                'data-userid' => $userid,
            ]);
        };
        $badge = fn(string $text, string $class) => html_writer::span(s($text), "badge $class me-1");
        $component = 'datalynxfield_teammemberselect';
        $html = '';

        $hasadmissiblerole = array_intersect($field->dlx()->get_user_datalynx_permissions($USER->id), $field->admissibleroles);
        $isauthor = (int) $USER->id === $membership->author();
        $limit = $field->get_member_limit($entryid);
        if (!$isauthor && $hasadmissiblerole && $limit !== 0) {
            switch ($membership->status((int) $USER->id)) {
                case membership::MEMBER:
                    $html .= $badge(get_string('confirmed', $component), 'bg-success');
                    if ($field->allowunsubscription) {
                        $html .= $button('withdraw', (int) $USER->id, get_string('leaveteam', $component), 'btn-outline-secondary');
                    }
                    break;
                case membership::REQUESTED:
                    $html .= $badge(get_string('requested', $component), 'bg-warning text-dark');
                    $html .= $button(
                        'withdraw',
                        (int) $USER->id,
                        get_string('withdrawrequest', $component),
                        'btn-outline-secondary'
                    );
                    break;
                case membership::DECLINED:
                    $html .= $badge(get_string('declined', $component), 'bg-secondary');
                    break;
                default:
                    $html .= $membership->has_room()
                        ? $button(
                            'request',
                            (int) $USER->id,
                            $label !== '' ? $label : get_string('requestjoin', $component),
                            'btn-primary'
                        )
                        : $badge(get_string('teamfull', $component), 'bg-secondary');
            }
        }

        if ($membership->can_decide((int) $USER->id)) {
            $requests = $membership->open_requests();
            if ($requests) {
                global $DB;
                $users = $DB->get_records_list('user', 'id', array_keys($requests), '', \core_user\fields::for_name()
                    ->including('id')->get_sql('', false, '', '', false)->selects);
                $items = '';
                foreach ($requests as $userid => $request) {
                    $name = isset($users[$userid]) ? fullname($users[$userid]) : get_string('unknownuser');
                    $details = $field->describe_request($entryid, $userid, $request['data']);
                    $items .= html_writer::tag(
                        'li',
                        html_writer::span(s($name), 'fw-semibold me-2')
                        . ($details !== '' ? html_writer::span($details, 'small text-muted me-2') : '')
                        . $button('approve', $userid, get_string('approve', $component), 'btn-success')
                        . $button('decline', $userid, get_string('decline', $component), 'btn-outline-danger'),
                        ['class' => 'mb-1']
                    );
                }
                $html .= html_writer::div(
                    html_writer::div(get_string('openrequests', $component), 'small fw-semibold mb-1')
                        . html_writer::tag('ul', $items, ['class' => 'list-unstyled mb-0']),
                    'datalynxfield_teamrequests mt-2'
                );
            }
        }

        if ($html !== '') {
            $PAGE->requires->js_call_amd('mod_datalynx/teamrequests', 'init');
            $html = html_writer::div($html, 'datalynxfield_teamactions mt-1');
        }
        return $html;
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /** @var array Cached user list. */
    private static $userlist = [];

    /**
     * Returns a list of formatted user names with profile links.
     *
     * @param int $courseid
     * @param array $userids
     * @return array
     */
    private function get_user_list(int $courseid, array $userids): array {
        global $DB;

        $list = [];
        $notpresent = [];
        foreach ($userids as $userid) {
            if (!$userid) {
                continue;
            } else {
                if (isset(self::$userlist[$userid])) {
                    $list[] = self::$userlist[$userid];
                } else {
                    $notpresent[] = $userid;
                }
            }
        }

        if (!empty($notpresent)) {
            $baseurl = new moodle_url('/user/view.php', ['course' => $courseid]);
            [$insql, $params] = $DB->get_in_or_equal($notpresent);
            $sql = "SELECT * FROM {user} WHERE id $insql";
            $users = $DB->get_records_sql($sql, $params);
            foreach ($users as $user) {
                $baseurl->param('id', $user->id);
                $fullname = fullname($user);
                $item = "<a href=\"$baseurl\">$fullname</a>";
                self::$userlist[$user->id] = $item;
            }

            foreach ($notpresent as $userid) {
                if (isset(self::$userlist[$userid])) {
                    $list[] = self::$userlist[$userid];
                }
            }
        }

        return $list;
    }

    // phpcs:disable moodle.PHP.ForbiddenGlobalUse.BadGlobal
    /**
     * Render the edit mode for the teammemberselect field.
     *
     * @param MoodleQuickForm $mform
     * @param stdClass $entry
     * @param ?array $options
     */
    public function render_edit_mode(MoodleQuickForm &$mform, stdClass $entry, ?array $options = null): void {
        global $USER, $PAGE;

        // Variable $field datalynxfield_teammemberselect.
        $field = $this->field;
        $fieldid = $field->id();
        $entryid = $entry->id;
        $fieldname = "field_{$fieldid}_$entryid";
        $classname = "teammemberselect_{$fieldid}_{$entryid}";
        $required = !empty($options['required']);

        // If we edit an existing entry that is not required we need a workaround.
        $newentry = optional_param('new', null, PARAM_INT) === null ? 1 : 0;
        if (!$newentry && !$required) {
            $PAGE->requires->js_amd_inline("
                document.querySelectorAll('option[value=\"-999\"]').forEach(option => {
                    option.selected = false;
                });
            ");
        }

        // We create a hidden field to force sending. Needs to be done via directly inserting
        // html.
        if (!$required) {
            $mform->addElement(
                'html',
                '<input type="hidden" name="' . $fieldname . '[-1]" value="-999">'
            );
        }
        $selected = !empty($entry->{"c{$fieldid}_content"}) ? json_decode(
            $entry->{"c{$fieldid}_content"},
            true
        ) : [];
        $authorid = isset($entry->userid) ? $entry->userid : $USER->id;
        $menu = $field->options_menu(true, false, $field->usercanaddself ? 0 : $authorid);

        // Members the field no longer offers cannot stay selected: saving removes them. Say so.
        if ($notoffered = $field->get_members_not_offered($entry, (array) $selected)) {
            $mform->addElement('html', html_writer::div(
                get_string('teammembersnotoffered', 'datalynxfield_teammemberselect', implode(', ', $notoffered)),
                'alert alert-warning',
                ['id' => "{$classname}_notoffered", 'role' => 'alert']
            ));
        }

        $mform->addElement(
            'autocomplete',
            $fieldname,
            null,
            $menu,
            ['class' => "datalynxfield_teammemberselect $classname", 'multiple' => true,
            'noselectionstring' => "Gerade keine Auswahl."]
        );
        $mform->setType($fieldname, PARAM_INT);
        $mform->setDefault($fieldname, $selected); // Not value after validation fails.

        if ($required) {
            $mform->addRule($fieldname, get_string('fieldrequired', 'datalynx'), 'required', null, 'client');
        }
    }
    // phpcs:enable moodle.PHP.ForbiddenGlobalUse.BadGlobal

    /**
     * Render the search mode for the teammemberselect field.
     *
     * @param MoodleQuickForm $mform
     * @param int $i
     * @param string $value
     * @return array
     */
    public function render_search_mode(MoodleQuickForm &$mform, int $i = 0, string $value = ''): array {
        $field = $this->field;
        $fieldid = $field->id();
        $fieldname = "f_{$i}_{$fieldid}";
        $menu = [-1 => ''] + $field->options_menu();
        $options = ['multiple' => true];

        $elements = [];
        $elements[] = $mform->createElement('autocomplete', $fieldname, null, $menu, $options);
        $mform->setType($fieldname, PARAM_INT);
        // The users of a saved criterion arrive JSON-encoded; the autocomplete needs a list.
        $mform->setDefault($fieldname, self::decode_saved_search_value($value));
        $mform->disabledIf($fieldname, "searchoperator{$i}", 'eq', '');
        $mform->disabledIf($fieldname, "searchoperator{$i}", 'eq', 'USER');

        return [$elements, null];
    }

    /**
     * Returns the field patterns for the teammemberselect field.
     *
     * @return array
     */
    protected function patterns(): array {
        // The parent::patterns() contributes [[fieldname]] plus a visible tag per user-created
        // teammemberselect format (e.g. a "subscribe" format). The legacy :subscribe suffix stays
        // recognised for old templates but is hidden from the menu.
        $patterns = parent::patterns();

        return $this->add_legacy_suffix_patterns($patterns, ['subscribe']);
    }

    /**
     * Validate the teammemberselect field entry data.
     *
     * @param int|string $entryid
     * @param array $tags
     * @param stdClass $formdata
     * @return array
     */
    public function validate($entryid, $tags, $formdata): array {
        $fieldid = $this->field->id();

        $formfieldname = "field_{$fieldid}_{$entryid}";

        // In case we see a fieldgroup validate inputs only if line was visible to the user.
        if (!is_numeric($entryid)) {
            $linenumber = explode("_", $entryid)[3];
            $lastvisible = "fieldgroup_" . explode("_", $entryid)[2] . "_lastvisible";
            $lastvisibleline = $formdata->$lastvisible ?? null;
            if ($lastvisibleline !== null && $linenumber >= $lastvisibleline) {
                return [];
            }
        }

        $errors = [];
        // Whether a value is required is checked by the entry submission service. The team size is
        // counted in chosen users; the form adds -999 for an empty selection.
        if (isset($formdata->$formfieldname)) {
            $teamsize = count(array_filter((array) $formdata->$formfieldname, fn($userid) => (int) $userid > 0));
            if ($teamsize > 0) {
                // The limit of this entry: the limit field as submitted in the same form, else as stored.
                $limitcontent = null;
                if ($limitfield = $this->field->get_limit_field()) {
                    $limitcontent = $formdata->{"field_{$limitfield->id()}_{$entryid}"} ?? null;
                }
                $limit = $this->field->get_member_limit(is_numeric($entryid) ? (int) $entryid : 0, $limitcontent);
                if ($limit !== null && $teamsize > $limit) {
                    $errors[$formfieldname] = get_string(
                        'maxteamsizeerrorform',
                        'datalynx',
                        $limit
                    );
                }
                if ($teamsize < $this->field->minteamsize) {
                    $errors[$formfieldname] = get_string(
                        'minteamsizeerrorform',
                        'datalynx',
                        $this->field->minteamsize
                    );
                }
            }
        }
        return $errors;
    }

    /**
     * Team members are searched by any of the users the field offers.
     *
     * @return string
     */
    public function get_customfilter_control(): string {
        return 'select';
    }

    /**
     * The users the field offers.
     *
     * @return array user id => name
     */
    public function get_customfilter_options(): array {
        return array_filter($this->field->options_menu(), fn($name, $id) => $id > 0 && $name !== null, ARRAY_FILTER_USE_BOTH);
    }
}
