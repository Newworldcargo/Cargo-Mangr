<!-- Shipment Details -->
<div class="mt-8 bg-white rounded-lg shadow-sm p-6">
    @php
        use Carbon\Carbon;
    @endphp
    <h2 class="text-lg font-bold text-gray-700 mb-4">{{ __('cargo::view.shipment_details') }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <div>
            <p class="text-sm text-gray-500">Cargo</p>
            <p class="font-medium">{{ ucfirst($shipment->consignment?->cargo_type ?? 'sea') }} Freight</p>
        </div>

        <div>
            <p class="text-sm text-gray-500">{{ __('cargo::view.shipment_type') }}</p>
            <p class="font-medium">{{ $shipment->type }}</p>
        </div>

        <div>
            <p class="text-sm text-gray-500">{{ __('cargo::view.current_branch') }}</p>
            @if ($user_role == $admin || auth()->user()->can('show-branches'))
                <a class="font-medium text-blue-600 hover:underline"
                    href="{{ route('branches.show', $shipment->branch_id ?? 1) }}">{{ $shipment->branch->name ?? 'Null' }}</a>
            @else
                <p class="font-medium">{{ $shipment->branch->name ?? 'Null' }}</p>
            @endif
        </div>

        <div>
            <p class="text-sm text-gray-500">{{ __('cargo::view.created_date') }}</p>
            <p class="font-medium">{{ $shipment->created_at->toFormattedDateString() }}</p>
        </div>

        <div>
            <p class="text-sm text-gray-500">{{ __('cargo::view.shipping_date') }}</p>
            <p class="font-medium">
                {{ ucfirst($shipment->consignment?->date) }}
            </p>
        </div>

        @if ($shipment->prev_branch)
            <div>
                <p class="text-sm text-gray-500">{{ __('cargo::view.previous_branch') }}</p>
                <p class="font-medium">
                    {{ Modules\Cargo\Entities\Branch::find($shipment->prev_branch)->name ?? 'Null' }}</p>
            </div>
        @endif
        <div>
            <p class="text-sm text-gray-500">{{ __('cargo::view.total_weight') }}</p>
            <p class="font-medium">{{ $shipment->total_weight }} {{ __('cargo::view.KG') }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500">{{ __('cargo::view.tax_duty') }}</p>
            <p class="font-medium">{{ format_shipment_price($shipment->tax, $shipment) }}</p>
        </div>
        <!-- New Consignment Fields -->
        <div>
            <p class="text-sm text-gray-500">Cargo Date</p>
            <p class="font-medium">
                {{ ucfirst($shipment->consignment?->date) }}
            </p>
        </div>
        <div>
            <p class="text-sm text-gray-500">ETA</p>
            <p class="font-medium">
                {{ ucfirst($shipment->consignment?->eta ?? '--') }}
            </p>
        </div>
        @if ($shipment->consignment?->cargo_type == 'sea')
            <div>
                <p class="text-sm text-gray-500">ETA DAR</p>
                <p class="font-medium">
                    {{ ucfirst($shipment->consignment?->eta_dar ?? '--') }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">ETA LUN</p>
                <p class="font-medium">
                    {{ $shipment->consignment?->eta_lun ?? '--' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Destination Port</p>
                <p class="font-medium">
                    {{ $shipment?->consignment?->destination ??  $shipment?->consignment?->dest_port }}
                </p>
            </div>
        @endif
        @if ($shipment->consignment?->cargo_type == 'air')
            <div>
                <img width="80" src="{{ asset('icon/plane.svg') }}" alt="">
            </div>
        @else
            <div>
                <img width="80" src="{{ asset('icon/ship.svg') }}" alt="">
            </div>
        @endif
    </div>
</div>
@php
    $shipmentEvidence = collect(json_decode((string) $shipment->attachments_before_shipping, true) ?: [])
        ->filter(fn ($item) => is_array($item) && !empty($item['file_id']))
        ->values();
@endphp
@if($shipmentEvidence->isNotEmpty())
    <div class="mt-8 bg-white rounded-lg shadow-sm p-6">
        <div class="d-flex align-items-center justify-content-between flex-wrap mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-700 mb-1">Customer uploaded evidence</h2>
                <p class="text-sm text-gray-500 mb-0">Photos and documents attached from the customer app before submission.</p>
            </div>
            <span class="badge badge-light text-dark">{{ $shipmentEvidence->count() }} file{{ $shipmentEvidence->count() === 1 ? '' : 's' }}</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($shipmentEvidence as $file)
                <a class="border rounded-lg p-4 d-flex align-items-center text-decoration-none hover:shadow-sm"
                   href="{{ route('shipments.evidence.download', ['shipment' => $shipment->id, 'fileId' => $file['file_id']]) }}"
                   target="_blank" rel="noopener">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle mr-3"
                          style="width: 42px; height: 42px; background: #eef4ff; color: #1d4ed8;">
                        <i class="{{ str_starts_with((string) ($file['content_type'] ?? ''), 'image/') ? 'fas fa-image' : 'fas fa-file-alt' }}"></i>
                    </span>
                    <span class="min-w-0">
                        <span class="d-block font-medium text-gray-700 text-truncate">{{ $file['name'] ?? 'Shipment evidence' }}</span>
                        <span class="d-block text-sm text-gray-500">
                            {{ strtoupper((string) ($file['purpose'] ?? 'shipment-evidence')) }}
                            @if(!empty($file['size_bytes']))
                                · {{ number_format(((int) $file['size_bytes']) / 1024, 1) }} KB
                            @endif
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endif
