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
 * Manages dynamic loading of course groups via AJAX when the course selection changes.
 *
 * @module      mod_datalynx/coursegroup
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    init(options) {
      const {coursefield, groupfield, acturl: actionurl} = options;
      // Update input field with the selected group id
      document.getElementById(`id_${groupfield}`).addEventListener('change', function() {
        const selectedValue = document.querySelector(`#id_${groupfield} option:checked`).value;
        document.getElementById(`id_${groupfield}id`).value = selectedValue;
      });
      // When the course is changed, fetch groups using AJAX
      document.getElementById(`id_${coursefield}`).addEventListener('change', function() {
        const groupSelect = document.getElementById(`id_${groupfield}`);
        const selectedCourseId = document.querySelector(`#id_${coursefield} option:checked`).value;
        // Remove current options
        groupSelect.innerHTML = '';
        // Load groups for selected course
        if (selectedCourseId !== '0') {
          // Fetch groups via fetch API
          return fetch(actionurl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `courseid=${selectedCourseId}`
          })
          .then(response => {
            // Return the response text to the next .then()
            return response.text();
          })
          .then(data => {
            if (data) {
              // Populate group options
              data.split(',').forEach(group => {
                const value = group.split(' ', 1)[0];
                const option = document.createElement('option');
                option.value = value;
                option.textContent = group;
                groupSelect.appendChild(option);
              });
            }
            // Return null if there's nothing further to do
            return null;
          })
          .catch(() => {
            throw new Error('Group loading failed');
          });
        } else {
          return Promise.resolve(); // Return a resolved promise if no action is needed
        }
      });
    }
  };