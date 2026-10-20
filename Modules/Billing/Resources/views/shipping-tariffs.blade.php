@extends('cargo::adminLte.layouts.master')
@section('pageTitle','Shipping rates')
@section('content')
@include('billing::styles')
@php $c=$configuration; @endphp
<div class="container-fluid sales-page sales-settings">
<header class="sales-page-heading"><h1>Shipping rates</h1><a class="btn btn-outline-primary" href="{{ route('billing.settings',['branch_id'=>$id]) }}">Sales settings</a></header>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="get"><label for="rate-branch">Billing branch</label><select id="rate-branch" class="form-control mb-4" name="branch_id" onchange="this.form.submit()">@foreach($branches as $branch)<option value="{{ $branch->id }}" {{ $id===$branch->id?'selected':'' }}>{{ $branch->name }}</option>@endforeach</select></form>
<div class="table-responsive"><table class="table table-row-dashed"><thead><tr><th>Service</th><th>Rate currency</th><th>Unit</th><th>Effective from</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($services as $service)@php $t=$tariffs->get($service->id);$v=$t?json_decode($t->configuration,true):null; @endphp
<tr><td>{{ $service->name }}</td><td>{{ $v['currency']??$service->currency }}</td><td>{{ $v['unit']??$service->unit }}</td><td>{{ $v['effective_from']??'-' }}</td><td>{{ $v?($v['active']?'Active':'Inactive'):'Fixed service rate' }}</td><td><a class="btn btn-outline-secondary btn-sm" href="{{ route('billing.shipping-tariffs',['branch_id'=>$id,'service_id'=>$service->id]) }}#rate-editor" title="Configure shipping rate" aria-label="Configure {{ $service->name }}"><i class="fas fa-pen"></i></a></td></tr>
@empty<tr><td colspan="6">No services configured. <a href="{{ route('billing.settings',['branch_id'=>$id]) }}#service-settings">Add a service</a></td></tr>@endforelse
</tbody></table></div>
@if($services->contains('id',(int)request('service_id')))
<section id="rate-editor" class="mt-4">
<h2 class="h5">{{ $services->firstWhere('id',(int)request('service_id'))->name }}</h2>
<form method="post" action="{{ route('billing.shipping-tariffs.save') }}">@csrf
<input type="hidden" name="branch_id" value="{{ $id }}"><input type="hidden" name="service_id" value="{{ request('service_id') }}"><input type="hidden" name="revision" value="{{ old('revision',$selected->revision??0) }}">
<div class="row">
@foreach(['origin_id'=>'Origin office','destination_id'=>'Destination office'] as $field=>$label)
<div class="col-md-6 mb-3"><label>{{ $label }}<select class="form-control" name="{{ $field }}" required><option value="">Choose office</option>@foreach($routes as $branch)<option value="{{ $branch->id }}" {{ (string)old($field,$c[$field]??($field==='destination_id'?$id:''))===(string)$branch->id?'selected':'' }}>{{ $branch->name }}</option>@endforeach</select></label></div>
@endforeach
@foreach(['method'=>['Air'=>'air','Sea'=>'sea','Road'=>'road'],'category'=>['General goods'=>'general','Small parcels'=>'small_parcels','Special cargo'=>'special','Motorbikes'=>'motorbike','Heavy goods'=>'heavy'],'currency'=>['USD'=>'USD','ZMW'=>'ZMW'],'unit'=>['Kilogram'=>'kg','Cubic metre (CBM)'=>'cbm','Metric ton'=>'ton'],'weight_basis'=>['Actual measurement'=>'actual','Verified chargeable weight'=>'chargeable'],'duty'=>['Unconfirmed'=>'unconfirmed','Included'=>'included','Excluded'=>'excluded']] as $field=>$options)
<div class="col-md-4 mb-3"><label>{{ ucfirst(str_replace('_',' ',$field)) }}<select name="{{ $field }}" class="form-control" required>@foreach($options as $label=>$value)<option value="{{ $value }}" {{ old($field,$c[$field]??'')===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></label></div>
@endforeach
@foreach(['minimum'=>'Minimum chargeable quantity','rounding'=>'Round up to quantity increment (0 = exact)','volume_below'=>'Cargo volume must be below (CBM, optional)'] as $field=>$label)
<div class="col-md-4 mb-3"><label>{{ $label }}<input class="form-control" inputmode="decimal" name="{{ $field }}" value="{{ old($field,$c[$field]??($field==='volume_below'?'':'0')) }}" {{ $field!=='volume_below'?'required':'' }}></label></div>
@endforeach
<div class="col-md-6 mb-3"><label>Effective from<input class="form-control" type="date" name="effective_from" value="{{ old('effective_from',$c['effective_from']??date('Y-m-d')) }}" required></label></div>
<div class="col-md-6 mb-3"><label>Effective until<input class="form-control" type="date" name="effective_until" value="{{ old('effective_until',$c['effective_until']??'') }}"></label></div>
<div class="col-md-6 mb-3"><label>Estimated transit days<input class="form-control" type="number" min="1" max="365" name="transit_days" value="{{ old('transit_days',$c['transit_days']??'') }}"></label></div>
<div class="col-md-6 mb-3"><label>Departure days<input class="form-control" name="departure_days" maxlength="100" value="{{ old('departure_days',$c['departure_days']??'') }}"></label></div>
</div>
<h3 class="h6">Quantity bands: selected rate applies to the whole chargeable quantity</h3>
<div id="shipping-bands">
@foreach(old('tiers',$c['tiers']??[['from'=>'0','rate'=>'']]) as $index=>$tier)
<div class="row shipping-band"><div class="col-5"><label>From quantity (inclusive)<input class="form-control" name="tiers[{{ $index }}][from]" value="{{ $tier['from'] }}" inputmode="decimal" required></label></div><div class="col-5"><label>Rate per unit<input class="form-control" name="tiers[{{ $index }}][rate]" value="{{ $tier['rate'] }}" inputmode="decimal" required></label></div><div class="col-2 d-flex align-items-end pb-2"><button class="btn btn-outline-danger" type="button" data-remove-band title="Remove band" aria-label="Remove band"><i class="fas fa-trash"></i></button></div></div>
@endforeach
</div>
<button type="button" class="btn btn-outline-primary my-3" id="add-band" aria-label="Add band"><i class="fas fa-plus" aria-hidden="true"></i> Add band</button>
<label>Service terms<textarea class="form-control" name="terms" maxlength="2000" rows="3">{{ old('terms',$c['terms']??'') }}</textarea></label>
<input type="hidden" name="active" value="0"><label class="d-block my-3"><input type="checkbox" name="active" value="1" {{ old('active',$c['active']??false)?'checked':'' }}> Approved for use in Sales</label>
<input type="hidden" name="booking_enabled" value="0"><label class="d-block my-3"><input type="checkbox" name="booking_enabled" value="1" {{ old('booking_enabled',$c['booking_enabled']??false)?'checked':'' }}> Use for matching import bookings when advance pricing is enabled</label>
<button type="submit" class="btn btn-primary">Save shipping rate</button>
</form></section>@endif
</div>
@endsection
@section('scripts')
<script>
(()=>{const bands=document.getElementById('shipping-bands');if(!bands)return;const index=()=>bands.querySelectorAll('.shipping-band').forEach((row,i)=>row.querySelectorAll('input').forEach(input=>input.name=input.name.replace(/tiers\[\d+\]/,'tiers['+i+']')));
document.getElementById('add-band').onclick=()=>{if(bands.children.length>=30)return;const row=bands.firstElementChild.cloneNode(true);row.querySelectorAll('input').forEach(input=>input.value='');bands.append(row);index();row.querySelector('input').focus();};
bands.onclick=event=>{const button=event.target.closest('[data-remove-band]');if(button&&bands.children.length>1){button.closest('.shipping-band').remove();index();}};})();
</script>
@endsection
