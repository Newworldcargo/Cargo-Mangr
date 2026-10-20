<div class="sales-section-heading"><h2>Services and charges</h2><span id="invoice-currency" class="sales-status" title="Currency of the issuing branch"></span></div>
<div id="catalogue-status" role="status"></div>
<div class="invoice-line-head" aria-hidden="true"><span>No.</span><span>Description</span><span>Qty / Unit</span><span>Unit price</span><span>Amount</span><span></span></div>
<div class="sales-lines-editor" id="lines">
@foreach($lines as $index=>$line)
<div class="sales-form-row sales-service-line">
<span class="invoice-line-number"></span>
<div class="invoice-description">
<div class="service-search-wrap"><label class="sr-only">Service<select class="form-control" data-service name="items[{{ $index }}][service_id]" data-selected="{{ $line['service_id']??'' }}" tabindex="-1"><option value="">Custom service</option></select></label>
<input type="search" class="form-control" data-service-search aria-label="Search services" placeholder="Search services" autocomplete="off"><div class="service-suggestions list-group" hidden></div></div>
<input aria-label="Description" class="form-control" name="items[{{ $index }}][description]" maxlength="500" value="{{ $line['description']??'' }}" required placeholder="Service description">
</div>
<div><label class="invoice-mobile-label">Quantity</label><input aria-label="Quantity" class="form-control" name="items[{{ $index }}][quantity]" inputmode="decimal" value="{{ $line['quantity']??'1' }}" required>
<select aria-label="Unit" class="form-control" name="items[{{ $index }}][unit]">@foreach(\Modules\Billing\Services\SalesConfiguration::UNITS as $unit)<option value="{{ $unit }}" {{ ($line['unit']??'service')===$unit?'selected':'' }}>{{ ucfirst($unit) }}</option>@endforeach</select></div>
<div><label class="invoice-mobile-label" data-rate-label>Unit rate</label><input aria-label="Unit rate" class="form-control" name="items[{{ $index }}][unit_price]" inputmode="decimal" value="{{ $line['unit_price']??'' }}" required></div>
<output class="invoice-line-amount" aria-label="Line amount">-</output>
<button class="btn btn-outline-danger" type="button" data-remove title="Remove item" aria-label="Remove item"><i class="fas fa-trash" aria-hidden="true"></i></button>
<details class="invoice-line-options"><summary>Tax, discount and cargo volume</summary><div class="invoice-line-options-grid">
<label>Discount amount<input class="form-control" name="items[{{ $index }}][discount]" inputmode="decimal" value="{{ $line['discount']??'0' }}" required></label>
<label>Tax category<select class="form-control" data-tax name="items[{{ $index }}][tax_id]" data-selected="{{ $line['tax_id']??'' }}" required><option value="">Choose tax</option></select><input type="hidden" name="items[{{ $index }}][tax_percent]" value="{{ $line['tax_percent']??'0' }}"></label>
<label>Prices<select class="form-control" name="items[{{ $index }}][tax_mode]"><option value="exclusive" {{ ($line['tax_mode']??'exclusive')==='exclusive'?'selected':'' }}>Exclude tax</option><option value="inclusive" {{ ($line['tax_mode']??'')==='inclusive'?'selected':'' }}>Include tax</option></select></label>
<label>Volume (CBM)<input class="form-control" name="items[{{ $index }}][cargo_volume]" inputmode="decimal" value="{{ $line['cargo_volume']??'' }}"></label>
</div></details>
</div>
@endforeach
</div>
