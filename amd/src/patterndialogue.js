// This file is part of Moodle - http:// Moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.


/**
 * @module     mod_datalynx/patterndialogue
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';
import Modal from 'core/modal';

class PatternDialogue {
    constructor(options) {
        this.options = options;
        this.dialogInstances = new Map();
    }

    init() {
        this.waitForTinyMCE();

        // Add event listeners for all tag menu dropdowns
        document.querySelectorAll('select[id$="_tag_menu"]').forEach((dropdown) => {
            dropdown.addEventListener('change', () => this.insertTagFromDropdown(dropdown));
        });

        const form = document.querySelector('#datalynx-view-edit-form');
        form?.addEventListener('submit', () => this.convertButtonsToTagsBeforeSubmit());
    }

    replaceTagsWithButtons(editor) {
        const div = document.createElement('div');
        div.innerHTML = editor.getContent();

        div.innerHTML = div.innerHTML
            .replace(/##([^#]+)##/g, (_, action) =>
                `<button type="button" contenteditable="false" data-action-tag-button="true"
                data-datalynx-field="${action}">${action}</button>`
            )
            .replace(/\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]/g,
                (_, field, behavior = '', renderer = '') =>
                    `<button type="button" contenteditable="false" class="datalynx-field-tag"
                data-datalynx-field="${field}" data-datalynx-behavior="${behavior}"
                data-datalynx-renderer="${renderer}">${field}</button>`
            );

        editor.setContent(div.innerHTML);
    }

    reInitializeButtons(editor) {
        let dataid = 0;
        editor.getBody().querySelectorAll('button[data-action-tag-button], button.datalynx-field-tag').forEach((button) => {
            if (!button.getAttribute('data-click-initialized')) {
                button.addEventListener('click', () => this.openMoodleDialog(button));
                button.setAttribute('data-click-initialized', 'true');
                button.setAttribute('data-id', `datalynx-button-id-${dataid++}`);
            }
        });
    }

    initializeTinyMCEButtons() {
        if (!window.tinyMCE?.get) {
            return;
        }

        window.tinyMCE.get().forEach((editor) => {
            editor.on('init', () => this.reInitializeButtons(editor));
            editor.on('SetContent', () => this.reInitializeButtons(editor));
            editor.on('change', () => this.reInitializeButtons(editor));
        });
    }

    async openMoodleDialog(button) {
        const isFieldTag = button.classList.contains('datalynx-field-tag');
        const buttonId = button.getAttribute('data-id');

        let bodyHtml;
        if (isFieldTag) {
            const behaviors = Object.entries(this.options.behaviors || {})
                .map(([val, label]) => `<option value="${val}">${label}</option>`).join('');
            const field = button.getAttribute('data-datalynx-field');
            const fieldType = (this.options.types || {})[field] || '';
            const renderers = Object.entries((this.options.renderers || {})[field] || {})
                .map(([val, label]) => `<option value="${val}">${label}</option>`).join('');
            bodyHtml = `
                <p data-region="datalynx-tag-field">${field}</p>
                <p data-region="datalynx-tag-fieldtype">${fieldType}</p>
                <label>${await Str.get_string('behavior', 'datalynx')}</label>
                <select data-region="tag-behavior-select">
                    ${behaviors}
                </select>
                <label>${await Str.get_string('renderer', 'datalynx')}</label>
                <select data-region="tag-renderer-select">
                    ${renderers}
                </select>
                <button type="button" data-region="delete-tag">${await Str.get_string('deletetag', 'datalynx')}</button>
            `;
        } else {
            bodyHtml = `
                <p data-region="datalynx-tag-action">${button.textContent}</p>
                <button type="button" data-region="delete-tag">${await Str.get_string('deletetag', 'datalynx')}</button>
            `;
        }

        const tagtype = isFieldTag ? 'Field' : 'Action';
        const tagname = isFieldTag ? button.getAttribute('data-datalynx-field') : button.textContent;
        const modal = await Modal.create({
            title: await Str.get_string('tagproperties', 'datalynx', {tagtype, tagname}),
            body: bodyHtml,
            show: true,
            removeOnClose: true,
        });

        modal.getRoot()[0].querySelector('[data-region="delete-tag"]')?.addEventListener('click', () => {
            button.remove();
            modal.hide();
        });

        this.dialogInstances.set(buttonId, modal);
    }

    waitForTinyMCE() {
        if (window.tinyMCE?.activeEditor && window.tinyMCE.get().length > 0) {
            this.initReplaceTagsWithButtons();
            this.initializeTinyMCEButtons();
        } else {
            setTimeout(() => this.waitForTinyMCE(), 100);
        }
    }

    initReplaceTagsWithButtons() {
        window.tinyMCE.get().forEach((editor) => {
            editor.on('init', () => this.replaceTagsWithButtons(editor));
            editor.on('SetContent', () => this.reInitializeButtons(editor));
            editor.on('change', () => this.reInitializeButtons(editor));
        });
    }

    insertTagFromDropdown(dropdown) {
        const selectedValue = dropdown.value;
        const actionTagMatch = selectedValue.match(/^##(.+?)##$/);
        const fieldTagMatch = selectedValue.match(/^\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]$/);
        let contentToInsert = '';

        if (actionTagMatch) {
            contentToInsert = `<button type="button" contenteditable="false" class="action-tag-button"
                data-action-tag-button="true" data-datalynx-field="${actionTagMatch[1]}">${actionTagMatch[1]}</button>`;
        } else if (fieldTagMatch) {
            const field = fieldTagMatch[1];
            const behavior = fieldTagMatch[2] || '';
            const renderer = fieldTagMatch[3] || '';
            contentToInsert = `<button type="button" contenteditable="false" class="datalynx-field-tag"
                data-datalynx-field="${field}" data-datalynx-behavior="${behavior}"
                data-datalynx-renderer="${renderer}">${field}</button>`;
        } else {
            contentToInsert = selectedValue;
        }

        // Extract editor id from dropdown id, e.g., esection_editor_general_tag_menu -> id_esection_editor
        const editorIdMatch = dropdown.id.match(/^(.+)_editor_.+_tag_menu$/);
        if (editorIdMatch) {
            const editorId = `id_${editorIdMatch[1]}_editor`;
            window.tinyMCE.get().forEach((editor) => {
                if (editor.id === editorId) {
                    editor.insertContent(contentToInsert);
                    this.reInitializeButtons(editor);
                }
            });
        }
    }

    convertButtonsToTagsBeforeSubmit() {
        window.tinyMCE.get().forEach((editor) => {
            const div = document.createElement('div');
            div.innerHTML = editor.getContent();

            div.querySelectorAll('button[data-action-tag-button]').forEach((button) => {
                button.replaceWith(`##${button.getAttribute('data-datalynx-field')}##`);
            });

            div.querySelectorAll('button.datalynx-field-tag').forEach((button) => {
                const field = button.getAttribute('data-datalynx-field')?.trim();
                const behavior = button.getAttribute('data-datalynx-behavior') || '';
                const renderer = button.getAttribute('data-datalynx-renderer') || '';
                const rawFieldTag = `[[${field}${behavior ? '|' + behavior : ''}${renderer ? '|' + renderer : ''}]]`;
                button.replaceWith(rawFieldTag);
            });

            editor.setContent(div.innerHTML);
        });
    }
}

export const init = () => {
    const optionsElement = document.getElementById('mod_datalynx-patterndialogue-options');
    const options = optionsElement ? JSON.parse(optionsElement.textContent) : {};
    const patterndialogue = new PatternDialogue(options);
    patterndialogue.init();
};
