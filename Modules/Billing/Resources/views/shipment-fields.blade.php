@php $shipping = $shipmentDetails ?? []; @endphp
<fieldset id="shipment-fields" class="mb-4" hidden disabled>
<legend class="h5">Shipment information <span class="badge badge-light">Not booked</span></legend>
<div class="row">
<div class="col-md-6 mb-3"><label for="shipping-type">Shipment type</label><select id="shipping-type" name="shipment_details[shipment_type]" class="form-control"><option value="pickup" {{ ($shipping['shipment_type'] ?? '') === 'pickup' ? 'selected' : '' }}>Pickup - door to door</option><option value="drop_off" {{ ($shipping['shipment_type'] ?? '') === 'drop_off' ? 'selected' : '' }}>Drop off at branch</option></select></div>
@foreach (['shipping_date'=>['Shipping date','date'], 'collection_time'=>['Collection time','time'], 'sender_phone'=>['Customer phone','tel'], 'sender_address'=>['Customer address','text'], 'receiver_name'=>['Receiver name','text'], 'receiver_phone'=>['Receiver phone','tel'], 'receiver_address'=>['Receiver address','text'], 'from_country'=>['From country','text'], 'to_country'=>['To country','text'], 'from_region'=>['From region','text'], 'to_region'=>['To region','text'], 'from_area'=>['From area','text'], 'to_area'=>['To area','text'], 'order_id'=>['Order ID','text'], 'delivery_time'=>['Expected delivery time','text']] as $field => [$label,$inputType])
<div class="col-md-6 mb-3"><label for="shipping-{{ $field }}">{{ $label }}</label><input id="shipping-{{ $field }}" name="shipment_details[{{ $field }}]" type="{{ $inputType }}" class="form-control" value="{{ $shipping[$field] ?? ($field === 'shipping_date' ? date('Y-m-d') : '') }}" maxlength="{{ str_contains($field,'address') ? 500 : (str_contains($field,'phone') ? 40 : ($field === 'receiver_name' ? 160 : 100)) }}" {{ in_array($field,['shipping_date','sender_phone','sender_address','receiver_name','receiver_phone','receiver_address','from_country','to_country']) ? 'required' : '' }}></div>
@endforeach
<div class="col-md-6 mb-3"><label for="shipping-payment-type">Payment terms</label><select id="shipping-payment-type" class="form-control" name="shipment_details[payment_type]">@foreach(['postpaid'=>'Postpaid','prepaid'=>'Prepaid'] as $value=>$label)<option value="{{ $value }}" {{ ($shipping['payment_type'] ?? 'postpaid') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6 mb-3"><label for="shipping-payment-method">Preferred payment method</label><select id="shipping-payment-method" class="form-control" name="shipment_details[payment_method]">@foreach(['cash'=>'Cash','bank_transfer'=>'Bank transfer','mobile_money'=>'Mobile money','card'=>'Card'] as $value=>$label)<option value="{{ $value }}" {{ ($shipping['payment_method'] ?? 'cash') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
</div>
<h2 class="h5">Package information</h2>
<div id="shipment-packages">
@foreach (($shipping['packages'] ?? [['quantity'=>'1','weight'=>'0.5']]) as $index=>$package)
<div class="row border-bottom mb-3 pb-2" data-package>
@foreach (['type'=>'Package type','description'=>'Description','quantity'=>'Quantity','weight'=>'Weight per package (kg)','length'=>'Length (cm)','width'=>'Width (cm)','height'=>'Height (cm)'] as $field=>$label)
<div class="{{ $field === 'description' ? 'col-12' : 'col-6 col-md-4' }} mb-3"><label>{{ $label }}<input class="form-control" name="shipment_details[packages][{{ $index }}][{{ $field }}]" value="{{ $package[$field] ?? '' }}" type="{{ in_array($field,['type','description']) ? 'text' : 'number' }}" @if(!in_array($field,['type','description'])) min="{{ $field === 'quantity' ? '1' : '0.001' }}" step="{{ $field === 'quantity' ? '1' : '0.001' }}" max="100000" @else maxlength="{{ $field === 'type' ? 80 : 500 }}" @endif {{ in_array($field,['type','description','quantity','weight']) ? 'required' : '' }}></label></div>
@endforeach
<div class="col-12"><button type="button" class="btn btn-outline-danger mb-2" data-remove-package aria-label="Remove package" title="Remove package"><i class="fas fa-trash" aria-hidden="true"></i></button></div>
</div>
@endforeach
</div>
<div class="d-flex justify-content-between align-items-center flex-wrap"><button type="button" id="add-package" class="btn btn-outline-primary"><i class="fas fa-plus" aria-hidden="true"></i> Add package</button><output id="shipment-weight" class="font-weight-bold" aria-live="polite"></output></div>
</fieldset>
