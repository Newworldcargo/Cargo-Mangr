<form class="form-horizontal" action="{{ route('shipments.settings.fees.mobile-pricing.store') }}" method="POST">
    @csrf
    <div class="alert alert-info">
        Booking prices apply to the customer portal and mobile app. When advance pricing is off, staff confirm the price after receiving the booking.
    </div>

    <div class="card border-primary mb-5">
        <div class="card-header"><h5 class="mb-0 h6">International quote formula</h5></div>
        <div class="card-body">
            <h4 class="mb-4">International freight + Zambia onward delivery</h4>
            <div class="row">
                <div class="col-md-4"><strong>Collect at Zambia hub</strong><p class="pricing-help mb-0">International freight only. No onward delivery fee.</p></div>
                <div class="col-md-4"><strong>Deliver within the hub city</strong><p class="pricing-help mb-0">International freight stays the same, then Local Delivery pricing is added.</p></div>
                <div class="col-md-4"><strong>Deliver to another city</strong><p class="pricing-help mb-0">International freight stays the same, then the enabled City-to-City route is added.</p></div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Pricing was not saved.</strong>
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h5 class="mb-0 h6">Quote settings</h5></div>
        <div class="card-body">
            <div class="row">
                @foreach(['local' => 'Local Delivery', 'intercity' => 'City-to-City', 'import' => 'International'] as $service => $label)
                    <div class="col-md-4 mb-3">
                        <input type="hidden" name="advance_pricing[{{ $service }}]" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="advance-pricing-{{ $service }}" name="advance_pricing[{{ $service }}]" value="1" {{ old('advance_pricing.' . $service, $advancePricing[$service] ?? false) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="advance-pricing-{{ $service }}">{{ $label }} advance pricing</label>
                        </div>
                    </div>
                @endforeach
                <div class="form-group col-md-4">
                    <label>Currency</label>
                    <input class="form-control text-uppercase" name="currency" maxlength="3" value="{{ old('currency', Modules\Cargo\Entities\ShipmentSetting::getVal('mobile_pricing_currency') ?: 'ZMW') }}" required>
                </div>
            </div>
            <p class="pricing-help mb-0">Amounts are entered in the selected currency. Per-kilometre and per-kilogram charges may be zero when intentionally unused. Every enabled service or route needs a positive base fee.</p>
        </div>
    </div>

    @php
        $groups = [
            'Local Delivery' => [
                'local_base_fee' => 'Base fee', 'local_per_km' => 'Per km', 'local_per_kg' => 'Per kg',
                'local_fragile_fee' => 'Fragile handling', 'local_scooter_fee' => 'Motorcycle',
                'local_small_van_fee' => 'Small van', 'local_cargo_van_fee' => 'Cargo van',
            ],
            'City-to-City defaults' => [
                'intercity_base_fee' => 'Default base fee', 'intercity_per_km' => 'Per km',
                'intercity_per_kg' => 'Per kg', 'intercity_fragile_fee' => 'Fragile handling',
                'intercity_container_fee' => 'Container handling',
            ],
            'International freight defaults' => [
                'import_base_fee' => 'Default base fee', 'import_per_kg' => 'Per kg',
                'import_fragile_fee' => 'Fragile handling', 'import_container_fee' => 'Container handling',
            ],
        ];
    @endphp
    <div class="row">
        @foreach ($groups as $title => $fields)
            <div class="col-xl-4 d-flex">
                <div class="card mt-5 w-100">
                    <div class="card-header"><h5 class="mb-0 h6">{{ $title }}</h5></div>
                    <div class="card-body">
                        @foreach ($fields as $field => $label)
                            <div class="form-group">
                                <label>{{ $label }}</label>
                                <input type="number" min="0" step="0.01" class="form-control" name="pricing[{{ $field }}]" value="{{ old('pricing.'.$field, $mobilePricingValues[$field] ?? '') }}">
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mt-5">
        <div class="card-header"><h5 class="mb-0 h6">City-to-City branch routes</h5></div>
        <div class="card-body">
            <p class="pricing-help">Enable only routes operations can fulfil. Route values override the City-to-City defaults.</p>
            <div class="table-responsive">
                <table class="table table-bordered pricing-table">
                    <thead><tr><th>Enabled</th><th>From</th><th>To</th><th>Base</th><th>Per km</th><th>Per kg</th><th>Fragile</th><th>Container</th></tr></thead>
                    <tbody>
                    @foreach ($branches as $origin)
                        @foreach ($branches as $destination)
                            @continue($origin->id === $destination->id)
                            @php $route = $mobileRouteValues['intercity'][$origin->id][$destination->id] ?? []; $prefix = "intercity_routes.{$origin->id}.{$destination->id}"; @endphp
                            <tr>
                                <td><input type="checkbox" name="intercity_routes[{{ $origin->id }}][{{ $destination->id }}][enabled]" value="1" {{ old($prefix.'.enabled', $route['enabled'] ?? null) == '1' ? 'checked' : '' }}></td>
                                <td>{{ $origin->name }}</td><td>{{ $destination->name }}</td>
                                @foreach (['base_fee', 'per_km', 'per_kg', 'fragile_fee', 'container_fee'] as $field)
                                    <td><input type="number" min="0" step="0.01" class="form-control" name="intercity_routes[{{ $origin->id }}][{{ $destination->id }}][{{ $field }}]" value="{{ old($prefix.'.'.$field, $route[$field] ?? '') }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-5">
        <div class="card-header"><h5 class="mb-0 h6">International freight lanes — Air and Sea</h5></div>
        <div class="card-body">
            <p class="pricing-help">Configure each supported overseas branch or country office to its Zambia receiving hub. Air and Sea are enabled and priced independently. These rates price only the international leg; the customer's selected Zambia delivery is added from the Local or City-to-City sections above.</p>
            <div class="table-responsive">
                <table class="table table-bordered pricing-table">
                    <thead><tr><th>Enabled</th><th>From</th><th>To</th><th>Method</th><th>Base</th><th>Per kg</th><th>Fragile</th><th>Container</th></tr></thead>
                    <tbody>
                    @foreach ($branches as $origin)
                        @foreach ($branches as $destination)
                            @continue($origin->id === $destination->id)
                            @foreach (['air' => 'Air', 'sea' => 'Sea'] as $mode => $modeLabel)
                                @php $route = $mobileRouteValues['import'][$origin->id][$destination->id][$mode] ?? []; $prefix = "import_routes.{$origin->id}.{$destination->id}.{$mode}"; @endphp
                                <tr>
                                    <td><input type="checkbox" name="import_routes[{{ $origin->id }}][{{ $destination->id }}][{{ $mode }}][enabled]" value="1" {{ old($prefix.'.enabled', $route['enabled'] ?? null) == '1' ? 'checked' : '' }}></td>
                                    <td>{{ $origin->name }}</td><td>{{ $destination->name }}</td><td>{{ $modeLabel }}</td>
                                    @foreach (['base_fee', 'per_kg', 'fragile_fee', 'container_fee'] as $field)
                                        <td><input type="number" min="0" step="0.01" class="form-control" name="import_routes[{{ $origin->id }}][{{ $destination->id }}][{{ $mode }}][{{ $field }}]" value="{{ old($prefix.'.'.$field, $route[$field] ?? '') }}"></td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mb-0 text-right form-group">
        <button type="submit" class="btnclicky mt-2 btn btn-lg btn-success">Save mobile pricing</button>
    </div>
</form>
