@php
    $customer = json_decode($document->customer_snapshot, true);
    $issuer = json_decode($document->issuer_snapshot, true);
    $money = fn ($value) => $document->currency . ' ' . number_format($value / 100, 2);
@endphp
<article class="sales-paper">
    <header class="sales-doc-header">
        <div class="sales-identity">
            <div class="sales-logo"><img src="{{ asset('assets/lte/cargo-logo-email.png') }}" alt="New World Cargo" width="180" height="72"></div>
            <strong>{{ $issuer['name'] }}</strong>
            <p>{{ $issuer['branch'] ?? '' }}</p>
        </div>
        <div class="sales-doc-meta">
            <h1>{{ $type === 'invoices' ? 'Invoice' : 'Quotation' }}</h1>
            <p class="sales-reference">{{ $document->number ?: 'Draft #' . $document->id }}</p>
            <dl><dt>Issue date</dt><dd>{{ \Carbon\Carbon::parse($document->issue_date)->format('d M Y') }}</dd><dt>{{ $type === 'invoices' ? 'Due date' : 'Valid until' }}</dt><dd>{{ \Carbon\Carbon::parse($document->due_date)->format('d M Y') }}</dd><dt>Currency</dt><dd>{{ $document->currency }}</dd></dl>
        </div>
    </header>
    <section class="sales-recipient">
        <div><h2>{{ $type === 'invoices' ? 'Bill to' : 'Prepared for' }}</h2><strong>{{ $customer['name'] }}</strong><p>{{ $customer['email'] ?? '' }}</p></div>
        <div class="sales-document-state"><span class="sales-status sales-status-{{ $document->status }}">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</span></div>
    </section>
    <div class="table-responsive sales-items-wrap"><table class="sales-lines"><thead><tr><th scope="col">Description</th><th scope="col">Qty</th><th scope="col">Unit price</th><th scope="col">Discount</th><th scope="col">Tax</th><th scope="col">Amount</th></tr></thead><tbody>
        @foreach ($items as $item)<tr><td data-label="Description"><strong>{{ $item->description }}</strong></td><td data-label="Quantity">{{ number_format($item->quantity_hundredths / 100, 2) }}</td><td data-label="Unit price">{{ $money($item->unit_price_minor) }}</td><td data-label="Discount">{{ $money($item->discount_minor) }}</td><td data-label="Tax">{{ $money($item->tax_minor) }}</td><td data-label="Amount"><strong>{{ $money($item->total_minor) }}</strong></td></tr>@endforeach
    </tbody></table></div>
    <div class="sales-document-bottom">
        <section class="sales-terms">@if ($document->terms)<h2>Terms &amp; payment instructions</h2><p>{{ $document->terms }}</p>@endif</section>
        <dl class="sales-totals"><dt>Subtotal</dt><dd>{{ $money($document->subtotal_minor) }}</dd><dt>Discount</dt><dd>{{ $money($document->discount_minor) }}</dd><dt>Tax</dt><dd>{{ $money($document->tax_minor) }}</dd><dt class="sales-grand">Total</dt><dd class="sales-grand">{{ $money($document->total_minor) }}</dd>
        @if ($type === 'invoices' && empty($originalDocument))<dt>Amount paid</dt><dd>{{ $money($document->paid_minor) }}</dd><dt class="sales-balance">Balance due</dt><dd class="sales-balance">{{ $money($document->total_minor - $document->paid_minor) }}</dd>@endif</dl>
    </div>
    <footer><strong>Thank you for choosing New World Cargo.</strong><span>{{ $issuer['branch'] ?? '' }}</span></footer>
</article>
