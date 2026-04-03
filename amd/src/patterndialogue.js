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
 * Button convention
 * -----------------
 * Every button stores the **complete** datalynx tag in its single
 * `data-datalynx-field` attribute so that saving is trivial:
 *
 *   Action tags  →  data-datalynx-field="##entries##"
 *   Field tags   →  data-datalynx-field="[[fieldname|behavior|renderer]]"
 *
 * `convertButtonsInHtml()` therefore just replaces every button with the
 * value of that one attribute — no further parsing needed.
 *
 * @module     mod_datalynx/patterndialogue
 * @copyright  2025 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Str from 'core/str';
import Modal from 'core/modal';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';

/** Regex that matches a full [[field|behavior|renderer]] tag (behavior and renderer optional). */
const FIELD_TAG_RE = /^\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]$/;

/**
 * Parse a field tag pattern into its components.
 * @param {string} pattern  e.g. "[[Text|Behavior|Renderer]]"
 * @returns {{field:string, behavior:string, renderer:string}}
 */
function parseFieldTag(pattern) {
    const m = pattern.match(FIELD_TAG_RE);
    return {
        field:    m ? m[1] : '',
        behavior: m ? (m[2] || '') : '',
        renderer: m ? (m[3] || '') : '',
    };
}

/**
 * Build a [[field|behavior|renderer]] pattern string.
 * Omits trailing empty segments.
 * @param {string} field
 * @param {string} behavior
 * @param {string} renderer
 * @returns {string}
 */
function buildFieldTagPattern(field, behavior, renderer) {
    if (renderer) {
        return '[[' + field + '|' + behavior + '|' + renderer + ']]';
    }
    if (behavior) {
        return '[[' + field + '|' + behavior + ']]';
    }
    return '[[' + field + ']]';
}

class PatternDialogue {
    constructor(options) {
        this.options = options;
        this._currentButton = null;
        // Track which button DOM elements already have a click listener.
        // Using a WeakSet (keyed on the actual element reference) instead of a
        // data-attribute so that fresh DOM nodes created after a TinyMCE source-edit
        // are never falsely considered "already initialized".
        this._initializedButtons = new WeakSet();
    }

    init() {
        // _editorsWithMenus: editors that have a FIELD tag menu and therefore need
        // tag→button replacement and the click dialog.
        // Editors with only a 'general' tag menu (esection_editor) must NOT get
        // replaceTagsWithButtons — their ##action## content works fine as plain text
        // and was working before these JS changes. Touching it causes ## to be lost.
        this._editorsWithMenus = new Set();

        document.querySelectorAll('select[id$="_tag_menu"]').forEach((dropdown) => {
            dropdown.addEventListener('change', () => this.insertTagFromDropdown(dropdown));

            // Only 'field' tag menus require button-replacement and dialogs.
            const m = dropdown.id.match(/^(.+_editor)_field_tag_menu$/);
            if (m) {
                this._editorsWithMenus.add('id_' + m[1]);
            }
        });

        this.waitForTinyMCE();
    }

    // -------------------------------------------------------------------------
    // Button ↔ tag conversion
    // -------------------------------------------------------------------------

    /**
     * Replace datalynx tag strings in the editor with clickable buttons.
     * The full tag pattern is stored in data-datalynx-field so saving is trivial.
     * @param {object} editor TinyMCE editor instance
     */
    replaceTagsWithButtons(editor) {
        const div = document.createElement('div');
        div.innerHTML = editor.getContent();

        div.innerHTML = div.innerHTML
            // ##action## tags
            .replace(/##([^#]+)##/g, (match, action) =>
                '<button type="button" contenteditable="false"' +
                ' class="btn btn-sm btn-outline-info"' +
                ' data-action-tag-button="true"' +
                ' data-datalynx-field="' + match + '">' + action + '</button>'
            )
            // [[field|behavior|renderer]] tags
            .replace(/\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]/g,
                (match, field, behavior, renderer) => {
                    behavior = behavior || '';
                    renderer = renderer || '';
                    return '<button type="button" contenteditable="false"' +
                        ' class="btn btn-sm btn-outline-secondary datalynx-field-tag"' +
                        ' data-datalynx-field="' + match + '">' +
                        this.buildButtonLabel(field, behavior, renderer) + '</button>';
                }
            );

        editor.setContent(div.innerHTML);
    }

    /**
     * Convert datalynx buttons in an HTML string back to their tag syntax.
     * Trivial: every button stores the full tag in data-datalynx-field.
     * Called from the GetContent hook (with e.save===true) so it fires before TinyMCE writes to the textarea.
     * @param {string} html
     * @returns {string}
     */
    convertButtonsInHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        div.querySelectorAll(
            'button[data-action-tag-button], button.datalynx-field-tag'
        ).forEach((btn) => {
            const tag = btn.getAttribute('data-datalynx-field') || '';
            btn.replaceWith(tag);
        });
        return div.innerHTML;
    }

    // -------------------------------------------------------------------------
    // Button label helpers
    // -------------------------------------------------------------------------

    /**
     * Build the inner HTML for a field-tag button.
     * Shows the field name plus Bootstrap-compatible behavior/renderer badges.
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

    // -------------------------------------------------------------------------
    // Button lifecycle
    // -------------------------------------------------------------------------

    /**
     * Attach click listeners to newly inserted buttons and refresh badge labels.
     * @param {object} editor TinyMCE editor instance
     */
    reInitializeButtons(editor) {
        const allBtns = editor.getBody().querySelectorAll(
            'button[data-action-tag-button], button.datalynx-field-tag'
        );
        allBtns.forEach((button) => {
            if (!this._initializedButtons.has(button)) {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this._currentButton = button;
                    this.openMoodleDialog(button);
                });
                this._initializedButtons.add(button);
            }
            // Refresh badge label from the stored pattern.
            if (button.classList.contains('datalynx-field-tag')) {
                const {field, behavior, renderer} = parseFieldTag(
                    button.getAttribute('data-datalynx-field') || ''
                );
                button.innerHTML = this.buildButtonLabel(field, behavior, renderer);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Editor / TinyMCE wiring
    // -------------------------------------------------------------------------

    /**
     * Find the TinyMCE editor that owns a given DOM element.
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

    waitForTinyMCE() {
        if (window.tinyMCE) {
            this.initReplaceTagsWithButtons();
        } else {
            setTimeout(() => this.waitForTinyMCE(), 100);
        }
    }

    initReplaceTagsWithButtons() {
        const registered = new Set();

        const registerEditor = (editor) => {
            if (registered.has(editor.id)) {
                return;
            }
            registered.add(editor.id);

            // Only editors with a FIELD tag menu need button-replacement and SaveContent.
            // Editors with only general/character menus (e.g. esection_editor) must be left
            // untouched — their ##action## content is stored as plain text and works correctly.
            if (!this._editorsWithMenus.has(editor.id)) {
                return;
            }

            // TinyMCE 6: SaveContent clones saveArgs, so e.content mutations don't propagate.
            // GetContent IS used by reference (postProcessGetContent returns dispatcherArgs.content).
            // We only convert when the get is triggered by a save() call (e.save === true).
            editor.on('GetContent', (e) => {
                if (e.save) {
                    e.content = this.convertButtonsInHtml(e.content);
                }
            });

            editor.on('SetContent', () => this.reInitializeButtons(editor));
            editor.on('change', () => this.reInitializeButtons(editor));
            if (editor.initialized) {
                this.replaceTagsWithButtons(editor);
            } else {
                editor.on('init', () => this.replaceTagsWithButtons(editor));
            }
        };

        // Editors already known (e.g. tab is already visible).
        window.tinyMCE.get().forEach(registerEditor);

        // Editors that initialize later (accordion / lazy tabs).
        window.tinyMCE.on('AddEditor', ({editor}) => registerEditor(editor));
        window.tinyMCE.on('RemoveEditor', ({editor}) => registered.delete(editor.id));
    }

    // -------------------------------------------------------------------------
    // Dialog
    // -------------------------------------------------------------------------

    /**
     * Open a Moodle dialog for editing a tag button's properties.
     * Field-tag buttons use ModalSaveCancel; action-tag buttons use a plain Modal.
     * @param {HTMLElement} button
     */
    async openMoodleDialog(button) {
        const isFieldTag = button.classList.contains('datalynx-field-tag');

        const [behaviorLabel, rendererLabel, deleteLabel] = await Promise.all([
            Str.get_string('behavior', 'datalynx'),
            Str.get_string('renderer', 'datalynx'),
            Str.get_string('deletetag', 'datalynx'),
        ]);

        const pattern = button.getAttribute('data-datalynx-field') || '';

        if (isFieldTag) {
            const {field, behavior: currentBehavior, renderer: currentRenderer} = parseFieldTag(pattern);
            const fieldType = (this.options.types || {})[field] || '';
            const tagtype = 'Field';
            const tagname = field;
            const titleStr = await Str.get_string('tagproperties', 'datalynx', {tagtype, tagname});

            const behaviorsHtml = Object.entries(this.options.behaviors || {})
                .map(([val, label]) =>
                    '<option value="' + val + '"' +
                    (val === currentBehavior ? ' selected="selected"' : '') + '>' +
                    label + '</option>'
                ).join('');
            const renderersHtml = Object.entries((this.options.renderers || {})[field] || {})
                .map(([val, label]) =>
                    '<option value="' + val + '"' +
                    (val === currentRenderer ? ' selected="selected"' : '') + '>' +
                    label + '</option>'
                ).join('');

            const fieldInfo = field + (fieldType ? ' <small class="text-muted">(' + fieldType + ')</small>' : '');
            const bodyHtml =
                '<p><strong data-region="datalynx-tag-field">' + fieldInfo + '</strong></p>' +
                '<div class="form-group">' +
                '<label for="dlx-behavior-select">' + behaviorLabel + '</label>' +
                '<select class="form-control custom-select" id="dlx-behavior-select" name="dlx-behavior-select"' +
                ' data-region="tag-behavior-select">' +
                behaviorsHtml + '</select></div>' +
                '<div class="form-group">' +
                '<label for="dlx-renderer-select">' + rendererLabel + '</label>' +
                '<select class="form-control custom-select" id="dlx-renderer-select" name="dlx-renderer-select"' +
                ' data-region="tag-renderer-select">' +
                renderersHtml + '</select></div>' +
                '<div class="mt-2">' +
                '<button type="button" class="btn btn-danger btn-sm" data-action="dlx-delete" data-region="delete-tag">' +
                deleteLabel + '</button></div>';

            const modal = await ModalSaveCancel.create({
                title: titleStr,
                body: bodyHtml,
                show: true,
                removeOnClose: true,
            });

            modal.getRoot().on(ModalEvents.save, (e) => {
                e.preventDefault();
                const modalBody = modal.getBody()[0];
                const newBehavior = modalBody.querySelector('#dlx-behavior-select')?.value || '';
                const newRenderer = modalBody.querySelector('#dlx-renderer-select')?.value || '';
                const newPattern = buildFieldTagPattern(field, newBehavior, newRenderer);

                const ed = this.findEditorForButton(button);
                if (ed) {
                    ed.dom.setAttrib(button, 'data-datalynx-field', newPattern);
                    button.innerHTML = this.buildButtonLabel(field, newBehavior, newRenderer);
                    ed.undoManager.add();
                } else {
                    button.setAttribute('data-datalynx-field', newPattern);
                    button.innerHTML = this.buildButtonLabel(field, newBehavior, newRenderer);
                }
                modal.hide();
            });

            const deleteTagBtn = modal.getBody()[0].querySelector('[data-action="dlx-delete"]');
            if (deleteTagBtn) {
                deleteTagBtn.addEventListener('click', () => {
                    const ed = this.findEditorForButton(button);
                    if (ed) {
                        // Remove the button and the offscreen-selection clone TinyMCE creates
                        // for contenteditable=false elements when they are selected.
                        ed.dom.remove(button);
                        ed.getBody().querySelectorAll('.mce-offscreen-selection').forEach(
                            el => el.parentNode.removeChild(el)
                        );
                        ed.undoManager.add();
                    } else {
                        button.remove();
                    }
                    modal.hide();
                });
            }

        } else {
            // Action tag: display the tag text, allow deletion only.
            const action = pattern.replace(/^##|##$/g, '');
            const titleStr = await Str.get_string('tagproperties', 'datalynx', {tagtype: 'Action', tagname: action});
            const bodyHtml =
                '<p><code>' + pattern + '</code></p>' +
                '<button type="button" class="btn btn-danger btn-sm" data-action="dlx-delete" data-region="delete-tag">' +
                deleteLabel + '</button>';

            const modal = await Modal.create({
                title: titleStr,
                body: bodyHtml,
                show: true,
                removeOnClose: true,
            });

            const deleteActionBtn = modal.getBody()[0].querySelector('[data-action="dlx-delete"]');
            if (deleteActionBtn) {
                deleteActionBtn.addEventListener('click', () => {
                    const ed = this.findEditorForButton(button);
                    if (ed) {
                        ed.dom.remove(button);
                        ed.getBody().querySelectorAll('.mce-offscreen-selection').forEach(
                            el => el.parentNode.removeChild(el)
                        );
                        ed.undoManager.add();
                    } else {
                        button.remove();
                    }
                    modal.hide();
                });
            }
        }
    }

    // -------------------------------------------------------------------------
    // Dropdown tag insertion
    // -------------------------------------------------------------------------

    insertTagFromDropdown(dropdown) {
        const selectedValue = dropdown.value;
        const actionTagMatch = selectedValue.match(/^##(.+?)##$/);
        const fieldTagMatch  = selectedValue.match(FIELD_TAG_RE);
        let contentToInsert  = '';

        if (actionTagMatch) {
            const action = actionTagMatch[1];
            contentToInsert =
                '<button type="button" contenteditable="false"' +
                ' class="btn btn-sm btn-outline-info"' +
                ' data-action-tag-button="true"' +
                ' data-datalynx-field="' + selectedValue + '">' + action + '</button>';
        } else if (fieldTagMatch) {
            const field    = fieldTagMatch[1];
            const behavior = fieldTagMatch[2] || '';
            const renderer = fieldTagMatch[3] || '';
            contentToInsert =
                '<button type="button" contenteditable="false"' +
                ' class="btn btn-sm btn-outline-secondary datalynx-field-tag"' +
                ' data-datalynx-field="' + selectedValue + '">' +
                this.buildButtonLabel(field, behavior, renderer) + '</button>';
        } else {
            contentToInsert = selectedValue;
        }

        const m = dropdown.id.match(/^(.+_editor)_.+_tag_menu$/);
        if (m) {
            const editorId = 'id_' + m[1];
            const ed = window.tinyMCE?.get(editorId);
            if (ed) {
                ed.insertContent(contentToInsert);
                this.reInitializeButtons(ed);
            }
        }
    }
}

export const init = () => {
    const optionsElement = document.getElementById('mod_datalynx-patterndialogue-options');
    const options = optionsElement ? JSON.parse(optionsElement.textContent) : {};
    const patterndialogue = new PatternDialogue(options);
    patterndialogue.init();
};
