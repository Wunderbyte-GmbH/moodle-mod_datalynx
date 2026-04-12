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
 * Handles AJAX-based editing of datalynx behaviors and permissions.
 *
 * @module      mod_datalynx/behavior
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const setToggleState = (toggle, enabled) => {
    const icon = toggle.querySelector('i');
    toggle.dataset.enabled = enabled ? '1' : '0';
    toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');

    const enabledLabel = toggle.getAttribute('data-enabled-label');
    const disabledLabel = toggle.getAttribute('data-disabled-label');
    const currentLabel = enabled ? enabledLabel : disabledLabel;
    if (currentLabel) {
        toggle.setAttribute('title', currentLabel);
        toggle.setAttribute('aria-label', currentLabel);

        const toggleLabel = toggle.querySelector('.datalynx-behavior-toggle-label');
        if (toggleLabel) {
            toggleLabel.textContent = currentLabel;
        }
    }

    if (icon) {
        icon.classList.toggle('fa-circle-check', enabled);
        icon.classList.toggle('fa-circle-xmark', !enabled);
        icon.classList.toggle('text-success', enabled);
        icon.classList.toggle('text-danger', !enabled);
    }
};

export default {
    init() {
        document.querySelectorAll('.datalynx-behavior-toggle').forEach((toggle) => {
            if (toggle.dataset.behaviorToggleInitialized) {
                return;
            }

            toggle.dataset.behaviorToggleInitialized = 'true';
            toggle.addEventListener('click', (event) => {
                event.preventDefault();

                const behaviorid = Number(toggle.getAttribute('data-behavior-id'));
                const permissionid = Number(toggle.getAttribute('data-permission-id') || 0);
                const forproperty = toggle.getAttribute('data-for');

                if (!behaviorid || !forproperty) {
                    return;
                }

                toggle.disabled = true;
                Ajax.call([{
                    methodname: 'mod_datalynx_toggle_behavior',
                    args: {
                        behaviorid,
                        permissionid,
                        forproperty
                    }
                }])[0]
                .then((response) => {
                    setToggleState(toggle, response.enabled);
                    return response;
                })
                .catch((error) => {
                    Notification.exception(error);
                    return false;
                })
                .finally(() => {
                    toggle.disabled = false;
                });
            });
        });
    }
};
