@php
$customer=json_decode($document->customer_snapshot,true);
$issuer=json_decode($document->issuer_snapshot,true);
$meta=json_decode($document->document_details??'[]',true)??[];
$shipping=json_decode($document->shipment_details??'null',true);
$money=fn($value)=>$document->currency.' '.number_format($value/100,2);
@endphp
@include('billing::compact-document-style')
<article class="sales-paper compact-document">
<header class="invoice-heading">
<div class="invoice-company"><div class="invoice-brand"><img src="{{ !empty($pdfMode)?public_path('assets/lte/cargo-logo-email.png'):asset('assets/lte/cargo-logo-email.png') }}" alt="New World Cargo" width="125" height="50"></div>
<strong>{{ $issuer['name'] }}</strong><p>{{ $issuer['branch']??'' }}</p><p>{{ $issuer['address']??'' }}</p>
@if(!empty($issuer['tpin']))<p>TPIN / Tax ID: {{ $issuer['tpin'] }}</p>@endif</div>
<div class="invoice-heading-meta"><h1>{{ $type==='invoices'?'INVOICE':'QUOTATION' }}</h1><p>{{ $document->number?:'Draft #'.$document->id }}</p>
<p>Issue date: {{ $document->issue_date }}</p><p>{{ $type==='invoices'?'Due date':'Valid until' }}: {{ $document->due_date }}</p><p>Currency: {{ $document->currency }}</p>
@if(!empty($meta['prepared_by']))<p>Prepared by: {{ $meta['prepared_by'] }}</p>@endif
@if(empty($pdfMode))<span class="sales-status sales-status-{{ $document->status }}">{{ ucfirst(str_replace('_',' ',$document->status)) }}</span>@endif
</div></header>
<div class="invoice-parties">
<section><h2>Bill to</h2><strong>{{ $customer['name'] }}</strong><p>{{ $customer['email']??'' }}</p><p>{{ $meta['customer_address']??'' }}</p>
@if(!empty($meta['customer_tpin']))<p>TPIN / Tax ID: {{ $meta['customer_tpin'] }}</p>@endif
</section>
<section><h2>Ship to / Delivery details</h2>
@if($shipping)
<strong>{{ $shipping['receiver_name']??'' }}</strong><p>{{ $shipping['receiver_phone']??'' }}</p><p>{{ $shipping['receiver_address']??'' }}</p>
<p>{{ $shipping['from_country']??'' }} {{ $shipping['from_region']??'' }} &rarr; {{ $shipping['to_country']??'' }} {{ $shipping['to_region']??'' }}</p>
<p>{{ ($shipping['shipment_type']??'')==='pickup'?'Pickup':'Drop off' }}: {{ $shipping['shipping_date']??'' }} {{ $shipping['collection_time']??'' }}</p>
@else<p>Not specified</p>@endif
@if(!empty($meta['order_reference']))<p>Order / PO: {{ $meta['order_reference'] }}</p>@endif
@if(!empty($meta['sales_agent_name']))<p>Sales agent: {{ $meta['sales_agent_name'] }}</p>@endif
@if(!empty($meta['service_from']))<p>Service period: {{ $meta['service_from'] }} - {{ $meta['service_until']??'' }}</p>@endif
</section></div>
<div class="table-responsive sales-items-wrap"><table class="sales-lines"><thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Discount</th><th>Tax</th><th>Amount</th></tr></thead><tbody>
@foreach($items as $index=>$item)
<tr><td data-label="Description"><strong>{{ $index+1 }}. {{ $item->description }}</strong></td><td data-label="Quantity">{{ number_format(($item->quantity_thousandths??$item->quantity_hundredths*10)/1000,3) }} {{ $item->unit??'' }}</td>
<td data-label="Unit price">{{ $money($item->unit_price_minor) }}<small class="d-block">{{ ($item->tax_mode??'exclusive')==='inclusive'?'Includes tax':'Excludes tax' }}</small></td>
<td data-label="Discount (excl. tax)">{{ $money($item->discount_minor) }}</td><td data-label="Tax">{{ $money($item->tax_minor) }}</td><td data-label="Amount"><strong>{{ $money($item->total_minor) }}</strong></td></tr>
@endforeach
</tbody></table></div>
<div class="sales-document-bottom"><dl class="sales-totals"><dt>Subtotal excluding tax</dt><dd>{{ $money($document->subtotal_minor) }}</dd><dt>Discount excluding tax</dt><dd>{{ $money($document->discount_minor) }}</dd><dt>Tax</dt><dd>{{ $money($document->tax_minor) }}</dd><dt class="sales-grand">Grand total</dt><dd class="sales-grand">{{ $money($document->total_minor) }}</dd>
@if($type==='invoices'&&empty($originalDocument))<dt>Amount paid</dt><dd>{{ $money($document->paid_minor) }}</dd><dt class="sales-balance">Balance due</dt><dd class="sales-balance">{{ $money($document->total_minor-$document->paid_minor) }}</dd>@endif
</dl></div>
@include('billing::pricing-details')
@include('billing::tax-summary')
@if($shipping)
<section class="sales-terms"><h2>Cargo details</h2>
@foreach($shipping['packages']??[] as $package)<p>{{ $package['quantity'] }} &times; {{ $package['type'] }}: {{ $package['description'] }} ({{ $package['weight'] }} kg each) @if(!empty($package['length'])&&!empty($package['width'])&&!empty($package['height'])) &middot; {{ $package['length'] }} &times; {{ $package['width'] }} &times; {{ $package['height'] }} cm @endif</p>@endforeach
<p>Total weight: {{ $shipping['total_weight_kg']??'' }} kg</p><p>Sender: {{ $shipping['sender_phone']??'' }} {{ $shipping['sender_address']??'' }}</p>
@if(!empty($shipping['order_id']))<p>Order: {{ $shipping['order_id'] }}</p>@endif
@if(!empty($shipping['delivery_time']))<p>Expected delivery: {{ $shipping['delivery_time'] }}</p>@endif
</section>@endif
<div class="invoice-bottom-fields"><section class="sales-terms"><h2>Terms and payment instructions</h2><p>{{ $document->terms }}</p><p>{{ $issuer['payment_instructions']??'' }}</p></section>
<section class="sales-terms"><h2>Notes</h2><p>{{ $meta['customer_notes']??'' }}</p></section></div>
<footer class="invoice-footer"><span>Thank you for choosing New World Cargo.</span><span>Authorised by ____________________</span></footer>
</article>
