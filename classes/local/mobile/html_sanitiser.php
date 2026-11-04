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

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Prepares rendered entry HTML for display inside the Moodle App.
 *
 * Entry bodies are the author's own template rendered on the server. On the web they live in a
 * page that runs the plugin's JavaScript and posts its forms; in the app they are shown by
 * core-format-text, where neither happens. Scripts would never run there, and form controls
 * would look usable while doing nothing. This class removes both. The rest of the author's
 * markup is kept as it is, because that layout is the point of a datalynx view.
 *
 * It is not the security boundary for the Angular template: entry HTML reaches the app as data
 * bound with [text], which Angular never compiles. It does make sure no event handler or
 * javascript: URL is handed to the app's own sanitiser to deal with.
 *
 * @package    mod_datalynx
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class html_sanitiser {
    /** @var string[] Elements removed together with their content. */
    const REMOVED = ['script', 'style', 'noscript', 'template', 'object', 'embed', 'input', 'select', 'textarea', 'button'];

    /** @var string[] Elements replaced by their content: the wrapper does nothing in the app, its children do. */
    const UNWRAPPED = ['form'];

    /** @var string[] Attributes that carry a URL and so must not carry a script. */
    const URLATTRIBUTES = ['href', 'src', 'action', 'formaction', 'xlink:href', 'data'];

    /**
     * Clean rendered entry HTML for the app.
     *
     * @param string $html
     * @return string
     */
    public static function for_app(string $html): string {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML declaration makes libxml read the fragment as UTF-8; the wrapper gives it one
        // root to hang off, so the fragment can be written back without html/body.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="dlxmobileroot">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        foreach (self::REMOVED as $tag) {
            foreach (iterator_to_array($xpath->query("//$tag")) as $node) {
                $node->parentNode->removeChild($node);
            }
        }
        foreach (self::UNWRAPPED as $tag) {
            foreach (iterator_to_array($xpath->query("//$tag")) as $node) {
                while ($node->firstChild) {
                    $node->parentNode->insertBefore($node->firstChild, $node);
                }
                $node->parentNode->removeChild($node);
            }
        }
        foreach (iterator_to_array($xpath->query('//*[@*]')) as $element) {
            self::clean_attributes($element);
        }

        $root = $document->getElementById('dlxmobileroot');
        if ($root === null) {
            return '';
        }
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Drop event handler attributes and script URLs from one element.
     *
     * @param DOMElement $element
     */
    private static function clean_attributes(DOMElement $element): void {
        $remove = [];
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->nodeName);
            if (str_starts_with($name, 'on')) {
                $remove[] = $attribute->nodeName;
            } else if (in_array($name, self::URLATTRIBUTES, true) && self::is_script_url($attribute->nodeValue)) {
                $remove[] = $attribute->nodeName;
            }
        }
        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }
    }

    /**
     * Whether a URL would run code rather than load something.
     *
     * Browsers ignore whitespace and control characters inside the scheme, so they are
     * ignored here as well before the scheme is compared.
     *
     * @param string $url
     * @return bool
     */
    private static function is_script_url(string $url): bool {
        $scheme = strtolower(preg_replace('/[\x00-\x20]+/', '', $url));

        return str_starts_with($scheme, 'javascript:') || str_starts_with($scheme, 'vbscript:')
            || str_starts_with($scheme, 'data:text/html');
    }
}
