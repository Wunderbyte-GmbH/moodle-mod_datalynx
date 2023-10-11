// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * @package    mod_datalynx
 * @author     David Bogner
 * @copyright  2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Modal form to manage booking option tags (botags).
 *
 * @module     mod_datalynx/maximiseembed
 * @copyright  2023 Wunderbyte GmbH
 * @author     David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const initMaximisedEmbed = (id) => {
    const obj = document.querySelector(`#${id}`);
    if (!obj) {
        return;
    }

    const getHtmlElementSize = (el, prop) => {
        if (typeof el === 'string') {
            el = document.querySelector(`#${el}`);
        }
        // Ensure element exists.
        if (el) {
            let val = getComputedStyle(el).getPropertyValue(prop);
            if (val === 'auto') {
                val = getComputedStyle(el).getPropertyValue(prop);
            }
            val = parseInt(val, 10);
            if (isNaN(val)) {
                return 0;
            }
            return val;
        } else {
            return 0;
        }
    };

    const resizeObject = () => {
        obj.style.display = 'none';
        const newWidth = getHtmlElementSize('maincontent', 'width') - 35;

        if (newWidth > 500) {
            obj.style.width = `${newWidth}px`;
        } else {
            obj.style.width = '500px';
        }

        const header = document.querySelector('#page-header');
        const footer = document.querySelector('#page-footer');

        const headerHeight = getHtmlElementSize(header, 'height');
        const footerHeight = getHtmlElementSize(footer, 'height');
        const newHeight = document.body.scrollHeight - footerHeight - headerHeight - 100;
        if (newHeight < 400) {
            newHeight = 400;
        }
        obj.style.height = `${newHeight}px`;
        obj.style.display = '';
    };

    resizeObject();

    // Fix layout if window resized too
    window.addEventListener('resize', resizeObject);
};