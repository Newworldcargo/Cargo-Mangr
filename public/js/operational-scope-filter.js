(function () {
    'use strict';

    function initializeScopeFilters() {
        document.querySelectorAll('[data-operational-scope-filter]').forEach(function (form) {
            if (form.dataset.scopeFilterReady === 'true') return;
            form.dataset.scopeFilterReady = 'true';

            var branch = form.querySelector('[data-scope-branch]');
            var user = form.querySelector('[data-scope-user]');
            var self = form.querySelector('[data-scope-self]');
            var submit = function () {
                if (typeof form.requestSubmit === 'function') form.requestSubmit();
                else form.submit();
            };

            [branch, user].forEach(function (select) {
                if (!select) return;
                select.addEventListener('change', function () {
                    if (self) self.checked = false;
                    submit();
                });
            });

            if (self) {
                self.addEventListener('change', function () {
                    if (self.checked) {
                        if (branch) branch.value = '';
                        if (user) user.value = '';
                    }
                    submit();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeScopeFilters);
    } else {
        initializeScopeFilters();
    }
}());
