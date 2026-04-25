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

export default {
    getLastVisibleInput(wrapper) {
        return wrapper.querySelector('.fieldgroup-lastvisible');
    },

    getVisibleLineCount(wrapper) {
        return Array.from(wrapper.querySelectorAll('.lines[data-line]'))
            .filter(line => window.getComputedStyle(line).display !== 'none').length;
    },

    getAddButton(wrapper) {
        return wrapper.querySelector('.fieldgroup-addline');
    },

    updateAddButtonState(wrapper, maxlines) {
        const addButton = this.getAddButton(wrapper);
        const lastVisibleInput = this.getLastVisibleInput(wrapper);

        if (!addButton) {
            return;
        }

        const visibleLineCount = lastVisibleInput
            ? parseInt(lastVisibleInput.value, 10)
            : this.getVisibleLineCount(wrapper);
        const canAddMoreLines = visibleLineCount < maxlines;
        addButton.disabled = !canAddMoreLines;
        addButton.style.display = canAddMoreLines ? '' : 'none';
    },

    clearLine(thisLine) {
        // Remove all associated files.
        thisLine.querySelectorAll('.fp-file').forEach(file => {
            file.click();
            document.querySelector(".fp-file-delete:visible")?.click();
            document.querySelector(".fp-dlg-butconfirm:visible")?.click();
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

    init(fieldgroupname, defaultlines, maxlines, requiredlines) {
        document.querySelectorAll(`div.datalynx-field-wrapper[data-field-type='fieldgroup'][data-field-name='${fieldgroupname}']`)
            .forEach(wrapper => {
                const visiblelines = parseInt(defaultlines, 10);

                // Hide lines after the visible range.
                for (let line = visiblelines + 1; line <= maxlines; line++) {
                    wrapper.querySelectorAll(`[data-line='${line}']`).forEach(element => {
                        element.style.display = 'none';
                    });
                }

                const button = this.getAddButton(wrapper);
                if (!button) {
                    return;
                }

            button.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent default button behavior
                const firstHiddenLine = Array.from(wrapper.querySelectorAll("[data-line]"))
                    .find(line => window.getComputedStyle(line).display === 'none');
                if (firstHiddenLine) {
                    firstHiddenLine.style.display = ''; // Show the first hidden line
                }

                // Increment lastvisible if less than maxlines
                const lastVisibleInput = this.getLastVisibleInput(wrapper);
                if (lastVisibleInput && parseInt(lastVisibleInput.value, 10) < maxlines) {
                    lastVisibleInput.value = parseInt(lastVisibleInput.value, 10) + 1;
                }

                this.updateAddButtonState(wrapper, maxlines);
            });

                this.updateAddButtonState(wrapper, maxlines);

                // Remove line functionality.
                wrapper.querySelectorAll('[data-removeline]').forEach(removeButton => {
                    removeButton.removeEventListener('click', this.handleRemoveLine);
                    removeButton.addEventListener('click', this.handleRemoveLine
                        .bind(this, removeButton, maxlines, requiredlines));
                });
        });
    },

    handleRemoveLine(removeButton, maxlines, requiredlines, e) {
        e.preventDefault(); // Prevent default link behavior

        const thisLine = removeButton.closest('.lines');
        const lineId = parseInt(thisLine.dataset.line, 10);
        const parentContainer = thisLine.closest('.datalynx-field-wrapper');
        const lastVisibleInput = this.getLastVisibleInput(parentContainer);
        const currentVisibleLines = lastVisibleInput
            ? parseInt(lastVisibleInput.value, 10)
            : this.getVisibleLineCount(parentContainer);

        this.clearLine(thisLine);

        if (currentVisibleLines <= requiredlines) {
            this.updateAddButtonState(parentContainer, maxlines);
            return;
        }

        // If there are extra visible rows, collapse the later rows upward.
        if (lineId >= 1) {
            thisLine.style.display = 'none';
            if (lastVisibleInput) {
                lastVisibleInput.value = currentVisibleLines - 1;
            }

            parentContainer.querySelectorAll('.lines[data-line]').forEach(line => {
                const currentLineId = parseInt(line.dataset.line, 10);
                if (currentLineId > lineId && currentLineId <= currentVisibleLines) {
                    line.dataset.line = currentLineId - 1;
                } else if (currentLineId === lineId) {
                    line.dataset.line = currentVisibleLines;
                }
            });

            const rowElements = Array.from(parentContainer.querySelectorAll('.lines[data-line]'));
            const footerAnchor = parentContainer.querySelector('.fieldgroup-addline-wrapper');
            rowElements
                .sort((first, second) => parseInt(first.dataset.line, 10) - parseInt(second.dataset.line, 10))
                .forEach(line => {
                    if (footerAnchor) {
                        parentContainer.insertBefore(line, footerAnchor);
                    } else {
                        parentContainer.appendChild(line);
                    }
                });
        }

        this.updateAddButtonState(parentContainer, maxlines);
    }
};
