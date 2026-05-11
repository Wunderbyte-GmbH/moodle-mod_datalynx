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

/** @type {boolean} */
let isListeningForBootstrap = false;

const COMMENT_WIDGET_SELECTOR = '.datalynx-comment-widget';

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
 * Initialise one comment widget if it has not been bootstrapped yet.
 *
 * @param {HTMLElement} widget
 */
const initialiseCommentWidget = (widget) => {
    if (widget.dataset.commentInitialized === '1' || widget.dataset.commentInitializing === '1') {
        return;
    }

    widget.dataset.commentInitializing = '1';
    const options = getCommentOptions(widget);

    YUI.use('moodle-core-comment', (Y) => {
        M.core_comment.init(Y, options);
        widget.dataset.commentInitialized = '1';
        delete widget.dataset.commentInitializing;
    });
};

/**
 * Initialise autostart comment widgets inside one DOM root.
 *
 * @param {Document|Element} root
 */
const initialiseAutostartCommentWidgets = (root) => {
    if (!('querySelectorAll' in root)) {
        return;
    }

    root.querySelectorAll(`${COMMENT_WIDGET_SELECTOR}[data-comment-autostart="1"]`).forEach((widget) => {
        initialiseCommentWidget(widget);
    });
};

/**
 * Lazily bootstrap a comment widget from a delegated document event.
 *
 * @param {Event} event
 */
const bootstrapCommentWidget = (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const widget = event.target.closest(COMMENT_WIDGET_SELECTOR);
    if (widget instanceof HTMLElement) {
        initialiseCommentWidget(widget);
    }
};

/**
 * Initialise the module.
 */
export const init = () => {
    initialiseAutostartCommentWidgets(document);

    if (!isListeningForBootstrap) {
        document.addEventListener('pointerdown', bootstrapCommentWidget);
        document.addEventListener('focusin', bootstrapCommentWidget);
        isListeningForBootstrap = true;
    }

    if (!isListeningForUpdates) {
        document.addEventListener('mod_datalynx:viewContentUpdated', (event) => {
            const root = event.detail?.target;
            if (root) {
                initialiseAutostartCommentWidgets(root);
            }
        });
        isListeningForUpdates = true;
    }
};
