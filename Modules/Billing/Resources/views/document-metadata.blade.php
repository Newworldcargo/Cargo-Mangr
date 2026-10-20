@php $meta=json_decode($document->document_details??'[]',true)??[]; @endphp
@if(!empty($issuer['address']))<p>{{ $issuer['address'] }}</p>@endif
@if(!empty($issuer['tpin']))<p>Supplier TPIN / Tax ID: {{ $issuer['tpin'] }}</p>@endif
@if(!empty($meta['customer_address']))<p>Billing address: {{ $meta['customer_address'] }}</p>@endif
@if(!empty($meta['customer_tpin']))<p>Customer TPIN / Tax ID: {{ $meta['customer_tpin'] }}</p>@endif
@if(!empty($meta['order_reference']))<p>Order / PO: {{ $meta['order_reference'] }}</p>@endif
@if(!empty($meta['sales_agent_name']))<p>Sales agent: {{ $meta['sales_agent_name'] }}</p>@endif
@if(!empty($meta['prepared_by']))<p>Prepared by: {{ $meta['prepared_by'] }}</p>@endif
@if(!empty($meta['service_from']))<p>Service period: {{ $meta['service_from'] }} - {{ $meta['service_until']??'' }}</p>@endif
@if(!empty($meta['customer_notes']))<p style="white-space:pre-wrap">{{ $meta['customer_notes'] }}</p>@endif
