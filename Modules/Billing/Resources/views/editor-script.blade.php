<script>
(() => {
const form=document.querySelector('.sales-editor'),branch=document.getElementById('branch'),lines=document.getElementById('lines'),issued=document.getElementById('issued'),due=document.getElementById('due'),terms=document.getElementById('payment-terms'),agent=document.getElementById('sales-agent'),status=document.getElementById('catalogue-status');
let catalogue=@json($catalogue),generation=0,previewGeneration=0,timer,dirty=false,previewController,saving=false;
const lineValues=()=>[...lines.children].map(row=>Object.fromEntries([...row.querySelectorAll('[name^="items["]')].map(input=>[input.name.match(/\[([^\]]+)\]$/)[1],input.value])));
const option=(label,value)=>{const node=document.createElement('option');node.textContent=label;node.value=value;return node;};
const fill=(select,rows,placeholder,label)=>{const current=select.dataset.selected??select.value;delete select.dataset.selected;select.replaceChildren(option(placeholder,''));rows.forEach(row=>select.append(option(label(row),row.id)));select.value=rows.some(row=>String(row.id)===String(current))?current:'';};
const fillLines=()=>{const taxes=(catalogue?.taxes??[]).filter(tax=>tax.effective_from<=issued.value&&(!tax.effective_until||tax.effective_until>=issued.value));lines.querySelectorAll('[data-tax]').forEach(select=>{fill(select,taxes,'Choose tax',row=>row.label+' ('+(row.basis_points/100)+'%)');if(!select.value&&taxes.length===1)select.value=taxes[0].id;});lines.querySelectorAll('[data-service]').forEach(select=>fill(select,catalogue?.services??[],'Custom service',row=>row.name));};
const render=()=>{document.getElementById('invoice-currency').textContent=catalogue?.currency??'';document.getElementById('issuer-name').textContent=catalogue?.profile?.legal_name??'New World Cargo';document.getElementById('issuer-address').textContent=catalogue?.profile?.address??'';document.getElementById('issuer-payment').textContent=catalogue?.profile?.payment_instructions??'';fill(agent,catalogue?.agents??[],'Unassigned',row=>row.name);fillLines();status.textContent=!branch.value?'Choose a branch.':!catalogue?'Settings could not be loaded.':!catalogue.profile?'Branch billing profile needs configuration before issuing.':!catalogue.taxes.length?'No active tax categories. Configure taxes in Sales settings.':'';};
branch.addEventListener('change',()=>{lines.querySelectorAll('[name$="[unit_price]"],[data-service-search]').forEach(input=>input.value='');lines.querySelectorAll('.service-suggestions').forEach(list=>{list.replaceChildren();list.hidden=true;});});
branch.addEventListener('change',async()=>{const version=++generation;catalogue=null;render();schedule();if(!branch.value)return;status.textContent='Loading services and taxes...';try{const url=new URL(@json(route('billing.catalogue')));url.searchParams.set('branch_id',branch.value);const response=await fetch(url,{headers:{Accept:'application/json'}});if(!response.ok)throw Error();const body=await response.json();if(version!==generation)return;catalogue=body;render();schedule();}catch(error){if(version===generation){status.textContent='Could not load branch settings. ';const retry=document.createElement('button');retry.type='button';retry.className='btn btn-outline-primary btn-sm';retry.textContent='Retry';retry.onclick=()=>branch.dispatchEvent(new Event('change'));status.append(retry);}}});
lines.addEventListener('change',event=>{const row=event.target.closest('.sales-form-row');if(!row)return;if(event.target.matches('[data-service]')){const service=catalogue?.services.find(service=>String(service.id)===event.target.value);if(service){const values={description:service.name,unit:service.unit,unit_price:(service.price_minor/100).toFixed(2),tax_id:String(service.tax_id),tax_mode:service.tax_mode};Object.entries(values).forEach(([field,value])=>{row.querySelector('[name$="['+field+']"]').value=value;});}}const tax=catalogue?.taxes.find(tax=>String(tax.id)===row.querySelector('[data-tax]').value);row.querySelector('[name$="[tax_percent]"]').value=tax?(tax.basis_points/100).toFixed(2):'0';schedule();});
const dates=()=>{if(terms.value==='custom')return;const date=new Date(issued.value+'T12:00:00Z');if(Number.isNaN(date.getTime()))return;date.setUTCDate(date.getUTCDate()+(terms.value==='receipt'?0:Number(terms.value.slice(3))));due.value=date.toISOString().slice(0,10);};
function shippingFields(){lines.querySelectorAll('.sales-service-line').forEach(row=>{const service=catalogue?.services.find(s=>String(s.id)===row.querySelector('[data-service]').value),tariff=service?.shipping_tariff,price=row.querySelector('[name$="[unit_price]"]'),label=row.querySelector('[data-rate-label]');price.readOnly=!!tariff;label.textContent=tariff?'Freight total':'Unit rate';label.classList.toggle('tariff-price-label',!!tariff);row.querySelector('[data-remove]').disabled=lines.children.length===1;if(tariff){row.querySelector('[name$="[unit]"]').value=tariff.unit;if(!price.value)price.value='0';}});}
lines.addEventListener('change',shippingFields);
terms.addEventListener('change',dates);due.addEventListener('change',()=>terms.value='custom');issued.addEventListener('change',()=>{dates();fillLines();schedule();});
const money=(minor,currency)=>currency+' '+(minor/100).toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2});
function clearTotals(){document.querySelectorAll('[data-total],.invoice-line-amount').forEach(node=>node.textContent='-');}
function clearErrors(){
 lines.querySelectorAll('[aria-invalid]').forEach(input=>{input.removeAttribute('aria-invalid');input.removeAttribute('aria-errormessage');});
 lines.querySelectorAll('.line-validation').forEach(node=>node.remove());
}
function showErrors(errors){
 Object.entries(errors??{}).forEach(([key,messages])=>{
  const match=key.match(/^items\.(\d+)\.(\w+)$/),row=match?lines.children[Number(match[1])]:null;
  if(!row)return;
  const input=row.querySelector('[name$="['+match[2]+']"]');
  if(input){input.setAttribute('aria-invalid','true');input.setAttribute('aria-errormessage','line-error-'+match[1]+'-'+match[2]);}
  const message=document.createElement('p');message.id='line-error-'+match[1]+'-'+match[2];message.className='line-validation text-danger';message.setAttribute('role','alert');message.textContent=[].concat(messages).join(' ');row.append(message);
 });
}
function retryMessage(text){
 const notice=document.getElementById('totals-status');notice.textContent=text+' ';
 const retry=document.createElement('button');retry.type='button';retry.className='btn btn-outline-primary btn-sm';retry.textContent='Retry';retry.onclick=schedule;notice.append(retry);
}
async function preview(version){
 const notice=document.getElementById('totals-status'),totals=document.getElementById('live-totals'),controller=new AbortController();
 previewController=controller;
 const timeout=setTimeout(()=>controller.abort(),15000);
 try{
  shippingFields();
  const items=lineValues();
  const response=await fetch(@json(route('billing.preview')),{method:'POST',signal:controller.signal,headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},body:JSON.stringify({branch_id:branch.value,issue_date:issued.value,items})});
  if(version!==previewGeneration)return;
  if([401,419].includes(response.status)){retryMessage('Your session has expired. Sign in in another tab, then retry. Your edits are still here.');return;}
  const body=await response.json();
  if(version!==previewGeneration)return;
  if(!response.ok){clearTotals();showErrors(body.errors);if(body.errors)notice.textContent=Object.values(body.errors).flat()[0];else retryMessage('Could not update the total. Your edits are still here.');return;}
  notice.textContent=body.tax_pending?'Estimate only. Select a configured tax category before saving or issuing.':body.price_pending?'Enter the remaining item prices to complete the total.':'';
  body.items.forEach((item,index)=>{const row=lines.children[index];if(!row)return;row.querySelector('.invoice-line-amount').textContent=money(item.total_minor,body.currency);if(item.pricing_snapshot)row.querySelector('[name$="[unit_price]"]').value=(item.unit_price_minor/100).toFixed(2);});
  totals.querySelector('dt.sales-grand').textContent=body.tax_pending||body.price_pending?'Estimated total':'Grand total';
  totals.querySelectorAll('[data-total]').forEach(node=>node.textContent=node.dataset.total==='tax_minor'&&body.tax_pending?'Not set':money(body[node.dataset.total],body.currency));
 }catch(error){if(version===previewGeneration){clearTotals();retryMessage('Could not update the total. Your edits are still here.');}}
 finally{clearTimeout(timeout);if(version===previewGeneration){totals.classList.remove('is-calculating');totals.setAttribute('aria-busy','false');}}
}
function schedule(){clearTimeout(timer);const version=++previewGeneration;previewController?.abort();const totals=document.getElementById('live-totals');clearErrors();clearTotals();totals.classList.remove('is-calculating');totals.setAttribute('aria-busy','false');if(!branch.value){document.getElementById('totals-status').textContent='Choose a branch to calculate the total.';return;}totals.classList.add('is-calculating');totals.setAttribute('aria-busy','true');document.getElementById('totals-status').textContent='Calculating...';timer=setTimeout(()=>preview(version),150);}
form.addEventListener('input',event=>{dirty=true;if(event.target===lines){fillLines();shippingFields();}if(event.target===lines||event.target.closest('#lines')&&!event.target.matches('[data-service-search]'))schedule();});form.addEventListener('change',event=>{dirty=true;if(event.target.closest('#lines')||event.target===branch||event.target===issued)schedule();});lines.addEventListener('click',event=>{if(event.target.closest('[data-remove]')){dirty=true;shippingFields();schedule();}});
window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
const saveButtons=()=>document.querySelectorAll('.sales-editor button[type="submit"],button[form="sales-document-editor"]');
form.addEventListener('submit',async event=>{
 event.preventDefault();if(saving)return;
 const notice=document.getElementById('save-status'),payload=new FormData(form);
 for(const name of [...payload.keys()])if(name.startsWith('items['))payload.delete(name);
 lineValues().forEach((item,index)=>Object.entries(item).forEach(([key,value])=>payload.append('items['+index+']['+key+']',value)));
 saving=true;form.inert=true;form.setAttribute('aria-busy','true');saveButtons().forEach(button=>button.disabled=true);notice.textContent='Saving...';
 const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),20000);
 try{
  const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:payload,signal:controller.signal});
  const body=await response.json().catch(()=>({}));
  if(!response.ok){
   dirty=true;
   notice.textContent=[401,419].includes(response.status)?'Your session has expired. Sign in in another tab, then save again. Your edits are still here.':body.errors?Object.values(body.errors).flat().join(' '):'Could not save. Your edits are still here. Please try again.';
   clearErrors();showErrors(body.errors);return;
  }
  if(!body.url)throw Error('Missing document location');
  notice.textContent='Saved';dirty=false;window.location.assign(body.url);
 }catch(error){dirty=true;notice.textContent='Could not confirm the save. Your edits are still here. Retry Save draft to check and save.';}
 finally{clearTimeout(timeout);saving=false;form.inert=false;form.setAttribute('aria-busy','false');saveButtons().forEach(button=>button.disabled=false);}
});
window.addEventListener('pageshow',()=>{form.inert=false;saveButtons().forEach(button=>button.disabled=false);});
form.addEventListener('invalid',event=>{let parent=event.target.parentElement;while(parent&&parent!==form){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}},true);
lines.addEventListener('focusin',event=>{if(event.target.matches('input[inputmode="decimal"]'))event.target.dataset.beforeEdit=event.target.value;});
lines.addEventListener('keydown',event=>{
 if(!event.target.matches('input[inputmode="decimal"]'))return;
 if(event.key==='Enter'){event.preventDefault();event.target.blur();}
 if(event.key==='Escape'){event.preventDefault();event.target.value=event.target.dataset.beforeEdit??event.target.value;event.target.dispatchEvent(new Event('input',{bubbles:true}));event.target.blur();}
});
function serviceResults(input){
 const row=input.closest('.sales-service-line'),list=row.querySelector('.service-suggestions'),query=input.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
 list.replaceChildren();list.hidden=false;
 const choices=(catalogue?.services??[]).filter(service=>query.every(word=>service.name.toLowerCase().includes(word))).slice(0,20);
 function choice(label,value){const button=document.createElement('button');button.type='button';button.className='list-group-item list-group-item-action';button.textContent=label;button.onclick=()=>{const select=row.querySelector('[data-service]');select.value=value;input.value=value?label:'';list.hidden=true;select.dispatchEvent(new Event('change',{bubbles:true}));row.querySelector('[name$="[description]"]').focus();};list.append(button);}
 choices.forEach(service=>choice(service.name,String(service.id)));choice('Custom service','');
}
lines.addEventListener('input',event=>{if(event.target.matches('[data-service-search]'))serviceResults(event.target);});
lines.addEventListener('focusin',event=>{if(event.target.matches('[data-service-search]'))serviceResults(event.target);});
lines.addEventListener('focusout',event=>{const wrap=event.target.closest('.service-search-wrap');if(wrap&&!wrap.contains(event.relatedTarget))wrap.querySelector('.service-suggestions').hidden=true;});
lines.addEventListener('keydown',event=>{const wrap=event.target.closest('.service-search-wrap');if(!wrap)return;const list=wrap.querySelector('.service-suggestions'),buttons=[...list.querySelectorAll('button')],input=wrap.querySelector('[data-service-search]');if(event.key==='Escape'){list.hidden=true;input.focus();list.hidden=true;event.preventDefault();}else if(['ArrowDown','ArrowUp'].includes(event.key)){event.preventDefault();if(list.hidden)serviceResults(input);const available=[...list.querySelectorAll('button')],index=available.indexOf(document.activeElement);available[(index+(event.key==='ArrowDown'?1:available.length-1))%available.length]?.focus();}else if(event.key==='Enter'&&event.target===input){event.preventDefault();buttons[0]?.click();}});
document.addEventListener('pointerdown',event=>{document.querySelectorAll('.service-search-wrap').forEach(wrap=>{if(!wrap.contains(event.target))wrap.querySelector('.service-suggestions').hidden=true;});});
render();
shippingFields();
lines.querySelectorAll('[data-service-search]').forEach(input=>{const select=input.closest('.sales-service-line').querySelector('[data-service]');input.value=catalogue?.services.find(service=>String(service.id)===select.value)?.name??'';});
if(branch.value)schedule();
})();
</script>
