@extends('cargo::adminLte.layouts.master')

@section('pageTitle', 'Online Bookings')

@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .booking-shell { color: #102a43; letter-spacing: 0; }
        .booking-tab[aria-selected="true"] { color: #064e3b; border-color: #10b981; background: #ecfdf5; }
        .booking-tab { min-height: 44px; }
        .booking-modal { background: rgba(15, 23, 42, .58); }
        .booking-table th, .booking-table td { vertical-align: middle; }
        .booking-action { width: 36px; height: 36px; }
    </style>
@endsection

@section('content')
<div class="booking-shell space-y-4" id="online-bookings-app"
    data-feed-url="{{ fr_route('shipments.online-bookings.feed') }}"
    data-show-url="{{ fr_route('shipments.online-bookings.show', ['onlineBooking' => '__ID__']) }}"
    data-update-url="{{ fr_route('shipments.online-bookings.update', ['onlineBooking' => '__ID__']) }}"
    data-convert-url="{{ fr_route('shipments.online-bookings.convert', ['onlineBooking' => '__ID__']) }}"
    data-reject-url="{{ fr_route('shipments.online-bookings.reject', ['onlineBooking' => '__ID__']) }}"
    data-can-manage="{{ $canManage ? '1' : '0' }}">

    <section class="bg-white border border-gray-200 shadow-sm overflow-hidden" style="border-radius: 8px;">
        <div class="px-5 py-5 border-b border-gray-200 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-gray-900">Online booking requests</h1>
                    <span class="inline-flex items-center gap-2 px-2.5 py-1 text-xs font-semibold text-green-800 bg-green-100 rounded">
                        <span class="w-2 h-2 bg-green-500 rounded-full"></span> Live
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">New requests from the customer portal and mobile apps. Oldest requests appear first.</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="text-gray-500">Last updated</span>
                <strong id="last-refreshed" class="text-gray-800">Loading...</strong>
                <button type="button" id="refresh-bookings" title="Refresh bookings"
                    class="booking-action inline-flex items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-50 rounded">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>

        <nav class="px-4 pt-3 border-b border-gray-200 overflow-x-auto" aria-label="Booking categories">
            <div class="flex gap-2 min-w-max" id="booking-tabs">
                @foreach([
                    'all' => ['All requests', 'fa-inbox'],
                    'local' => ['Local delivery', 'fa-motorcycle'],
                    'intercity' => ['City to city', 'fa-truck'],
                    'international_air' => ['International air', 'fa-plane'],
                    'international_sea' => ['International sea', 'fa-ship'],
                ] as $key => [$label, $icon])
                    <button type="button" data-category="{{ $key }}" aria-selected="{{ $key === 'all' ? 'true' : 'false' }}"
                        class="booking-tab flex items-center gap-2 px-4 py-2.5 border border-transparent border-b-2 text-sm font-semibold text-gray-600 hover:text-gray-900 rounded-t">
                        <i class="fas {{ $icon }}"></i>
                        <span>{{ $label }}</span>
                        <span data-count="{{ $key }}" class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">0</span>
                    </button>
                @endforeach
            </div>
        </nav>

        <div class="px-5 py-4 bg-gray-50 border-b border-gray-200 flex flex-col md:flex-row gap-3 md:items-center md:justify-between">
            <div class="relative w-full md:max-w-md">
                <i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i>
                <input id="booking-search" type="search" placeholder="Search reference, customer, recipient or phone"
                    class="w-full h-11 pl-10 pr-4 border border-gray-300 bg-white text-sm rounded focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            <div class="flex items-center gap-3">
                <label for="booking-status" class="text-sm font-medium text-gray-600">Status</label>
                <select id="booking-status" class="h-11 px-3 border border-gray-300 bg-white text-sm rounded focus:ring-2 focus:ring-green-500">
                    <option value="pending">Pending review</option>
                    <option value="accepted">Accepted</option>
                    <option value="rejected">Rejected</option>
                    <option value="all">All statuses</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="booking-table min-w-full divide-y divide-gray-200">
                <thead class="bg-white">
                    <tr class="text-left text-xs font-semibold uppercase text-gray-500">
                        <th class="px-5 py-3">Queue</th>
                        <th class="px-4 py-3">Reference / Customer</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Route</th>
                        <th class="px-4 py-3">Quote</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="booking-rows" class="divide-y divide-gray-100 bg-white"></tbody>
            </table>
        </div>

        <div id="booking-empty" class="hidden px-6 py-16 text-center">
            <div class="mx-auto w-12 h-12 flex items-center justify-center bg-gray-100 text-gray-500 rounded">
                <i class="fas fa-inbox text-xl"></i>
            </div>
            <h2 class="mt-4 text-base font-semibold text-gray-900">No bookings in this queue</h2>
            <p class="mt-1 text-sm text-gray-500">New online requests will appear here automatically.</p>
        </div>

        <div id="booking-loading" class="px-6 py-16 text-center text-gray-500">
            <i class="fas fa-circle-notch fa-spin mr-2"></i> Loading online bookings...
        </div>
        <div class="px-5 py-3 border-t border-gray-200 text-center">
            <button id="booking-more" type="button" class="hidden px-4 h-10 border border-gray-300 text-sm font-semibold text-gray-700 rounded hover:bg-gray-50">Load more</button>
        </div>
    </section>

    <div id="booking-modal" class="booking-modal hidden fixed inset-0 z-50 p-4 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="min-h-full flex items-center justify-center">
            <div class="bg-white w-full max-w-3xl shadow-xl" style="border-radius: 8px;">
                <div class="px-5 py-4 border-b border-gray-200 flex items-start justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase text-green-700" id="modal-category">Booking request</p>
                        <h2 class="mt-1 text-lg font-bold text-gray-900" id="modal-reference"></h2>
                    </div>
                    <button type="button" data-close-modal title="Close" class="booking-action text-gray-500 hover:bg-gray-100 rounded">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="booking-form">
                    @csrf
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Service</label>
                            <select name="service" class="booking-input w-full h-11 border border-gray-300 px-3 rounded">
                                <option value="local">Local Delivery</option>
                                <option value="intercity">City to City</option>
                                <option value="import">International</option>
                                <option value="custom">Custom Request</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Transport</label>
                            <select name="transport_mode" class="booking-input w-full h-11 border border-gray-300 px-3 rounded">
                                <option value="">Not applicable</option>
                                <option value="air">Air</option>
                                <option value="sea">Sea</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Pickup</label>
                            <input name="pickup_address" class="booking-input w-full h-11 border border-gray-300 px-3 rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Destination</label>
                            <input name="destination_address" class="booking-input w-full h-11 border border-gray-300 px-3 rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Recipient</label>
                            <input name="recipient_name" class="booking-input w-full h-11 border border-gray-300 px-3 rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Recipient phone</label>
                            <input name="recipient_phone" class="booking-input w-full h-11 border border-gray-300 px-3 rounded">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Schedule</label>
                            <input name="schedule" class="booking-input w-full h-11 border border-gray-300 px-3 rounded">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold uppercase text-gray-500 mb-1">Instructions</label>
                            <textarea name="instructions" rows="3" class="booking-input w-full border border-gray-300 px-3 py-2 rounded"></textarea>
                        </div>
                    </div>

                    <div class="px-5 py-4 bg-gray-50 border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:justify-between gap-3">
                        <div class="flex gap-2">
                            @if($canManage)
                                <button type="button" id="reject-booking" class="px-4 h-10 border border-red-300 text-red-700 font-semibold text-sm rounded hover:bg-red-50">Reject</button>
                            @endif
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" data-close-modal class="px-4 h-10 border border-gray-300 text-gray-700 font-semibold text-sm rounded hover:bg-white">Close</button>
                            @if($canManage)
                                <button type="submit" id="save-booking" class="px-4 h-10 border border-gray-300 bg-white text-gray-800 font-semibold text-sm rounded hover:bg-gray-50">Save changes</button>
                                <button type="button" id="convert-booking" class="px-4 h-10 bg-green-600 text-white font-semibold text-sm rounded hover:bg-green-700">
                                    <i class="fas fa-check mr-2"></i>Accept & create shipment
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('online-bookings-app');
    const rows = document.getElementById('booking-rows');
    const loading = document.getElementById('booking-loading');
    const empty = document.getElementById('booking-empty');
    const search = document.getElementById('booking-search');
    const status = document.getElementById('booking-status');
    const modal = document.getElementById('booking-modal');
    const form = document.getElementById('booking-form');
    const canManage = app.dataset.canManage === '1';
    let category = 'all';
    let activeBooking = null;
    let searchTimer = null;
    let page = 1;
    let loadingMore = false;

    const endpoint = (key, id) => app.dataset[key].replace('__ID__', id);
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const badge = value => {
        const styles = {pending:'bg-yellow-100 text-yellow-800', accepted:'bg-green-100 text-green-800', rejected:'bg-red-100 text-red-800'};
        return `<span class="inline-flex px-2 py-1 text-xs font-semibold rounded ${styles[value] || 'bg-gray-100 text-gray-700'}">${escapeHtml(value)}</span>`;
    };

    function render(bookings, append = false) {
        loading.classList.add('hidden');
        empty.classList.toggle('hidden', !append && bookings.length === 0);
        const markup = bookings.map((booking, index) => {
            const mode = booking.transportMode ? ` · ${booking.transportMode.toUpperCase()}` : '';
            const shipment = booking.shipment ? `<div class="mt-1 text-xs text-green-700">Shipment ${escapeHtml(booking.shipment.code)}</div>` : '';
            return `<tr class="hover:bg-gray-50">
                <td class="px-5 py-4"><span class="inline-flex w-8 h-8 items-center justify-center bg-gray-100 font-bold text-gray-700 rounded">${(page - 1) * 100 + index + 1}</span></td>
                <td class="px-4 py-4"><button type="button" data-view="${booking.id}" class="font-bold text-green-700 hover:underline">${escapeHtml(booking.reference)}</button><div class="mt-1 text-sm text-gray-600">${escapeHtml(booking.customer || 'Customer')}</div>${shipment}</td>
                <td class="px-4 py-4"><div class="font-semibold text-gray-900">${escapeHtml(booking.serviceLabel)}${escapeHtml(mode)}</div><div class="mt-1">${badge(booking.status)}</div></td>
                <td class="px-4 py-4 max-w-xs"><div class="text-sm text-gray-800 truncate" title="${escapeHtml(booking.pickupAddress)}">${escapeHtml(booking.pickupAddress)}</div><div class="text-xs text-gray-400 my-1"><i class="fas fa-arrow-down"></i></div><div class="text-sm text-gray-800 truncate" title="${escapeHtml(booking.destinationAddress)}">${escapeHtml(booking.destinationAddress)}</div></td>
                <td class="px-4 py-4"><div class="font-bold text-gray-900">${escapeHtml(booking.currency)} ${Number(booking.quotedAmount).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</div><div class="text-xs text-gray-500">${Number(booking.weight).toFixed(2)} kg</div></td>
                <td class="px-4 py-4"><div class="text-sm font-medium text-gray-800">${escapeHtml(booking.submittedLabel || '-')}</div><div class="text-xs text-gray-500">${escapeHtml(booking.branch || '')}</div></td>
                <td class="px-5 py-4 text-right"><button type="button" data-view="${booking.id}" title="View booking" class="booking-action inline-flex items-center justify-center border border-gray-300 text-gray-600 hover:bg-gray-100 rounded"><i class="fas ${canManage && booking.canManage ? 'fa-edit' : 'fa-eye'}"></i></button></td>
            </tr>`;
        }).join('');
        if (append) rows.insertAdjacentHTML('beforeend', markup);
        else rows.innerHTML = markup;
    }

    async function loadBookings(showLoader = false, append = false) {
        if (loadingMore) return;
        loadingMore = true;
        if (!append) page = 1;
        if (showLoader) loading.classList.remove('hidden');
        const params = new URLSearchParams({category, status: status.value, search: search.value.trim(), page});
        try {
            const response = await fetch(`${app.dataset.feedUrl}?${params}`, {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
            if (!response.ok) throw new Error('Could not load bookings');
            const result = await response.json();
            render(result.data || [], append);
            document.getElementById('booking-more').classList.toggle('hidden', !result.hasMore);
            Object.entries(result.counts || {}).forEach(([key, count]) => {
                const target = document.querySelector(`[data-count="${key}"]`);
                if (target) target.textContent = count;
            });
            document.getElementById('last-refreshed').textContent = new Date(result.refreshedAt).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        } catch (error) {
            if (append) page--;
            loading.innerHTML = '<span class="text-red-700">Bookings could not be loaded. Use refresh to try again.</span>';
            loading.classList.remove('hidden');
        } finally {
            loadingMore = false;
        }
    }

    async function openBooking(id) {
        const response = await fetch(endpoint('showUrl', id), {headers:{'Accept':'application/json'}, credentials:'same-origin'});
        if (!response.ok) return;
        activeBooking = (await response.json()).data;
        document.getElementById('modal-reference').textContent = activeBooking.reference;
        document.getElementById('modal-category').textContent = `${activeBooking.serviceLabel}${activeBooking.transportMode ? ' · ' + activeBooking.transportMode.toUpperCase() : ''} · ${activeBooking.status}`;
        const values = {service:activeBooking.service, transport_mode:activeBooking.transportMode || '', pickup_address:activeBooking.pickupAddress, destination_address:activeBooking.destinationAddress, recipient_name:activeBooking.recipientName, recipient_phone:activeBooking.recipientPhone, schedule:activeBooking.schedule || '', instructions:activeBooking.instructions || ''};
        Object.entries(values).forEach(([name, value]) => { const field=form.elements[name]; if(field) field.value=value; });
        form.querySelectorAll('.booking-input').forEach(field => field.disabled = !activeBooking.canManage);
        ['save-booking','convert-booking','reject-booking'].forEach(id => { const button=document.getElementById(id); if(button) button.classList.toggle('hidden', !activeBooking.canManage); });
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); activeBooking=null; }
    async function mutate(url, method, body) {
        const response = await fetch(url, {method, credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}, body:JSON.stringify(body || {})});
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.message || 'The request could not be completed.');
        return result;
    }

    document.getElementById('booking-tabs').addEventListener('click', event => {
        const tab = event.target.closest('[data-category]'); if (!tab) return;
        category = tab.dataset.category;
        document.querySelectorAll('[data-category]').forEach(item => item.setAttribute('aria-selected', item === tab ? 'true' : 'false'));
        loadBookings(true);
    });
    rows.addEventListener('click', event => { const button=event.target.closest('[data-view]'); if(button) openBooking(button.dataset.view); });
    document.querySelectorAll('[data-close-modal]').forEach(button => button.addEventListener('click', closeModal));
    modal.addEventListener('click', event => { if(event.target === modal) closeModal(); });
    document.getElementById('refresh-bookings').addEventListener('click', () => loadBookings(true));
    document.getElementById('booking-more').addEventListener('click', () => { page++; loadBookings(false, true); });
    status.addEventListener('change', () => loadBookings(true));
    search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer=setTimeout(() => loadBookings(true), 300); });
    form.addEventListener('submit', async event => {
        event.preventDefault(); if(!activeBooking?.canManage) return;
        const data=Object.fromEntries(new FormData(form).entries()); delete data._token;
        try { await mutate(endpoint('updateUrl', activeBooking.id), 'PATCH', data); closeModal(); loadBookings(); } catch(error) { alert(error.message); }
    });
    document.getElementById('convert-booking')?.addEventListener('click', async () => {
        if(!activeBooking || !confirm('Accept this booking and create the shipment now?')) return;
        try { const result=await mutate(endpoint('convertUrl', activeBooking.id), 'POST'); closeModal(); loadBookings(); if(result.shipment?.url) window.location.href=result.shipment.url; } catch(error) { alert(error.message); }
    });
    document.getElementById('reject-booking')?.addEventListener('click', async () => {
        if(!activeBooking) return; const reason=prompt('Reason for rejecting this booking:'); if(!reason) return;
        try { await mutate(endpoint('rejectUrl', activeBooking.id), 'POST', {reason}); closeModal(); loadBookings(); } catch(error) { alert(error.message); }
    });

    loadBookings(true);
    window.setInterval(() => { if(document.visibilityState === 'visible' && modal.classList.contains('hidden')) loadBookings(); }, 10000);
});
</script>
@endsection
