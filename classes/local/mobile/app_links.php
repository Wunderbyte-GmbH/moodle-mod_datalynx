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

use mod_datalynx\datalynx;
use mod_datalynx\local\view\base;
use moodle_url;

/**
 * Links to other views in the Moodle App (mod_datalynx#275).
 *
 * On the web, ##viewlink##, ##viewsesslink## and ##addnewentry## are anchors to view.php. Inside
 * the app's core-format-text such an anchor opens the browser, and the app session is lost. The
 * app therefore renders a view with a link renderer of its own (render option linkrenderer): each
 * link the view's tags resolve to (datalynxview_patterns::get_view_link(), the same rules as on
 * the web) is kept here and leaves a placeholder in the HTML. parts() then cuts the HTML at the
 * placeholders into a list of parts the template shows one after the other: HTML, a link button,
 * HTML, and so on.
 *
 * A link opens in the app when the app can show what it points to:
 * - new=1 opens the entry form of the target view (mobile_edit_entry);
 * - editentries=ID opens the form for that one entry;
 * - otherwise a grid or tabular view opens as a list (mobile_course_view), with the saved filter
 *   (filter=) where the target view offers it, and the entries named by eids=.
 * Anything else (another view type, an action such as delete= or pdfexportentry=, several entries
 * to edit) opens in the browser, as before.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class app_links {
    /** @var string The placeholder a link leaves in the HTML; %d is its index. */
    const PLACEHOLDER = '@@DLXAPPLINK%d@@';

    /** @var string[] Elements without an end tag. */
    const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /** @var string[] Parameters of a link's URL query the app understands. */
    const UNDERSTOOD = ['new', 'editentries', 'eids', 'filter', 'sourceview'];

    /** @var datalynx */
    private datalynx $dlx;

    /** @var array[] The links rendered so far, in the app's shape. */
    private array $links = [];

    /** @var base[] Target views built to check their filters, by id. */
    private array $views = [];

    /**
     * Constructor.
     *
     * @param datalynx $dlx
     */
    public function __construct(datalynx $dlx) {
        $this->dlx = $dlx;
    }

    /**
     * The render options that make a view render its links through this object.
     *
     * @return array linkrenderer, pagecontrols and app, see datalynxview_patterns::get_replacements()
     */
    public function render_options(): array {
        return [
            'linkrenderer' => fn(array $link): string => $this->placeholder($link),
            'pagecontrols' => false,
            // Fields that draw on the web with JavaScript render their static form for the app.
            'app' => true,
        ];
    }

    /**
     * Keep a link and return the placeholder it leaves in the HTML.
     *
     * @param array $link see datalynxview_patterns::get_view_link()
     * @return string
     */
    public function placeholder(array $link): string {
        $this->links[] = $this->project($link);

        return sprintf(self::PLACEHOLDER, count($this->links) - 1);
    }

    /**
     * Cut rendered HTML at its link placeholders, and at ##entries## when $entries is set.
     *
     * Each HTML part is cleaned for the app on its own, which also closes the elements a cut left
     * open. Parts that show nothing (only the wrappers around a link) are left out.
     *
     * @param string $html
     * @param bool $entries whether ##entries## marks where the entries are listed
     * @return array parts, each with html (string) and entries (bool), and a link part with link
     */
    public function parts(string $html, bool $entries = false): array {
        $placeholder = str_replace('%d', '\\d+', preg_quote(self::PLACEHOLDER, '/'));
        $pattern = '/(' . $placeholder . ($entries ? '|##entries##' : '') . ')/';
        $pieces = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        $parts = [];
        $listed = false;
        foreach ($pieces as $i => $piece) {
            if ($i % 2 === 0) {
                $text = html_sanitiser::for_app(self::drop_unopened_end_tags($piece));
                if (self::shows_something($text)) {
                    $parts[] = ['html' => $text, 'entries' => false];
                }
            } else if ($piece === '##entries##') {
                // One list per page: a second ##entries## would show the same entries again.
                if (!$listed) {
                    $parts[] = ['html' => '', 'entries' => true];
                    $listed = true;
                }
            } else {
                $index = (int) preg_replace('/\D/', '', $piece);
                if (isset($this->links[$index])) {
                    $parts[] = ['html' => '', 'entries' => false, 'link' => $this->links[$index]];
                }
            }
        }

        return $parts;
    }

    /**
     * Remove the end tags of elements a piece does not open itself.
     *
     * A cut at a link leaves the next piece starting with the end tags of the elements around
     * the link. They belong to the previous piece, which html_sanitiser closes; left in place they
     * would close elements that are not theirs.
     *
     * @param string $html
     * @return string
     */
    private static function drop_unopened_end_tags(string $html): string {
        $open = [];

        return preg_replace_callback('~<(/?)([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*?(/?)>~', function (array $tag) use (&$open): string {
            [$markup, $closing, $name, $selfclosing] = $tag;
            $name = strtolower($name);
            if ($selfclosing !== '' || in_array($name, self::VOID_ELEMENTS, true)) {
                return $markup;
            }
            if ($closing === '') {
                $open[] = $name;
                return $markup;
            }
            $index = array_search($name, array_reverse($open, true), true);
            if ($index === false) {
                return '';
            }
            array_splice($open, $index);

            return $markup;
        }, $html);
    }

    /**
     * Whether cleaned HTML shows anything: text, or an image, a video and the like.
     *
     * @param string $html
     * @return bool
     */
    private static function shows_something(string $html): bool {
        if (preg_match('/<(img|video|audio|iframe|svg|hr|canvas|picture)\b/i', $html)) {
            return true;
        }
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) !== '';
    }

    /**
     * Project a link onto the shape the app's template shows.
     *
     * @param array $link see datalynxview_patterns::get_view_link()
     * @return array label, title, method (empty: open url in the browser), viewid, entryid,
     *               filterid, eids, url, fill and expand
     */
    private function project(array $link): array {
        $records = $this->dlx->get_view_records();
        $record = $records[$link['viewid']] ?? null;
        $query = array_filter((array) $link['query'], fn($value): bool => is_scalar($value) && (string) $value !== '');

        $url = new moodle_url($link['url']);
        // A session key of the app's web service session is of no use in the browser.
        $url->remove_params('sesskey');

        $projected = [
            'label' => self::label_text((string) $link['label']),
            'title' => $record ? format_string($record->name, true, ['context' => $this->dlx->context]) : '',
            'method' => '',
            'viewid' => (int) $link['viewid'],
            'entryid' => 0,
            'filterid' => 0,
            'eids' => '',
            'url' => $url->out(false),
        ] + self::appearance((string) $link['cssclass']);

        if (!$record || array_diff(array_keys($query), self::UNDERSTOOD)) {
            return $projected;
        }
        if (!empty($query['new'])) {
            $projected['method'] = 'mobile_edit_entry';
        } else if (isset($query['editentries'])) {
            $entryid = clean_param((string) $query['editentries'], PARAM_INT);
            if ((string) $entryid === (string) $query['editentries'] && $entryid > 0) {
                $projected['method'] = 'mobile_edit_entry';
                $projected['entryid'] = (int) $entryid;
            }
        } else if (isset(entries_payload_builder::MANAGERS[$record->type])) {
            $projected['method'] = 'mobile_course_view';
            $projected['eids'] = isset($query['eids']) ? clean_param((string) $query['eids'], PARAM_SEQUENCE) : '';
            $filterid = isset($query['filter']) ? (int) $query['filter'] : 0;
            // As on the web, a filter the target view does not offer is not applied.
            if ($filterid && array_key_exists($filterid, $this->view($record)->get_filter_menu())) {
                $projected['filterid'] = $filterid;
            }
        }

        return $projected;
    }

    /**
     * The view object of a target view, built once.
     *
     * @param \stdClass $record
     * @return base
     */
    private function view(\stdClass $record): base {
        return $this->views[$record->id] ??= $this->dlx->get_view($record->type, $record, false);
    }

    /**
     * The text of a link label: its text, or the name of an icon used as the label.
     *
     * @param string $label HTML
     * @return string
     */
    private static function label_text(string $label): string {
        $text = trim(html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($text === '' && preg_match('/\b(?:aria-label|title|alt)="([^"]+)"/', $label, $matches)) {
            $text = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $text;
    }

    /**
     * How the app's button looks, from the classes the author gave the link.
     *
     * Bootstrap buttons become filled buttons (outline buttons outlined), full-width ones expand.
     * The add link of ##addnewentry## is the app's add button. Other links stay plain.
     *
     * @param string $cssclass
     * @return array fill (solid, outline or clear) and expand (bool)
     */
    private static function appearance(string $cssclass): array {
        $classes = preg_split('/\s+/', trim($cssclass), -1, PREG_SPLIT_NO_EMPTY);
        if (in_array('addnewentry', $classes, true)) {
            return ['fill' => 'solid', 'expand' => true];
        }
        $fill = 'clear';
        foreach ($classes as $class) {
            if (str_starts_with($class, 'btn-outline')) {
                $fill = 'outline';
                break;
            }
            if ($class === 'btn' || preg_match('/^btn-(primary|secondary|success|danger|warning|info|light|dark)$/', $class)) {
                $fill = 'solid';
            }
        }

        return [
            'fill' => $fill,
            'expand' => (bool) array_intersect($classes, ['w-100', 'btn-block', 'd-block']),
        ];
    }
}
