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