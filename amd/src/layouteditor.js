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
 * Enhances the field-layout editor (fieldlayout/layout_edit.php): large resizable template editors,
 * a live preview that mirrors server rendering, and a one-click Bootstrap-5 preset gallery.
 *
 * The underlying moodleform (radios + textareas + save/validation) is untouched: this module only
 * writes into the existing textareas and toggles the existing "custom" radio, so the submitted data
 * is exactly what the form already handles. If JS is off, the (now larger) textareas still work.
 *
 * @module     mod_datalynx/layouteditor
 * @copyright  2026 David Bogner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Notification from 'core/notification';
import {getStrings} from 'core/str';

/** Signifier for the "custom" radio (raw HTML in the textarea). */
const CUSTOM = '___2___';
/** Templates whose token is #value vs. #input. */
const VALUE_BASED = ['display', 'novalue', 'notvisible'];

/**
 * Escape a plain string for safe insertion as text inside HTML markup.
 *
 * @param {string} text
 * @returns {string}
 */
const esc = (text) => String(text).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[c]));

/**
 * Is the given value one of the form's option signifiers (___0___ … ___4___) rather than HTML?
 *
 * @param {string} value
 * @returns {boolean}
 */
const isSignifier = (value) => /^___\d+___$/.test(value);

/**
 * Entry point. Wires the preset gallery + live preview onto the layout edit form.
 *
 * @returns {Promise<void>}
 */
export const init = async() => {
    try {
        const presetsEl = document.getElementById('mod_datalynx-layouteditor-presets');
        const firstTextarea = document.querySelector('.dlx-layout-textarea');
        if (!presetsEl || !firstTextarea) {
            return;
        }
        const form = firstTextarea.closest('form');
        if (!form || form.querySelector('.dlx-layout-editor')) {
            return; // No form, or already enhanced (idempotent for validation re-renders).
        }

        const catalogue = JSON.parse(presetsEl.textContent);
        const [
            sChooseStyle, sLivePreview, sSampleValue, sInsertValue, sInsertInput, sEmpty,
        ] = await getStrings([
            {key: 'choosestyle', component: 'datalynx'},
            {key: 'livepreview', component: 'datalynx'},
            {key: 'samplevalue', component: 'datalynx'},
            {key: 'insertvaluetag', component: 'datalynx'},
            {key: 'insertinputtag', component: 'datalynx'},
            {key: 'previewempty', component: 'datalynx'},
        ]);

        const previews = {};
        let sampleValue = sSampleValue;

        // --- Small DOM/value helpers (closures over form state). -------------------------------

        const textareaFor = (base) => form.querySelector(`#id_${base}template`);
        const checkedValue = (base) => {
            const radio = form.querySelector(`input[name="${base}options"]:checked`);
            return radio ? radio.value : '';
        };
        const setRadio = (base, value) => {
            const radio = form.querySelector(`input[name="${base}options"][value="${value}"]`);
            if (radio) {
                radio.checked = true;
                // Dispatch change so Moodle's disabledIf re-enables/disables the textarea.
                radio.dispatchEvent(new Event('change', {bubbles: true}));
            }
        };

        const button = (label, cls) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = cls;
            b.textContent = label;
            return b;
        };

        const sampleInput = (disabled) =>
            `<input type="text" class="form-control" value="${esc(sampleValue)}"${disabled ? ' disabled' : ''}>`;
        const subValue = (tpl, value) => String(tpl).split('#value').join(esc(value));
        const subInput = (tpl) => String(tpl).split('#input').join(sampleInput(false));

        // The effective display template (token form), honouring the display radio.
        const effectiveDisplay = () =>
            (checkedValue('display') === CUSTOM ? textareaFor('display').value : '#value');

        // --- Live preview, mirroring datalynxfield_renderer semantics. -------------------------

        const computePreview = (base) => {
            const opt = checkedValue(base);
            const raw = textareaFor(base).value;
            switch (base) {
                case 'display':
                    return opt === CUSTOM ? subValue(raw, sampleValue) : esc(sampleValue);
                case 'notvisible':
                    return opt === CUSTOM ? raw : '';
                case 'novalue':
                    if (opt === '___0___') {
                        return '';
                    }
                    if (opt === '___1___') {
                        return subValue(effectiveDisplay(), '');
                    }
                    return raw; // Custom: server emits it literally.
                case 'edit':
                    if (opt === '___4___') {
                        return sampleInput(false);
                    }
                    if (opt === '___1___') {
                        return subValue(effectiveDisplay(), sampleValue);
                    }
                    return subInput(raw); // Custom.
                case 'noteditable':
                    if (opt === '___0___') {
                        return '';
                    }
                    if (opt === '___1___') {
                        return subValue(effectiveDisplay(), sampleValue);
                    }
                    if (opt === '___3___') {
                        return sampleInput(true);
                    }
                    return raw; // Custom.
                default:
                    return '';
            }
        };

        const updatePreview = (base) => {
            if (!previews[base]) {
                return;
            }
            const html = computePreview(base);
            previews[base].innerHTML = html !== ''
                ? html
                : `<span class="text-muted fst-italic">${esc(sEmpty)}</span>`;
            // Display drives the "as display" previews of the other templates.
            if (base === 'display') {
                ['novalue', 'edit', 'noteditable'].forEach((other) => {
                    if (previews[other]) {
                        updatePreview(other);
                    }
                });
            }
        };

        // --- Applying presets / snippets. ------------------------------------------------------

        const applyToTemplate = (base, value) => {
            if (isSignifier(value)) {
                setRadio(base, value);
            } else {
                setRadio(base, CUSTOM);
                const ta = textareaFor(base);
                ta.disabled = false;
                ta.value = value;
                ta.dispatchEvent(new Event('input', {bubbles: true}));
            }
            updatePreview(base);
        };

        const insertToken = (base, token) => {
            const ta = textareaFor(base);
            setRadio(base, CUSTOM);
            ta.disabled = false;
            const start = ta.selectionStart ?? ta.value.length;
            const end = ta.selectionEnd ?? ta.value.length;
            ta.value = ta.value.slice(0, start) + token + ta.value.slice(end);
            ta.focus();
            ta.selectionStart = ta.selectionEnd = start + token.length;
            ta.dispatchEvent(new Event('input', {bubbles: true}));
            updatePreview(base);
        };

        // --- Preset gallery (modal). -----------------------------------------------------------

        const primarySnippet = (preset) => Object.values(preset.templates)[0];
        const cardHtml = (id, label, previewHtml) =>
            `<button type="button" class="dlx-layout-card" data-dlx-pick="${esc(id)}">` +
            `<span class="dlx-layout-card-preview">${previewHtml}</span>` +
            `<span class="dlx-layout-card-label">${esc(label)}</span></button>`;

        const openGallery = async(title, bodyHtml, onPick) => {
            const modal = await Modal.create({title, body: bodyHtml, large: true, removeOnClose: true, show: true});
            modal.getBody()[0].addEventListener('click', (e) => {
                const card = e.target.closest('[data-dlx-pick]');
                if (!card) {
                    return;
                }
                onPick(card.getAttribute('data-dlx-pick'));
                modal.hide();
            });
        };

        // Per-template snippet picker: value-based templates show display presets, input-based show edit.
        const openSnippetGallery = (base) => {
            const wantScope = VALUE_BASED.includes(base) ? 'display' : 'edit';
            const presets = catalogue.presets.filter((p) => p.scope === wantScope);
            const byFamily = {};
            presets.forEach((p) => {
                (byFamily[p.familylabel] = byFamily[p.familylabel] || []).push(p);
            });
            let html = '';
            Object.keys(byFamily).forEach((familylabel) => {
                html += `<h5 class="dlx-layout-gallery-heading">${esc(familylabel)}</h5>`;
                html += '<div class="dlx-layout-gallery">';
                byFamily[familylabel].forEach((p) => {
                    const snippet = primarySnippet(p);
                    const preview = p.scope === 'edit' ? subInput(snippet) : subValue(snippet, sampleValue);
                    html += cardHtml(p.id, p.label, preview);
                });
                html += '</div>';
            });
            openGallery(sChooseStyle, html, (id) => {
                const preset = catalogue.presets.find((p) => p.id === id);
                if (preset) {
                    applyToTemplate(base, primarySnippet(preset));
                }
            });
        };

        // --- Build the UI around each template textarea. ---------------------------------------

        let firstEditor = null;
        const textareas = [...form.querySelectorAll('.dlx-layout-textarea')];
        textareas.forEach((ta) => {
            const base = ta.dataset.dlxTemplate;
            const editor = document.createElement('div');
            editor.className = 'dlx-layout-editor';
            const left = document.createElement('div');
            left.className = 'dlx-layout-pane-edit';
            const right = document.createElement('div');
            right.className = 'dlx-layout-pane-preview';

            const toolbar = document.createElement('div');
            toolbar.className = 'dlx-layout-toolbar';
            const chooseBtn = button(sChooseStyle, 'btn btn-sm btn-outline-primary dlx-layout-choose');
            chooseBtn.addEventListener('click', () => openSnippetGallery(base));
            const token = VALUE_BASED.includes(base) ? '#value' : '#input';
            const chipLabel = VALUE_BASED.includes(base) ? sInsertValue : sInsertInput;
            const chip = button(chipLabel, 'btn btn-sm btn-outline-secondary dlx-layout-insert');
            chip.addEventListener('click', () => insertToken(base, token));
            toolbar.append(chooseBtn, chip);

            const pvHeading = document.createElement('div');
            pvHeading.className = 'dlx-layout-preview-heading';
            pvHeading.textContent = sLivePreview;
            const pv = document.createElement('div');
            pv.className = 'dlx-layout-preview';
            pv.setAttribute('aria-hidden', 'true');
            previews[base] = pv;

            ta.parentNode.insertBefore(editor, ta);
            left.append(toolbar, ta);
            right.append(pvHeading, pv);
            editor.append(left, right);
            firstEditor = firstEditor || editor;

            ta.addEventListener('input', () => {
                if (ta.value !== '' && checkedValue(base) !== CUSTOM) {
                    setRadio(base, CUSTOM);
                }
                updatePreview(base);
            });
            form.querySelectorAll(`input[name="${base}options"]`).forEach((radio) => {
                radio.addEventListener('change', () => updatePreview(base));
            });
            updatePreview(base);
        });

        // --- Top toolbar: sample-value control. ------------------------------------------------

        if (firstEditor) {
            const topbar = document.createElement('div');
            topbar.className = 'dlx-layout-topbar';

            const sampleWrap = document.createElement('label');
            sampleWrap.className = 'dlx-layout-sample';
            sampleWrap.textContent = `${sSampleValue}: `;
            const sampleInputEl = document.createElement('input');
            sampleInputEl.type = 'text';
            sampleInputEl.className = 'form-control form-control-sm';
            sampleInputEl.value = sampleValue;
            sampleInputEl.addEventListener('input', () => {
                sampleValue = sampleInputEl.value;
                Object.keys(previews).forEach((base) => updatePreview(base));
            });
            sampleWrap.append(sampleInputEl);

            topbar.append(sampleWrap);
            firstEditor.parentNode.insertBefore(topbar, firstEditor);
        }
    } catch (error) {
        Notification.exception(error);
    }
};
