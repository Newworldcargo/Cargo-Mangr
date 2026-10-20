@foreach($items as $pricedItem)
@php $price=!empty($pricedItem->pricing_snapshot)?json_decode($pricedItem->pricing_snapshot,true):null; @endphp
@if($price)
<p class="small"><strong>{{ $pricedItem->description }}</strong><br>
{{ $price['chargeable_quantity'] }} {{ $price['unit'] }} &times; {{ $price['source_currency'] }} {{ $price['tier']['rate'] }}
= {{ $price['source_currency'] }} {{ number_format($price['source_minor']/100,2) }}
@if($price['source_currency']!==$price['currency'])
<br>Exchange rate: 1 {{ $price['source_currency'] }} = {{ number_format($price['exchange_rate'],6) }} {{ $price['currency'] }}.
Freight: {{ $price['currency'] }} {{ number_format($price['amount_minor']/100,2) }}.
@endif
<br>Duty: {{ $price['configuration']['duty'] }}.
@if(!empty($price['configuration']['transit_days'])) Estimated transit: {{ $price['configuration']['transit_days'] }} days.@endif
@if(!empty($price['configuration']['terms']))<br>{{ $price['configuration']['terms'] }}@endif
</p>
@endif
@endforeach
