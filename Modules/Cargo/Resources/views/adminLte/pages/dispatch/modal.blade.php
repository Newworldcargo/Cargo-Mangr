<style>
    #dispatch-dialog { border:0; border-radius:8px; padding:24px; width:480px; max-width:calc(100% - 32px); color:#212529; }
    #dispatch-dialog::backdrop { background:rgba(0,0,0,.5); }
    #dispatch-dialog .btn-primary { background:#007bff; color:#fff; }
</style>
<dialog id="dispatch-dialog" aria-labelledby="dispatch-title">
    <form id="dispatch-form">
        @csrf
        <h2 id="dispatch-title" class="h5">Send to dispatch</h2>
        <p id="dispatch-reference" class="font-weight-bold"></p>
        <p id="dispatch-current" class="text-muted" role="status"></p>
        <label for="dispatch-driver">Driver (optional)</label>
        <select id="dispatch-driver" class="form-control" name="driver_id"><option value="">Assign later</option></select>
        <p id="dispatch-error" class="text-danger mt-3" role="alert" hidden></p>
        <button id="dispatch-retry" type="button" class="btn btn-outline-secondary mt-2" hidden>Try again</button>
        <div class="d-flex justify-content-end mt-4" style="gap:12px">
            <button id="dispatch-cancel" class="btn btn-outline-secondary" type="button">Cancel</button>
            <button id="dispatch-submit" class="btn btn-primary" type="submit">Send to dispatch</button>
        </div>
    </form>
</dialog>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('dispatch-dialog');
    const form = document.getElementById('dispatch-form');
    const driver = document.getElementById('dispatch-driver');
    const submit = document.getElementById('dispatch-submit');
    const error = document.getElementById('dispatch-error');
    const retry = document.getElementById('dispatch-retry');
    const current = document.getElementById('dispatch-current');
    const cancel = document.getElementById('dispatch-cancel');
    const driversUrl = @json(fr_route('shipments.dispatch.drivers', ['shipment' => '__ID__']));
    const storeUrl = @json(fr_route('shipments.dispatch.store', ['shipment' => '__ID__']));
    let id, busy = false, controller;
    function showError(message) { error.textContent = message; error.hidden = false; }
    async function loadDrivers() {
        controller?.abort(); controller = new AbortController();
        submit.disabled = true; driver.disabled = true; error.hidden = true; retry.hidden = true;
        current.textContent = 'Loading drivers...';
        driver.replaceChildren(new Option('Assign later', ''));
        try {
            const response = await fetch(driversUrl.replace('__ID__', id), {headers: {'Accept':'application/json'}, signal:controller.signal});
            if (!response.ok) throw new Error(response.status === 403 ? 'You do not have permission to dispatch this shipment.' : 'We could not load drivers. Please try again.');
            const data = await response.json();
            driver.options[0].textContent = data.currentDriver ? 'Keep current driver' : 'Assign later';
            data.drivers.forEach(item => driver.add(new Option(item.name, item.id)));
            current.textContent = data.currentDriver ? 'Current driver: ' + data.currentDriver : (data.drivers.length ? 'No driver assigned.' : 'No active drivers available for this branch. You can assign one later.');
            if (data.missionAssigned) current.textContent += ' Driver changes are managed through the assigned mission.';
            driver.disabled = data.missionAssigned || !data.drivers.length; submit.disabled = false;
        } catch (e) { if (e.name !== 'AbortError') { current.textContent = ''; showError(e.message); retry.hidden = false; } }
    }
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-dispatch-shipment]');
        if (!button || busy) return;
        id = button.dataset.dispatchShipment;
        document.getElementById('dispatch-reference').textContent = button.dataset.dispatchCode;
        submit.textContent = button.textContent.trim() === 'Assign driver' ? 'Save assignment' : 'Send to dispatch';
        dialog.showModal(); loadDrivers();
    });
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => controller?.abort());
    retry.addEventListener('click', loadDrivers);
    form.addEventListener('submit', async function (event) {
        event.preventDefault(); if (busy || submit.disabled) return;
        busy = true; submit.disabled = true; cancel.disabled = true; error.hidden = true;
        const label = submit.textContent; submit.textContent = 'Saving...';
        try {
            const response = await fetch(storeUrl.replace('__ID__', id), {method:'POST', headers:{'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}, body:JSON.stringify({driver_id:driver.disabled ? null : (driver.value || null)})});
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(response.status === 422 ? Object.values(data.errors || {}).flat().join(' ') || 'Please check your selection.' : response.status === 419 || response.status === 401 ? 'Your session has expired. Refresh this page and sign in again.' : 'We could not save dispatch. Please try again.');
            window.location.assign(data.url);
        } catch (e) { showError(e.message); busy = false; submit.disabled = false; cancel.disabled = false; submit.textContent = label; }
    });
});
</script>
