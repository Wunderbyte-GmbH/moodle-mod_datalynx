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
 * Manage the fieldgroup subfield list in the fieldgroup field settings form.
 *
 * @module      mod_datalynx/fieldgroup_subfield_settings
 * @copyright   2026 David Bogner
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {getString} from 'core/str';

/** @type {object} Configuration passed from PHP init call. */
let config = {};

/** @type {boolean} Guard so we only attach the global listener once. */
let initialised = false;

/**
 * Initialise the subfield list manager.
 *
 * @param {object} cfg  {d, cmid, formClass, fieldmap}
 */
export const init = (cfg) => {
    config = cfg;

    if (initialised) {
        return;
    }
    initialised = true;

    // Render rows from the hidden param1 input so we always reflect the current JSON,
    // even after a form submission with a validation error.
    renderFromHiddenInput();

    document.addEventListener('click', handleClick);
};

// ---------------------------------------------------------------------------
// Initial render from hidden input
// ---------------------------------------------------------------------------

/**
 * Parse the hidden param1 JSON and render a table row for each entry.
 * Uses config.fieldmap to resolve field IDs to human-readable names.
 */
const renderFromHiddenInput = () => {
    const input = document.querySelector('input[name="param1"][type="hidden"]');
    if (!input) {
        return;
    }
    let entries;
    try {
        entries = JSON.parse(input.value || '[]');
    } catch (e) {
        entries = [];
    }
    entries.forEach(rawValue => {
        const value   = String(rawValue); // old DB format stores bare integers; coerce to string
        const colon   = value.indexOf(':');
        const fieldid = parseInt(colon === -1 ? value : value.slice(0, colon), 10);
        const rest    = colon === -1 ? '' : value.slice(colon + 1);
        const mods    = rest.split('|');
        appendRow({
            value,
            fieldname:    (config.fieldmap && config.fieldmap[fieldid]) || `#${fieldid}`,
            formatname:   mods[0] || '',
            behaviorname: mods[1] || '',
            layoutname:   mods[2] || '',
        });
    });
};

// ---------------------------------------------------------------------------
// Click dispatch
// ---------------------------------------------------------------------------

const handleClick = (e) => {
    const addBtn    = e.target.closest('[data-action="fieldgroup-addsubfield"]');
    const editBtn   = e.target.closest('[data-action="fieldgroup-editsubfield"]');
    const removeBtn = e.target.closest('[data-action="fieldgroup-removesubfield"]');

    if (addBtn) {
        e.preventDefault();
        openModal('', addBtn);
    } else if (editBtn) {
        e.preventDefault();
        openModal(editBtn.dataset.value || '', editBtn);
    } else if (removeBtn) {
        e.preventDefault();
        removeBtn.closest('tr').remove();
        syncParam1();
    }
};

// ---------------------------------------------------------------------------
// Modal
// ---------------------------------------------------------------------------

const openModal = async(value, trigger) => {
    const titleStr = value
        ? await getString('subfieldeditmodaltitle', 'datalynx')
        : await getString('subfieldaddmodaltitle', 'datalynx');

    const modal = new ModalForm({
        formClass: config.formClass,
        args: {d: config.d, cmid: config.cmid, value},
        modalConfig: {title: titleStr},
        returnFocus: trigger,
    });

    // When the user changes the field selector, auto-fire the server-side reload of the
    // format dropdown (same pattern as behaviorform.js auto-fires reloadconditions).
    modal.addEventListener('change', (e) => {
        if (e.target.name !== 'fieldid') {
            return;
        }
        const form   = e.target.closest('form');
        const button = form && form.querySelector('[name="reloadmodifiers"]');
        if (button) {
            window.skipClientValidation = true;
            modal.processNoSubmitButton(button);
        }
    });

    modal.addEventListener(modal.events.FORM_SUBMITTED, (e) => {
        const result = e.detail;
        updateOrAppendRow(result);
        syncParam1();
    });

    modal.show();
};

// ---------------------------------------------------------------------------
// Row management
// ---------------------------------------------------------------------------

/**
 * Add a new <tr> to the subfield table body.
 *
 * @param {object} row  {value, fieldname, formatname, behaviorname, layoutname}
 */
const appendRow = (row) => {
    const tbody = document.getElementById('fieldgroup-subfields-body');
    if (!tbody) {
        return;
    }
    tbody.appendChild(buildRow(row));
};

/**
 * If an existing row has data-value === result.originalvalue, update it in-place.
 * Otherwise append a new row.
 *
 * @param {object} result  process_dynamic_submission() return value
 */
const updateOrAppendRow = (result) => {
    const original = result.originalvalue || '';
    const tbody    = document.getElementById('fieldgroup-subfields-body');
    if (!tbody) {
        return;
    }

    if (original) {
        const existing = tbody.querySelector(`tr[data-value="${CSS.escape(original)}"]`);
        if (existing) {
            const newRow = buildRow(result);
            existing.replaceWith(newRow);
            return;
        }
    }

    tbody.appendChild(buildRow(result));
};

/**
 * Build a <tr> element for a subfield row.
 *
 * @param {object} row  {value, fieldname, formatname, behaviorname, layoutname}
 * @returns {HTMLTableRowElement}
 */
const buildRow = (row) => {
    const tr = document.createElement('tr');
    tr.dataset.value = row.value || '';

    const d    = config.d;
    const cmid = config.cmid;

    tr.innerHTML = `
        <td>${escapeHtml(row.fieldname || '')}</td>
        <td>${escapeHtml(row.formatname || '')}</td>
        <td>${escapeHtml(row.behaviorname || '')}</td>
        <td>${escapeHtml(row.layoutname || '')}</td>
        <td>
            <button type="button" class="btn btn-sm btn-secondary me-1"
                    data-action="fieldgroup-editsubfield"
                    data-value="${escapeAttr(row.value || '')}"
                    data-d="${d}" data-cmid="${cmid}">
                ✎
            </button>
            <button type="button" class="btn btn-sm btn-danger"
                    data-action="fieldgroup-removesubfield">
                ✕
            </button>
        </td>`;

    return tr;
};

// ---------------------------------------------------------------------------
// Sync hidden input
// ---------------------------------------------------------------------------

/**
 * Collect all data-value attributes from the table body and write the JSON array
 * into the hidden <input name="param1"> so it is submitted with the main form.
 */
const syncParam1 = () => {
    const tbody = document.getElementById('fieldgroup-subfields-body');
    const input = document.querySelector('input[name="param1"][type="hidden"]');
    if (!tbody || !input) {
        return;
    }
    const values = Array.from(tbody.querySelectorAll('tr[data-value]'))
        .map(tr => tr.dataset.value)
        .filter(v => v !== '');
    input.value = JSON.stringify(values);
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const escapeHtml = (str) => {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
};

const escapeAttr = (str) => {
    return String(str).replace(/"/g, '&quot;');
};
