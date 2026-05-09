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
 * Re-initialise Moodle comment widgets after Datalynx injects browse content with AJAX.
 *
 * @module      mod_datalynx/comments
 * @copyright   2026 David Bogner
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import YUI from 'core/yui';

/** @type {boolean} */
let isListeningForUpdates = false;

/**
 * Convert one widget wrapper into the option object expected by M.core_comment.init.
 *
 * @param {HTMLElement} widget
 * @returns {Object}
 */
const getCommentOptions = (widget) => ({
    client_id: widget.dataset.commentClientId,
    commentarea: widget.dataset.commentArea,
    itemid: Number.parseInt(widget.dataset.commentItemid, 10),
    page: 0,
    courseid: Number.parseInt(widget.dataset.commentCourseid, 10),
    contextid: Number.parseInt(widget.dataset.commentContextid, 10),
    component: widget.dataset.commentComponent,
    notoggle: widget.dataset.commentNotoggle === '1',
    autostart: widget.dataset.commentAutostart === '1',
});

/**
 * Initialise all comment widgets inside one updated DOM root.
 *
 * @param {Document|Element} root
 */
const initialiseCommentWidgets = (root) => {
    if (!('querySelectorAll' in root)) {
        return;
    }

    root.querySelectorAll('.datalynx-comment-widget').forEach((widget) => {
        if (widget.dataset.commentInitialized === '1') {
            return;
        }

        widget.dataset.commentInitialized = '1';
        const options = getCommentOptions(widget);

        YUI.use('moodle-core-comment', (Y) => {
            M.core_comment.init(Y, options);
        });
    });
};

/**
 * Initialise the module.
 */
export const init = () => {
    if (isListeningForUpdates) {
        return;
    }

    document.addEventListener('mod_datalynx:viewContentUpdated', (event) => {
        const root = event.detail?.target;
        if (root) {
            initialiseCommentWidgets(root);
        }
    });
    isListeningForUpdates = true;
};
