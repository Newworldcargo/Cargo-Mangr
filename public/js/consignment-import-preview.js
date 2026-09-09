document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('importRowsSelectAll');
    var rowCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.import-row-checkbox:not(:disabled)'));

    if (selectAll) {
        var refreshSelectAll = function () {
            var selected = rowCheckboxes.filter(function (checkbox) { return checkbox.checked; }).length;
            selectAll.checked = rowCheckboxes.length > 0 && selected === rowCheckboxes.length;
            selectAll.indeterminate = selected > 0 && selected < rowCheckboxes.length;
        };

        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(function (checkbox) { checkbox.checked = selectAll.checked; });
            refreshSelectAll();
        });
        rowCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', refreshSelectAll);
        });
        refreshSelectAll();
    }

    document.querySelectorAll('form[data-confirm-message]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm-message'))) event.preventDefault();
        });
    });
});
