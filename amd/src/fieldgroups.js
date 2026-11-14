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
 * Handles dynamic display of field group lines, allowing users to add more lines up to a maximum.
 *
 * @module      mod_datalynx/fieldgroups
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const fieldGroupConfigs = new Map();
let isListeningForClicks = false;
let isListeningForUpdates = false;

const fieldGroups = {
    getLastVisibleInput(wrapper) {
        return wrapper.querySelector('.fieldgroup-lastvisible');
    },

    getRows(wrapper) {
        return Array.from(wrapper.querySelectorAll('.lines[data-line]'));
    },

    getFieldgroupName(wrapper) {
        return wrapper.getAttribute('data-field-name') || '';
    },

    getFieldgroupToken(wrapper) {
        return wrapper.querySelector('.fieldgroup-marker')?.getAttribute('name') || '';
    },

    getVisibleLineCount(wrapper) {
        return this.getRows(wrapper)
            .filter(line => window.getComputedStyle(line).display !== 'none').length;
    },

    getConfig(wrapper) {
        const fieldgroupname = this.getFieldgroupName(wrapper);
        return fieldGroupConfigs.get(fieldgroupname) || null;
    },

    lineHasContent(line) {
        if (line.querySelector('.fp-file, .form-autocomplete-selection .tag')) {
            return true;
        }

        const inputs = Array.from(line.querySelectorAll('input'));
        if (inputs.some(input => {
            if (input.type === 'hidden' || input.disabled) {
                return false;
            }

            if (input.type === 'checkbox' || input.type === 'radio') {
                return input.checked;
            }

            return input.value !== '';
        })) {
            return true;
        }

        if (Array.from(line.querySelectorAll('textarea')).some(textarea => !textarea.disabled && textarea.value !== '')) {
            return true;
        }

        // The selects of a date that is not enabled always have an option selected, but are no content.
        // Check the "Enable" checkbox, as the selects may not be disabled yet when this runs.
        const isInactiveDate = select => {
            const enabled = select.closest('fieldset')?.querySelector('input[type="checkbox"][name$="[enabled]"]');
            return !!enabled && !enabled.checked;
        };
        return Array.from(line.querySelectorAll('select')).some(select => !select.disabled && !isInactiveDate(select) &&
            Array.from(select.options).some(option => option.selected && option.value !== '' && option.value !== '-999')
        );
    },

    getInitialVisibleLineCount(wrapper, defaultlines, requiredlines) {
        const lastPrefilledLine = this.getRows(wrapper).reduce((highestVisibleLine, line) => {
            const currentLine = parseInt(line.dataset.line, 10);
            if (this.lineHasContent(line) && currentLine > highestVisibleLine) {
                return currentLine;
            }

            return highestVisibleLine;
        }, 0);

        return Math.max(parseInt(defaultlines, 10), parseInt(requiredlines, 10), lastPrefilledLine);
    },

    getAddButton(wrapper) {
        return wrapper.querySelector('.fieldgroup-addline');
    },

    isVisible(element) {
        return !!element && window.getComputedStyle(element).display !== 'none' && element.getClientRects().length > 0;
    },

    /**
     * Name the row and its delete button after the row number, using the templates from the renderer.
     *
     * @param {HTMLElement} line the row
     * @param {Object} labels label templates ({line} = row number, {row} = row label)
     */
    labelLine(line, labels) {
        if (!labels) {
            return;
        }
        const rowlabel = labels.row.split('{line}').join(line.dataset.line);
        line.setAttribute('aria-label', rowlabel);
        const removeButton = line.querySelector('[data-removeline]');
        if (removeButton) {
            removeButton.setAttribute('aria-label', labels.delete.split('{row}').join(rowlabel));
        }
    },

    /**
     * Announce a change in the fieldgroup's polite live region.
     *
     * @param {HTMLElement} wrapper the fieldgroup wrapper
     * @param {string} template message template with {row}
     * @param {number} lineNumber the row number
     */
    announce(wrapper, template, lineNumber) {
        const config = this.getConfig(wrapper);
        const status = wrapper.querySelector('.fieldgroup-status');
        if (!config?.labels || !status || !template) {
            return;
        }
        const rowlabel = config.labels.row.split('{line}').join(lineNumber);
        // Clear first so that the same message is announced again.
        status.textContent = '';
        window.setTimeout(() => {
            status.textContent = template.split('{row}').join(rowlabel);
        }, 50);
    },

    focusFirstInput(line) {
        const target = Array.from(line.querySelectorAll(
            'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), ' +
            'button:not([disabled]):not([data-removeline]), [contenteditable="true"]'
        )).find(element => this.isVisible(element));
        (target || line.querySelector('[data-removeline]'))?.focus();
    },

    replaceLineIndex(value, fieldgroupname, oldline, newline) {
        if (!value || !fieldgroupname || oldline === newline) {
            return value;
        }

        const oldtoken = `${fieldgroupname}_${oldline - 1}`;
        const newtoken = `${fieldgroupname}_${newline - 1}`;

        return value.split(oldtoken).join(newtoken);
    },

    renumberLine(line, fieldgroupname, newline) {
        const oldline = parseInt(line.dataset.line, 10);
        if (oldline === newline) {
            return;
        }

        const attrs = ['name', 'id', 'for', 'aria-describedby', 'data-groupname'];
        [line, ...line.querySelectorAll('*')].forEach(element => {
            attrs.forEach(attr => {
                if (!element.hasAttribute(attr)) {
                    return;
                }

                element.setAttribute(
                    attr,
                    this.replaceLineIndex(element.getAttribute(attr), fieldgroupname, oldline, newline)
                );
            });
        });

        const removeButton = line.querySelector('[data-removeline]');
        if (removeButton) {
            removeButton.dataset.removeline = newline;
        }

        line.dataset.line = newline;
    },

    updateAddButtonState(wrapper, maxlines) {
        const addButton = this.getAddButton(wrapper);
        const addButtonWrapper = addButton?.closest('.fieldgroup-addline-wrapper');
        const lastVisibleInput = this.getLastVisibleInput(wrapper);

        if (!addButton) {
            return;
        }

        const visibleLineCount = lastVisibleInput
            ? parseInt(lastVisibleInput.value, 10)
            : this.getVisibleLineCount(wrapper);
        const canAddMoreLines = visibleLineCount < maxlines;
        if (typeof addButton.dataset.originalLabel === 'undefined') {
            addButton.dataset.originalLabel = addButton.textContent.trim();
        }

        addButton.disabled = !canAddMoreLines;
        addButton.hidden = !canAddMoreLines;
        addButton.style.display = canAddMoreLines ? '' : 'none';
        addButton.textContent = canAddMoreLines ? addButton.dataset.originalLabel : '';
        addButton.setAttribute('aria-hidden', canAddMoreLines ? 'false' : 'true');

        if (addButtonWrapper) {
            addButtonWrapper.hidden = !canAddMoreLines;
            addButtonWrapper.style.display = canAddMoreLines ? '' : 'none';
        }
    },

    clearLine(thisLine) {
        // Remove all associated files.
        thisLine.querySelectorAll('.fp-file').forEach(file => {
            file.click();
            document.querySelector('.fp-file-delete:visible')?.click();
            document.querySelector('.fp-dlg-butconfirm:visible')?.click();
        });

        // Clear input fields (except hidden).
        thisLine.querySelectorAll('input').forEach(input => {
            if (input.type === 'hidden') {
                return;
            }

            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
                return;
            }

            input.value = '';
        });

        // Clear editor content (TinyMCE) and textareas.
        thisLine.querySelectorAll('textarea').forEach(textarea => {
            if (window.tinyMCE) {
                const editor = window.tinyMCE.get(textarea.id);
                if (editor) {
                    editor.setContent('');
                    return;
                }
            }
            textarea.value = '';
        });

        // Clear select elements.
        thisLine.querySelectorAll('select').forEach(select => {
            const emptyOption = select.querySelector('option[value=""]');
            if (emptyOption) {
                emptyOption.selected = true;
            } else {
                select.selectedIndex = -1;
            }
        });

        // Deactivate time/date and remove tags.
        thisLine.querySelectorAll('[id$=enabled]:checked, .form-autocomplete-selection .tag').forEach(element => {
            element.click();
        });
    },

    initialiseWrapper(wrapper) {
        const config = this.getConfig(wrapper);
        if (!config || wrapper.dataset.fieldgroupInitialised === '1') {
            return;
        }

        const visiblelines = this.getInitialVisibleLineCount(wrapper, config.defaultlines, config.requiredlines);
        const lastVisibleInput = this.getLastVisibleInput(wrapper);

        if (lastVisibleInput) {
            lastVisibleInput.value = visiblelines;
        }

        for (let line = 1; line <= config.maxlines; line++) {
            wrapper.querySelectorAll(`[data-line='${line}']`).forEach(element => {
                element.style.display = line <= visiblelines ? '' : 'none';
            });
        }

        if (config.labels?.group) {
            wrapper.setAttribute('role', 'group');
            wrapper.setAttribute('aria-label', config.labels.group);
        }

        this.updateAddButtonState(wrapper, config.maxlines);
        wrapper.dataset.fieldgroupInitialised = '1';
    },

    initialiseWrappers(root = document, fieldgroupname = null) {
        const selector = fieldgroupname
            ? `div.datalynx-field-wrapper[data-field-type='fieldgroup'][data-field-name='${fieldgroupname}']`
            : 'div.datalynx-field-wrapper[data-field-type="fieldgroup"]';

        root.querySelectorAll(selector).forEach(wrapper => {
            this.initialiseWrapper(wrapper);
        });
    },

    handleAddLine(addButton, event) {
        const wrapper = addButton.closest('.datalynx-field-wrapper');
        const config = wrapper ? this.getConfig(wrapper) : null;
        if (!wrapper || !config) {
            return;
        }

        this.initialiseWrapper(wrapper);
        event.preventDefault();

        const firstHiddenLine = this.getRows(wrapper)
            .find(line => window.getComputedStyle(line).display === 'none');
        if (firstHiddenLine) {
            firstHiddenLine.style.display = '';
        }

        const lastVisibleInput = this.getLastVisibleInput(wrapper);
        if (lastVisibleInput && parseInt(lastVisibleInput.value, 10) < config.maxlines) {
            lastVisibleInput.value = parseInt(lastVisibleInput.value, 10) + 1;
        }

        this.updateAddButtonState(wrapper, config.maxlines);

        // Move focus into the new row and announce it.
        if (firstHiddenLine) {
            this.focusFirstInput(firstHiddenLine);
            this.announce(wrapper, config.labels?.added, firstHiddenLine.dataset.line);
        }
    },

    handleRemoveLine(removeButton, event) {
        const thisLine = removeButton.closest('.lines');
        const parentContainer = thisLine?.closest('.datalynx-field-wrapper');
        const config = parentContainer ? this.getConfig(parentContainer) : null;

        if (!thisLine || !parentContainer || !config) {
            return;
        }

        this.initialiseWrapper(parentContainer);
        event.preventDefault();

        const lineId = parseInt(thisLine.dataset.line, 10);
        const lastVisibleInput = this.getLastVisibleInput(parentContainer);
        const currentVisibleLines = lastVisibleInput
            ? parseInt(lastVisibleInput.value, 10)
            : this.getVisibleLineCount(parentContainer);

        this.clearLine(thisLine);

        if (currentVisibleLines <= config.requiredlines) {
            // Required rows are only cleared; focus stays on the delete button.
            this.updateAddButtonState(parentContainer, config.maxlines);
            this.announce(parentContainer, config.labels?.cleared, lineId);
            return;
        }

        if (lineId >= 1) {
            const fieldgroupname = this.getFieldgroupToken(parentContainer);
            thisLine.style.display = 'none';
            if (lastVisibleInput) {
                lastVisibleInput.value = currentVisibleLines - 1;
            }

            parentContainer.querySelectorAll('.lines[data-line]').forEach(line => {
                const currentLineId = parseInt(line.dataset.line, 10);
                if (currentLineId > lineId && currentLineId <= currentVisibleLines) {
                    this.renumberLine(line, fieldgroupname, currentLineId - 1);
                } else if (currentLineId === lineId) {
                    this.renumberLine(line, fieldgroupname, currentVisibleLines);
                }
                this.labelLine(line, config.labels);
            });

            // The rows and the add button are siblings, but a custom layout may wrap them in its own element.
            const rowElements = Array.from(parentContainer.querySelectorAll('.lines[data-line]'));
            const footerAnchor = parentContainer.querySelector('.fieldgroup-addline-wrapper');
            const rowParent = footerAnchor?.parentNode || rowElements[0]?.parentNode || parentContainer;
            rowElements
                .sort((first, second) => parseInt(first.dataset.line, 10) - parseInt(second.dataset.line, 10))
                .forEach(line => {
                    if (footerAnchor) {
                        rowParent.insertBefore(line, footerAnchor);
                    } else {
                        rowParent.appendChild(line);
                    }
                });
        }

        this.updateAddButtonState(parentContainer, config.maxlines);

        // The focused delete button is gone: go to the previous row's delete button, else the add button,
        // else the first row. Then announce the deletion.
        const visibleRows = this.getRows(parentContainer).filter(line => this.isVisible(line));
        const previousRow = visibleRows.find(line => parseInt(line.dataset.line, 10) === lineId - 1);
        const addButton = this.getAddButton(parentContainer);
        if (previousRow?.querySelector('[data-removeline]')) {
            previousRow.querySelector('[data-removeline]').focus();
        } else if (addButton && this.isVisible(addButton) && !addButton.disabled) {
            addButton.focus();
        } else if (visibleRows.length) {
            this.focusFirstInput(visibleRows[0]);
        }
        this.announce(parentContainer, config.labels?.deleted, lineId);
    },

    registerDelegatedHandlers() {
        if (!isListeningForClicks) {
            document.addEventListener('click', (event) => {
                if (!(event.target instanceof Element)) {
                    return;
                }

                const addButton = event.target.closest('.fieldgroup-addline');
                if (addButton) {
                    this.handleAddLine(addButton, event);
                    return;
                }

                const removeButton = event.target.closest('[data-removeline]');
                if (removeButton) {
                    this.handleRemoveLine(removeButton, event);
                }
            });
            isListeningForClicks = true;
        }

        if (!isListeningForUpdates) {
            document.addEventListener('mod_datalynx:viewContentUpdated', (event) => {
                const root = event.detail?.target;
                if (root instanceof Element || root instanceof DocumentFragment || root instanceof Document) {
                    this.initialiseWrappers(root);
                }
            });
            isListeningForUpdates = true;
        }
    },

    init(fieldgroupname, defaultlines, maxlines, requiredlines, labels = null) {
        fieldGroupConfigs.set(fieldgroupname, {
            defaultlines: parseInt(defaultlines, 10),
            maxlines: parseInt(maxlines, 10),
            requiredlines: parseInt(requiredlines, 10),
            labels,
        });

        this.registerDelegatedHandlers();
        this.initialiseWrappers(document, fieldgroupname);
    }
};

export const init = (...args) => fieldGroups.init(...args);

export default fieldGroups;
