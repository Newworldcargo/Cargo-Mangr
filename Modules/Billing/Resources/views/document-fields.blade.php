@php $meta=$details??[]; @endphp
<div class="row mb-4">
<div class="col-md-6 mb-3"><label for="sales-agent">Sales agent</label><select id="sales-agent" class="form-control" name="details[sales_agent_id]" data-selected="{{ $meta['sales_agent_id']??'' }}"><option value="">Unassigned</option></select></div>
<div class="col-md-6 mb-3"><label for="payment-terms">Payment terms</label><select class="form-control" id="payment-terms" name="details[payment_terms]">@foreach(['custom'=>'Custom dates','receipt'=>'Due on receipt','net7'=>'Net 7 days','net14'=>'Net 14 days','net30'=>'Net 30 days','net60'=>'Net 60 days'] as $value=>$label)<option value="{{ $value }}" {{ ($meta['payment_terms']??'custom')===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></div>
@foreach(['order_reference'=>['Order / purchase-order reference','text'], 'customer_tpin'=>['Customer TPIN / Tax ID','text'],'service_from'=>['Service period from','date'],'service_until'=>['Service period until','date']] as $field=>[$label,$inputType])
<div class="col-md-6 mb-3"><label for="detail-{{ $field }}">{{ $label }}</label><input id="detail-{{ $field }}" class="form-control" name="details[{{ $field }}]" type="{{ $inputType }}" value="{{ $meta[$field]??'' }}" maxlength="{{ $field==='customer_tpin'?40:120 }}"></div>@endforeach
<div class="col-12"><label for="billing-address">Customer billing address</label><textarea id="billing-address" class="form-control" name="details[customer_address]" rows="2" maxlength="1000">{{ $meta['customer_address']??'' }}</textarea></div>
</div>
