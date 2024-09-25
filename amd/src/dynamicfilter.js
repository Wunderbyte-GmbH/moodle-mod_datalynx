define(['core_form/dynamicform', 'core/toast'], (DynamicForm, Toast) => {
    const init = () => {
        const container = document.querySelector('#formcontainer');
        if (!container) {
            return;
        }

        const dynamicForm = new DynamicForm(container, '\mod_datalynx\\mod_datalynx_filter_form');

        dynamicForm.addEventListener(dynamicForm.events.FORM_SUBMITTED, (e) => {
            e.detail.good.forEach(successMessage => {
                Toast.add(successMessage, { type: 'success' });
            });
            e.detail.bad.forEach(errorMessage => {
                Toast.add(errorMessage, { type: 'danger' });
            });

            const searchParams = new URLSearchParams(window.location.search);
            dynamicForm.load({
                d: searchParams.get('d'),
                fid: searchParams.get('fid')
            });
        });

        container.addEventListener('change', (e) => {
            if (e.target.matches('.custom-select') && (e.target.name.startsWith('searchfield')
                || e.target.name.startsWith('sortfield'))) {
                e.preventDefault();
                document.getElementsByName('refreshonly')[0].value = '1';
                dynamicForm.submitFormAjax();
            }
        });

        container.addEventListener('click', (e) => {
            if (e.target.matches('input[type="submit"]')) {
                e.preventDefault();
                document.getElementsByName('refreshonly')[0].value = '0';
                dynamicForm.submitFormAjax();
            }
        });

        // Initialize form loading on page load.
        document.addEventListener('DOMContentLoaded', () => {
            const searchParams = new URLSearchParams(window.location.search);
            dynamicForm.load({
                d: searchParams.get('d'),
                fid: searchParams.get('fid')
            });
        });
    };

    return {
        init
    };
});