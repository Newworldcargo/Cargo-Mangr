@php
    $customer = json_decode($document->customer_snapshot, true);
    $issuer = json_decode($document->issuer_snapshot, true);
    $money = fn ($value) => $document->currency . ' ' . number_format($value / 100, 2);
@endphp
<article class="sales-paper">
    <header class="sales-doc-header">
        <div><img src="{{ asset('assets/lte/cargo-logo-email.png') }}" alt="New World Cargo" width="180" height="72"><h2>New World Cargo</h2><p>{{ $issuer['branch'] ?? '' }}</p></div>
        <div><h1>{{ $type === 'invoices' ? 'Invoice' : 'Quotation' }}</h1><strong>{{ $document->number ?: 'Draft #' . $document->id }}</strong><p>{{ ucfirst(str_replace('_', ' ', $document->status)) }}</p></div>
    </header>
    <div class="sales-doc-header">
        <div><h3>{{ $type === 'invoices' ? 'Bill to' : 'Prepared for' }}</h3><strong>{{ $customer['name'] }}</strong><p>{{ $customer['email'] ?? '' }}</p></div>
        <div><p>Issued: {{ $document->issue_date }}</p><p>{{ $type === 'invoices' ? 'Due' : 'Valid until' }}: {{ $document->due_date }}</p><p>Currency: {{ $document->currency }}</p></div>
    </div>
    <div class="table-responsive"><table class="sales-lines"><thead><tr><th>Description</th><th>Quantity</th><th>Unit price</th><th>Discount</th><th>Tax</th><th>Total</th></tr></thead><tbody>
        @foreach ($items as $item)<tr><td>{{ $item->description }}</td><td>{{ number_format($item->quantity_hundredths / 100, 2) }}</td><td>{{ $money($item->unit_price_minor) }}</td><td>{{ $money($item->discount_minor) }}</td><td>{{ $money($item->tax_minor) }}</td><td>{{ $money($item->total_minor) }}</td></tr>@endforeach
    </tbody></table></div>
    <dl class="sales-totals"><dt>Subtotal</dt><dd>{{ $money($document->subtotal_minor) }}</dd><dt>Discount</dt><dd>{{ $money($document->discount_minor) }}</dd><dt>Tax</dt><dd>{{ $money($document->tax_minor) }}</dd><dt class="sales-grand">Total</dt><dd class="sales-grand">{{ $money($document->total_minor) }}</dd>
    @if ($type === 'invoices' && empty($originalDocument))<dt>Paid</dt><dd>{{ $money($document->paid_minor) }}</dd><dt>Balance due</dt><dd>{{ $money($document->total_minor - $document->paid_minor) }}</dd>@endif</dl>
    @if ($document->terms)<h3>Terms</h3><p style="white-space:pre-wrap">{{ $document->terms }}</p>@endif
    <footer>Thank you for choosing New World Cargo.</footer>
</article>
