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
 * Function to replace ##value## or [[field|behavior|renderer]] tags with buttons
 * @param {*} editor - The TinyMCE editor instance
 */
function replaceTagsWithButtons(editor) {
    const content = editor.getContent();

    const div = document.createElement('div');
    div.innerHTML = content;

    div.innerHTML = div.innerHTML.replace(/##([^#]+)##/g, (match, action) => {
        return `<button type="button" contenteditable="false" data-action-tag-button="true"
        data-datalynx-field="${action}">${action}</button>`;
    });

    div.innerHTML = div.innerHTML.replace(/\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]/g,
        (match, field, behavior, renderer) => {
        behavior = behavior || '';
        renderer = renderer || '';
        return `<button type="button" contenteditable="false" class="datalynx-field-tag" data-datalynx-field="
        ${field}" data-datalynx-behavior="${behavior}" data-datalynx-renderer="${renderer}">${field}</button>`;
    });

    editor.setContent(div.innerHTML);
}

/**
 * Reinitialize buttons to attach event listeners
 * @param {*} editor - The TinyMCE editor instance
 */
function reInitializeButtons(editor) {
    editor.getBody().querySelectorAll('button[data-action-tag-button="true"], button.datalynx-field-tag').forEach((button) => {
        if (!button.getAttribute('data-click-initialized')) {
            button.addEventListener('click', () => openMoodleDialog(button));
            button.setAttribute('data-click-initialized', 'true');
        }
    });
}

/**
 * Initialize buttons in TinyMCE after the editor is ready
 */
function initializeTinyMCEButtons() {
    if (window.tinyMCE?.get) {
        window.tinyMCE.get().forEach((editor) => {
            editor.on('init', () => reInitializeButtons(editor));
            editor.on('SetContent', () => reInitializeButtons(editor));
            editor.on('change', () => reInitializeButtons(editor));
        });
    } else {
        setTimeout(initializeTinyMCEButtons, 100);
    }
}

/**
 * Open Moodle dialog for tags (action or field)
 * @param {*} button - The button element clicked
 */
function openMoodleDialog(button) {
    const isFieldTag = button.classList.contains('datalynx-field-tag');
    const dialogContent = document.createElement('div');

    if (isFieldTag) {
        dialogContent.innerHTML = `
            <p>Field tag properties:</p>
            <p>Field: ${button.textContent}</p>
            <label>Behavior:</label>
            <select id="tag-behavior-select">
                <option value="behavior1">Behavior 1</option>
                <option value="behavior2">Behavior 2</option>
            </select>
            <label>Renderer:</label>
            <select id="tag-renderer-select">
                <option value="renderer1">Renderer 1</option>
                <option value="renderer2">Renderer 2</option>
            </select>
            <button type="button" id="delete-tag">Delete tag</button>
        `;

        document.getElementById('tag-behavior-select').value = button.getAttribute('data-datalynx-behavior');
        document.getElementById('tag-renderer-select').value = button.getAttribute('data-datalynx-renderer');
    } else {
        dialogContent.innerHTML = `
            <p>Action tag properties:</p>
            <p>Action: ${button.textContent}</p>
            <button type="button" id="delete-tag">Delete tag</button>
        `;
    }

    const deleteButton = dialogContent.querySelector('#delete-tag');
    deleteButton.addEventListener('click', () => {
        button.remove();
        dialog.hide();
    });

    const dialog = new M.core.dialogue({
        bodyContent: dialogContent,
        width: '400px',
        draggable: true,
        modal: true,
        visible: true,
        footerContent: ''
    });
}

/**
 * Initialize the replacement of tags with buttons because get() does not work without a method
 */
function initReplaceTagsWithButtons() {
    window.tinyMCE.get().forEach((editor) => {
        // Replace tags with buttons when editor is initialized
        editor.on('init', () => replaceTagsWithButtons(editor));
        // Reinitialize buttons after content is loaded or changed
        editor.on('SetContent', () => reInitializeButtons(editor));
        editor.on('change', () => reInitializeButtons(editor));
    });
}

/**
 * Wait for TinyMCE to be fully ready before initializing
 */
function waitForTinyMCE() {
    if (window.tinyMCE?.activeEditor && window.tinyMCE.get().length > 0) {
        initReplaceTagsWithButtons();
        initializeTinyMCEButtons();
    } else {
        setTimeout(waitForTinyMCE, 100);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const dropdownMenu = document.getElementById('esection_editor_general_tag_menu');

    if (typeof tinymce !== 'undefined') {
        waitForTinyMCE();
    } else {
        document.addEventListener('tinymce-editor-init', waitForTinyMCE);
    }

    if (dropdownMenu) {
        dropdownMenu.addEventListener('change', () => {
            const selectedValue = dropdownMenu.value;

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
                contentToInsert = `<button type="button" contenteditable="false" class="datalynx-field-tag" data-datalynx-field="
                ${field}" data-datalynx-behavior="${behavior}" data-datalynx-renderer="${renderer}">${field}</button>`;
            } else {
                contentToInsert = selectedValue;
            }

            window.tinyMCE.get().forEach((editor) => {
                editor.insertContent(contentToInsert);
                reInitializeButtons(editor);
            });
        });
    }

    const form = document.querySelector('#datalynx-view-edit-form');
    form.addEventListener('submit', (event) => {
        window.tinyMCE.get().forEach((editor) => {
            const content = editor.getContent();
            const div = document.createElement('div');
            div.innerHTML = content;

            div.querySelectorAll('button[data-action-tag-button="true"]').forEach((button) => {
                const action = button.getAttribute('data-datalynx-field');
                button.replaceWith(`##${action}##`);
            });

            div.querySelectorAll('button.datalynx-field-tag').forEach((button) => {
                const field = button.getAttribute('data-datalynx-field');
                const behavior = button.getAttribute('data-datalynx-behavior') || '';
                const renderer = button.getAttribute('data-datalynx-renderer') || '';
                const rawFieldTag = `[[${field}${behavior ? '|' + behavior : ''}${renderer ? '|' + renderer : ''}]]`;
                button.replaceWith(rawFieldTag);
            });

            editor.setContent(div.innerHTML);
        });
    });
});

waitForTinyMCE();
