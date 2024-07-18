export default {
    init(approvedIcon, disapprovedIcon) {
        // After initialization, loop through all elements for subscribe/unsubscribe.
        document.querySelectorAll(".datalynxfield__approve").forEach(element => {
            const href = element.href;
            const params = extractParams(href.split('?')[1]);

            // Add new click event listener
            element.addEventListener('click', (e) => {
                e.preventDefault(); // Don't follow hrefs.

                // AJAX call
                const actionUrl = "field/_approve/ajax.php";
                fetch(actionUrl, {
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
                    const imgElement = element.querySelector('img');
                    if (data && imgElement.classList.contains('approved')) {
                        imgElement.classList.remove('approved');
                        imgElement.src = disapprovedIcon;
                        imgElement.alt = 'approve';
                        imgElement.title = 'approve';
                        params.action = 'approve';
                    } else if (data && !imgElement.classList.contains('approved')) {
                        imgElement.classList.add('approved');
                        imgElement.src = approvedIcon;
                        imgElement.alt = 'disapprove';
                        imgElement.title = 'disapprove';
                        params.action = 'disapprove';
                    }
                    return null;
                })
                .catch((error) => {
                    throw error;
                });
            });
        });

        // Extract params from a query string.
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
    }
};
