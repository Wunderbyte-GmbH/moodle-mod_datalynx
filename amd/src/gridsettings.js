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
 * Handles grid view settings display and dynamic infobox wrapper preview.
 *
 * @module      mod_datalynx/gridsettings
 * @copyright   2026 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';

/**
 * Escape HTML special characters.
 * @param {string} text
 * @returns {string}
 */
const escapeHtml = (text) => {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

/**
 * Update the wrapper infobox content based on current selections.
 */
const updateInfobox = async () => {
    const select = document.getElementById('id_param3');
    const customInput = document.getElementById('id_param4');
    const infoBox = document.getElementById('grid-wrapper-info');

    if (!select || !infoBox) {
        return;
    }

    const value = select.value;
    const customValue = customInput ? customInput.value.trim() : '';

    // Fetch translated strings from mod_datalynx_grid language pack.
    const strings = await Str.get_strings([
        {key: 'infoboxheader', component: 'datalynxview_grid'},
        {key: 'infoboxintro', component: 'datalynxview_grid'},
        {key: 'infoboxviewwrapper', component: 'datalynxview_grid'},
        {key: 'infoboxentrywrapper', component: 'datalynxview_grid'},
        {key: 'infoboxnowrapper', component: 'datalynxview_grid'},
        {key: 'infoboxnowrapperdesc', component: 'datalynxview_grid'},
        {key: 'infoboxlegacyviewdesc', component: 'datalynxview_grid'}
    ]);

    const [header, intro, viewLabel, entryLabel, , noWrapperDesc, legacyViewDesc] = strings;

    let viewWrapperHtml = '';
    let entryWrapperHtml = '';

    if (value === 'col') {
        viewWrapperHtml = escapeHtml('<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">');
        entryWrapperHtml = escapeHtml('<div class="col entry">');
    } else if (value === 'col-12') {
        viewWrapperHtml = escapeHtml('<div class="row g-4">');
        entryWrapperHtml = escapeHtml('<div class="col-12 entry">');
    } else if (value === 'col-12 col-md-6') {
        viewWrapperHtml = escapeHtml('<div class="row g-4">');
        entryWrapperHtml = escapeHtml('<div class="col-12 col-md-6 entry">');
    } else if (value === 'col-12 col-md-6 col-lg-4') {
        viewWrapperHtml = escapeHtml('<div class="row g-4">');
        entryWrapperHtml = escapeHtml('<div class="col-12 col-md-6 col-lg-4 entry">');
    } else if (value === 'col-12 col-md-6 col-lg-3') {
        viewWrapperHtml = escapeHtml('<div class="row g-4">');
        entryWrapperHtml = escapeHtml('<div class="col-12 col-md-6 col-lg-3 entry">');
    } else if (value === 'entry') {
        viewWrapperHtml = `<em>${escapeHtml(legacyViewDesc)}</em>`;
        entryWrapperHtml = escapeHtml('<div class="entry entry">');
    } else if (value === 'custom') {
        const wrapperClass = customValue !== '' ? customValue : 'entry';
        const hasCol = wrapperClass.includes('col-');
        if (hasCol) {
            viewWrapperHtml = escapeHtml('<div class="row g-4">');
        } else {
            viewWrapperHtml = `<em>${escapeHtml(legacyViewDesc)}</em>`;
        }
        entryWrapperHtml = escapeHtml(`<div class="${wrapperClass} entry">`);
    } else if (value === 'none') {
        viewWrapperHtml = `<em>${escapeHtml(legacyViewDesc)}</em>`;
        entryWrapperHtml = `<em>${escapeHtml(noWrapperDesc)}</em>`;
    }

    let html = `<strong>${escapeHtml(header)}</strong><br>`;
    html += `<span class="small">${escapeHtml(intro)}</span>`;
    html += '<ul class="mt-2 mb-0 pl-3 small">';
    html += `<li><strong>${escapeHtml(viewLabel)}:</strong> <code>${viewWrapperHtml}</code></li>`;
    html += `<li><strong>${escapeHtml(entryLabel)}:</strong> <code>${entryWrapperHtml}</code></li>`;
    html += '</ul>';

    infoBox.innerHTML = html;
    infoBox.style.display = 'block';
};

export default {
    init() {
        const select = document.getElementById('id_param3');
        const customInput = document.getElementById('id_param4');

        if (select) {
            select.addEventListener('change', updateInfobox);
        }
        if (customInput) {
            customInput.addEventListener('input', updateInfobox);
        }

        // Run initially to show the correct preview on page load.
        updateInfobox();
    }
};
