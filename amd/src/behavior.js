export default {
    init() {
        document.querySelectorAll('img[data-for]').forEach((img) => {
            img.addEventListener("click", (event) => {
                const clickedImg = event.target;
                const behaviorid = clickedImg.getAttribute('data-behavior-id');
                const permissionid = clickedImg.getAttribute('data-permission-id');
                const forproperty = clickedImg.getAttribute('data-for');
                const sesskey = document.querySelector('table.datalynx-behaviors')?.getAttribute('data-sesskey') || M.cfg.sesskey;
                const actionurl = "behavior_edit_ajax.php";

                // Construct the object
                const obj = {
                    behaviorid,
                    permissionid,
                    forproperty,
                    sesskey
                };

                // Serialize the object to a query string
                const list = Object.keys(obj).map((k) => {
                    const encodedKey = encodeURIComponent(k);
                    const value = obj[k];
                    if (Array.isArray(value)) {
                        return value.map(v => `${encodedKey}[]=${encodeURIComponent(v)}`).join('&');
                    } else {
                        return `${encodedKey}=${encodeURIComponent(value)}`;
                    }
                }).join('&');

                // Create an AbortController to implement timeout
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 5000);

                // Fetch API request with abort signal
                fetch(actionurl, {
                    method: "POST",
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: list,
                    signal: controller.signal
                })
                .then(response => {
                    clearTimeout(timeoutId);
                    return response.text();  // Ensure that you return the result of response.text()
                })
                .then(data => {
                    if (data !== '') {
                        let src = clickedImg.getAttribute("src");
                        if (src.includes("-enabled")) {
                            src = src.replace("-enabled", "-n");
                        } else if (src.includes("-n")) {
                            src = src.replace("-n", "-enabled");
                        }
                        clickedImg.setAttribute("src", src);
                    }
                    return true; // Return a value to satisfy the ESLint rule
                })
                .catch((err) => {
                    throw err;
                });
            });
        });
    }
};
