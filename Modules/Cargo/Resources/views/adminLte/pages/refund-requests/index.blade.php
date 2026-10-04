@php
    use Illuminate\Support\Str;
@endphp

@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Refund Requests')
@section('content')

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title mb-0">Refund Requests</h3>
        <form method="get" action="{{ fr_route('refund-requests.index') }}" class="d-flex flex-wrap align-items-center" style="gap:8px;max-width:100%">
            <label for="refund-search" class="sr-only">Search refunds</label>
            <input id="refund-search" type="search" name="q" value="{{ $search }}" maxlength="100"
                class="form-control form-control-sm" placeholder="Shipment, customer, receipt..." style="width:260px;max-width:100%">
            <label for="refund-status" class="sr-only">Refund status</label>
            <select id="refund-status" name="status" class="custom-select custom-select-sm" style="width:auto;max-width:100%">
                <option value="">All Statuses</option>
                @foreach([App\Models\RefundRequest::STATUS_PENDING, App\Models\RefundRequest::STATUS_APPROVED, App\Models\RefundRequest::STATUS_DECLINED] as $filterStatus)
                    <option value="{{ $filterStatus }}" {{ $status === $filterStatus ? 'selected' : '' }}>{{ Str::headline($filterStatus) }}</option>
                @endforeach
            </select>
            <label for="refund-page-size" class="sr-only">Records per page</label>
            <select id="refund-page-size" name="per_page" class="custom-select custom-select-sm" style="width:auto;max-width:100%">
                @foreach([20, 50, 100] as $size)
                    <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }} per page</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search mr-1" aria-hidden="true"></i>Search</button>
            @if($search !== '' || $status)
                <a href="{{ fr_route('refund-requests.index') }}" class="btn btn-sm btn-outline-secondary" title="Clear filters" aria-label="Clear filters"><i class="fas fa-times" aria-hidden="true"></i></a>
            @endif
        </form>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Shipment</th>
                    <th>Client</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Requested By</th>
                    <th>Reviewed By</th>
                    <th>Requested At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($refundRequests as $refundRequest)
                    @php
                        $badge = match ($refundRequest->status) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'declined' => 'danger',
                            default => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td>{{ $refundRequest->id }}</td>
                        <td>{{ $refundRequest->shipment?->code ?? '-' }}</td>
                        <td>{{ $refundRequest->shipment?->client?->name ?? '-' }}</td>
                        <td>{{ Str::headline($refundRequest->refund_type) }}</td>
                        <td>{{ number_format((float) $refundRequest->amount, 2) }}</td>
                        <td><span class="badge bg-{{ $badge }}">{{ Str::headline($refundRequest->status) }}</span></td>
                        <td>{{ $refundRequest->requester?->name ?? '-' }}</td>
                        <td>{{ $refundRequest->reviewer?->name ?? '-' }}</td>
                        <td>{{ optional($refundRequest->created_at)->format('Y-m-d H:i') }}</td>
                        <td>
                            <a href="{{ fr_route('refund-requests.show', $refundRequest->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted">{{ $search !== '' || $status ? 'No refunds match your search and filters.' : 'No refund requests found.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex flex-wrap align-items-center justify-content-between refund-pagination" style="gap:12px">
        <span class="text-muted text-sm">Showing {{ $refundRequests->firstItem() ?? 0 }}-{{ $refundRequests->lastItem() ?? 0 }} of {{ $refundRequests->total() }} refunds</span>
        {{ $refundRequests->onEachSide(1)->links('pagination::bootstrap-4') }}
    </div>
</div>
<style>.refund-pagination .pagination { flex-wrap: wrap; margin-bottom: 0; }</style>

@endsection
