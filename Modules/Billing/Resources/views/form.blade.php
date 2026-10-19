@extends('cargo::adminLte.layouts.master')
@section('pageTitle', ($document ? 'Edit ' : 'New ') . ($type === 'invoices' ? 'invoice' : 'quotation'))
@section('content')
@include('billing::styles')
<div class="container-fluid py-3 sales-page"><a class="sales-back" href="{{ route('billing.' . $type . '.index') }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> {{ ucfirst($type) }}</a><header class="sales-page-heading"><h1>{{ $document ? 'Edit' : 'New' }} {{ $type === 'invoices' ? 'invoice' : 'quotation' }}</h1><span class="sales-status">Draft</span></header>
@if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form class="sales-editor" method="post" action="{{ $document ? route('billing.documents.update', ['type' => $type, 'id' => $document->id]) : route('billing.documents.store', ['type' => $type]) }}">
@csrf @if ($document) @method('PUT') @endif
<input type="hidden" name="request_key" value="{{ old('request_key', $key) }}"><input type="hidden" name="revision" value="{{ old('revision', $document->revision ?? 1) }}">
<div class="row my-4"><div class="col-md-6 mb-3"><label for="branch">Branch</label><select id="branch" class="form-control" name="branch_id" required><option value="">Choose a branch</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" {{ (string) old('branch_id', $document->branch_id ?? '') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>@endforeach</select></div>
<div class="col-md-6 mb-3"><label for="customer-search">Customer</label><input class="form-control" id="customer-search" type="search" autocomplete="off" placeholder="Search name or email" value="{{ $document ? (json_decode($document->customer_snapshot, true)['name'] ?? '') : '' }}"><input type="hidden" id="client-id" name="client_id" value="{{ old('client_id', $document->client_id ?? '') }}"><div id="customer-results" class="list-group" aria-live="polite"></div></div>
<div class="col-md-6 mb-3"><label for="issued">Issue date</label><input class="form-control" id="issued" type="date" name="issue_date" value="{{ old('issue_date', $document->issue_date ?? date('Y-m-d')) }}" required></div>
<div class="col-md-6 mb-3"><label for="due">{{ $type === 'invoices' ? 'Due date' : 'Valid until' }}</label><input class="form-control" id="due" type="date" name="due_date" value="{{ old('due_date', $document->due_date ?? now()->addDays(14)->toDateString()) }}" required></div></div>
<h2 class="h5">Items</h2><div class="sales-lines-editor" id="lines">@foreach ($lines as $index => $line)<div class="sales-form-row">@foreach (['description' => 'Description', 'quantity' => 'Quantity', 'unit_price' => 'Unit price', 'discount' => 'Discount amount', 'tax_percent' => 'Tax %'] as $field => $label)<label>{{ $label }}<input class="form-control" name="items[{{ $index }}][{{ $field }}]" value="{{ $line[$field] ?? '' }}" {{ $field === 'description' ? '' : 'inputmode=decimal' }} required></label>@endforeach<button class="btn btn-outline-danger align-self-end mb-2" type="button" data-remove title="Remove item" aria-label="Remove item"><i class="fas fa-trash" aria-hidden="true"></i></button></div>@endforeach</div>
<button id="add-line" class="btn btn-outline-primary mb-4" type="button"><i class="fas fa-plus" aria-hidden="true"></i> Add item</button>
<div class="mb-4"><label for="terms">Terms and payment instructions</label><textarea id="terms" class="form-control" name="terms" maxlength="5000" rows="4">{{ old('terms', $document->terms ?? '') }}</textarea></div>
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
document.getElementById('add-line').onclick=()=>{if(lines.children.length>=100)return;const row=lines.firstElementChild.cloneNode(true);row.querySelectorAll('input').forEach(input=>{input.name=input.name.replace(/items\[\d+\]/,'items['+index+']');input.value=input.name.includes('[quantity]')?'1':input.name.includes('[discount]')||input.name.includes('[tax_percent]')?'0':'';});index++;lines.append(row);};
lines.addEventListener('click',event=>{const button=event.target.closest('[data-remove]');if(button&&lines.children.length>1)button.parentElement.remove();});
const search=document.getElementById('customer-search'),results=document.getElementById('customer-results'),selected=document.getElementById('client-id'),branch=document.getElementById('branch');let timer,generation=0;
const customerForm=document.getElementById('add-customer-form');
if(customerForm){
 const add=document.createElement('button');add.type='button';add.className='btn btn-outline-primary mt-2';add.textContent='Add customer';add.id='add-customer';search.parentElement.append(add);
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
search.oninput=()=>{selected.value='';clearTimeout(timer);const version=++generation;results.replaceChildren();if(search.value.trim().length<2)return;if(!branch.value){results.textContent='Choose a branch first.';return;}timer=setTimeout(async()=>{results.textContent='Searching...';try{const url=new URL(@json(route('billing.customers')));url.searchParams.set('q',search.value);url.searchParams.set('branch_id',branch.value);const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw Error();const rows=await response.json();if(version!==generation)return;results.replaceChildren();if(!rows.length)results.textContent='No matching customers.';rows.forEach(customer=>{const button=document.createElement('button');button.type='button';button.className='list-group-item list-group-item-action';button.textContent=customer.name+' '+(customer.email||'');button.onclick=()=>{generation++;selected.value=customer.id;search.value=customer.name;results.replaceChildren();};results.append(button);});}catch(error){if(version===generation)results.textContent='Search could not be completed. Please try again.';}},300);};
})();
</script>
@endsection
