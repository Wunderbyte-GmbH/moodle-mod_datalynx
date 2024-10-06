export default {
    init(fieldgroupname, defaultlines, maxlines, requiredlines, fieldgroup) {
        // Increment defaultlines to match the logic in original code
        defaultlines++;

        // Loop from defaultlines to maxlines to hide lines
        for (let line = defaultlines; line <= maxlines; line++) {
            document.querySelectorAll(`div[data-field-name='${fieldgroupname}'] [data-line='${line}']`).forEach(element => {
                element.style.display = 'none'; // Hide the line
            });
        }

        // Add button functionality to show hidden lines
        document.querySelectorAll("div.datalynx-field-wrapper #id_addline").forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent default button behavior
                const wrapper = button.closest(".datalynx-field-wrapper");
                const firstHiddenLine = wrapper.querySelector("[data-line]:not([style*='display: none'])");
                if (firstHiddenLine) {
                    firstHiddenLine.style.display = ''; // Show the first hidden line
                }

                // Increment lastvisible if less than maxlines
                const lastVisibleInput = document.querySelector(`input[name='${fieldgroup}_lastvisible']`);
                if (parseInt(lastVisibleInput.value, 10) < maxlines) {
                    lastVisibleInput.value = parseInt(lastVisibleInput.value, 10) + 1;
                }
            });
        });

        // Remove line functionality
        document.querySelectorAll(`div[data-field-name='${fieldgroupname}'] [data-removeline]`).forEach(removeButton => {
            removeButton.removeEventListener('click', this.handleRemoveLine); // Remove any existing event listeners
            removeButton.addEventListener('click', this.handleRemoveLine
                .bind(this, removeButton, fieldgroupname, maxlines, requiredlines, fieldgroup)); // Attach new event listener
        });
    },

    handleRemoveLine(removeButton, fieldgroupname, maxlines, requiredlines, fieldgroup, e) {
        e.preventDefault(); // Prevent default link behavior

        const thisLine = removeButton.closest('.lines');
        const lineId = parseInt(thisLine.dataset.line, 10);
        const parentContainer = thisLine.closest('.datalynx-field-wrapper');
        const lastVisibleLine = Array.from(parentContainer
            .querySelectorAll('.lines')).reverse().find(line => line.style.display !== 'none');
        const lastVisibleLineId = parseInt(lastVisibleLine.dataset.line, 10);

        // Remove all associated files
        thisLine.querySelectorAll('.fp-file').forEach(file => {
            file.click();
            document.querySelector(".fp-file-delete:visible")?.click();
            document.querySelector(".fp-dlg-butconfirm:visible")?.click();
        });

        // Clear input fields (except hidden)
        thisLine.querySelectorAll('input').forEach(input => {
            if (input.type !== 'hidden') {
                input.value = '';
            }
        });

        // Clear editor content
        thisLine.querySelectorAll('.editor_atto_content').forEach(editor => {
            editor.innerHTML = '';
        });

        // Clear textareas
        thisLine.querySelectorAll('textarea').forEach(textarea => {
            textarea.value = '';
        });

        // Clear select elements
        thisLine.querySelectorAll('select').forEach(select => {
            select.querySelector('option[value=""]').selected = true;
        });

        // Deactivate time/date and remove tags
        thisLine.querySelectorAll('[id$=enabled]:checked, .form-autocomplete-selection .tag').forEach(element => {
            element.click();
        });

        // Update last visible line
        const lastVisibleInput = document.querySelector(`input[name='${fieldgroup}_lastvisible']`);
        lastVisibleInput.value = parseInt(lastVisibleInput.value, 10) - 1;

        // Hide the line if not required
        if (lineId > requiredlines && lineId >= 1) {
            thisLine.style.display = 'none';

            // Reorder lines
            if (lineId !== maxlines) {
                parentContainer.querySelectorAll('[data-line]').forEach(line => {
                    const currentLineId = parseInt(line.dataset.line, 10);
                    if (currentLineId > lineId && currentLineId <= lastVisibleLineId) {
                        line.dataset.line = currentLineId - 1;
                    } else if (currentLineId === lineId) {
                        line.dataset.line = lastVisibleLineId;
                    }
                });
            }

            // Rebuild content order
            const newContentOrder = [];
            parentContainer.querySelectorAll('[data-line]').forEach(line => {
                newContentOrder[line.dataset.line] = line;
            });

            parentContainer.innerHTML = ''; // Clear parent container
            newContentOrder.reverse().forEach(line => {
                if (line) {
                    parentContainer.appendChild(line);
                }
            });
        }
    }
};
