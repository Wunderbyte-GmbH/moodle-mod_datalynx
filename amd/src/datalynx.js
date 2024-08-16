/* eslint-disable no-unused-vars */
// This file is part of Moodle - http:// Moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// It under the terms of the GNU General Public License as published by
// The Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// But WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// Along with Moodle. If not, see <http:// Www.gnu.org/licenses/>.

/**
 * @package
 * @copyright 2013 onwards David Bogner, Michael Pollak, Ivan Sakic and others.
 * @copyright based on the work by 2011 Itamar Tzadok
 * @license http:// Www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 *
 */

/**
 * Function to replace ##value## tags with buttons
 * @param {*} editor
 */
function replaceTagsWithButtons(editor) {
    const content = editor.getContent();

    const div = document.createElement('div');
    div.innerHTML = content;

    div.innerHTML = div.innerHTML.replace(/##([^#]+)##/g, (match, action) => {
        return `<button type="button" data-action-tag-button="true" data-datalynx-field="${action}">${action}</button>`;
    });

    editor.setContent(div.innerHTML);
}

/**
 * Initialize the replacement of tags with buttons because get does not work without a method
 */
function initReplaceTagsWithButtons() {
    window.tinyMCE.get().forEach((editor) => {
        editor.on('init', () => replaceTagsWithButtons(editor));
    });
}

/**
 * Wait for TinyMCE to be ready before initializing because get would not work in this context
 */
function waitForTinyMCE() {
    if (window.tinyMCE?.activeEditor) {
        // BUG: Button can not be pressed until line: 83 is executed !!!
        initReplaceTagsWithButtons();
    } else {
        setTimeout(waitForTinyMCE, 100);
    }
}


document.addEventListener('DOMContentLoaded', () => {
    const dropdownMenu = document.getElementById('esection_editor_general_tag_menu');

    if (typeof tinymce !== 'undefined') {
        initReplaceTagsWithButtons();
    } else {
        document.addEventListener('tinymce-editor-init', initReplaceTagsWithButtons);
    }

    dropdownMenu.addEventListener('change', () => {
        const selectedValue = dropdownMenu.value;
        const matches = selectedValue.match(/^##(.+?)##$/);
        const contentToInsert = matches
            ? `<button type="button" class="action-tag-button" data-action-tag-button="true"
              data-datalynx-field="${matches[1]}">${matches[1]}</button>`
            : selectedValue;

        window.tinyMCE.get().forEach((editor) => {
            editor.insertContent(contentToInsert);
            reInitializeButtons(editor);
        });
    });

    /**
     * Open Moodle dialog for tags
     * @param {*} button b
     */
    function openMoodleDialog(button) {
        const dialogContent = document.createElement('div');
        dialogContent.innerHTML = `
            <p>Action tag properties:</p>
            <p>${button.textContent}</p>
            <button type="button">Delete tag</button>
        `;

        const deleteButton = dialogContent.querySelector('button');
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
     * Reinitialize buttons to attach event listeners
     * @param {*} editor e
     */
    function reInitializeButtons(editor) {
        editor.getBody().querySelectorAll('button[data-action-tag-button="true"]').forEach((button) => {
            if (!button.getAttribute('data-click-initialized')) {
                button.addEventListener('click', () => openMoodleDialog(button));
                button.setAttribute('data-click-initialized', 'true');
            }
        });
    }

    /**
     * Initialize TinyMCE buttons after the editor is ready
     */
    function initializeTinyMCEButtons() {
        if (window.tinyMCE?.get) {
            window.tinyMCE.get().forEach((editor) => {
                reInitializeButtons(editor);

                editor.on('init', () => reInitializeButtons(editor));
                editor.on('SetContent change', () => reInitializeButtons(editor));
            });
        } else {
            setTimeout(initializeTinyMCEButtons, 100);
        }
    }

    initializeTinyMCEButtons();

    // Handle form submission to replace buttons with tags
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

            editor.setContent(div.innerHTML);
        });
    });
});

waitForTinyMCE();
