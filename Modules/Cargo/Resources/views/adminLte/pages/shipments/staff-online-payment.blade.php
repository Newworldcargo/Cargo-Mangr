<link rel="stylesheet" href="{{ asset('css/shipment-payment-modal.css') }}?v=20260930-2">
<div class="payment-channel-tabs" role="tablist" aria-label="Payment channel">
    <button type="button" id="online-payment-tab" role="tab" aria-selected="false" aria-controls="online-payment-panel" tabindex="-1"><i class="fas fa-mobile-alt" aria-hidden="true"></i> Online</button>
    <button type="button" id="offline-payment-tab" class="active" role="tab" aria-selected="true" aria-controls="offline-payment-panel"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Offline</button>
</div>
<div id="online-payment-panel" role="tabpanel" aria-labelledby="online-payment-tab" hidden>
    <fieldset id="online-payment-networks" class="payment-networks">
        <legend>Mobile network</legend>
        <div class="payment-network-options">
            <label><input type="radio" name="online_network" value="mtn"><span><span class="network-dot network-mtn" aria-hidden="true"></span>MTN</span></label>
            <label><input type="radio" name="online_network" value="airtel"><span><span class="network-dot network-airtel" aria-hidden="true"></span>Airtel</span></label>
            <label><input type="radio" name="online_network" value="zamtel"><span><span class="network-dot network-zamtel" aria-hidden="true"></span>Zamtel</span></label>
        </div>
    </fieldset>
    <label for="online-payment-phone">Mobile money number</label>
    <input id="online-payment-phone" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" pattern="0[79][0-9]{8}" class="form-control" placeholder="0972827372" aria-describedby="online-phone-hint online-phone-error">
    <div class="payment-phone-hint"><small id="online-phone-hint">10 digits, starting with 07 or 09</small><small id="online-phone-count">0 / 10</small></div>
    <p id="online-phone-error" class="payment-field-error" role="alert" hidden></p>
    <p class="payment-approval-note"><i class="fas fa-lock" aria-hidden="true"></i> The customer enters their PIN on their own phone.</p>
    <p id="online-payment-bill" class="font-weight-bold"></p>
    <p id="online-payment-status" role="status" aria-live="polite">Checking payment availability...</p>
    <div class="payment-online-actions">
        <button type="button" id="online-payment-send" class="btn btn-primary" disabled>Send payment prompt</button>
        <button type="button" id="online-payment-check" class="btn btn-outline-primary">Check payment status</button>
        <button type="button" id="online-payment-cash" class="btn btn-outline-primary"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> Switch to cash</button>
    </div>
</div>
<style>
#markPaidModal .payment-channel-tabs { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); margin:0 -24px 24px; border-top:1px solid #dbe2ea; border-bottom:1px solid #dbe2ea; }
#markPaidModal .payment-channel-tabs button { min-height:60px; border:0; border-radius:0; background:#f4f6f9; color:#374151; font-size:16px; font-weight:600; display:flex; gap:10px; align-items:center; justify-content:center; padding:14px 8px; }
#markPaidModal .payment-channel-tabs button + button { border-left:1px solid #dbe2ea; }
#markPaidModal .payment-channel-tabs button.active { background:var(--primary, #0a2463); color:#fff; }
#markPaidModal .payment-channel-tabs button:not(.active):hover { background:#e8edf5; }
#markPaidModal .payment-channel-tabs button:disabled { cursor:not-allowed; opacity:.65; }
#markPaidModal .payment-channel-tabs button:focus-visible, #online-payment-panel button:focus-visible { outline:3px solid #4f91ed; outline-offset:-3px; }
#online-payment-panel { color:#253044; }
#online-payment-panel .payment-networks { border:0; padding:0; margin:0 0 24px; min-width:0; }
#online-payment-panel legend, #online-payment-panel > label { font-size:14px; font-weight:600; margin-bottom:10px; }
#online-payment-panel .payment-network-options { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
#online-payment-panel .payment-network-options label { position:relative; margin:0; cursor:pointer; min-width:0; }
#online-payment-panel .payment-network-options input { position:absolute; opacity:0; width:1px; height:1px; }
#online-payment-panel .payment-network-options label > span { display:flex; align-items:center; justify-content:center; gap:7px; min-height:52px; border:1px solid #cbd5e1; border-radius:6px; font-size:14px; font-weight:600; background:#fff; }
#online-payment-panel .payment-network-options input:checked + span { border:2px solid var(--primary, #0a2463); background:#eef4ff; color:#0a2463; }
#online-payment-panel .payment-network-options input:focus-visible + span { outline:3px solid #4f91ed; outline-offset:2px; }
#online-payment-panel .payment-network-options label:hover > span { background:#f1f5f9; }
#online-payment-panel .payment-networks:disabled label { opacity:.65; cursor:not-allowed; }
#online-payment-panel .network-dot { width:10px; height:10px; flex:0 0 10px; border-radius:50%; }
#online-payment-panel .network-mtn { background:#ffcb05; }
#online-payment-panel .network-airtel { background:#e4002b; }
#online-payment-panel .network-zamtel { background:#008b45; }
#online-payment-phone { min-height:52px; font-size:18px; border:1px solid #cbd5e1; border-radius:6px; color:#172033; background:#fff; }
#online-payment-phone:focus { border-color:#2563eb; box-shadow:0 0 0 3px #dbeafe; }
#online-payment-phone[aria-invalid="true"] { border-color:#b91c1c; }
#online-payment-panel .payment-phone-hint { display:flex; justify-content:space-between; gap:8px; margin-top:7px; color:#596579; }
#online-phone-count { flex-shrink:0; white-space:nowrap; }
#online-payment-panel .payment-field-error { color:#b91c1c; font-size:13px; margin-top:8px; }
#online-payment-panel .payment-approval-note { display:flex; gap:8px; align-items:baseline; font-size:13px; color:#596579; margin:20px 0; }
#online-payment-bill:empty { display:none; }
#online-payment-status { border-left:3px solid #94a3b8; padding:10px 12px; background:#f5f7fa; font-size:14px; line-height:1.5; }
#online-payment-panel .payment-online-actions { display:grid; grid-template-columns:minmax(0,1fr); gap:10px; }
#online-payment-panel .payment-online-actions button { min-height:48px; border-radius:6px; white-space:normal; }
#online-payment-send { background:var(--primary, #0a2463); color:#fff; border-color:var(--primary, #0a2463); }
#online-payment-check { color:#0a2463; background:#fff; border-color:#cbd5e1; }
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
    const cash = document.getElementById('online-payment-cash');
    const phone = document.getElementById('online-payment-phone');
    const networks = document.getElementById('online-payment-networks');
    const phoneError = document.getElementById('online-phone-error');
    function selectedNetwork() { return networks.querySelector('input:checked')?.value; }
    function validatePhone() {
        const valid = /^0[79][0-9]{8}$/.test(phone.value);
        phone.setAttribute('aria-invalid', String(!valid));
        phoneError.textContent = valid ? '' : 'Enter a 10-digit mobile number starting with 07 or 09.';
        phoneError.hidden = valid;
        return valid;
    }
    phone.addEventListener('input', () => {
        phone.value = phone.value.replace(/[^0-9]/g, '').slice(0, 10);
        document.getElementById('online-phone-count').textContent = phone.value.length + ' / 10';
        if (phone.getAttribute('aria-invalid') === 'true' || phone.value.length === 10) validatePhone();
    });
    phone.addEventListener('blur', () => { if (phone.value) validatePhone(); });
    const message = document.getElementById('online-payment-status');
    const endpoint = @json(route('shipments.online-payment.store', $shipment->id));
    let busy = false, checking = false, open = false, state = null, key = null, uncertain = false, error = '', wantsCash = false;
    networks.addEventListener('change', () => { error = ''; render(); });
    function updateFooter() {
        const confirmed = document.getElementById('payment-footer-confirmed-total');
        const currency = document.getElementById('payment-footer-currency');
        if (!confirmed || !currency) return;
        const bill = modal.dataset.paymentMode === 'online' && state && state.bill;
        confirmed.hidden = !bill;
        document.getElementById('finalTotal').hidden = !!bill;
        currency.textContent = bill ? bill.currency : currency.dataset.currency;
        if (bill) confirmed.textContent = Number(bill.total).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    function mode(value) {
        if (value === 'offline' && (uncertain || (state && state.data && !['failed', 'succeeded'].includes(state.data.status)))) return;
        modal.dataset.paymentMode = value;
        updateFooter();
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
        networks.disabled = locked;
        if (locked && intent && intent.network) {
            const savedNetwork = networks.querySelector('input[value="' + intent.network + '"]');
            if (savedNetwork) savedNetwork.checked = true;
        }
        send.disabled = locked || !state || !state.canPrompt;
        cash.disabled = busy || checking || !!(state && state.paid);
        document.querySelectorAll('#discountType, #discountValue, #charge-rows input, #charge-rows button, #addChargeBtn').forEach(el => el.disabled = locked || !!(state && state.bill && modal.dataset.paymentMode === 'online'));
        const bill = state && state.bill;
        document.getElementById('online-payment-bill').textContent = bill ? 'Confirmed bill: ' + bill.currency + ' ' + Number(bill.total).toFixed(2) : '';
        const summary = document.getElementById('originalTotal')?.closest('.card');
        if (summary) summary.classList.toggle('online-existing-bill-summary', !!bill);
        updateFooter();
        if (locked) mode('online');
        if (state && state.paid) { message.textContent = 'Payment confirmed. Refreshing shipment...'; window.location.reload(); return; }
        if (uncertain) message.textContent = 'Checking whether the payment was started. Do not collect another payment yet.';
        else if (intent) message.textContent = intent.status === 'failed' ? 'Payment was unsuccessful. You can send a new prompt.' : intent.status === 'review' ? 'This payment needs confirmation by your payment team. Do not collect another payment.' : 'Awaiting payment confirmation. The customer should approve the request on their phone.';
        else message.textContent = state && state.available ? 'Ready to request the full bill amount.' : 'Online payment is not available yet. You can use offline payment.';
        send.textContent = intent && intent.status === 'failed' ? 'Send another prompt' : 'Send payment prompt';
        if (error && !locked) message.textContent = error;
        if (wantsCash && !uncertain && state && state.canSwitchOffline) {
            wantsCash = false;
            mode('offline');
            const method = document.querySelector('#payment-rows select[name="method_of_payment[]"]');
            if (method) {
                method.value = 'cash_payment';
                method.dispatchEvent(new Event('change', {bubbles: true}));
                method.focus();
            }
        } else if (wantsCash) {
            send.disabled = true;
            message.textContent = intent && intent.status === 'review'
                ? 'This payment needs review. Ask your payment team to confirm the outcome before accepting cash.'
                : uncertain ? 'We are still checking whether the request was sent. Cash payment will become available once its outcome is confirmed.'
                : 'The mobile-money request is still active. Ask the customer to decline it on their phone. We will switch to cash when the failed payment is confirmed. Do not collect cash yet.';
        }
    }
    async function status() {
        if (checking || busy) return;
        checking = true; check.disabled = true; cash.disabled = true;
        try {
            const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', signal: AbortSignal.timeout(30000)});
            if (!response.ok) throw new Error();
            state = await response.json();
            // A missing attempt after a dropped POST is not proof it cannot still arrive.
            if (state.data) uncertain = false;
            render();
        } catch (_) { message.textContent = 'We could not check the payment. Please check again before collecting money.'; send.disabled = true; }
        finally { checking = false; check.disabled = false; cash.disabled = busy || !!(state && state.paid); }
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
    cash.addEventListener('click', async () => {
        if (busy || checking) return;
        wantsCash = true;
        send.disabled = true;
        message.textContent = 'Checking the mobile-money payment before switching to cash...';
        await status();
    });
    document.getElementById('confirmMarkPaidBtn').addEventListener('click', event => {
        if (modal.dataset.paymentLocked === 'true' || modal.dataset.paymentMode === 'online') { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    send.addEventListener('click', async function () {
        if (busy || checking || !state || !state.canPrompt || uncertain) return;
        if (!selectedNetwork()) { message.textContent = 'Choose the customer mobile network.'; networks.querySelector('input').focus(); return; }
        if (!validatePhone()) { phone.focus(); return; }
        key = crypto.randomUUID();
        const charges = Array.from(document.querySelectorAll('#charge-rows input[name="charge_description[]"]')).map(input => ({
            description: input.value.trim(), amount: input.closest('.charge-row').querySelector('[name="charge_amount[]"]').value
        })).filter(charge => charge.description || Number(charge.amount));
        const body = {phone: phone.value, network: selectedNetwork(), idempotencyKey: key, charges,
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
