export default {
    // We get parameters from initialization in render.php
    init: function(fieldid, userurl, username, canunsubscribe) {
        // After initialization we loop through all links for subscribe / unsubscribe.
        document.querySelectorAll("a.datalynxfield_subscribe").forEach(link => {
            const href = link.getAttribute("href");
            const params = extractParams(href.split("?")[1]);

            if (params.fieldid !== fieldid) {
                return;
            }

            params.ajax = true;

            link.addEventListener("click", function(e) {
                e.preventDefault();
                let ul = this.previousElementSibling;

                if (!ul || ul.tagName !== "UL") {
                    ul = document.createElement("ul");
                    this.before(ul);
                }

                const actionurl = "field/teammemberselect/ajax.php";

                // Perform AJAX request using fetch API
                fetch(actionurl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify(params),
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && this.classList.contains("subscribed")) {
                        if (canunsubscribe) {
                            this.classList.toggle("subscribed");
                            this.setAttribute("title", "Eintragen"); // TODO: Multilang.
                            this.innerHTML = "Eintragen"; // TODO: Multilang.
                            params.action = "subscribe";
                            this.setAttribute("href", this.getAttribute("href").replace("unsubscribe", "subscribe"));
                        }
                        removeUser(ul);
                    } else if (data && !this.classList.contains("subscribed")) {
                        this.classList.toggle("subscribed");
                        this.setAttribute("title", "Austragen"); // TODO: Multilang.
                        this.innerHTML = "Austragen"; // TODO: Multilang.
                        params.action = "unsubscribe";
                        this.setAttribute("href", this.getAttribute("href").replace("subscribe", "unsubscribe"));
                        const li = document.createElement("li");
                        li.innerHTML = `<a href="${userurl}">${username}</a>`;
                        ul.appendChild(li);
                    }
                    return undefined; // Explicitly returning a value
                })
                .catch(error => {
                    throw error; // Rethrow the error to follow ESLint rule
                });
            });
        });

        /**
         * Remove user from selection.
         *
         * @param {HTMLElement} listelement
         */
        function removeUser(listelement) {
            const userurlparams = extractParams(userurl.split("?")[1]);

            // Loop through list items and find all <li> elements.
            listelement.querySelectorAll("li a").forEach(anchor => {
                const anchorparams = extractParams(anchor.getAttribute("href").split("?")[1]);

                if (userurlparams.id == anchorparams.id) {
                    anchor.parentElement.remove();
                }
            });

            // Delete <ul> if no <li> is left.
            if (listelement.children.length === 0) {
                listelement.remove();
            }
        }

        /**
         * Extract params.
         * @param {string} paramstring
         * @returns {{}}
         */
        function extractParams(paramstring) {
            return paramstring.split("&").reduce((output, param) => {
                const [key, value] = param.split("=");
                output[key] = value;
                return output;
            }, {});
        }
    }
};
