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
 * Buttons that press another button of the same form.
 *
 * The entries form repeats its save and cancel buttons below the entries. A repeated button
 * carries data-datalynx-delegate-click="<id of the real button>"; pressing it presses that one.
 *
 * @module      mod_datalynx/delegatebuttons
 * @copyright   2026 onwards The datalynx contributors
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {boolean} */
let isListening = false;

export const init = () => {
    if (isListening) {
        return;
    }
    isListening = true;

    document.addEventListener('click', (e) => {
        const button = e.target instanceof Element ? e.target.closest('[data-datalynx-delegate-click]') : null;
        if (!button) {
            return;
        }
        e.preventDefault();
        const form = button.closest('form');
        const target = (form || document).querySelector(`#${CSS.escape(button.dataset.datalynxDelegateClick)}`);
        if (target instanceof HTMLElement) {
            target.click();
        }
    });
};
