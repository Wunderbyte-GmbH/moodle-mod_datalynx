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

namespace mod_datalynx\local\mobile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/datalynx/tests/fixtures/mobile_test_trait.php');

use advanced_testcase;
use core_external\external_api;
use mod_datalynx\datalynx;
use mod_datalynx\external\get_mobile_entries;
use mod_datalynx\local\view\datalynxview_patterns;
use mod_datalynx\mobile_test_trait;

/**
 * Links to other views open in the Moodle App (#275).
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_datalynx\local\mobile\app_links
 * @covers     \mod_datalynx\local\mobile\entries_payload_builder
 * @covers     \mod_datalynx\local\view\datalynxview_patterns
 */
final class app_links_test extends advanced_testcase {
    use mobile_test_trait;

    /** @var string The first step of the Mitfahrbörse wizard. */
    private const STEP1 = 'Schritt 1 – Fahrttyp und Route';

    /**
     * Reset the per-request caches of the view patterns.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        datalynxview_patterns::reset_static_caches();
    }

    /**
     * The portal view of the Mitfahrbörse (cli/create_ridesharing_instance.php): its three buttons
     * become app links to the right views, and no anchor to view.php is left in its HTML.
     */
    public function test_ridesharing_portal_links_open_in_the_app(): void {
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $this->add_mobile_field($dlx, 'text', 'Bemerkungen');
        $card = fn(string $title, string $link): string => '<div class="col-md-4"><div class="card h-100 border-0 shadow-sm">'
            . '<div class="card-body"><h3 class="card-title">' . $title . '</h3></div>'
            . '<div class="card-footer bg-transparent border-0">' . $link . '</div></div></div>';
        $portalid = $this->add_mobile_view($dlx, 'grid', 'Mitfahrbörse', '', [
            'section' => '<div class="container py-4"><div class="p-5 mb-4"><h1>Mitfahrbörse</h1>'
                . '<p class="lead">Gemeinsam fahren statt allein.</p></div><div class="row g-4">'
                . $card('Fahrt eintragen', '##viewsesslink:' . self::STEP1 . ';Fahrt eintragen;new=1;btn btn-primary w-100 py-2##')
                . $card('Fahrt suchen', '##viewlink:Fahrt suchen;Fahrt suchen;;btn btn-primary w-100 py-2##')
                . $card('Meine Fahrten', '##viewlink:Meine Fahrten;Meine Fahrten;;btn btn-outline-primary w-100 py-2##')
                . '</div></div>',
        ]);
        $step1id = $this->add_mobile_view($dlx, 'grid', self::STEP1, '[[Bemerkungen]] ##submit##');
        $searchid = $this->add_mobile_view($dlx, 'grid', 'Fahrt suchen', '<p>[[Bemerkungen]]</p>');
        $mineid = $this->add_mobile_view($dlx, 'grid', 'Meine Fahrten', '<p>[[Bemerkungen]]</p>');
        $dlx = $this->set_mobile_default_view($dlx, $portalid);

        $payload = (new entries_payload_builder($dlx))->build([]);

        $this->assertSame($portalid, $payload['viewid']);
        $this->assertFalse($payload['listsentries']);
        $html = implode('', array_column($payload['section'], 'html'));
        $this->assertStringContainsString('Gemeinsam fahren statt allein.', $html);
        $this->assertStringContainsString('<h3 class="card-title">Meine Fahrten</h3>', $html);
        $this->assertStringNotContainsString('view.php?', $html);
        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringNotContainsString('@@DLXAPPLINK', $html);

        $links = array_values(array_filter(array_column($payload['section'], 'link')));
        $this->assertSame([$step1id, $searchid, $mineid], array_column($links, 'viewid'));
        $this->assertSame(['Fahrt eintragen', 'Fahrt suchen', 'Meine Fahrten'], array_column($links, 'label'));
        $this->assertSame(['mobile_edit_entry', 'mobile_course_view', 'mobile_course_view'], array_column($links, 'method'));
        $this->assertSame(0, $links[0]['entryid']);
        $this->assertSame(['solid', 'solid', 'outline'], array_column($links, 'fill'));
        $this->assertSame([true, true, true], array_column($links, 'expand'));
        foreach ($links as $link) {
            $this->assertStringNotContainsString('sesskey', $link['url']);
        }

        // Each link sits after the card text it belongs to.
        $order = array_map(fn(array $part): string => isset($part['link']) ? 'link' : 'html', $payload['section']);
        $this->assertSame(['html', 'link', 'html', 'link', 'html', 'link'], $order);
    }

    /**
     * Links in an entry template carry the entry: eids opens the target view with that entry,
     * editentries opens its form. Actions the app cannot do and views it cannot show stay
     * links to the web.
     */
    public function test_entry_template_links(): void {
        global $USER;

        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $this->add_mobile_view($dlx, 'grid', 'Detail', '<p>[[Title]]</p>');
        $this->add_mobile_view($dlx, 'grid', 'Form', '[[Title]] ##submit##');
        $this->add_mobile_view($dlx, 'csv', 'Export');
        $listid = $this->add_mobile_view(
            $dlx,
            'grid',
            'List',
            '<p>Fruit: [[Title]]</p>'
                . '##viewlink:Detail;More;eids=##entryid##;##'
                . '##viewsesslink:Form;Change;editentries=##entryid##;btn btn-secondary##'
                . '##viewsesslink:Detail;Remove;delete=##entryid##;##'
                . '##viewlink:Export;Download;;##'
        );
        $entryid = $this->add_mobile_entry($dlx, $USER->id, [$titleid => 'Apple']);
        $records = $dlx->get_view_records();
        $ids = array_flip(array_map(fn($record) => $record->name, $records));

        $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $listid]);

        $parts = $payload['entries'][0]['parts'];
        $this->assertStringContainsString('Fruit: Apple', $parts[0]['html']);
        $links = array_column(array_values(array_filter($parts, fn(array $part): bool => isset($part['link']))), 'link');
        $this->assertSame(['More', 'Change', 'Remove', 'Download'], array_column($links, 'label'));

        [$more, $change, $remove, $download] = $links;
        $this->assertSame('mobile_course_view', $more['method']);
        $this->assertSame($ids['Detail'], $more['viewid']);
        $this->assertSame((string) $entryid, $more['eids']);
        $this->assertSame('mobile_edit_entry', $change['method']);
        $this->assertSame($ids['Form'], $change['viewid']);
        $this->assertSame($entryid, $change['entryid']);
        $this->assertSame('solid', $change['fill']);
        // The app does not delete through a link: the web page asks for the confirmation.
        $this->assertSame('', $remove['method']);
        $this->assertStringContainsString('delete=' . $entryid, $remove['url']);
        $this->assertStringNotContainsString('sesskey', $remove['url']);
        $this->assertSame('', $download['method']);
        $this->assertStringContainsString('view=' . $ids['Export'], $download['url']);
    }

    /**
     * A filter in the link is applied where the target view offers it, as on the web; a view
     * that forces its filter ignores it.
     */
    public function test_link_filter_only_where_offered(): void {
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $this->add_mobile_field($dlx, 'text', 'Title');
        $filterid = $this->add_mobile_filter($dlx, 'Recent');
        $forcedfilterid = $this->add_mobile_filter($dlx, 'Mine');
        $this->add_mobile_view($dlx, 'grid', 'Open', '<p>[[Title]]</p>');
        $this->add_mobile_view($dlx, 'grid', 'Forced', '<p>[[Title]]</p>', ['filter' => $forcedfilterid, 'param5' => 0]);
        $startid = $this->add_mobile_view($dlx, 'grid', 'Start', '', [
            'section' => '<p>Go</p>##viewlink:Open;Recent entries;filter=' . $filterid . ';##'
                . '##viewlink:Forced;Mine only;filter=' . $filterid . ';##',
        ]);

        $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $startid]);

        $links = array_values(array_filter(array_column($payload['section'], 'link')));
        $this->assertSame(['Recent entries', 'Mine only'], array_column($links, 'label'));
        $this->assertSame([$filterid, 0], array_column($links, 'filterid'));
        $this->assertSame(['mobile_course_view', 'mobile_course_view'], array_column($links, 'method'));
    }

    /**
     * The view template is shown around the entries: its text before and after them, the entries
     * where ##entries## stands, ##addnewentry## as the app's add button, and none of the web page's
     * own controls (paging bar, quick search, view menu).
     */
    public function test_section_around_the_entries(): void {
        global $USER;

        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Cards', '<p>[[Title]]</p>', [
            'section' => '<h2>All fruits</h2> ##viewsmenu## ##quicksearch## ##pagingbar## ##addnewentry##'
                . ' ##entries## <p>Missing a fruit? Ask your teacher.</p>',
        ]);
        $this->add_mobile_entry($dlx, $USER->id, [$titleid => 'Apple']);

        $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $viewid]);

        $this->assertTrue($payload['listsentries']);
        $section = $payload['section'];
        $this->assertCount(4, $section);
        $this->assertStringContainsString('<h2>All fruits</h2>', $section[0]['html']);
        $this->assertSame('mobile_edit_entry', $section[1]['link']['method']);
        $this->assertSame($viewid, $section[1]['link']['viewid']);
        $this->assertSame(0, $section[1]['link']['entryid']);
        $this->assertSame(get_string('entryaddnew', 'datalynx'), $section[1]['link']['label']);
        $this->assertTrue($section[2]['entries']);
        $this->assertStringContainsString('Missing a fruit?', $section[3]['html']);
        foreach (['<form', '<select', '<input', 'page=', 'view.php'] as $needle) {
            $this->assertStringNotContainsString($needle, $section[0]['html']);
        }
    }

    /**
     * A view without a template lists its entries only.
     */
    public function test_view_without_section_lists_entries(): void {
        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $this->add_mobile_field($dlx, 'text', 'Title');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'Cards', '<p>[[Title]]</p>', ['section' => '']);

        $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $viewid]);

        $this->assertSame([['html' => '', 'entries' => true]], $payload['section']);
        $this->assertTrue($payload['listsentries']);
    }

    /**
     * A student who may not add entries gets no link to the entry form; the web leaves the same
     * ##viewsesslink:...;new=1## out for them.
     */
    public function test_new_entry_link_only_for_users_who_may_add(): void {
        [$course, $dlx] = $this->create_mobile_instance();
        $this->add_mobile_field($dlx, 'text', 'Title');
        $this->add_mobile_view($dlx, 'grid', 'Form', '[[Title]] ##submit##');
        $startid = $this->add_mobile_view($dlx, 'grid', 'Start', '', [
            'section' => '<p>Welcome</p>##viewsesslink:Form;Add one;new=1;btn btn-primary##',
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        assign_capability('mod/datalynx:writeentry', CAP_PROHIBIT, $this->get_role_id('student'), $dlx->context->id, true);

        $payload = (new entries_payload_builder(new datalynx($dlx->id())))->build(['viewid' => $startid]);

        $this->assertSame([], array_values(array_filter(array_column($payload['section'], 'link'))));
    }

    /**
     * The parts pass the service's declared return structure, links included.
     */
    public function test_service_returns_the_parts(): void {
        global $USER;

        $this->setAdminUser();
        [, $dlx] = $this->create_mobile_instance();
        $titleid = $this->add_mobile_field($dlx, 'text', 'Title');
        $this->add_mobile_view($dlx, 'grid', 'Detail', '<p>[[Title]]</p>');
        $viewid = $this->add_mobile_view($dlx, 'grid', 'List', '<p>[[Title]]</p>##viewlink:Detail;More;eids=##entryid##;##', [
            'section' => '<h2>Fruits</h2>##entries##',
        ]);
        $this->add_mobile_entry($dlx, $USER->id, [$titleid => 'Apple']);

        $result = external_api::clean_returnvalue(
            get_mobile_entries::execute_returns(),
            get_mobile_entries::execute($dlx->id(), $viewid)
        );

        $this->assertStringContainsString('Fruits', $result['section'][0]['html']);
        $this->assertTrue($result['section'][1]['entries']);
        $this->assertSame('More', $result['entries'][0]['parts'][1]['link']['label']);
        $this->assertSame('mobile_course_view', $result['entries'][0]['parts'][1]['link']['method']);
    }

    /**
     * The id of a role.
     *
     * @param string $shortname
     * @return int
     */
    private function get_role_id(string $shortname): int {
        global $DB;

        return (int) $DB->get_field('role', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
