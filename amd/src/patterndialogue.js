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
 * Manages pattern dialogues for inserting tags and editing properties in datalynx editors.
 *
 * @module     mod_datalynx/patterndialogue
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';
import Modal from 'core/modal';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

class PatternDialogue {
    constructor(options) {
        this.options = options;
    }

    init() {
        this.waitForTinyMCE();

        document.querySelectorAll('select[id$="_tag_menu"]').forEach((dropdown) => {
            dropdown.addEventListener('change', () => this.insertTagFromDropdown(dropdown));
        });
    }

    /**
     * Replace [[field|behavior|renderer]] and ##action## tags in the editor with clickable buttons.
     * @param {object} editor TinyMCE editor instance
     */
    replaceTagsWithButtons(editor) {
        const div = document.createElement('div');
        div.innerHTML = editor.getContent();

        div.innerHTML = div.innerHTML
            .replace(/##([^#]+)##/g, (_, action) =>
                '<button type="button" contenteditable="false" data-action-tag-button="true"' +
                ' data-datalynx-field="' + action + '">' + action + '</button>'
            )
            .replace(/\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]/g,
                (_, field, behavior, renderer) => {
                    behavior = behavior || '';
                    renderer = renderer || '';
                    return '<button type="button" contenteditable="false" class="datalynx-field-tag"' +
                        ' data-datalynx-field="' + field + '" data-datalynx-behavior="' + behavior + '"' +
                        ' data-datalynx-renderer="' + renderer + '">' +
                        this.buildButtonLabel(field, behavior, renderer) + '</button>';
                }
            );

        editor.setContent(div.innerHTML);
    }

    /**
     * Build the inner HTML label for a field tag button, including Bootstrap-compatible
     * behavior/renderer badges (Bootstrap 4 + 5 compatible class names).
     * @param {string} field
     * @param {string} behavior
     * @param {string} renderer
     * @returns {string}
     */
    buildButtonLabel(field, behavior, renderer) {
        let html = field;
        if (behavior) {
            html += ' <span class="badge badge-info bg-info" style="pointer-events:none">' + behavior + '</span>';
        }
        if (renderer) {
            html += ' <span class="badge badge-secondary bg-secondary" style="pointer-events:none">' + renderer + '</span>';
        }
        return html;
    }

    /**
     * Attach click listeners and data-id attributes to newly created buttons,
     * and refresh badge labels on field-tag buttons.
     * @param {object} editor TinyMCE editor instance
     */
    reInitializeButtons(editor) {
        let dataid = 0;
        editor.getBody().querySelectorAll('button[data-action-tag-button], button.datalynx-field-tag').forEach((button) => {
            if (!button.getAttribute('data-click-initialized')) {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.openMoodleDialog(button);
                });
                button.setAttribute('data-click-initialized', 'true');
                button.setAttribute('data-id', 'datalynx-button-id-' + dataid++);
            }
            if (button.classList.contains('datalynx-field-tag')) {
                const field = button.getAttribute('data-datalynx-field') || '';
                const behavior = button.getAttribute('data-datalynx-behavior') || '';
                const renderer = button.getAttribute('data-datalynx-renderer') || '';
                button.innerHTML = this.buildButtonLabel(field, behavior, renderer);
            }
        });
    }

    /**
     * Find the TinyMCE editor that owns the given button DOM element.
     * @param {HTMLElement} btn
     * @returns {object|null}
     */
    findEditorForButton(btn) {
        for (const ed of (window.tinyMCE?.get() || [])) {
            if (ed.getBody().contains(btn)) {
                return ed;
            }
        }
        return null;
    }

    /**
     * Find a button by its data-id across all TinyMCE editors.
     * @param {string} buttonId
     * @returns {HTMLElement|null}
     */
    findButtonById(buttonId) {
        for (const ed of (window.tinyMCE?.get() || [])) {
            const found = ed.getBody().querySelector('[data-id="' + buttonId + '"]');
            if (found) {
                return found;
            }
        }
        return null;
    }

    /**
     * Open a Moodle dialog for editing a tag button's properties.
     * Field-tag buttons use ModalSaveCancel (reliable footer Save button).
     * Action-tag buttons use a simple Modal with a delete button only.
     * @param {HTMLElement} button
     */
    async openMoodleDialog(button) {
        const isFieldTag = button.classList.contains('datalynx-field-tag');
        const buttonId = button.getAttribute('data-id');
        const currentBehavior = button.getAttribute('data-datalynx-behavior') || '';
        const currentRenderer = button.getAttribute('data-datalynx-renderer') || '';

        const [behaviorLabel, rendererLabel, deleteLabel] = await Promise.all([
            Str.get_string('behavior', 'datalynx'),
            Str.get_string('renderer', 'datalynx'),
            Str.get_string('deletetag', 'datalynx'),
        ]);

        const tagtype = isFieldTag ? 'Field' : 'Action';
        const tagname = isFieldTag ? button.getAttribute('data-datalynx-field') : button.textContent;
        const titleStr = await Str.get_string('tagproperties', 'datalynx', {tagtype, tagname});

        if (isFieldTag) {
            const field = button.getAttribute('data-datalynx-field');
            const fieldType = (this.options.types || {})[field] || '';

            const behaviorsHtml = Object.entries(this.options.behaviors || {})
                .map(([val, label]) =>
                    '<option value="' + val + '"' + (val === currentBehavior ? ' selected="selected"' : '') + '>' +
                    label + '</option>'
                ).join('');
            const renderersHtml = Object.entries((this.options.renderers || {})[field] || {})
                .map(([val, label]) =>
                    '<option value="' + val + '"' + (val === currentRenderer ? ' selected="selected"' : '') + '>' +
                    label + '</option>'
                ).join('');

            const fieldInfo = field + (fieldType ? ' <small class="text-muted">(' + fieldType + ')</small>' : '');
            const bodyHtml =
                '<p><strong>' + fieldInfo + '</strong></p>' +
                '<div class="form-group">' +
                '<label for="dlx-behavior-select">' + behaviorLabel + '</label>' +
                '<select class="form-control custom-select" id="dlx-behavior-select" name="dlx-behavior-select">' +
                behaviorsHtml +
                '</select></div>' +
                '<div class="form-group">' +
                '<label for="dlx-renderer-select">' + rendererLabel + '</label>' +
                '<select class="form-control custom-select" id="dlx-renderer-select" name="dlx-renderer-select">' +
                renderersHtml +
                '</select></div>' +
                '<div class="mt-2">' +
                '<button type="button" class="btn btn-danger btn-sm" data-action="dlx-delete">' + deleteLabel + '</button>' +
                '</div>';

            const modal = await ModalSaveCancel.create({
                title: titleStr,
                body: bodyHtml,
                show: true,
                removeOnClose: true,
            });

            // Save: read select values, update button data attributes, rebuild label badges.
            modal.getRoot().on(ModalEvents.save, (e) => {
                e.preventDefault();

                const modalBody = modal.getBody()[0];
                const behaviorSelect = modalBody.querySelector('#dlx-behavior-select');
                const rendererSelect = modalBody.querySelector('#dlx-renderer-select');
                const newBehavior = behaviorSelect ? behaviorSelect.value : '';
                const newRenderer = rendererSelect ? rendererSelect.value : '';

                const liveButton = this.findButtonById(buttonId) || button;
                const ed = this.findEditorForButton(liveButton);
                if (ed) {
                    ed.dom.setAttrib(liveButton, 'data-datalynx-behavior', newBehavior);
                    ed.dom.setAttrib(liveButton, 'data-datalynx-renderer', newRenderer);
                    liveButton.innerHTML = this.buildButtonLabel(
                        liveButton.getAttribute('data-datalynx-field') || field,
                        newBehavior,
                        newRenderer
                    );
                    ed.undoManager.add();
                } else {
                    liveButton.setAttribute('data-datalynx-behavior', newBehavior);
                    liveButton.setAttribute('data-datalynx-renderer', newRenderer);
                    liveButton.innerHTML = this.buildButtonLabel(field, newBehavior, newRenderer);
                }
                modal.hide();
            });

            // Delete button inside the modal body.
            modal.getBody()[0].addEventListener('click', (e) => {
                if (e.target.closest('[data-action="dlx-delete"]')) {
                    const liveButton = this.findButtonById(buttonId) || button;
                    liveButton.remove();
                    modal.hide();
                }
            });

        } else {
            // Action tag: simple modal with only a delete option.
            const bodyHtml =
                '<p>' + (button.getAttribute('data-datalynx-field') || button.textContent) + '</p>' +
                '<button type="button" class="btn btn-danger btn-sm" data-action="dlx-delete">' + deleteLabel + '</button>';

            const modal = await Modal.create({
                title: titleStr,
                body: bodyHtml,
                show: true,
                removeOnClose: true,
            });

            modal.getBody()[0].addEventListener('click', (e) => {
                if (e.target.closest('[data-action="dlx-delete"]')) {
                    const liveButton = this.findButtonById(buttonId) || button;
                    liveButton.remove();
                    modal.hide();
                }
            });
        }
    }

    waitForTinyMCE() {
        if (window.tinyMCE?.activeEditor && window.tinyMCE.get().length > 0) {
            this.initReplaceTagsWithButtons();
        } else {
            setTimeout(() => this.waitForTinyMCE(), 100);
        }
    }

    initReplaceTagsWithButtons() {
        window.tinyMCE.get().forEach((editor) => {
            editor.on('SetContent', () => this.reInitializeButtons(editor));
            editor.on('change', () => this.reInitializeButtons(editor));
            // SaveContent fires inside editor.save() BEFORE content is written to the textarea.
            // This is the only reliable interception point to convert buttons back to tags.
            editor.on('SaveContent', (e) => {
                e.content = this.convertButtonsInHtml(e.content);
            });
            if (editor.initialized) {
                this.replaceTagsWithButtons(editor);
            } else {
                editor.on('init', () => this.replaceTagsWithButtons(editor));
            }
        });
    }

    /**
     * Convert datalynx button elements in an HTML string back to tag syntax.
     * Called from the SaveContent hook.
     * @param {string} html Serialized editor HTML from TinyMCE
     * @returns {string} HTML with buttons replaced by [[field|behavior|renderer]] / ##action##
     */
    convertButtonsInHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;

        div.querySelectorAll('button[data-action-tag-button]').forEach((btn) => {
            btn.replaceWith('##' + btn.getAttribute('data-datalynx-field') + '##');
        });

        div.querySelectorAll('button.datalynx-field-tag').forEach((btn) => {
            const field = (btn.getAttribute('data-datalynx-field') || '').trim();
            const behavior = btn.getAttribute('data-datalynx-behavior') || '';
            const renderer = btn.getAttribute('data-datalynx-renderer') || '';
            let tag;
            if (renderer) {
                tag = '[[' + field + '|' + behavior + '|' + renderer + ']]';
            } else if (behavior) {
                tag = '[[' + field + '|' + behavior + ']]';
            } else {
                tag = '[[' + field + ']]';
            }
            btn.replaceWith(tag);
        });

        return div.innerHTML;
    }

    insertTagFromDropdown(dropdown) {
        const selectedValue = dropdown.value;
        const actionTagMatch = selectedValue.match(/^##(.+?)##$/);
        const fieldTagMatch = selectedValue.match(/^\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]$/);
        let contentToInsert = '';

        if (actionTagMatch) {
            const action = actionTagMatch[1];
            contentToInsert =
                '<button type="button" contenteditable="false" data-action-tag-button="true"' +
                ' data-datalynx-field="' + action + '">' + action + '</button>';
        } else if (fieldTagMatch) {
            const field = fieldTagMatch[1];
            const behavior = fieldTagMatch[2] || '';
            const renderer = fieldTagMatch[3] || '';
            contentToInsert =
                '<button type="button" contenteditable="false" class="datalynx-field-tag"' +
                ' data-datalynx-field="' + field + '" data-datalynx-behavior="' + behavior + '"' +
                ' data-datalynx-renderer="' + renderer + '">' +
                this.buildButtonLabel(field, behavior, renderer) + '</button>';
        } else {
            contentToInsert = selectedValue;
        }

        const editorIdMatch = dropdown.id.match(/^(.+)_editor_.+_tag_menu$/);
        if (editorIdMatch) {
            const editorId = 'id_' + editorIdMatch[1] + '_editor';
            window.tinyMCE.get().forEach((editor) => {
                if (editor.id === editorId) {
                    editor.insertContent(contentToInsert);
                    this.reInitializeButtons(editor);
                }
            });
        }
    }
}

export const init = () => {
    const optionsElement = document.getElementById('mod_datalynx-patterndialogue-options');
    const options = optionsElement ? JSON.parse(optionsElement.textContent) : {};
    const patterndialogue = new PatternDialogue(options);
    patterndialogue.init();
};
