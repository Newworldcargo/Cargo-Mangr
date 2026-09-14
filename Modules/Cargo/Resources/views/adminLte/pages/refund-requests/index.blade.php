@php
    use Illuminate\Support\Str;
@endphp

@extends('cargo::adminLte.layouts.master')
@section('pageTitle', 'Refund Requests')
@section('content')

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="card-title mb-0">Refund Requests</h3>
        <form method="get" class="d-flex align-items-center gap-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                @foreach([App\Models\RefundRequest::STATUS_PENDING, App\Models\RefundRequest::STATUS_APPROVED, App\Models\RefundRequest::STATUS_DECLINED] as $filterStatus)
                    <option value="{{ $filterStatus }}" {{ $status === $filterStatus ? 'selected' : '' }}>{{ Str::headline($filterStatus) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
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
                        <td colspan="10" class="text-center text-muted">No refund requests found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-3">
            {{ $refundRequests->withQueryString()->links() }}
        </div>
    </div>
</div>

@endsection
