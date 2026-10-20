@php $meta=$details??[]; @endphp
<div class="row mb-4">
<div class="col-md-6 mb-3"><label for="sales-agent">Sales agent</label><select id="sales-agent" class="form-control" name="details[sales_agent_id]" data-selected="{{ $meta['sales_agent_id']??'' }}"><option value="">Unassigned</option></select></div>
@foreach(['customer_tpin'=>['Customer TPIN / Tax ID','text'],'service_from'=>['Service period from','date'],'service_until'=>['Service period until','date']] as $field=>[$label,$inputType])
<div class="col-md-6 mb-3"><label for="detail-{{ $field }}">{{ $label }}</label><input id="detail-{{ $field }}" class="form-control" name="details[{{ $field }}]" type="{{ $inputType }}" value="{{ $meta[$field]??'' }}" maxlength="{{ $field==='customer_tpin'?40:120 }}"></div>@endforeach
</div>
