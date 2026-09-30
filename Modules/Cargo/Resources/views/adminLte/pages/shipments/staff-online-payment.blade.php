<div class="nav nav-tabs mb-3" role="tablist" aria-label="Payment channel">
    <button type="button" id="offline-payment-tab" class="nav-link active" role="tab" aria-selected="true" aria-controls="offline-payment-panel">Offline</button>
    <button type="button" id="online-payment-tab" class="nav-link" role="tab" aria-selected="false" aria-controls="online-payment-panel">Online</button>
</div>
<div id="online-payment-panel" role="tabpanel" aria-labelledby="online-payment-tab" hidden>
    <label for="online-payment-phone">Mobile money number</label>
    <input id="online-payment-phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="30" class="form-control mb-2" placeholder="e.g. 0972 827 372">
    <p class="text-muted">The customer approves the payment on their phone. Never ask for their PIN.</p>
    <p id="online-payment-bill" class="font-weight-bold"></p>
    <p id="online-payment-status" role="status" aria-live="polite">Checking payment availability...</p>
    <div class="d-flex flex-wrap" style="gap:8px">
        <button type="button" id="online-payment-send" class="btn btn-primary" disabled>Send payment prompt</button>
        <button type="button" id="online-payment-check" class="btn btn-outline-primary">Check payment status</button>
    </div>
</div>
<style>
#online-payment-panel[hidden], #offline-payment-panel[hidden] { display: none !important; }
#markPaidModal[data-payment-mode="online"] #confirmMarkPaidBtn { display: none !important; }
#markPaidModal[data-payment-mode="online"] .offline-payment-summary { display: none !important; }
#markPaidModal[data-payment-mode="online"] .online-existing-bill-summary { display: none !important; }
#markPaidModal[data-payment-locked="true"] #confirmMarkPaidBtn { pointer-events: none; opacity: .5; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('markPaidModal');
    const offline = document.getElementById('offline-payment-tab');
    const online = document.getElementById('online-payment-tab');
    const send = document.getElementById('online-payment-send');
    const check = document.getElementById('online-payment-check');
    const phone = document.getElementById('online-payment-phone');
    const message = document.getElementById('online-payment-status');
    const endpoint = @json(route('shipments.online-payment.store', $shipment->id));
    let busy = false, checking = false, open = false, state = null, key = null, uncertain = false, error = '';
    function mode(value) {
        if (value === 'offline' && (uncertain || (state && state.data && !['failed', 'succeeded'].includes(state.data.status)))) return;
        modal.dataset.paymentMode = value;
        [offline, online].forEach((tab, index) => {
            const selected = value === (index ? 'online' : 'offline');
            tab.classList.toggle('active', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
        });
        document.getElementById('offline-payment-panel').hidden = value !== 'offline';
        document.getElementById('online-payment-panel').hidden = value !== 'online';
        const billLocked = value === 'online' && state && state.bill;
        document.querySelectorAll('#discountType, #discountValue, #charge-rows input, #charge-rows button, #addChargeBtn').forEach(el => el.disabled = !!billLocked || modal.dataset.paymentLocked === 'true');
        ['paymentsTotal', 'remainingTotal', 'paymentsStatus', 'fillRemainingBtn'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.parentElement.classList.add('offline-payment-summary');
        });
    }
    function render() {
        const intent = state && state.data;
        const locked = busy || uncertain || !!(intent && intent.status !== 'failed');
        modal.dataset.paymentLocked = String(locked);
        offline.disabled = locked;
        phone.disabled = locked;
        send.disabled = locked || !state || !state.canPrompt;
        document.querySelectorAll('#discountType, #discountValue, #charge-rows input, #charge-rows button, #addChargeBtn').forEach(el => el.disabled = locked || !!(state && state.bill && modal.dataset.paymentMode === 'online'));
        const bill = state && state.bill;
        document.getElementById('online-payment-bill').textContent = bill ? 'Confirmed bill: ' + bill.currency + ' ' + Number(bill.total).toFixed(2) : '';
        const summary = document.getElementById('finalTotal').closest('.card');
        if (summary) summary.classList.toggle('online-existing-bill-summary', !!bill);
        if (locked) mode('online');
        if (state && state.paid) { message.textContent = 'Payment confirmed. Refreshing shipment...'; window.location.reload(); return; }
        if (uncertain) message.textContent = 'Checking whether the payment was started. Do not collect another payment yet.';
        else if (intent) message.textContent = intent.status === 'failed' ? 'Payment was unsuccessful. You can send a new prompt.' : intent.status === 'review' ? 'This payment needs confirmation by your payment team. Do not collect another payment.' : 'Awaiting payment confirmation. The customer should approve the request on their phone.';
        else message.textContent = state && state.available ? 'Ready to request the full bill amount.' : 'Online payment is not available yet. You can use offline payment.';
        send.textContent = intent && intent.status === 'failed' ? 'Send another prompt' : 'Send payment prompt';
        if (error && !locked) message.textContent = error;
    }
    async function status() {
        if (checking || busy) return;
        checking = true; check.disabled = true;
        try {
            const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', signal: AbortSignal.timeout(30000)});
            if (!response.ok) throw new Error();
            state = await response.json();
            // A missing attempt after a dropped POST is not proof it cannot still arrive.
            if (state.data) uncertain = false;
            render();
        } catch (_) { message.textContent = 'We could not check the payment. Please check again before collecting money.'; send.disabled = true; }
        finally { checking = false; check.disabled = false; }
    }
    offline.addEventListener('click', () => mode('offline'));
    online.addEventListener('click', () => { mode('online'); status(); });
    [offline, online].forEach(tab => tab.addEventListener('keydown', event => {
        if (['ArrowLeft', 'ArrowRight'].includes(event.key)) {
            event.preventDefault(); const target = tab === online ? offline : online;
            if (!target.disabled) { target.click(); target.focus(); }
        }
    }));
    check.addEventListener('click', status);
    document.getElementById('confirmMarkPaidBtn').addEventListener('click', event => {
        if (modal.dataset.paymentLocked === 'true' || modal.dataset.paymentMode === 'online') { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    send.addEventListener('click', async function () {
        if (busy || checking || !state || !state.canPrompt || uncertain) return;
        if (!phone.value.trim()) { message.textContent = 'Enter the customer mobile money number.'; phone.focus(); return; }
        key = crypto.randomUUID();
        const charges = Array.from(document.querySelectorAll('#charge-rows input[name="charge_description[]"]')).map(input => ({
            description: input.value.trim(), amount: input.closest('.charge-row').querySelector('[name="charge_amount[]"]').value
        })).filter(charge => charge.description || Number(charge.amount));
        const body = {phone: phone.value, idempotencyKey: key, charges,
            discount_type: document.getElementById('discountType').value || null,
            discount_value: document.getElementById('discountValue').value || 0,
            final_total: state.bill ? state.bill.total : document.getElementById('finalTotal').textContent.replace(/[^0-9.-]/g, '')};
        busy = true; error = ''; render(); message.textContent = 'Sending payment request...';
        try {
            const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', signal: AbortSignal.timeout(30000), headers: {
                'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())
            }, body: JSON.stringify(body)});
            const result = await response.json();
            if (!response.ok) {
                if (response.status >= 500) uncertain = true;
                error = result.errors ? Object.values(result.errors).flat().join(' ') : result.message || 'We could not request payment. Please check the payment status.';
            } else { state = { ...state, data: result.data, canPrompt: false }; uncertain = false; }
        } catch (_) { uncertain = true; }
        finally { busy = false; render(); await status(); }
    });
    if (window.jQuery) {
        window.jQuery(modal).on('shown.bs.modal', () => { open = true; status(); });
        window.jQuery(modal).on('hidden.bs.modal', () => { open = false; });
    }
    setInterval(() => { if (open && modal.dataset.paymentMode === 'online') status(); }, 6000);
});
</script>
