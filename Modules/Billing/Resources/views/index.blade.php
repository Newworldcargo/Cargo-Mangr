@extends('cargo::adminLte.layouts.master')
@section('pageTitle', $title)
@section('content')
@php
    $access = app(\Modules\Billing\Services\SalesAccess::class);
    $listUrl = route('billing.' . $type . '.index');
    $statusColors = ['draft' => 'secondary', 'issued' => 'primary', 'partially_paid' => 'warning', 'paid' => 'success', 'accepted' => 'success', 'declined' => 'danger', 'void' => 'secondary', 'converted' => 'info'];
@endphp
<div class="card sales-list">
    <form method="get" action="{{ $listUrl }}" class="card-header border-0 pt-6 sales-list-toolbar">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1 sales-list-search">
                <i class="fas fa-search position-absolute" aria-hidden="true"></i>
                <label for="sales-search" class="sr-only">Search {{ strtolower($title) }} by reference</label>
                <input id="sales-search" name="q" type="search" maxlength="100" class="form-control form-control-solid w-250px ps-15" placeholder="Search reference" value="{{ request('q') }}">
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex flex-wrap align-items-center sales-list-controls">
                <label for="sales-per-page" class="sr-only">Rows per page</label>
                <select id="sales-per-page" name="per_page" class="form-control form-control-solid sales-list-length" onchange="this.form.submit()">
                    @foreach ([10,25,50,100] as $length)<option value="{{ $length }}" {{ (int) request('per_page',25) === $length ? 'selected' : '' }}>Show {{ $length }}</option>@endforeach
                </select>
                <a href="{{ request()->fullUrl() }}" class="btn btn-light-primary m-1" title="Reload table" aria-label="Reload table"><i class="fas fa-sync-alt fs-3" aria-hidden="true"></i></a>
                <label for="sales-status" class="sr-only">Status</label>
                <select id="sales-status" name="status" class="form-control form-control-solid sales-list-status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)<option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach
                </select>
                <button class="btn btn-light-primary m-1" type="submit" title="Apply search and filters" aria-label="Apply search and filters"><i class="fas fa-filter" aria-hidden="true"></i></button>
                @if ($ready && $access->canAct(auth()->user(), 'create', $type))
                    <a class="btn btn-primary m-1" href="{{ route('billing.documents.create', ['type' => $type]) }}">Add {{ $type === 'invoices' ? 'invoice' : 'quotation' }}</a>
                @endif
            </div>
        </div>
    </form>
    <div class="card-body pt-6">
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        @if (!$ready)
            <div class="alert alert-info" role="status">{{ $title }} are not available yet.</div>
        @else
            <div class="table-responsive">
                <table id="sales-{{ $type }}" class="table align-middle table-row-dashed fs-6 gy-5 sales-list-table">
                    <caption class="sr-only">{{ $title }}</caption>
                    <thead><tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                        <th scope="col">#</th><th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Status</th>
                        <th scope="col">{{ $type === 'invoices' ? 'Due date' : 'Valid until' }}</th><th scope="col" class="text-right">Total</th>
                        @if ($type === 'invoices')<th scope="col" class="text-right">Paid</th><th scope="col" class="text-right">Balance</th>@endif
                        <th scope="col" class="text-center">Actions</th>
                    </tr></thead>
                    <tbody class="text-gray-600 fw-bold">
                        @forelse ($records as $record)
                            @php $customer = json_decode($record->customer_snapshot, true) ?: []; $params = ['type' => $type, 'id' => $record->id]; @endphp
                            <tr>
                                <td>{{ $record->id }}</td>
                                <td class="text-nowrap"><a href="{{ route('billing.documents.show', $params) }}">{{ $record->number ?: 'Draft #' . $record->id }}</a></td>
                                <td><div class="d-flex flex-column"><span class="text-gray-800">{{ $customer['name'] ?? 'Customer' }}</span><small class="text-muted">{{ $customer['email'] ?? '' }}</small></div></td>
                                <td><span class="badge badge-{{ $statusColors[$record->status] ?? 'secondary' }}">{{ $statuses[$record->status] ?? ucfirst(str_replace('_', ' ', $record->status)) }}</span></td>
                                <td class="text-nowrap">{{ $record->due_date }}</td>
                                <td class="text-right text-nowrap">{{ $record->currency }} {{ number_format($record->total_minor / 100, 2) }}</td>
                                @if ($type === 'invoices')
                                    <td class="text-right text-nowrap">{{ $record->currency }} {{ number_format($record->paid_minor / 100, 2) }}</td>
                                    <td class="text-right text-nowrap">{{ $record->currency }} {{ number_format(($record->total_minor - $record->paid_minor) / 100, 2) }}</td>
                                @endif
                                <td class="text-center text-nowrap">
                                    <a href="{{ route('billing.documents.show', $params) }}" class="btn btn-sm btn-secondary btn-action-table" title="View document" aria-label="View {{ $record->number ?: 'draft ' . $record->id }}"><i class="fas fa-eye fa-fw" aria-hidden="true"></i></a>
                                    @if ($record->status === 'draft' && $access->canAct(auth()->user(), 'edit', $type))
                                        <a href="{{ route('billing.documents.edit', $params) }}" class="btn btn-sm btn-secondary btn-action-table" title="Edit draft" aria-label="Edit draft {{ $record->id }}"><i class="fas fa-edit fa-fw" aria-hidden="true"></i></a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $type === 'invoices' ? 9 : 7 }}" class="text-center py-5 text-muted">{{ request()->filled('q') || request()->filled('status') ? 'No ' . $type . ' match your search.' : 'No ' . $type . ' yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 sales-list-footer">
                <div class="text-muted">Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} entries</div>
                {{ $records->onEachSide(1)->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
</div>
@endsection
@section('styles')
<link rel="stylesheet" href="{{ asset('assets/lte/plugins/custom/datatables/datatables.bundle.css') }}">
<style>
.sales-list-table td a:not(.btn){color:#07539b!important}.sales-list-table td a:not(.btn):hover{text-decoration:underline}@media(max-width:600px){.sales-list .btn-action-table{min-width:44px;min-height:44px;display:inline-flex;align-items:center;justify-content:center}}
.sales-list-toolbar{gap:12px;align-items:center}.sales-list-toolbar .card-title{margin:0}.sales-list-controls{gap:4px}.sales-list-search{max-width:100%}.sales-list-search>i{left:16px;z-index:1;color:#687785}.sales-list-search input{padding-left:42px!important;max-width:100%}.sales-list-length{width:112px}.sales-list-status{width:160px}.sales-list-table th{white-space:nowrap}.sales-list .btn-primary{background:#07539b!important;border-color:#07539b!important;color:white!important}.sales-list .btn-light-primary{color:#07539b!important;background:#eaf3fc!important}.sales-list-footer{gap:12px}.sales-list-footer .pagination{margin-bottom:0}.sales-list .badge{white-space:nowrap}.sales-list .btn:focus-visible,.sales-list input:focus-visible,.sales-list select:focus-visible{outline:2px solid #07539b;outline-offset:2px}@media(max-width:600px){.sales-list-toolbar .card-title,.sales-list-toolbar .card-toolbar,.sales-list-search{width:100%}.sales-list-search input{width:100%!important}.sales-list .card-body,.sales-list .card-header{padding-left:16px;padding-right:16px}.sales-list-footer nav{max-width:100%;overflow-x:auto}}
</style>
@endsection
