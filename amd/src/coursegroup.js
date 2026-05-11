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
 * Manages dynamic loading of course groups via AJAX when the course selection changes.
 *
 * @module      mod_datalynx/coursegroup
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const configs = [];
let isListening = false;

const getElementId = (field) => `id_${field}`;

const updateSelectedGroup = (config, groupSelect = document.getElementById(getElementId(config.groupfield))) => {
    const hiddenInput = document.getElementById(`${getElementId(config.groupfield)}id`);
    if (hiddenInput && groupSelect) {
        hiddenInput.value = groupSelect.value;
    }
};

const populateGroupOptions = (groupSelect, data) => {
    groupSelect.innerHTML = '';

    if (!data) {
        updateSelectedGroup({groupfield: groupSelect.id.replace(/^id_/, '')}, groupSelect);
        return;
    }

    data.split(',').forEach(group => {
        const value = group.split(' ', 1)[0];
        const option = document.createElement('option');
        option.value = value;
        option.textContent = group;
        groupSelect.appendChild(option);
    });
};

const loadGroups = (config, selectedCourseId) => {
    const groupSelect = document.getElementById(getElementId(config.groupfield));
    if (!groupSelect) {
        return Promise.resolve();
    }

    groupSelect.innerHTML = '';

    if (selectedCourseId === '0') {
        updateSelectedGroup(config, groupSelect);
        return Promise.resolve();
    }

    return fetch(config.acturl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `courseid=${selectedCourseId}`
    })
    .then(response => response.text())
    .then(data => {
        populateGroupOptions(groupSelect, data);
        updateSelectedGroup(config, groupSelect);
        return null;
    })
    .catch(() => {
        throw new Error('Group loading failed');
    });
};

const getConfigForTarget = (target) => configs.find((config) =>
    target.id === getElementId(config.coursefield) || target.id === getElementId(config.groupfield)
);

const handleDocumentChange = (event) => {
    if (!(event.target instanceof HTMLSelectElement)) {
        return;
    }

    const config = getConfigForTarget(event.target);
    if (!config) {
        return;
    }

    if (event.target.id === getElementId(config.groupfield)) {
        updateSelectedGroup(config, event.target);
        return;
    }

    if (event.target.id === getElementId(config.coursefield)) {
        void loadGroups(config, event.target.value);
    }
};

export default {
    init(options) {
        if (!configs.some((config) =>
            config.coursefield === options.coursefield && config.groupfield === options.groupfield
        )) {
            configs.push(options);
        }

        if (isListening) {
            return;
        }

        document.addEventListener('change', handleDocumentChange);
        isListening = true;
    }
};
