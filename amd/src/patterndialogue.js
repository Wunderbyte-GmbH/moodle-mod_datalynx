/* eslint-disable no-unused-vars */
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

class PatternDialogue {
    constructor(options) {
        this.options = options;
        this.dialogInstances = new Map();
    }

    init() {
        console.log(this.options);
        document.addEventListener('DOMContentLoaded', () => {
            this.waitForTinyMCE();

            const dropdownMenu = document.getElementById('eparam2_editor_field_tag_menu');
            if (dropdownMenu) {
                dropdownMenu.addEventListener('change', () => this.insertTagFromDropdown(dropdownMenu));
            }

            const form = document.querySelector('#datalynx-view-edit-form');
            form?.addEventListener('submit', (e) => this.convertButtonsToTagsBeforeSubmit());
        });
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

    openMoodleDialog(button) {
        const isFieldTag = button.classList.contains('datalynx-field-tag');
        const dialogContent = document.createElement('div');
        dialogContent.id = button.getAttribute('data-id');

        if (isFieldTag) {
            dialogContent.innerHTML = `
                <p>Field tag properties:</p>
                <p>Field: ${button.textContent}</p>
                <label>Behavior:</label>
                <select id="tag-behavior-select-${dialogContent.id}">
                    <option value="behavior1">Behavior 1</option>
                    <option value="behavior2">Behavior 2</option>
                </select>
                <label>Renderer:</label>
                <select id="tag-renderer-select-${dialogContent.id}">
                    <option value="renderer1">Renderer 1</option>
                    <option value="renderer2">Renderer 2</option>
                </select>
                <button type="button" class="delete-tag">Delete tag</button>
            `;
        } else {
            dialogContent.innerHTML = `
                <p>Action tag properties:</p>
                <p>Action: ${button.textContent}</p>
                <button type="button" class="delete-tag">Delete tag</button>
            `;
        }

        dialogContent.querySelectorAll('.delete-tag').forEach((delBtn) =>
            delBtn.addEventListener('click', () => {
                button.remove();
                dialog?.hide();
            })
        );

        const dialog = new M.core.dialogue({
            bodyContent: dialogContent,
            width: '400px',
            draggable: true,
            modal: true,
            visible: true,
        });

        this.dialogInstances.set(dialogContent.id, dialog);
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

        window.tinyMCE.get().forEach((editor) => {
            if (editor.id === "id_eparam2_editor") {
                editor.insertContent(contentToInsert);
                this.reInitializeButtons(editor);
            }
        });
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

export const init = (options) => {
    const patterndialogue = new PatternDialogue(options);
    patterndialogue.init();
};
