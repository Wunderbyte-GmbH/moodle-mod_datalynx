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
 * Swaps the visible label of the ##status## toggle between its "on" and "off" text
 * whenever the user clicks the checkbox.
 *
 * @module     mod_datalynx/status_toggle
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTOR = 'input[data-status-toggle="1"]';

/**
 * Update the label text next to the checkbox to match the current checked state.
 *
 * @param {HTMLInputElement} checkbox
 */
const updateLabel = (checkbox) => {
    const label = checkbox.closest('span')?.querySelector('label') ??
                  document.querySelector(`label[for="${checkbox.id}"]`);
    if (!label) {
        return;
    }
    label.textContent = checkbox.checked
        ? checkbox.dataset.labelOn
        : checkbox.dataset.labelOff;
};

/**
 * Initialise toggle label behaviour for all status switches on the page.
 */
export const init = () => {
    document.querySelectorAll(SELECTOR).forEach((checkbox) => {
        updateLabel(checkbox);
        checkbox.addEventListener('change', () => updateLabel(checkbox));
    });
};
