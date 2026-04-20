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
 * Javascript to handle approval of entries.
 *
 * @module      mod_datalynx/approve
 * @copyright   2023 onwards
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/**
 * Registers event listeners for the approval buttons.
 *
 * @param {String} approveString The localized string for "Approve".
 * @param {String} unapproveString The localized string for "Unapprove".
 */
const registerEventListeners = (approveString, unapproveString) => {
    document.querySelectorAll(".datalynxfield_approve").forEach(element => {
        // Prevent multiple listeners on the same element
        if (element.dataset.approveInitialized) {
            return;
        }
        element.dataset.approveInitialized = "true";

        element.addEventListener('click', (e) => {
            e.preventDefault();
            const fallbackUrl = element.href;

            // Get parameters from data attributes
            const entryid = element.dataset.entryid;
            const d = element.dataset.d;
            const view = element.dataset.view;
            const sesskey = element.dataset.sesskey;

            if (!entryid || !d || !sesskey) {
                // Missing required data, fallback to normal link behavior
                window.location.href = fallbackUrl;
                return;
            }

            const params = {
                entryid: entryid,
                d: d,
                view: view,
                sesskey: sesskey,
                action: 'toggle-approval'
            };

            fetch("field/approve/ajax.php", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams(params)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Expecting { entryid: ..., approved: 1/0 }
                if (!data || typeof data.approved === 'undefined') {
                    // Unexpected response format, reload to be safe
                    window.location.href = fallbackUrl;
                    return null;
                }

                const icon = element.querySelector('i');
                const label = element.querySelector('.datalynxfield_approve-label');
                const isApproved = (data.approved == 1);

                if (isApproved) {
                    if (label) {
                        label.innerHTML = unapproveString;
                    }
                    if (icon) {
                        icon.classList.remove('fa-circle-xmark', 'text-danger');
                        icon.classList.add('fa-circle-check', 'text-success');
                    }
                } else {
                    if (label) {
                        label.innerHTML = approveString;
                    }
                    if (icon) {
                        icon.classList.remove('fa-circle-check', 'text-success');
                        icon.classList.add('fa-circle-xmark', 'text-danger');
                    }
                }

                return data;
            })
            .catch(() => {
                // AJAX failed — fall back to full page reload via original href.
                window.location.href = fallbackUrl;
            });
        });
    });
};

/**
 * Initialize the module.
 */
export const init = async() => {
    const [approveString, unapproveString] = await Promise.all([
        getString('approve', 'core'),
        getString('unapprove', 'mod_datalynx')
    ]);

    registerEventListeners(approveString, unapproveString);
};
