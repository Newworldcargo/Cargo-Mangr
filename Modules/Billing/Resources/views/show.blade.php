@extends('cargo::adminLte.layouts.master')
@section('pageTitle', $document->number ?: 'Draft')
@section('content')
@include('billing::styles')
@php
    $can = fn ($action) => app(\Modules\Billing\Services\SalesAccess::class)->canAct(auth()->user(), $action, $type);
    $params = ['type' => $type, 'id' => $document->id];
    $customer = json_decode($document->customer_snapshot, true);
    $canPay = $type === 'invoices' && in_array($document->status, ['issued','partially_paid']) && $can('record-payment');
    $canRemind = $type === 'invoices' && in_array($document->status, ['issued','partially_paid']) && $can('schedule-reminder');
    $canSend = $document->number && $document->status !== 'void' && $can('send');
    $canVoid = $type === 'invoices' && $document->status !== 'void' && !$document->paid_minor && $can('void');
@endphp
<div class="container-fluid sales-page">
    @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <a class="sales-back" href="{{ route('billing.' . $type . '.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ ucfirst($type) }}</a>
    <header class="sales-page-heading">
        <div><h1>{{ $document->number ?: ($type === 'invoices' ? 'Invoice draft' : 'Quotation draft') }}</h1><p>{{ $customer['name'] }}</p></div>
        <div class="sales-toolbar">
            @if ($document->status === 'draft' && $can('edit'))<a class="btn btn-outline-secondary" href="{{ route('billing.documents.edit', $params) }}"><i class="fas fa-pen" aria-hidden="true"></i> Edit</a>@endif
            <a class="btn btn-outline-secondary" href="{{ route('billing.documents.download', $params) }}"><i class="fas fa-download" aria-hidden="true"></i> PDF</a>
            @if ($document->status === 'draft' && $can('issue'))<form method="post" action="{{ route('billing.documents.action', $params + ['action' => 'issue']) }}">@csrf<button class="btn btn-primary">Issue {{ $type === 'invoices' ? 'invoice' : 'quotation' }}</button></form>
            @elseif ($canPay)<button class="btn btn-primary" data-toggle="modal" data-target="#payment-modal" type="button"><i class="fas fa-plus" aria-hidden="true"></i> Record received payment</button>
            @elseif ($canSend)<button class="btn btn-primary" data-toggle="modal" data-target="#email-modal" type="button"><i class="far fa-paper-plane" aria-hidden="true"></i> Send document</button>@endif
            @if ($type === 'quotations' && $document->status === 'issued' && $can('record-decision'))<button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#decision-modal">Record decision</button>@endif
            @if ($type === 'quotations' && $document->status === 'accepted' && $can('convert') && app(\Modules\Billing\Services\SalesAccess::class)->canAct(auth()->user(), 'create', 'invoices'))<form method="post" action="{{ route('billing.documents.action', $params + ['action' => 'convert']) }}">@csrf<button class="btn btn-primary">Convert to invoice draft</button></form>@endif
            <div class="dropdown">
                <button class="btn btn-outline-secondary" data-toggle="dropdown" type="button" aria-label="More options" title="More options"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" target="_blank" rel="noopener" href="{{ route('billing.documents.print', $params) }}"><i class="fas fa-print" aria-hidden="true"></i> Print / Save as PDF</a>
                    <a class="dropdown-item" href="{{ route('billing.documents.download', $params) }}"><i class="fas fa-download" aria-hidden="true"></i> Download PDF</a>
                    @if ($canSend)<button class="dropdown-item" type="button" data-toggle="modal" data-target="#email-modal"><i class="far fa-envelope" aria-hidden="true"></i> Send by email</button>@endif
                    @if ($canRemind)<button class="dropdown-item" type="button" data-toggle="modal" data-target="#reminder-modal"><i class="far fa-clock" aria-hidden="true"></i> Schedule reminder</button>@endif
                    @if ($can('create'))<div class="dropdown-divider"></div><form method="post" action="{{ route('billing.documents.duplicate', $params) }}">@csrf<input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><button class="dropdown-item"><i class="far fa-copy" aria-hidden="true"></i> Duplicate as draft</button></form>@endif
                    @if ($canVoid)<div class="dropdown-divider"></div><button class="dropdown-item" type="button" data-toggle="modal" data-target="#void-modal"><i class="fas fa-ban" aria-hidden="true"></i> Void invoice</button>@endif
                </div>
            </div>
        </div>
    </header>
    @if ($type === 'quotations' && $document->converted_invoice_id)<p><a href="{{ route('billing.documents.show', ['type'=>'invoices','id'=>$document->converted_invoice_id]) }}">View linked invoice <i class="fas fa-arrow-right" aria-hidden="true"></i></a></p>@endif
    <nav class="nav nav-tabs sales-tabs" role="tablist" aria-label="Document views">
        <a class="nav-link active" id="document-tab" href="#document-view" data-toggle="tab" role="tab" aria-controls="document-view" aria-selected="true"><i class="far fa-file-alt" aria-hidden="true"></i> Document</a>
        @if ($type === 'invoices')<a class="nav-link" id="payments-tab" href="#payments-view" data-toggle="tab" role="tab" aria-controls="payments-view" aria-selected="false"><i class="far fa-credit-card" aria-hidden="true"></i> Payments <span class="sales-tab-count">{{ $payments->count() }}</span></a>@endif
        <a class="nav-link" id="email-tab" href="#email-view" data-toggle="tab" role="tab" aria-controls="email-view" aria-selected="false"><i class="far fa-envelope" aria-hidden="true"></i> Email <span class="sales-tab-count">{{ $deliveries->count() }}</span></a>
        <a class="nav-link" id="activity-tab" href="#activity-view" data-toggle="tab" role="tab" aria-controls="activity-view" aria-selected="false"><i class="fas fa-history" aria-hidden="true"></i> Activity</a>
    </nav>
    <div class="tab-content">
        <section class="tab-pane active" id="document-view" role="tabpanel" aria-labelledby="document-tab">@include('billing::document')</section>
        @if ($type === 'invoices')
        <section class="tab-pane" id="payments-view" role="tabpanel" aria-labelledby="payments-tab">
            <div class="sales-section-heading"><h2>Payment history</h2><span class="sales-status sales-status-{{ $document->status }}">{{ ucfirst(str_replace('_',' ',$document->status)) }}</span></div>
            @if ($payments->isEmpty())<div class="sales-empty"><i class="far fa-credit-card" aria-hidden="true"></i>No payments recorded.</div>
            @else<div class="table-responsive"><table class="table sales-history-table"><thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Remaining</th><th></th></tr></thead><tbody>
                @foreach ($payments as $payment)<tr><td>{{ $payment->receipt_number }}</td><td>{{ $payment->paid_on }}</td><td>{{ ucfirst(str_replace('_',' ',$payment->method)) }}</td><td>{{ $payment->reference }}</td><td class="text-nowrap">{{ $document->currency }} {{ number_format($payment->amount_minor/100,2) }}</td><td class="text-nowrap">{{ $document->currency }} {{ number_format($payment->balance_after_minor/100,2) }}</td><td><a class="btn btn-outline-secondary btn-sm" href="{{ route('billing.documents.receipt', ['id'=>$document->id,'payment'=>$payment->id]) }}" title="Download receipt" aria-label="Download receipt {{ $payment->receipt_number }}"><i class="fas fa-download" aria-hidden="true"></i></a></td></tr>@endforeach
            </tbody></table></div>@endif
        </section>
        @endif
        <section class="tab-pane" id="email-view" role="tabpanel" aria-labelledby="email-tab">
            <div class="sales-section-heading"><h2>Email history</h2>@if ($canSend)<button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#email-modal"><i class="far fa-paper-plane" aria-hidden="true"></i> Send document</button>@endif</div>
            @if ($deliveries->isEmpty())<div class="sales-empty"><i class="far fa-envelope" aria-hidden="true"></i>No emails scheduled or sent.</div>
            @else<div class="table-responsive"><table class="table sales-history-table"><thead><tr><th>Recipient</th><th>Type</th><th>Scheduled (Lusaka)</th><th>Status</th><th></th></tr></thead><tbody>@foreach($deliveries as $delivery)<tr><td>{{ $delivery->recipient }}</td><td>{{ ucfirst($delivery->purpose) }}</td><td class="text-nowrap">{{ \Carbon\Carbon::parse($delivery->send_at)->timezone('Africa/Lusaka')->format('d M Y H:i') }}</td><td>{{ ucfirst($delivery->status) }} {{ $delivery->last_error }}</td><td>@if($delivery->purpose==='reminder' && $delivery->status==='pending' && $can('schedule-reminder'))<form method="post" action="{{ route('billing.documents.delivery.cancel',$params+['delivery'=>$delivery->id]) }}">@csrf<button class="btn btn-outline-secondary btn-sm">Cancel reminder</button></form>@endif</td></tr>@endforeach</tbody></table></div>@endif
        </section>
        <section class="tab-pane" id="activity-view" role="tabpanel" aria-labelledby="activity-tab">
            @php $privateDetails=json_decode($document->document_details??'[]',true); @endphp
            @if(!empty($privateDetails['internal_notes']))<h2 class="h5">Internal notes</h2><p style="white-space:pre-wrap">{{ $privateDetails['internal_notes'] }}</p>@endif
            <div class="sales-section-heading"><h2>Document activity</h2></div><div class="table-responsive"><table class="table sales-history-table"><thead><tr><th>Date</th><th>Action</th><th>Staff</th></tr></thead><tbody>@foreach ($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ ucfirst(str_replace('_',' ',$event->event)) }}</td><td>{{ $event->actor_name ?: 'Staff #'.$event->actor_id }}</td></tr>@endforeach</tbody></table></div>{{ $events->fragment('activity-view')->links() }}
        </section>
    </div>
    @if ($canPay)
        @include('billing::modal-start', ['modalId'=>'payment-modal','modalTitle'=>'Record received payment','actionUrl'=>route('billing.documents.action',$params+['action'=>'payment'])])
        <div class="sales-modal-balance"><span>Balance due</span><strong>{{ $document->currency }} {{ number_format(($document->total_minor-$document->paid_minor)/100,2) }}</strong></div>
        <input type="hidden" name="request_key" value="{{ old('request_key',(string) \Illuminate\Support\Str::uuid()) }}">
        <div class="row"><div class="col-sm-6"><label>Amount ({{ $document->currency }})<input class="form-control" name="amount" inputmode="decimal" required value="{{ old('amount') }}" placeholder="0.00"></label></div><div class="col-sm-6"><label>Payment method<select class="form-control" name="method"><option value="cash" {{ old('method')==='cash'?'selected':'' }}>Cash</option><option value="bank_transfer" {{ old('method')==='bank_transfer'?'selected':'' }}>Received bank transfer</option></select></label></div></div>
        <label>Reference<input name="reference" class="form-control" maxlength="120" value="{{ old('reference') }}"></label><label>Date received<input type="date" class="form-control" name="paid_on" required value="{{ old('paid_on',date('Y-m-d')) }}" max="{{ date('Y-m-d') }}"></label>
        @include('billing::modal-end',['submitLabel'=>'Confirm received payment'])
    @endif
    @if ($canSend)
        @include('billing::modal-start',['modalId'=>'email-modal','modalTitle'=>'Send document','actionUrl'=>route('billing.documents.delivery',$params)])
        <input type="hidden" name="purpose" value="document"><input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <label>To<input class="form-control" value="{{ $customer['email'] ?? '' }}" readonly></label><p><i class="far fa-file-pdf" aria-hidden="true"></i> {{ $document->number }}.pdf</p>
        @include('billing::modal-end',['submitLabel'=>'Send document'])
    @endif
    @if ($canRemind)
        @include('billing::modal-start',['modalId'=>'reminder-modal','modalTitle'=>'Schedule payment reminder','actionUrl'=>route('billing.documents.delivery',$params)])
        <input type="hidden" name="purpose" value="reminder"><input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <label>To<input class="form-control" value="{{ $customer['email'] ?? '' }}" readonly></label><label>Date and time (Lusaka)<input class="form-control" type="datetime-local" name="send_at" required value="{{ old('send_at') }}"></label>
        @include('billing::modal-end',['submitLabel'=>'Schedule reminder'])
    @endif
    @if ($canVoid)
        @include('billing::modal-start',['modalId'=>'void-modal','modalTitle'=>'Void invoice','actionUrl'=>route('billing.documents.action',$params+['action'=>'void'])])
        <label>Reason<textarea class="form-control" name="note" minlength="5" maxlength="1000" rows="3" required></textarea></label>
        @include('billing::modal-end',['submitLabel'=>'Confirm void','submitClass'=>'btn-outline-danger'])
    @endif
    @if ($type === 'quotations' && $document->status === 'issued' && $can('record-decision'))
        @include('billing::modal-start',['modalId'=>'decision-modal','modalTitle'=>'Record customer decision','actionUrl'=>route('billing.documents.action',$params+['action'=>'accept'])])
        <label>Customer decision evidence<textarea class="form-control" name="note" minlength="5" maxlength="1000" required rows="4">{{ old('note') }}</textarea></label>
        <button class="btn btn-outline-secondary" formaction="{{ route('billing.documents.action',$params+['action'=>'decline']) }}">Record decline</button>
        @include('billing::modal-end',['submitLabel'=>'Record acceptance'])
    @endif
</div>
@endsection
@section('scripts')
<script>
$(function(){const hash=window.location.hash;if(['#document-view','#payments-view','#email-view','#activity-view'].includes(hash)){$('.sales-tabs a[href="'+hash+'"]').tab('show');}$('.sales-tabs a').on('shown.bs.tab',function(){history.replaceState(null,'',this.getAttribute('href'));});});
</script>
@endsection
