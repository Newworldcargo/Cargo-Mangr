@extends('cargo::adminLte.layouts.master')
@section('pageTitle', $title)
@section('content')
@include('billing::styles')
<div class="container-fluid py-3 sales-page">
    <nav aria-label="Breadcrumb"><ol class="breadcrumb bg-transparent px-0"><li class="breadcrumb-item">Sales</li><li class="breadcrumb-item active" aria-current="page">{{ $title }}</li></ol></nav>
    <h1 class="h3 mb-4">{{ $title }}</h1>
    @if ($ready && app(\Modules\Billing\Services\SalesAccess::class)->canAct(auth()->user(), 'create', $type))
    <a class="btn btn-primary mb-3" href="{{ route('billing.documents.create', ['type' => $type]) }}"><i class="fas fa-plus" aria-hidden="true"></i> New {{ $type === 'invoices' ? 'invoice' : 'quotation' }}</a>
    @endif
    @if (!$ready)
        <div class="alert alert-info" role="status">{{ $title === 'Quotations' ? 'Quotation management is not available yet.' : 'Invoice management is not available yet.' }}</div>
    @else
        <form method="get" action="{{ route('billing.' . $type . '.index') }}" class="row align-items-end mb-4">
            <div class="col-md-6 mb-2">
                <label for="sales-search">Reference</label>
                <input id="sales-search" class="form-control" name="q" maxlength="100" value="{{ request('q') }}" type="search">
            </div>
            <div class="col-md-4 mb-2">
                <label for="sales-status">Status</label>
                <select id="sales-status" name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)<option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2 mb-2"><button class="btn btn-primary" type="submit"><i class="fas fa-search" aria-hidden="true"></i> Search</button></div>
        </form>
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col">{{ $type === 'invoices' ? 'Due date' : 'Valid until' }}</th><th scope="col" class="text-right">Total</th>@if ($type === 'invoices')<th scope="col" class="text-right">Paid</th><th scope="col" class="text-right">Balance</th>@endif</tr></thead>
                <tbody>
                @forelse ($records as $invoice)
                    @php $customer = json_decode($invoice->customer_snapshot, true) ?: []; @endphp
                    <tr>
                        <td><a href="{{ route('billing.documents.show', ['type' => $type, 'id' => $invoice->id]) }}">{{ $invoice->number ?: 'Draft #' . $invoice->id }}</a></td>
                        <td>{{ $customer['name'] ?? 'Customer' }}</td>
                        <td>{{ $statuses[$invoice->status] ?? ucfirst(str_replace('_', ' ', $invoice->status)) }}</td>
                        <td>{{ $invoice->due_date }}</td>
                        <td class="text-right text-nowrap">{{ $invoice->currency }} {{ number_format($invoice->total_minor / 100, 2) }}</td>
                        @if ($type === 'invoices')<td class="text-right text-nowrap">{{ $invoice->currency }} {{ number_format($invoice->paid_minor / 100, 2) }}</td>
                        <td class="text-right text-nowrap">{{ $invoice->currency }} {{ number_format(($invoice->total_minor - $invoice->paid_minor) / 100, 2) }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted">{{ request()->filled('q') || request()->filled('status') ? 'No ' . $type . ' match your search.' : 'No ' . $type . ' yet.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $records->links() }}
    @endif
</div>
@endsection
