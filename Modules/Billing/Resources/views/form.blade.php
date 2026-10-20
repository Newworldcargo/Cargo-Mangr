@extends('cargo::adminLte.layouts.master')
@section('pageTitle', ($document ? 'Edit ' : 'New ') . ($type === 'invoices' ? 'invoice' : 'quotation'))
@section('content')
@include('billing::styles')
@include('billing::compact-document-style')
<div class="container-fluid py-3 sales-page compact-sales-page"><header class="sales-page-heading"><a class="sales-back" href="{{ route('billing.' . $type . '.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ ucfirst($type) }}</a><span class="sales-status">Draft</span><button class="btn btn-primary" type="submit" form="sales-document-editor"><i class="fas fa-save" aria-hidden="true"></i> Save draft</button></header>
@if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form id="sales-document-editor" class="sales-editor compact-document" method="post" action="{{ $document ? route('billing.documents.update', ['type' => $type, 'id' => $document->id]) : route('billing.documents.store', ['type' => $type]) }}">
@csrf @if ($document) @method('PUT') @endif
<div class="nav nav-tabs sales-tabs" role="tablist" aria-label="Document type">
<button class="nav-link active" type="button" role="tab" id="plain-tab" aria-selected="true" aria-controls="shipment-fields" data-document-mode="plain"><i class="fas fa-file-alt" aria-hidden="true"></i> Plain {{ $type === 'invoices' ? 'invoice' : 'quotation' }}</button>
<button class="nav-link" type="button" role="tab" id="shipment-tab" aria-selected="false" aria-controls="shipment-fields" data-document-mode="shipment"><i class="fas fa-box" aria-hidden="true"></i> Shipment details</button>
</div>
<input type="hidden" id="document-mode" name="document_mode" value="{{ old('document_mode', $shipmentDetails ? 'shipment' : 'plain') }}">
<div id="existing-shipment" class="mb-4" hidden>
<label for="shipment-search">Already booked? Find the shipment</label><input type="search" id="shipment-search" class="form-control" placeholder="Search shipment reference" autocomplete="off"><div id="shipment-results" class="list-group" aria-live="polite"></div>
</div>
<input type="hidden" name="request_key" value="{{ old('request_key', $key) }}"><input type="hidden" name="revision" value="{{ old('revision', $document->revision ?? 1) }}">
<header class="invoice-heading">
<div class="invoice-company"><div class="invoice-brand"><img src="{{ asset('assets/lte/cargo-logo-email.png') }}" width="150" height="60" alt="New World Cargo"></div>
<strong id="issuer-name">{{ $catalogue['profile']->legal_name??'New World Cargo' }}</strong><p id="issuer-address">{{ $catalogue['profile']->address??'' }}</p>
<label for="branch">Issuing branch</label><select id="branch" class="form-control" name="branch_id" required><option value="">Choose a branch</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" {{ (string) old('branch_id', $document->branch_id ?? '') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>@endforeach</select>
</div>
<div class="invoice-heading-meta"><h1>{{ $type==='invoices'?'INVOICE':'QUOTATION' }}</h1><p>{{ $document->number??'DRAFT' }}</p>
<label for="issued">Issue date<input class="form-control" id="issued" type="date" name="issue_date" value="{{ old('issue_date', $document->issue_date ?? date('Y-m-d')) }}" required></label>
<label for="due">{{ $type === 'invoices' ? 'Due date' : 'Valid until' }}<input class="form-control" id="due" type="date" name="due_date" value="{{ old('due_date', $document->due_date ?? now()->addDays(14)->toDateString()) }}" required></label>
<small>Prepared by {{ auth()->user()->name }}</small></div></header>
<div class="invoice-parties">
<section><h2>Bill to</h2><div class="invoice-customer-search"><label class="sr-only" for="customer-search">Customer</label><input class="form-control" id="customer-search" name="customer_name" type="search" autocomplete="off" placeholder="Search customer name or email" value="{{ old('customer_name',$document ? (json_decode($document->customer_snapshot, true)['name'] ?? '') : '') }}"><input type="hidden" id="client-id" name="client_id" value="{{ old('client_id', $document->client_id ?? '') }}"><div id="customer-results" class="list-group" aria-live="polite"></div></div>
<label class="sr-only" for="billing-address">Customer billing address</label><textarea id="billing-address" class="form-control" name="details[customer_address]" rows="2" maxlength="1000" placeholder="Billing address">{{ $details['customer_address']??'' }}</textarea>
</section>
<section><h2>Delivery / Reference</h2><label for="detail-order_reference">Order / purchase-order reference<input id="detail-order_reference" class="form-control" name="details[order_reference]" maxlength="120" value="{{ $details['order_reference']??'' }}"></label><label for="payment-terms">Payment terms<select class="form-control" id="payment-terms" name="details[payment_terms]">@foreach(['custom'=>'Custom dates','receipt'=>'Due on receipt','net7'=>'Net 7 days','net14'=>'Net 14 days','net30'=>'Net 30 days','net60'=>'Net 60 days'] as $value=>$label)<option value="{{ $value }}" {{ ($details['payment_terms']??'custom')===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></label></section>
</div>
<details class="invoice-extra"><summary>Sales agent, tax details and service period</summary>@include('billing::document-fields')</details>
@include('billing::shipment-fields')
@include('billing::service-lines')
<button id="add-line" class="btn btn-link" type="button"><i class="fas fa-plus" aria-hidden="true"></i> Add item</button>
<div class="sales-preview-summary"><p id="totals-status" role="status">Add services to calculate the total.</p><dl class="sales-totals" id="live-totals" aria-live="polite" aria-atomic="true"><dt>Subtotal excluding tax</dt><dd data-total="subtotal_minor">-</dd><dt>Discount excluding tax</dt><dd data-total="discount_minor">-</dd><dt>Tax</dt><dd data-total="tax_minor">-</dd><dt class="sales-grand">Grand total</dt><dd class="sales-grand" data-total="total_minor">-</dd></dl></div>
<div class="invoice-bottom-fields"><section><label for="terms">Terms and payment instructions</label><textarea id="terms" class="form-control" name="terms" maxlength="5000" rows="2">{{ old('terms', $document->terms ?? '') }}</textarea><p id="issuer-payment">{{ $catalogue['profile']->payment_instructions??'' }}</p></section><section><label for="customer-notes">Customer notes</label><textarea id="customer-notes" class="form-control" name="details[customer_notes]" rows="2" maxlength="2000">{{ $details['customer_notes']??'' }}</textarea></section></div>
<details class="invoice-extra"><summary>Internal notes</summary><label class="sr-only" for="internal-notes">Internal notes</label><textarea id="internal-notes" class="form-control" name="details[internal_notes]" rows="2" maxlength="2000">{{ $details['internal_notes']??'' }}</textarea></details>
<footer class="invoice-footer"><span>Thank you for choosing New World Cargo.</span><span>Authorised by ____________________</span></footer>
<div class="sales-toolbar"><a href="{{ route('billing.' . $type . '.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary" type="submit">Save draft</button></div></form></div>
@if ((int) auth()->user()->role === 1 || auth()->user()->can('create-customers'))
<div class="modal fade sales-modal" id="add-customer-modal" tabindex="-1" role="dialog" aria-labelledby="add-customer-title" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
<form id="add-customer-form">@csrf
<div class="modal-header"><h2 class="modal-title h5" id="add-customer-title">Add customer</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
<div class="modal-body"><div id="add-customer-errors" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
<div class="row">@foreach (['first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email', 'phone' => 'Phone number', 'phone_2' => 'Second phone number (optional)'] as $field => $label)
<div class="{{ in_array($field, ['first_name','last_name']) ? 'col-sm-6' : 'col-12' }} mb-3"><label for="new-customer-{{ $field }}">{{ $label }}</label><input id="new-customer-{{ $field }}" name="{{ $field }}" class="form-control" type="{{ $field === 'email' ? 'email' : (str_starts_with($field, 'phone') ? 'tel' : 'text') }}" maxlength="{{ $field === 'email' ? 191 : (str_starts_with($field, 'phone') ? 40 : 80) }}" {{ $field !== 'phone_2' ? 'required' : '' }} @if(str_starts_with($field, 'phone')) placeholder="e.g. +260971234567" @endif></div>
@endforeach</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="save-customer">Add customer</button></div>
</form></div></div></div>
@endif
<script>
(() => {
const lines=document.getElementById('lines');let index={{ count($lines) }};
document.getElementById('add-line').onclick=()=>{if(lines.children.length>=100)return;const row=lines.firstElementChild.cloneNode(true);row.querySelectorAll('input,select').forEach(input=>{input.name=input.name.replace(/items\[\d+\]/,'items['+index+']');input.value=input.name.includes('[quantity]')?'1':input.name.includes('[discount]')||input.name.includes('[tax_percent]')?'0':input.name.includes('[unit]')?'service':input.name.includes('[tax_mode]')?'exclusive':'';delete input.dataset.selected;});index++;lines.append(row);lines.dispatchEvent(new Event('input',{bubbles:true}));};
lines.addEventListener('click',event=>{const button=event.target.closest('[data-remove]');if(button&&lines.children.length>1)button.closest('.sales-form-row').remove();});
const search=document.getElementById('customer-search'),results=document.getElementById('customer-results'),selected=document.getElementById('client-id'),branch=document.getElementById('branch');let timer,generation=0;
const mode=document.getElementById('document-mode'),shipping=document.getElementById('shipment-fields'),tabs=[...document.querySelectorAll('[data-document-mode]')],packages=document.getElementById('shipment-packages');let packageIndex=packages.children.length;
const setMode=value=>{mode.value=value;shipping.hidden=value!=='shipment';shipping.disabled=shipping.hidden;document.getElementById('existing-shipment').hidden=shipping.hidden;tabs.forEach(tab=>{const active=tab.dataset.documentMode===value;tab.classList.toggle('active',active);tab.setAttribute('aria-selected',String(active));tab.tabIndex=active?0:-1;});};
tabs.forEach((tab,index)=>{tab.onclick=()=>setMode(tab.dataset.documentMode);tab.onkeydown=event=>{if(['ArrowLeft','ArrowRight','Home','End'].includes(event.key)){event.preventDefault();const next=event.key==='Home'?tabs[0]:event.key==='End'?tabs[1]:tabs[1-index];next.click();next.focus();}};});setMode(mode.value);
const totalWeight=()=>{let total=0;packages.querySelectorAll('[data-package]').forEach(row=>{total+=(Number(row.querySelector('[name$="[weight]"]').value)||0)*(Number(row.querySelector('[name$="[quantity]"]').value)||0);});document.getElementById('shipment-weight').textContent='Total weight: '+total.toFixed(3)+' kg';packages.querySelectorAll('[data-remove-package]').forEach(button=>button.disabled=packages.children.length===1);};
document.getElementById('add-package').onclick=()=>{if(packages.children.length>=100)return;const row=packages.firstElementChild.cloneNode(true);row.querySelectorAll('input').forEach(input=>{input.name=input.name.replace(/\[packages\]\[\d+\]/,'[packages]['+packageIndex+']');input.value=input.name.endsWith('[quantity]')?'1':'';});packageIndex++;packages.append(row);totalWeight();};
packages.oninput=totalWeight;packages.onclick=event=>{const button=event.target.closest('[data-remove-package]');if(button&&packages.children.length>1){button.closest('[data-package]').remove();totalWeight();}};totalWeight();
const shipmentSearch=document.getElementById('shipment-search'),shipmentResults=document.getElementById('shipment-results');let shipmentTimer,shipmentGeneration=0;
branch.addEventListener('change',()=>{shipmentGeneration++;shipmentSearch.value='';shipmentResults.replaceChildren();});
shipmentSearch.oninput=()=>{clearTimeout(shipmentTimer);const version=++shipmentGeneration;shipmentResults.replaceChildren();if(shipmentSearch.value.trim().length<2)return;if(!branch.value){shipmentResults.textContent='Choose a branch first.';return;}shipmentTimer=setTimeout(async()=>{shipmentResults.textContent='Searching...';try{const url=new URL(@json(route('billing.shipments')));url.searchParams.set('q',shipmentSearch.value);url.searchParams.set('branch_id',branch.value);const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw Error();const rows=await response.json();if(version!==shipmentGeneration)return;shipmentResults.replaceChildren();if(!rows.length)shipmentResults.textContent='No matching shipments in this branch.';rows.forEach(row=>{const link=document.createElement('a');link.className='list-group-item list-group-item-action';link.href=row.url;link.target='_blank';link.rel='noopener';link.textContent=row.code+' - Open shipment and bill (new tab)';shipmentResults.append(link);});}catch(error){if(version===shipmentGeneration)shipmentResults.textContent='Shipment search is unavailable. Please try again.';}},300);};
const customerForm=document.getElementById('add-customer-form');
if(customerForm){
 const add=document.createElement('button');add.type='button';add.className='btn btn-outline-primary mt-2';add.textContent='Add customer';add.id='add-customer';search.parentElement.insertAdjacentElement('afterend',add);
 let customerBranch='',saving=false;
 add.onclick=()=>{if(!branch.value){results.textContent='Choose a branch first.';branch.focus();return;}customerBranch=branch.value;window.jQuery('#add-customer-modal').off('.salesCustomer').on('shown.bs.modal.salesCustomer',()=>customerForm.elements.first_name.focus()).on('hide.bs.modal.salesCustomer',event=>{if(saving)event.preventDefault();}).modal('show');};
 customerForm.onsubmit=async event=>{
  event.preventDefault();if(saving)return;saving=true;
  const button=document.getElementById('save-customer'),errors=document.getElementById('add-customer-errors');button.disabled=true;button.textContent='Adding...';errors.classList.add('d-none');
  try{
   const data=new FormData(customerForm);data.set('branch_id',customerBranch);data.set('type',@json($type));
   const response=await fetch(@json(route('billing.customers.store')),{method:'POST',headers:{Accept:'application/json'},body:data});
   const body=await response.json().catch(()=>({}));
   if(!response.ok){const messages=body.errors?Object.values(body.errors).flat():[response.status===403?'You do not have permission to add customers.':response.status===419?'Your session has expired. Open a new tab to sign in, then try again.':response.status===409?'This contact is being saved. Please try again shortly.':'We could not add this customer. Search for them before trying again.'];errors.replaceChildren();messages.forEach(message=>{const item=document.createElement('div');item.textContent=message;errors.append(item);});errors.classList.remove('d-none');errors.focus();return;}
   generation++;clearTimeout(timer);results.replaceChildren();if(branch.value===String(body.branch_id)){selected.value=body.id;search.value=body.name;}customerForm.reset();saving=false;window.jQuery('#add-customer-modal').modal('hide');search.focus();
  }catch(error){errors.textContent='We could not confirm whether the customer was added. Search for them before trying again.';errors.classList.remove('d-none');errors.focus();}
  finally{saving=false;button.disabled=false;button.textContent='Add customer';}
 };
}
branch.onchange=()=>{generation++;selected.value='';search.value='';results.replaceChildren();};
search.addEventListener('keydown',event=>{if(event.key==='ArrowDown'){event.preventDefault();results.querySelector('button')?.focus();}if(event.key==='Enter'){event.preventDefault();results.querySelector('button')?.click();}if(event.key==='Escape'){generation++;results.replaceChildren();}});
results.addEventListener('keydown',event=>{const buttons=[...results.querySelectorAll('button')],index=buttons.indexOf(event.target);if(['ArrowDown','ArrowUp'].includes(event.key)&&buttons.length){event.preventDefault();buttons[(index+(event.key==='ArrowDown'?1:buttons.length-1))%buttons.length].focus();}if(event.key==='Escape'){event.preventDefault();results.replaceChildren();search.focus();}});
search.oninput=()=>{selected.value='';clearTimeout(timer);const version=++generation;results.replaceChildren();if(search.value.trim().length<2)return;if(!branch.value){results.textContent='Choose a branch first.';return;}timer=setTimeout(async()=>{results.textContent='Searching...';try{const url=new URL(@json(route('billing.customers')));url.searchParams.set('q',search.value);url.searchParams.set('branch_id',branch.value);const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw Error();const rows=await response.json();if(version!==generation)return;results.replaceChildren();if(!rows.length)results.textContent='No matching customers.';rows.forEach(customer=>{const button=document.createElement('button');button.type='button';button.className='list-group-item list-group-item-action';button.textContent=customer.name+' '+(customer.email||'');button.onclick=()=>{generation++;selected.value=customer.id;search.value=customer.name;results.replaceChildren();};results.append(button);});}catch(error){if(version===generation)results.textContent='Search could not be completed. Please try again.';}},300);};
})();
</script>
@include('billing::editor-script')
@endsection
