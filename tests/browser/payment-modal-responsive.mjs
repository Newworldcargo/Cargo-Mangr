import {chromium} from '/home/newworldcargo/web/staging/new-world-cargo-app/node_modules/@playwright/test/index.mjs';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const root='/home/newworldcargo/web/admin.newworldcargo.com/public_html/public';
const browser=await chromium.launch({args:['--no-sandbox']});
try {for (const width of [360,390,768,1440]) {
 const page=await browser.newPage({viewport:{width,height:850}});
 let bill = null, intent = null, cashOverride = false;
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const url=new URL(route.request().url());
  if(url.pathname==='/uat')return route.fulfill({contentType:'text/html',body:`<!doctype html><html><head><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/assets/lte/css/adminlte.css"><link rel="stylesheet" href="/assets/lte/plugins/fontawesome/css/all.min.css"><script src="https://cdn.tailwindcss.com"></script></head><body style="background:#cbd5e1">${readFileSync('/tmp/nwc-full-payment-modal.html','utf8')}</body></html>`});
  if(url.hostname==='cdn.tailwindcss.com')return route.continue();
  if(url.pathname.includes('shipment-online-payment')) {
   if(url.pathname.endsWith('/cash-override')) {
    const body=route.request().postDataJSON();assert.equal(body.intentId,intent.id);assert.equal(body.acknowledged,true);assert(body.reason.length>=5);cashOverride=true;
   }
   return route.fulfill({json:{data:intent,paid:false,available:true,canPrompt:!intent||intent.status==='failed',canSwitchOffline:!intent||intent.status==='failed'||cashOverride,canOverrideForCash:intent?.status==='processing',cashOverride,bill}});
  }
  if(url.pathname.endsWith('.css')||url.pathname.includes('/webfonts/'))return route.fulfill({body:readFileSync(root+url.pathname),contentType:url.pathname.endsWith('.css')?'text/css':'font/woff2'});
  return route.abort();
 });
 await page.goto('https://admin.newworldcargo.com/uat',{waitUntil:'networkidle'});
 await page.locator('#markPaidModal').evaluate(el=>{el.classList.add('show');el.style.display='block';el.removeAttribute('aria-hidden');});
 assert.equal((await page.getByRole('tab').first().innerText()).trim(),'Online');
 await page.getByRole('tab',{name:'Online',exact:true}).click();
 await page.getByRole('radio',{name:'Airtel',exact:true}).check();
 await page.getByLabel('Mobile money number').fill('0972827372');
 await page.screenshot({path:`/tmp/nwc-full-payment-modal-${width}.png`,fullPage:true});
 const dialog=await page.locator('#markPaidModal .modal-dialog').boundingBox();
 assert(dialog.x>=0&&dialog.x+dialog.width<=width);
 if(width===1440)assert(dialog.width===1040);
 const footer=await page.locator('#markPaidModal .modal-footer').boundingBox();
 assert(footer.y>=0&&footer.y+footer.height<=850);
 assert(await page.locator('.modal-footer #finalTotal').isVisible());
 assert.equal(await page.locator('#finalTotal').innerText(),'K1,000.00');
 bill={total:875.50,currency:'ZMW'};
 await page.getByRole('button',{name:'Check payment status'}).click();
 await page.locator('#payment-footer-confirmed-total').waitFor({state:'visible'});
 assert.equal(await page.locator('#payment-footer-confirmed-total').innerText(),'875.50');
 assert(!(await page.locator('#finalTotal').isVisible()));
 intent={id:'a4d3aa13-60c6-4ffb-a286-10b1c96d3157',status:'processing',network:'airtel'};
 await page.getByRole('button',{name:'Switch to cash'}).click();
 await page.getByText(/Lipila has not confirmed the cancellation/).waitFor();
 assert(await page.getByRole('tab',{name:'Offline',exact:true}).isDisabled());
 intent.status='failed';
 await page.getByRole('button',{name:'Check payment status'}).click();
 await page.locator('#offline-payment-panel').waitFor({state:'visible'});
 assert.equal(await page.locator('#payment-rows select').first().inputValue(),'cash_payment');
 intent.status='processing';
 await page.getByRole('tab',{name:'Online',exact:true}).click();
 await page.getByRole('button',{name:'Switch to cash'}).click();
 await page.getByRole('button',{name:'Confirm switch to cash',exact:true}).click();
 await page.getByText('Enter a reason and confirm the agreement with the customer.').waitFor();
 await page.getByLabel('Reason for switching to cash').fill('Customer declined the prompt and agreed to pay cash');
 await page.getByLabel('The customer and I agreed to pay cash and not approve the online request.').check();
 await page.getByRole('button',{name:'Confirm switch to cash',exact:true}).click();
 await page.locator('#offline-payment-panel').waitFor({state:'visible'});
 assert(cashOverride);
 await page.getByRole('tab',{name:'Online',exact:true}).click();
 await page.getByRole('button',{name:'Check payment status'}).click();
 assert(await page.getByRole('button',{name:'Send payment prompt',exact:true}).isDisabled());
 assert(await page.locator('#markPaidModal .modal-body').evaluate(el=>el.scrollWidth<=el.clientWidth+1));
 await page.getByRole('tab',{name:'Offline',exact:true}).click();
 assert(await page.locator('#finalTotal').isVisible());
 await page.locator('input[name="payment_amount[]"]').first().fill('700');
 assert.equal(await page.locator('input[name="payment_amount[]"]').first().inputValue(),'700');
 assert(await page.locator('#confirmMarkPaidBtn').isVisible());
 assert.deepEqual(errors,[]);
 console.log(JSON.stringify({width,dialogWidth:dialog.width,footerVisible:true,noOverflow:true,tabs:true,offlineEditable:true,pendingCashBlocked:true,failedSwitchesToCash:true,acknowledgedOverride:true,noNewPrompt:true}));
 await page.close();
}} finally {await browser.close();}
