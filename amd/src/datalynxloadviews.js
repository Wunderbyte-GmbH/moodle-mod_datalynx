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
 * Dynamically loads datalynx views and textfields via AJAX based on selected datalynx instance.
 *
 * @module      mod_datalynx/datalynxloadviews
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    init(options) {
        // Destructure the options object for easier access
        const { dffield, viewfield, textfieldfield, acturl: actionurl, presentdlid, thisfieldstring, update, fieldtype } = options;

        // Read courseid and call ajax at change to receive all groups in course.
        const dffieldElement = document.getElementById(`id_${dffield}`);
        const viewElement = document.getElementById(`id_${viewfield}`);
        const textfieldElement = document.getElementById(`id_${textfieldfield}`);

        if (dffieldElement) {
            dffieldElement.addEventListener("change", function() {
                const dfid = this.value; // Get the datalynx id.

                // Remove view and textfield options.
                if (viewElement) {
                    viewElement.innerHTML = ''; // Clear all current options.
                }
                if (textfieldElement) {
                    textfieldElement.innerHTML = ''; // Clear all current options.
                }

                // Load views and/or textfields from datalynx if dfid is not zero.
                if (dfid != 0) {
                    // Fetch request to get current options.
                    return fetch(actionurl, {
                        method: "POST",
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `dfid=${dfid}`
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.text(); // Ensure we return the result of this then
                    })
                    .then(data => {
                        if (data !== '') {
                            const respoptions = data.split('#');

                            // Add view options.
                            if (viewElement) {
                                const viewoptions = respoptions[0].split(',');
                                viewoptions.forEach(option => {
                                    const [qid, ...qnameArr] = option.trim().split(' ');
                                    const qname = qnameArr.join(' ');
                                    const optionElement = document.createElement('option');
                                    optionElement.value = qid;
                                    optionElement.textContent = qname;
                                    viewElement.appendChild(optionElement);
                                });
                            }

                            // Add textfield options.
                            if (textfieldElement) {
                                const textfieldoptions = respoptions[1].split(',');

                                // If this datalynx instance itself is chosen, provide this new field itself as first option.
                                if (dfid == presentdlid && update === 0 && fieldtype === 'text') {
                                    const optionElement = document.createElement('option');
                                    optionElement.value = '-1';
                                    optionElement.textContent = thisfieldstring;
                                    textfieldElement.appendChild(optionElement);
                                }

                                textfieldoptions.forEach(option => {
                                    const [qid, ...qnameArr] = option.trim().split(' ');
                                    const qname = qnameArr.join(' ');
                                    const optionElement = document.createElement('option');
                                    optionElement.value = qid;
                                    optionElement.textContent = qname;
                                    textfieldElement.appendChild(optionElement);
                                });
                            }
                        }
                        return data; // Ensure we return the result of this then
                    })
                    .catch(() => {
                        throw new Error("Error while loading views and textfields.");
                    });
                } else {
                    // If dfid is 0, we should return undefined explicitly to satisfy consistent-return.
                    return undefined;
                }
            });
        }
    }
};
