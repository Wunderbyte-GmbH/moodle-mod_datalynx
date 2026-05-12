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
let isListeningForInteraction = false;

const COMMENT_WIDGET_SELECTOR = '.datalynx-comment-widget';
const COMMENT_TOGGLE_SELECTOR = '.comment-link';
const COMMENT_OPEN_KEYS = ['Enter', ' ', 'Spacebar'];
const commentWidgetInitialisation = new WeakMap();

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
 * @returns {Promise<void>}
 */
const initialiseCommentWidget = (widget) => {
    if (widget.dataset.commentInitialized === '1') {
        return Promise.resolve();
    }

    const initialisation = commentWidgetInitialisation.get(widget);
    if (initialisation) {
        return initialisation;
    }

    widget.dataset.commentInitializing = '1';
    const options = getCommentOptions(widget);

    const promise = new Promise((resolve) => {
        YUI.use('moodle-core-comment', (Y) => {
            M.core_comment.init(Y, options);
            widget.dataset.commentInitialized = '1';
            delete widget.dataset.commentInitializing;
            commentWidgetInitialisation.delete(widget);
            resolve();
        });
    });

    commentWidgetInitialisation.set(widget, promise);
    return promise;
};

/**
 * Initialise comment widgets inside one DOM root.
 *
 * @param {Document|Element} root
 */
const initialiseCommentWidgets = (root) => {
    if (!('querySelectorAll' in root)) {
        return;
    }

    root.querySelectorAll(COMMENT_WIDGET_SELECTOR).forEach((widget) => {
        initialiseCommentWidget(widget);
    });
};

/**
 * Find the wrapped Datalynx comment widget for a document event target.
 *
 * @param {EventTarget|null} target
 * @returns {HTMLElement|null}
 */
const getCommentWidgetFromTarget = (target) => {
    if (!(target instanceof Element)) {
        return null;
    }

    const widget = target.closest(COMMENT_WIDGET_SELECTOR);
    return widget instanceof HTMLElement ? widget : null;
};

/**
 * Open a lazily-rendered comment widget after its handlers are attached.
 *
 * @param {HTMLElement} widget
 */
const openCommentWidget = (widget) => {
    initialiseCommentWidget(widget).then(() => {
        const toggle = widget.querySelector(COMMENT_TOGGLE_SELECTOR);
        if (toggle instanceof HTMLElement) {
            toggle.click();
        }
    });
};

/**
 * Intercept the first click on a lazy comment toggle so the anchor never wins.
 *
 * @param {MouseEvent} event
 */
const handleCommentClick = (event) => {
    const toggle = event.target instanceof Element ? event.target.closest(COMMENT_TOGGLE_SELECTOR) : null;
    const widget = toggle ? getCommentWidgetFromTarget(toggle) : null;

    if (!widget || widget.dataset.commentInitialized === '1') {
        return;
    }

    event.preventDefault();
    openCommentWidget(widget);
};

/**
 * Intercept keyboard activation on a lazy comment toggle before the anchor scrolls the page.
 *
 * @param {KeyboardEvent} event
 */
const handleCommentKeydown = (event) => {
    if (!COMMENT_OPEN_KEYS.includes(event.key)) {
        return;
    }

    const toggle = event.target instanceof Element ? event.target.closest(COMMENT_TOGGLE_SELECTOR) : null;
    const widget = toggle ? getCommentWidgetFromTarget(toggle) : null;

    if (!widget || widget.dataset.commentInitialized === '1') {
        return;
    }

    event.preventDefault();
    openCommentWidget(widget);
};

/**
 * Initialise the module.
 */
export const init = () => {
    initialiseCommentWidgets(document);

    if (!isListeningForInteraction) {
        document.addEventListener('click', handleCommentClick);
        document.addEventListener('keydown', handleCommentKeydown);
        isListeningForInteraction = true;
    }

    if (!isListeningForUpdates) {
        document.addEventListener('mod_datalynx:viewContentUpdated', (event) => {
            const root = event.detail?.target;
            if (root) {
                initialiseCommentWidgets(root);
            }
        });
        isListeningForUpdates = true;
    }
};
