document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var dialog = document.getElementById('importCustomerDialog');
    if (!dialog) return;
    var search = document.getElementById('importCustomerSearch');
    var results = document.getElementById('importCustomerResults');
    var status = document.getElementById('importCustomerSearchStatus');
    var error = document.getElementById('importCustomerError');
    var picker = document.getElementById('importCustomerPicker');
    var form = document.getElementById('importCustomerCreate');
    var choice = document.getElementById('importCustomerChoice');
    var confirm = document.getElementById('importCustomerConfirm');
    var active, selected, timer, controller, searchVersion = 0, busy = false;

    function showError(message) { error.textContent = message; error.hidden = false; }
    async function request(url, options) {
        var response = await fetch(url, Object.assign({ credentials: 'same-origin' }, options));
        var data = await response.json().catch(function () { return {}; });
        if (!response.ok) {
            var messages = data.errors ? Object.values(data.errors).flat().join(' ') : '';
            throw new Error(messages || (response.status === 419 ? 'Your session expired. Reopen the preview after signing in.' : data.message || 'We could not save this change. Please try again.'));
        }
        return data;
    }
    function choose(customer) {
        selected = customer;
        choice.hidden = false;
        choice.textContent = 'Use ' + customer.name + ' (customer #' + customer.id + ')? Saved account contacts will be used; spreadsheet contacts will not replace them.';
        confirm.disabled = false;
    }
    async function findCustomers() {
        var version = ++searchVersion;
        if (controller) controller.abort();
        controller = new AbortController();
        results.replaceChildren();
        var q = search.value.trim();
        if (q.length < 2) { status.textContent = 'Enter at least two characters.'; return; }
        status.textContent = 'Searching customers...';
        try {
            var data = await request(dialog.dataset.searchUrl + '?q=' + encodeURIComponent(q), { signal: controller.signal, headers: { Accept: 'application/json' } });
            if (version !== searchVersion || !dialog.open) return;
            status.textContent = data.customers.length ? 'Select a customer to review.' : 'No matching customers. Try another name, email or phone.';
            data.customers.forEach(function (customer) {
                var button = document.createElement('button');
                button.type = 'button';
                var name = document.createElement('strong'); name.textContent = customer.name + ' - #' + customer.id;
                var details = document.createElement('div');
                details.className = 'small';
                details.textContent = [customer.email && !customer.email.endsWith('.invalid') ? customer.email : 'No contact email', customer.responsible_mobile, customer.secondary_mobile].filter(Boolean).join(' | ');
                button.append(name, details);
                button.addEventListener('click', function () { choose(customer); });
                results.appendChild(button);
            });
        } catch (failure) {
            if (failure.name !== 'AbortError' && version === searchVersion) status.textContent = 'Search unavailable. Change the search text to retry.';
        }
    }
    function setBusy(value) {
        busy = value;
        dialog.querySelectorAll('button, input, textarea').forEach(function (control) { control.disabled = value; });
        confirm.disabled = value || !selected;
        document.getElementById('importCustomerSaving').hidden = !value;
        document.getElementById('importActionForm').querySelectorAll('button[type="submit"]').forEach(function (button) {
            if (value) { button.dataset.wasDisabled = button.disabled ? '1' : '0'; button.disabled = true; }
            else button.disabled = button.dataset.wasDisabled === '1';
        });
    }
    function updateRow(data) {
        var row = active.closest('[data-import-row]'), value = data.row;
        row.dataset.customerVersion = value.customer_selection_version;
        row.dataset.selectedCustomer = value.selected_customer_id || '';
        var ready = ['new', 'update', 'unchanged'].includes(value.status);
        var checkbox = row.querySelector('.import-row-checkbox');
        var wasDisabled = checkbox.disabled;
        checkbox.disabled = !ready;
        if (!ready) checkbox.checked = false;
        else if (wasDisabled) checkbox.checked = true;
        var badge = row.querySelector('[data-row-status]');
        badge.textContent = value.status;
        badge.className = 'badge badge-' + (ready ? 'success' : 'danger');
        var issues = row.querySelector('[data-row-issues]');
        issues.textContent = Object.values(value.validation_errors || {}).join(' ') || Object.values(value.validation_warnings || {}).join(' ');
        issues.className = 'small ' + (ready ? 'text-muted' : 'text-danger');
        row.querySelector('[data-customer-label]').textContent = value.validation_warnings.customer || 'Customer not yet confirmed';
        active.textContent = value.selected_customer_id ? 'Change customer' : 'Choose / confirm customer';
        row.querySelectorAll('input[name^="phone_override"]').forEach(function (input) {
            input.readOnly = Boolean(value.selected_customer_id);
            input.value = input.name.startsWith('phone_override_2') ? data.phone_2_display || '' : data.phone_display || '';
        });
        var s = data.summary;
        document.getElementById('importLiveSummary').textContent = 'New: ' + s.new + ' | Updating: ' + s.update + ' | Unchanged: ' + s.unchanged + ' | Invalid: ' + s.invalid + ' | Conflicts: ' + s.conflict;
        var button = document.querySelector('[data-confirm-import]');
        if (button) {
            var count = s.new + s.update + s.unchanged;
            button.textContent = button.dataset.confirmLabel + ' (' + count + ' ready)';
            button.disabled = count < 1 || button.dataset.setupComplete !== '1';
        }
        document.dispatchEvent(new Event('import-rows-updated'));
        document.getElementById('importCustomerLiveStatus').textContent = 'Row updated. ' + (ready ? 'Ready to import.' : issues.textContent);
    }
    async function save(payload) {
        if (busy) return;
        var row = active.closest('[data-import-row]');
        error.hidden = true;
        payload.version = Number(row.dataset.customerVersion);
        setBusy(true);
        try {
            var data = await request(active.dataset.saveUrl, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('#importActionForm input[name="_token"]').value }, body: JSON.stringify(payload) });
            setBusy(false);
            updateRow(data);
            dialog.close();
            active.focus();
        } catch (failure) { setBusy(false); showError(failure.message); }
    }
    document.querySelectorAll('[data-pick-customer]').forEach(function (button) {
        var row = button.closest('[data-import-row]');
        if (row.dataset.selectedCustomer) row.querySelectorAll('input[name^="phone_override"]').forEach(function (input) { input.readOnly = true; });
        button.addEventListener('click', function () {
            active = button; selected = null; confirm.disabled = true; choice.hidden = true; error.hidden = true;
            picker.hidden = false; if (form) { form.hidden = true; form.reset(); }
            document.getElementById('importCustomerTitle').textContent = 'Choose customer';
            document.getElementById('importCustomerSource').textContent = 'Row ' + button.dataset.rowNumber + ': ' + button.dataset.rowName;
            search.value = button.dataset.rowPhone || button.dataset.rowName;
            dialog.showModal(); search.focus(); findCustomers();
        });
    });
    search.addEventListener('input', function () { clearTimeout(timer); ++searchVersion; if (controller) controller.abort(); timer = setTimeout(findCustomers, 250); });
    confirm.addEventListener('click', function () { if (selected) save({ customer_id: selected.id }); });
    document.getElementById('importCustomerReset').addEventListener('click', function () { save({ customer_id: null }); });
    dialog.querySelector('[data-close-customer]').addEventListener('click', function () { if (!busy) dialog.close(); });
    dialog.addEventListener('cancel', function (event) { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', function () { ++searchVersion; clearTimeout(timer); if (controller) controller.abort(); });
    if (form) {
        document.getElementById('importCustomerAdd').addEventListener('click', function () { picker.hidden = true; form.hidden = false; error.hidden = true; document.getElementById('importCustomerTitle').textContent = 'Add customer'; form.querySelector('input').focus(); });
        document.getElementById('importCustomerBack').addEventListener('click', function () { form.hidden = true; picker.hidden = false; error.hidden = true; document.getElementById('importCustomerTitle').textContent = 'Choose customer'; search.focus(); });
        form.addEventListener('submit', function (event) { event.preventDefault(); if (form.reportValidity()) save(Object.assign(Object.fromEntries(new FormData(form)), { create: true })); });
    }
});
