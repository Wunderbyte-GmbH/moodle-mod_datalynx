export default {
    init(approveLabel, unapproveLabel) {

        // Must be defined before the forEach that calls it.
        const extractParams = (paramString) => {
            const params = new URLSearchParams(paramString);
            const output = {};

            for (const [key, value] of params.entries()) {
                output[key] = value;
            }

            if ('approve' in output) {
                output.entryid = output.approve;
                output.action = 'approve';
            } else if ('disapprove' in output) {
                output.entryid = output.disapprove;
                output.action = 'disapprove';
            } else {
                output.entryid = output.approve;
                output.action = 'approve';
            }

            return output;
        };

        document.querySelectorAll(".datalynxfield__approve").forEach(element => {
            const href = element.href;
            const params = extractParams(href.split('?')[1]);

            element.addEventListener('click', (e) => {
                e.preventDefault();

                fetch("field/_approve/ajax.php", {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams(params)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) {
                        return null;
                    }
                    const iconElement = element.querySelector('i');
                    const labelElement = element.querySelector('.datalynxfield__approve-label');
                    const isApproved = iconElement.classList.contains('fa-circle-xmark');

                    if (isApproved) {
                        // Was approved — switch to unapproved state: show "Approve" action
                        iconElement.classList.remove('fa-circle-xmark', 'text-danger');
                        iconElement.classList.add('fa-circle-check', 'text-success');
                        labelElement.textContent = approveLabel;
                        element.title = approveLabel;
                        params.action = 'approve';
                        delete params.disapprove;
                        params.approve = params.entryid;
                    } else {
                        // Was not approved — switch to approved state: show "Unapprove" action
                        iconElement.classList.remove('fa-circle-check', 'text-success');
                        iconElement.classList.add('fa-circle-xmark', 'text-danger');
                        labelElement.textContent = unapproveLabel;
                        element.title = unapproveLabel;
                        params.action = 'disapprove';
                        delete params.approve;
                        params.disapprove = params.entryid;
                    }
                    return null;
                })
                .catch((error) => {
                    throw error;
                });
            });
        });
    }
};
