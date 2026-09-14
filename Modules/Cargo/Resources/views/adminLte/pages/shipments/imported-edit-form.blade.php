@csrf

@php
    $field = fn (string $name, $current = null) => old('Shipment.'.$name, $current);
    $storedType = $field('type', $model->type);
    $selectedType = is_numeric($storedType)
        ? (int) $storedType
        : (strtolower((string) $storedType) === 'pickup' ? 1 : 2);
    $areaName = function ($area) {
        $decoded = json_decode($area->name ?? '', true);
        return is_array($decoded) ? ($decoded[app()->getLocale()] ?? reset($decoded)) : $area->name;
    };
@endphp

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Nothing was saved.</strong> Please correct the highlighted information and try again.
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="alert alert-info d-flex align-items-start mb-6">
    <i class="fas fa-info-circle mt-1 me-3"></i>
    <div>
        <strong>Editing shipment {{ $model->code }}</strong>
        <div>All values below are loaded from the current shipment. Payment status remains controlled by receipts and payment actions.</div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-6">
    <span class="badge bg-light-primary text-primary px-4 py-3">HAWB: {{ $model->code }}</span>
    <span class="badge bg-light-dark text-dark px-4 py-3">Consignment: {{ optional($model->consignment)->consignment_code ?: '#'.$model->consignment_id }}</span>
    <span class="badge {{ $model->paid ? 'bg-success' : 'bg-secondary' }} px-4 py-3">{{ $model->paid ? 'PAID' : 'UNPAID' }}</span>
</div>

<div class="row g-5">
    <div class="col-xl-6">
        <div class="card h-100 border">
            <div class="card-header"><h3 class="card-title">Shipment and customer</h3></div>
            <div class="card-body row g-4">
                <div class="col-md-6">
                    <label class="form-label required">HAWB / shipment code</label>
                    <input name="Shipment[code]" value="{{ $field('code', $model->code) }}" class="form-control @error('Shipment.code') is-invalid @enderror" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Shipping date</label>
                    <input type="date" name="Shipment[shipping_date]" value="{{ substr((string) $field('shipping_date', $model->shipping_date), 0, 10) }}" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Pickup branch</label>
                    <select name="Shipment[branch_id]" class="form-select imported-select @error('Shipment.branch_id') is-invalid @enderror" required>
                        @foreach($editableBranches as $branch)
                            <option value="{{ $branch->id }}" @selected((int) $field('branch_id', $model->branch_id) === (int) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Handling type</label>
                    <select name="Shipment[type]" class="form-select" required>
                        <option value="1" @selected($selectedType === 1)>Pickup</option>
                        <option value="2" @selected($selectedType === 2)>Drop off</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label required">Customer</label>
                    <select name="Shipment[client_id]" class="form-select imported-select @error('Shipment.client_id') is-invalid @enderror" required>
                        @foreach($editableClients as $client)
                            <option value="{{ $client->id }}" @selected((int) $field('client_id', $model->client_id) === (int) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Customer phone</label>
                    <input name="Shipment[client_phone]" value="{{ $field('client_phone', $model->client_phone) }}" class="form-control @error('Shipment.client_phone') is-invalid @enderror" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Customer secondary phone</label>
                    <input name="Shipment[client_phone_2]" value="{{ $field('client_phone_2', $model->client_phone_2) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Consignee / receiver</label>
                    <input name="Shipment[reciver_name]" value="{{ $field('reciver_name', $model->reciver_name) }}" class="form-control @error('Shipment.reciver_name') is-invalid @enderror" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Receiver phone</label>
                    <input name="Shipment[reciver_phone]" value="{{ $field('reciver_phone', $model->reciver_phone) }}" class="form-control @error('Shipment.reciver_phone') is-invalid @enderror" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Receiver secondary phone</label>
                    <input name="Shipment[reciver_phone_2]" value="{{ $field('reciver_phone_2', $model->reciver_phone_2) }}" class="form-control">
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Destination address</label>
                    <input name="Shipment[reciver_address]" value="{{ $field('reciver_address', $model->reciver_address) }}" class="form-control @error('Shipment.reciver_address') is-invalid @enderror" required>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100 border">
            <div class="card-header"><h3 class="card-title">Route and import details</h3></div>
            <div class="card-body row g-4">
                <div class="col-md-6">
                    <label class="form-label required">Origin country</label>
                    <select id="import-from-country" name="Shipment[from_country_id]" class="form-select imported-select @error('Shipment.from_country_id') is-invalid @enderror" required>
                        <option value="">Select country</option>
                        @foreach($editableCountries as $country)<option value="{{ $country->id }}" @selected((int) $field('from_country_id', $model->from_country_id) === (int) $country->id)>{{ $country->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Destination country</label>
                    <select id="import-to-country" name="Shipment[to_country_id]" class="form-select imported-select @error('Shipment.to_country_id') is-invalid @enderror" required>
                        <option value="">Select country</option>
                        @foreach($editableCountries as $country)<option value="{{ $country->id }}" @selected((int) $field('to_country_id', $model->to_country_id) === (int) $country->id)>{{ $country->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Origin province/state</label>
                    <select id="import-from-state" name="Shipment[from_state_id]" class="form-select imported-select @error('Shipment.from_state_id') is-invalid @enderror" data-current="{{ $field('from_state_id', $model->from_state_id) }}" required>
                        <option value="">Select state</option>
                        @foreach($fromStates as $state)<option value="{{ $state->id }}" @selected((int) $field('from_state_id', $model->from_state_id) === (int) $state->id)>{{ $state->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label required">Destination province/state</label>
                    <select id="import-to-state" name="Shipment[to_state_id]" class="form-select imported-select @error('Shipment.to_state_id') is-invalid @enderror" data-current="{{ $field('to_state_id', $model->to_state_id) }}" required>
                        <option value="">Select state</option>
                        @foreach($toStates as $state)<option value="{{ $state->id }}" @selected((int) $field('to_state_id', $model->to_state_id) === (int) $state->id)>{{ $state->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Origin area</label>
                    <select id="import-from-area" name="Shipment[from_area_id]" class="form-select imported-select @error('Shipment.from_area_id') is-invalid @enderror" data-current="{{ $field('from_area_id', $model->from_area_id) }}">
                        <option value="">Not specified</option>
                        @foreach($fromAreas as $area)<option value="{{ $area->id }}" @selected((int) $field('from_area_id', $model->from_area_id) === (int) $area->id)>{{ $areaName($area) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Destination area</label>
                    <select id="import-to-area" name="Shipment[to_area_id]" class="form-select imported-select @error('Shipment.to_area_id') is-invalid @enderror" data-current="{{ $field('to_area_id', $model->to_area_id) }}">
                        <option value="">Not specified</option>
                        @foreach($toAreas as $area)<option value="{{ $area->id }}" @selected((int) $field('to_area_id', $model->to_area_id) === (int) $area->id)>{{ $areaName($area) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Destination branch/location</label><input name="Shipment[next_destination]" value="{{ $field('next_destination', $model->next_destination) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Destination port</label><input name="Shipment[dest_port]" value="{{ $field('dest_port', $model->dest_port) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Salesperson</label><input name="Shipment[salesman]" value="{{ $field('salesman', $model->salesman) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Volume (m³)</label><input type="number" min="0" step="0.001" name="Shipment[volume]" value="{{ $field('volume', $model->volume) }}" class="form-control"></div>
            </div>
        </div>
    </div>
</div>

<div class="card border mt-6">
    <div class="card-header"><h3 class="card-title">Goods and parcels</h3></div>
    <div class="card-body">
        @forelse($model->packageShipments as $index => $parcel)
            <input type="hidden" name="Package[{{ $index }}][id]" value="{{ $parcel->id }}">
            <div class="row g-4 pb-5 mb-5 border-bottom">
                <div class="col-md-3">
                    <label class="form-label required">Package type</label>
                    <select name="Package[{{ $index }}][package_id]" class="form-select imported-select @error("Package.$index.package_id") is-invalid @enderror" required>
                        @foreach($editablePackages as $package)
                            @php $packageLabel = json_decode($package->name, true); @endphp
                            <option value="{{ $package->id }}" @selected((int) old("Package.$index.package_id", $parcel->package_id) === (int) $package->id)>{{ is_array($packageLabel) ? ($packageLabel[app()->getLocale()] ?? reset($packageLabel)) : $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5"><label class="form-label required">Goods description</label><input name="Package[{{ $index }}][description]" value="{{ old("Package.$index.description", $parcel->description) }}" class="form-control @error("Package.$index.description") is-invalid @enderror" required></div>
                <div class="col-md-2"><label class="form-label required">Pieces</label><input type="number" min="0.01" step="0.01" name="Package[{{ $index }}][qty]" value="{{ old("Package.$index.qty", $parcel->qty) }}" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label required">Weight (kg)</label><input type="number" min="0.01" step="0.01" name="Package[{{ $index }}][weight]" value="{{ old("Package.$index.weight", $parcel->weight) }}" class="form-control parcel-weight @error("Package.$index.weight") is-invalid @enderror" required></div>
                <div class="col-md-4"><label class="form-label">Length</label><input type="number" min="0" step="0.01" name="Package[{{ $index }}][length]" value="{{ old("Package.$index.length", $parcel->length) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Width</label><input type="number" min="0" step="0.01" name="Package[{{ $index }}][width]" value="{{ old("Package.$index.width", $parcel->width) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Height</label><input type="number" min="0" step="0.01" name="Package[{{ $index }}][height]" value="{{ old("Package.$index.height", $parcel->height) }}" class="form-control"></div>
            </div>
        @empty
            <div class="alert alert-warning mb-0">No parcel row is attached to this shipment.</div>
        @endforelse
        <p class="text-muted mb-0"><i class="fas fa-shield-alt me-2"></i>Existing parcel rows are updated in place and cannot be deleted from this editor.</p>
    </div>
</div>

<div class="card border mt-6">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title">Weight and pricing</h3>
        <span class="badge {{ $model->paid ? 'bg-success' : 'bg-secondary' }}">{{ $model->paid ? 'PAID' : 'UNPAID' }}</span>
    </div>
    <div class="card-body row g-4">
        <div class="col-md-4"><label class="form-label required">Total weight (kg)</label><input id="shipment-total-weight" type="number" min="0.01" step="0.01" name="Shipment[total_weight]" value="{{ $field('total_weight', $model->total_weight) }}" class="form-control @error('Shipment.total_weight') is-invalid @enderror" required><div id="parcel-weight-help" class="form-text"></div></div>
        <div class="col-md-4"><label class="form-label required">Shipping price</label><input type="number" min="0" step="0.01" name="Shipment[shipping_cost]" value="{{ $field('shipping_cost', $model->shipping_cost) }}" class="form-control @error('Shipment.shipping_cost') is-invalid @enderror" required></div>
        <div class="col-md-4"><label class="form-label required">Amount to collect</label><input type="number" min="0" step="0.01" name="Shipment[amount_to_be_collected]" value="{{ $field('amount_to_be_collected', $model->amount_to_be_collected) }}" class="form-control @error('Shipment.amount_to_be_collected') is-invalid @enderror" required></div>
        <div class="col-md-4"><label class="form-label">Tax / duty</label><input type="number" min="0" step="0.01" name="Shipment[tax]" value="{{ $field('tax', $model->tax) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Insurance</label><input type="number" min="0" step="0.01" name="Shipment[insurance]" value="{{ $field('insurance', $model->insurance) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Return cost</label><input type="number" min="0" step="0.01" name="Shipment[return_cost]" value="{{ $field('return_cost', $model->return_cost) }}" class="form-control"></div>
    </div>
</div>

<input type="hidden" name="Shipment[payment_type]" value="{{ $model->payment_type }}">

<div class="shipment-save-bar d-flex flex-column flex-sm-row justify-content-end gap-3 mt-6 p-4 bg-white border rounded shadow-sm">
    <a href="{{ route('consignment.show', $model->consignment_id) }}" class="btn btn-light">Cancel</a>
    <button type="submit" id="save-imported-shipment" class="btn btn-success">
        <span class="save-label"><i class="fas fa-save me-2"></i>Save shipment changes</span>
        <span class="saving-label d-none"><span class="spinner-border spinner-border-sm me-2"></span>Saving…</span>
    </button>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('kt_account_profile_details_form');
    const saveButton = document.getElementById('save-imported-shipment');
    if (window.jQuery && jQuery.fn.select2) {
        jQuery('.imported-select').select2({ width: '100%' });
    }

    const totalWeight = document.getElementById('shipment-total-weight');
    const weightHelp = document.getElementById('parcel-weight-help');
    const updateWeightHelp = () => {
        const parcelTotal = Array.from(document.querySelectorAll('.parcel-weight'))
            .reduce((sum, input) => sum + (Number.parseFloat(input.value) || 0), 0);
        const matches = Math.abs(parcelTotal - (Number.parseFloat(totalWeight.value) || 0)) <= 0.01;
        weightHelp.textContent = `Parcel total: ${parcelTotal.toFixed(2)} kg${matches ? '' : ' — must match total weight'}`;
        weightHelp.classList.toggle('text-danger', !matches);
        weightHelp.classList.toggle('text-success', matches);
    };
    document.querySelectorAll('.parcel-weight').forEach(input => input.addEventListener('input', updateWeightHelp));
    totalWeight.addEventListener('input', updateWeightHelp);
    updateWeightHelp();
    form.addEventListener('submit', function () {
        if (!form.checkValidity()) return;
        saveButton.disabled = true;
        saveButton.querySelector('.save-label').classList.add('d-none');
        saveButton.querySelector('.saving-label').classList.remove('d-none');
    });

    const loadOptions = async (url, select, placeholder, selected, parseTranslatedName = false) => {
        select.disabled = true;
        select.innerHTML = `<option value="">Loading…</option>`;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Request failed');
            const options = await response.json();
            select.innerHTML = `<option value="">${placeholder}</option>`;
            options.forEach(item => {
                let label = item.name;
                if (parseTranslatedName) {
                    try {
                        const translated = JSON.parse(item.name);
                        label = translated['{{ app()->getLocale() }}'] || Object.values(translated)[0] || item.name;
                    } catch (error) {}
                }
                select.add(new Option(label, item.id, false, String(item.id) === String(selected || '')));
            });
            if (window.jQuery && jQuery.fn.select2) jQuery(select).trigger('change.select2');
        } catch (error) {
            select.innerHTML = `<option value="">Could not load options</option>`;
        } finally {
            select.disabled = false;
        }
    };

    const bindRoute = (countryId, stateId, areaId) => {
        const country = document.getElementById(countryId);
        const state = document.getElementById(stateId);
        const area = document.getElementById(areaId);
        country.addEventListener('change', () => {
            area.innerHTML = '<option value="">Not specified</option>';
            loadOptions(`{{ route('ajax.getStates') }}?country_id=${encodeURIComponent(country.value)}`, state, 'Select state', null);
        });
        state.addEventListener('change', () => {
            loadOptions(`{{ route('ajax.getAreas') }}?state_id=${encodeURIComponent(state.value)}`, area, 'Not specified', null, true);
        });
    };

    bindRoute('import-from-country', 'import-from-state', 'import-from-area');
    bindRoute('import-to-country', 'import-to-state', 'import-to-area');
});
</script>
<style>
    .shipment-save-bar { position: sticky; bottom: 1rem; z-index: 20; }
    .select2-container .select2-selection--single { min-height: 43px; padding-top: 6px; }
    .card.border > .card-header { background: #f8fafc; }
    @media (max-width: 575.98px) { .shipment-save-bar { bottom: .5rem; } }
</style>
@endsection
