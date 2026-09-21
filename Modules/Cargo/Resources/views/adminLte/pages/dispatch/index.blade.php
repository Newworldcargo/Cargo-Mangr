@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Dispatch')
@section('content')
<div class="bg-white p-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h1 class="h4 mb-0">Dispatch</h1>
        <a class="btn btn-outline-secondary" href="{{ request()->fullUrl() }}" aria-label="Refresh dispatch" title="Refresh dispatch"><i class="fas fa-sync-alt" aria-hidden="true"></i></a>
    </div>
    <form method="GET" class="d-flex flex-wrap align-items-end mb-4" style="gap:12px">
        <div><label for="dispatch-search">Shipment or recipient</label><input class="form-control" id="dispatch-search" name="search" value="{{ request('search') }}" maxlength="100" type="search"></div>
        <div><label for="dispatch-assignment">Driver assignment</label><select class="form-control" id="dispatch-assignment" name="assignment">
            @foreach(['all' => 'All assignments', 'unassigned' => 'Unassigned', 'assigned' => 'Assigned'] as $value => $label)
                <option value="{{ $value }}" @selected(request('assignment', 'all') === $value)>{{ $label }}</option>
            @endforeach
        </select></div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i> Search</button>
        @if(request('search') || request('assignment', 'all') !== 'all')<a href="{{ fr_route('shipments.dispatch.index') }}" class="btn btn-outline-secondary">Clear</a>@endif
    </form>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>Shipment</th><th>Recipient</th><th>Branch</th><th>Driver</th><th>Queued at</th><th>Shipment status</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($entries as $entry)
                @php($item = $entry->shipment)
                <tr>
                    <td><a href="{{ fr_route('shipments.show', $item->id) }}">{{ $item->code }}</a></td>
                    <td>{{ $item->reciver_name ?: '-' }}</td>
                    <td>{{ $item->branch?->name ?: '-' }}</td>
                    <td>{{ $item->captain?->name ?: 'Unassigned' }}</td>
                    <td class="text-nowrap">{{ $entry->created_at->format('d M Y, H:i') }}</td>
                    <td>{{ $item->getStatus() }}</td>
                    <td>@if($service->canManage(auth()->user(), $item) && $service->eligible($item))
                        <button type="button" class="btn btn-sm btn-outline-primary" data-dispatch-shipment="{{ $item->id }}" data-dispatch-code="{{ $item->code }}"><i class="fas fa-user-edit mr-1" aria-hidden="true"></i> Assign driver</button>
                    @endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">{{ request('search') || request('assignment', 'all') !== 'all' ? 'No dispatch entries match your filters.' : 'No shipments in dispatch yet.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $entries->links() }}
</div>
@include('cargo::adminLte.pages.dispatch.modal')
@endsection
