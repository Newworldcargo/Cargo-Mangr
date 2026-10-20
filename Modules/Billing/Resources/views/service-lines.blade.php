<div class="sales-section-heading"><h2 class="h5">Services and charges</h2><span id="invoice-currency" class="sales-status"></span></div>
<div id="catalogue-status" class="mb-3" role="status"></div>
<div class="sales-lines-editor" id="lines">
@foreach($lines as $index=>$line)
<div class="sales-form-row sales-service-line">
<label class="sales-line-wide">Service<select class="form-control" data-service name="items[{{ $index }}][service_id]" data-selected="{{ $line['service_id']??'' }}"><option value="">Custom service</option></select></label>
<label class="sales-line-wide">Description<input class="form-control" name="items[{{ $index }}][description]" maxlength="500" value="{{ $line['description']??'' }}" required></label>
<label>Quantity<input class="form-control" name="items[{{ $index }}][quantity]" inputmode="decimal" value="{{ $line['quantity']??'1' }}" required></label>
<label>Volume (CBM, if required)<input class="form-control" name="items[{{ $index }}][cargo_volume]" inputmode="decimal" value="{{ $line['cargo_volume']??'' }}"></label>
<label>Unit<select class="form-control" name="items[{{ $index }}][unit]">@foreach(\Modules\Billing\Services\SalesConfiguration::UNITS as $unit)<option value="{{ $unit }}" {{ ($line['unit']??'service')===$unit?'selected':'' }}>{{ ucfirst($unit) }}</option>@endforeach</select></label>
<label><span data-rate-label>Unit rate</span><input class="form-control" name="items[{{ $index }}][unit_price]" inputmode="decimal" value="{{ $line['unit_price']??'' }}" required></label>
<label>Discount amount<input class="form-control" name="items[{{ $index }}][discount]" inputmode="decimal" value="{{ $line['discount']??'0' }}" required></label>
<label>Tax category<select class="form-control" data-tax name="items[{{ $index }}][tax_id]" data-selected="{{ $line['tax_id']??'' }}" required><option value="">Choose tax</option></select><input type="hidden" name="items[{{ $index }}][tax_percent]" value="{{ $line['tax_percent']??'0' }}"></label>
<label>Prices<select class="form-control" name="items[{{ $index }}][tax_mode]"><option value="exclusive" {{ ($line['tax_mode']??'exclusive')==='exclusive'?'selected':'' }}>Exclude tax</option><option value="inclusive" {{ ($line['tax_mode']??'')==='inclusive'?'selected':'' }}>Include tax</option></select></label>
<button class="btn btn-outline-danger align-self-end mb-2" type="button" data-remove title="Remove item" aria-label="Remove item"><i class="fas fa-trash" aria-hidden="true"></i></button>
</div>
@endforeach
</div>
