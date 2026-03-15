export default {
    init() {
        window.select_allnone = (group, checked) => {
            document.querySelectorAll(`input[type="checkbox"][name="${group}selector"]`).forEach(cb => {
                cb.checked = checked;
            });
        };

        window.bulk_action = (group, url, action) => {
            const checkboxes = document.querySelectorAll(
                `input[type="checkbox"][name="${group}selector"]:checked`
            );
            if (!checkboxes.length) {
                return;
            }
            const ids = Array.from(checkboxes).map(cb => cb.value).join(',');
            const targetUrl = new URL(url);
            targetUrl.searchParams.set(action, ids);
            window.location.href = targetUrl.toString();
        };
    }
};
