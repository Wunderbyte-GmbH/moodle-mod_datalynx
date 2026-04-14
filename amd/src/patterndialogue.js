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

import Ajax from 'core/ajax';
import * as Str from 'core/str';
import Modal from 'core/modal';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Templates from 'core/templates';

/** Regex that matches a full [[field|behavior|renderer]] tag (behavior and renderer optional). */
const FIELD_TAG_RE = /^\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]$/;
const VIEW_URL_TAG_RE = /^##viewurl(?::([^#]+))?##$/;
const VIEW_LINK_TAG_RE = /^##(viewlink|viewsesslink):([^;#]+);([^;#]*);([^;#]*);([^#]*)##$/;

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

/**
 * Escape HTML special characters for safe inline rendering.
 * @param {string} value
 * @returns {string}
 */
function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Parse a view-reference pattern into its editable parts.
 * @param {string} pattern
 * @returns {{type:string, viewname:string, linktext:string, urlquery:string, cssclass:string}}
 */
function parseViewTag(pattern) {
    let match = pattern.match(VIEW_LINK_TAG_RE);
    if (match) {
        return {
            type: match[1],
            viewname: match[2] || '',
            linktext: match[3] || '',
            urlquery: match[4] || '',
            cssclass: match[5] || '',
        };
    }

    match = pattern.match(VIEW_URL_TAG_RE);
    if (match) {
        return {
            type: 'viewurl',
            viewname: match[1] || '',
            linktext: '',
            urlquery: '',
            cssclass: '',
        };
    }

    return {
        type: '',
        viewname: '',
        linktext: '',
        urlquery: '',
        cssclass: '',
    };
}

/**
 * Build a view-reference pattern string.
 * @param {string} type
 * @param {string} viewname
 * @param {string} linktext
 * @param {string} urlquery
 * @param {string} cssclass
 * @returns {string}
 */
function buildViewTagPattern(type, viewname, linktext = '', urlquery = '', cssclass = '') {
    if (type === 'viewurl') {
        return viewname ? `##viewurl:${viewname}##` : '##viewurl##';
    }

    return `##${type}:${viewname};${linktext};${urlquery};${cssclass}##`;
}

/**
 * Build a stable field id suffix for a view-reference dialog.
 *
 * @param {string} editorId
 * @returns {string}
 */
function getViewDialogFieldSuffix(editorId = '') {
    if (editorId.includes('esection_editor')) {
        return 'view';
    }

    if (editorId.includes('eparam2_editor')) {
        return 'entry';
    }

    return editorId
        .replace(/^id_/, '')
        .replace(/[^a-z0-9]+/gi, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase() || 'dialog';
}

/**
 * Build the DOM ids used in the view-reference dialog.
 *
 * @param {string} editorId
 * @returns {{viewselect: string, linktext: string, urlquery: string, cssclass: string}}
 */
function getViewDialogFieldIds(editorId = '') {
    const suffix = getViewDialogFieldSuffix(editorId);

    return {
        viewselect: `dlx-view-select-${suffix}`,
        linktext: `dlx-link-text-${suffix}`,
        urlquery: `dlx-url-query-${suffix}`,
        cssclass: `dlx-css-class-${suffix}`,
    };
}

/**
 * Add stable selectors to Datalynx save/cancel modals.
 * @param {object} modal Moodle modal instance
 * @param {string} modalClass Specific modal type class
 */
function decorateSaveCancelModal(modal, modalClass) {
    const root = modal.getRoot();
    root.addClass('datalynx-tag-modal');
    root.addClass(modalClass);
    root.find('[data-action="save"]').addClass('datalynx-tag-modal-save');
}

class PatternDialogue {
    constructor(options) {
        this.options = options;
        this._editorConfigs = new Map();
        this._referenceEditors = new Set(options.referenceeditors || []);
        this._hasPreloadedViews = Array.isArray(options.views);
        this._preloadedViews = this._hasPreloadedViews ? options.views : [];
        this._viewsPromise = null;
    }

    getEditorConfig(editorId) {
        if (!this._editorConfigs.has(editorId)) {
            this._editorConfigs.set(editorId, {
                supportsFieldTags: false,
                supportsReferenceTags: false,
                initializedButtons: new WeakSet(),
            });
        }

        return this._editorConfigs.get(editorId);
    }

    init() {
        document.querySelectorAll('select[id$="_tag_menu"]').forEach((dropdown) => {
            dropdown.addEventListener('change', () => this.insertTagFromDropdown(dropdown));

            const match = dropdown.id.match(/^(.+_editor)_(.+)_tag_menu$/);
            if (!match) {
                return;
            }

            const editorId = 'id_' + match[1];
            const tagType = match[2];
            const config = this.getEditorConfig(editorId);

            if (tagType === 'field') {
                config.supportsFieldTags = true;
            } else if (tagType === 'general' && this._referenceEditors.has(editorId)) {
                config.supportsReferenceTags = true;
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
        const config = this.getEditorConfig(editor.id);
        let html = editor.getContent();

        if (config.supportsReferenceTags) {
            html = this.replaceReferenceTagsInHtml(html, editor.id);
        }

        if (config.supportsFieldTags) {
            html = this.replaceFieldTagsInHtml(html, editor.id);
        }

        editor.setContent(html);
    }

    replaceFieldTagsInHtml(html, editorId = '') {
        return html
            .replace(/##([^#]+)##/g, (match, action) => {
                if (VIEW_URL_TAG_RE.test(match) || VIEW_LINK_TAG_RE.test(match)) {
                    return match;
                }

                return this.buildActionTagButtonHtml(match, action, editorId);
            })
            .replace(/\[\[([^\|\]]+)(?:\|([^\|\]]*))?(?:\|([^\|\]]*))?\]\]/g,
                (match, field, behavior, renderer) => {
                    behavior = behavior || '';
                    renderer = renderer || '';
                    return this.buildFieldTagButtonHtml(match, field, behavior, renderer, editorId);
                }
            );
    }

    replaceReferenceTagsInHtml(html, editorId = '') {
        return html
            .replace(/##viewurl(?::[^#]+)?##/g, (match) => this.buildViewTagButtonHtml(match, editorId))
            .replace(/##(?:viewlink|viewsesslink):[^;#]+;[^;#]*;[^;#]*;[^#]*##/g,
                (match) => this.buildViewTagButtonHtml(match, editorId)
            );
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
            'button[data-action-tag-button], button.datalynx-field-tag, button.datalynx-view-tag'
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
        let html = escapeHtml(field);
        if (behavior) {
            html += ' <span class="badge badge-info bg-info" style="pointer-events:none">' + escapeHtml(behavior) + '</span>';
        }
        if (renderer) {
            html += ' <span class="badge badge-secondary bg-secondary" style="pointer-events:none">' +
                escapeHtml(renderer) + '</span>';
        }
        return html;
    }

    buildEditorIdAttribute(editorId = '') {
        return editorId ? ' data-datalynx-editor-id="' + escapeHtml(editorId) + '"' : '';
    }

    buildActionTagButtonHtml(pattern, action, editorId = '') {
        return '<button type="button" contenteditable="false"' +
            ' class="btn btn-sm btn-outline-info"' +
            ' data-action-tag-button="true"' +
            this.buildEditorIdAttribute(editorId) +
            ' data-datalynx-field="' + escapeHtml(pattern) + '">' + escapeHtml(action) + '</button>';
    }

    buildFieldTagButtonHtml(pattern, field, behavior, renderer, editorId = '') {
        return '<button type="button" contenteditable="false"' +
            ' class="btn btn-sm btn-outline-secondary datalynx-field-tag"' +
            this.buildEditorIdAttribute(editorId) +
            ' data-datalynx-field="' + escapeHtml(pattern) + '">' +
            this.buildButtonLabel(field, behavior, renderer) + '</button>';
    }

    buildViewTagButtonLabel(type, viewname, linktext) {
        let html = escapeHtml(type);
        if (viewname) {
            html += ': ' + escapeHtml(viewname);
        }
        if (linktext) {
            html += ' <span class="badge badge-primary bg-primary" style="pointer-events:none">' +
                escapeHtml(linktext) + '</span>';
        }
        return html;
    }

    buildViewTagButtonHtml(pattern, editorId = '') {
        const {type, viewname, linktext} = parseViewTag(pattern);
        return '<button type="button" contenteditable="false"' +
            ' class="btn btn-sm btn-outline-primary datalynx-view-tag"' +
            this.buildEditorIdAttribute(editorId) +
            ' data-datalynx-field="' + escapeHtml(pattern) + '">' +
            this.buildViewTagButtonLabel(type, viewname, linktext) + '</button>';
    }

    // -------------------------------------------------------------------------
    // Button lifecycle
    // -------------------------------------------------------------------------

    /**
     * Attach click listeners to newly inserted buttons and refresh badge labels.
     * @param {object} editor TinyMCE editor instance
     */
    reInitializeButtons(editor) {
        const config = this.getEditorConfig(editor.id);
        const allBtns = editor.getBody().querySelectorAll(
            'button[data-action-tag-button], button.datalynx-field-tag, button.datalynx-view-tag'
        );
        allBtns.forEach((button) => {
            button.setAttribute('data-datalynx-editor-id', editor.id);

            if (!config.initializedButtons.has(button)) {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.openMoodleDialog(button, editor);
                });
                config.initializedButtons.add(button);
            }
            // Refresh badge label from the stored pattern.
            if (button.classList.contains('datalynx-field-tag')) {
                const {field, behavior, renderer} = parseFieldTag(
                    button.getAttribute('data-datalynx-field') || ''
                );
                button.innerHTML = this.buildButtonLabel(field, behavior, renderer);
            }
            if (button.classList.contains('datalynx-view-tag')) {
                const {type, viewname, linktext} = parseViewTag(
                    button.getAttribute('data-datalynx-field') || ''
                );
                button.innerHTML = this.buildViewTagButtonLabel(type, viewname, linktext);
            }
        });
    }

    async getViews() {
        if (!this._viewsPromise) {
            if (this._hasPreloadedViews) {
                this._viewsPromise = Promise.resolve(this._preloadedViews);
            } else {
                this._viewsPromise = Ajax.call([{
                    methodname: 'mod_datalynx_get_view_names',
                    args: {d: this.options.datalynxid},
                }])[0];
            }
        }

        return this._viewsPromise;
    }

    removeButton(button, editor = null) {
        const ed = editor || this.findEditorForButton(button);
        if (ed) {
            ed.dom.remove(button);
            ed.getBody().querySelectorAll('.mce-offscreen-selection').forEach(
                el => el.parentNode.removeChild(el)
            );
            ed.undoManager.add();
        } else {
            button.remove();
        }
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
        const editorId = btn.getAttribute('data-datalynx-editor-id');
        if (editorId) {
            const editor = window.tinyMCE?.get(editorId);
            if (editor) {
                return editor;
            }
        }

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
            const config = this.getEditorConfig(editor.id);
            if (!config.supportsFieldTags && !config.supportsReferenceTags) {
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
     * @param {object|null} editor
     */
    async openMoodleDialog(button, editor = null) {
        const owningEditor = editor || this.findEditorForButton(button);
        const isFieldTag = button.classList.contains('datalynx-field-tag');
        const isViewTag = button.classList.contains('datalynx-view-tag');

        const [behaviorLabel, rendererLabel, deleteLabel, viewLabel, linkTextLabel, urlQueryLabel, classLabel,
            currentViewLabel] = await Promise.all([
            Str.get_string('behavior', 'datalynx'),
            Str.get_string('renderer', 'datalynx'),
            Str.get_string('deletetag', 'datalynx'),
            Str.get_string('view', 'datalynx'),
            Str.get_string('viewpatternlinktext', 'datalynx'),
            Str.get_string('viewpatternurlquery', 'datalynx'),
            Str.get_string('viewpatternclass', 'datalynx'),
            Str.get_string('targetviewthis', 'datalynx'),
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
            decorateSaveCancelModal(modal, 'datalynx-field-tag-modal');

            modal.getRoot().on(ModalEvents.save, (e) => {
                e.preventDefault();
                const modalBody = modal.getBody()[0];
                const newBehavior = modalBody.querySelector('#dlx-behavior-select')?.value || '';
                const newRenderer = modalBody.querySelector('#dlx-renderer-select')?.value || '';
                const newPattern = buildFieldTagPattern(field, newBehavior, newRenderer);

                if (owningEditor) {
                    owningEditor.dom.setAttrib(button, 'data-datalynx-field', newPattern);
                    button.innerHTML = this.buildButtonLabel(field, newBehavior, newRenderer);
                    owningEditor.undoManager.add();
                } else {
                    button.setAttribute('data-datalynx-field', newPattern);
                    button.innerHTML = this.buildButtonLabel(field, newBehavior, newRenderer);
                }
                modal.hide();
            });

            const deleteTagBtn = modal.getBody()[0].querySelector('[data-action="dlx-delete"]');
            if (deleteTagBtn) {
                deleteTagBtn.addEventListener('click', () => {
                    this.removeButton(button, owningEditor);
                    modal.hide();
                });
            }

        } else if (isViewTag) {
            const {type, viewname, linktext, urlquery, cssclass} = parseViewTag(pattern);
            const views = await this.getViews();
            const editorId = owningEditor?.id || button.getAttribute('data-datalynx-editor-id') || '';
            const fieldids = getViewDialogFieldIds(editorId);
            const titleStr = await Str.get_string('tagproperties', 'datalynx', {
                tagtype: await Str.get_string('reference', 'datalynx'),
                tagname: type,
            });
            const selectOptions = [];

            if (type === 'viewurl') {
                selectOptions.push(
                    {
                        value: '',
                        name: currentViewLabel,
                        selected: !viewname,
                    }
                );
            }

            views.forEach((view) => {
                selectOptions.push(
                    {
                        value: view.name,
                        name: view.name,
                        selected: view.name === viewname,
                    }
                );
            });

            const isViewUrl = type === 'viewurl';

            const modal = await ModalSaveCancel.create({
                title: titleStr,
                body: Templates.render('mod_datalynx/tiny_view_tag_modal', {
                    fieldids,
                    labels: {
                        view: viewLabel,
                        linktext: linkTextLabel,
                        urlquery: urlQueryLabel,
                        cssclass: classLabel,
                        delete: deleteLabel,
                    },
                    options: selectOptions,
                    showlinkoptions: !isViewUrl,
                    values: {
                        linktext,
                        urlquery,
                        cssclass,
                    },
                }),
                show: true,
                removeOnClose: true,
            });
            decorateSaveCancelModal(modal, 'datalynx-view-tag-modal');

            modal.getRoot().on(ModalEvents.save, (e) => {
                e.preventDefault();
                const modalBody = modal.getBody()[0];
                const selectedView = modalBody.querySelector(`#${fieldids.viewselect}`)?.value || '';
                if (!isViewUrl && !selectedView) {
                    return;
                }

                const newPattern = buildViewTagPattern(
                    type,
                    selectedView,
                    modalBody.querySelector(`#${fieldids.linktext}`)?.value || '',
                    modalBody.querySelector(`#${fieldids.urlquery}`)?.value || '',
                    modalBody.querySelector(`#${fieldids.cssclass}`)?.value || ''
                );

                const {type: newType, viewname: newViewName, linktext: newLinkText} = parseViewTag(newPattern);
                if (owningEditor) {
                    owningEditor.dom.setAttrib(button, 'data-datalynx-field', newPattern);
                    button.innerHTML = this.buildViewTagButtonLabel(newType, newViewName, newLinkText);
                    owningEditor.undoManager.add();
                } else {
                    button.setAttribute('data-datalynx-field', newPattern);
                    button.innerHTML = this.buildViewTagButtonLabel(newType, newViewName, newLinkText);
                }

                modal.hide();
            });

            const deleteTagBtn = modal.getBody()[0].querySelector('[data-action="dlx-delete"]');
            if (deleteTagBtn) {
                deleteTagBtn.addEventListener('click', () => {
                    this.removeButton(button, owningEditor);
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
                    this.removeButton(button, owningEditor);
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
        const viewTagMatch = selectedValue.match(VIEW_LINK_TAG_RE) || selectedValue.match(VIEW_URL_TAG_RE);
        let contentToInsert  = '';

        const m = dropdown.id.match(/^(.+_editor)_.+_tag_menu$/);
        if (m) {
            const editorId = 'id_' + m[1];
            const config = this.getEditorConfig(editorId);

            if (config.supportsReferenceTags && viewTagMatch) {
                contentToInsert = this.buildViewTagButtonHtml(selectedValue, editorId);
            } else if (config.supportsFieldTags && actionTagMatch) {
                contentToInsert = this.buildActionTagButtonHtml(selectedValue, actionTagMatch[1], editorId);
            } else if (config.supportsFieldTags && fieldTagMatch) {
                const field = fieldTagMatch[1];
                const behavior = fieldTagMatch[2] || '';
                const renderer = fieldTagMatch[3] || '';
                contentToInsert = this.buildFieldTagButtonHtml(selectedValue, field, behavior, renderer, editorId);
            } else {
                contentToInsert = selectedValue;
            }

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
