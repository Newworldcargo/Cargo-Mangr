import {chromium} from '/home/newworldcargo/web/staging/new-world-cargo-app/node_modules/@playwright/test/index.mjs';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';
const root=process.cwd();
const partial='<link rel="stylesheet" href="/assets/lte/plugins/fontawesome/css/all.min.css">'+readFileSync(root+'/Modules/Cargo/Resources/views/adminLte/pages/shipments/staff-online-payment.blade.php','utf8')
 .replace("@json(route('shipments.online-payment.store', $shipment->id))",JSON.stringify('/shipment-online-payment/41533'))
 .replace('@json(csrf_token())',JSON.stringify('test-only'))
 .replace("{{ asset('css/shipment-payment-modal.css') }}", '/css/shipment-payment-modal.css');
const browser=await chromium.launch({args:['--no-sandbox']});
try {for(const width of [360,390,1440]) {
 const page=await browser.newPage({viewport:{width,height:950}});let posts=0, intent=null, failed=false;
 const errors=[]; page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://admin.newworldcargo.com/**',async route=>{
  const path=new URL(route.request().url()).pathname;
  if(path==='/uat')return route.fulfill({contentType:'text/html',body:`<html><head><meta name="viewport" content="width=device-width"><link rel="stylesheet" href="/web/assets/vendor/bootstrap/css/bootstrap.min.css"></head><body style="background:#f1f3f7"><main id="markPaidModal" style="max-width:650px;margin:24px auto;padding:16px"><section style="padding:24px;background:white;border-radius:8px;border:1px solid #e2e8f0"><h6 style="margin-bottom:20px">PAYMENT METHOD</h6>${partial}<div id="offline-payment-panel">Offline payment<input name="payment_amount[]" value="100"></div></section><div hidden><select id="discountType"><option value="">No discount</option></select><input id="discountValue" value="0"><div id="charge-rows"></div><button id="addChargeBtn">Add charge</button><span id="finalTotal">K100.00</span></div><button id="confirmMarkPaidBtn">Confirm paid</button></main></body></html>`});
  if(path.endsWith('.css'))return route.fulfill({contentType:'text/css',body:readFileSync(root+'/public'+path)});
  if(path.includes('/webfonts/'))return route.fulfill({contentType:'font/woff2',body:readFileSync(root+'/public'+path)});
  if(route.request().method()==='POST'){
   posts++; const payload=route.request().postDataJSON(); assert.equal(payload.final_total,'100.00');
   assert.equal(payload.phone,'0972827372');assert.equal(payload.network,'mtn');
   intent={id:'test',status:'processing',network:payload.network};return route.fulfill({status:201,json:{data:intent}});
  }
  return route.fulfill({json:{data:intent,paid:false,available:true,canPrompt:!intent||failed,statusAvailable:true}});
 });
 await page.goto('https://admin.newworldcargo.com/uat');
 await page.getByRole('tab',{name:'Online',exact:true}).click();
 await page.getByText('Ready to request the full bill amount.').waitFor();
 await page.getByRole('button',{name:'Send payment prompt'}).click();
 await page.getByText('Choose the customer mobile network.').waitFor();
 await page.getByRole('radio',{name:'MTN',exact:true}).check();
 await page.getByLabel('Mobile money number').fill('097abc');
 assert.equal(await page.getByLabel('Mobile money number').inputValue(),'097');
 await page.getByRole('button',{name:'Send payment prompt'}).click();
 await page.getByText('Enter a 10-digit mobile number starting with 07 or 09.').waitFor();
 assert.equal(posts,0);
 assert(!(await page.getByLabel('Mobile money number').isDisabled()));
 await page.getByLabel('Mobile money number').fill('0972827372');
 await page.getByLabel('Mobile money number').press('End');await page.getByLabel('Mobile money number').pressSequentially('123');
 assert.equal(await page.getByLabel('Mobile money number').inputValue(),'0972827372');
 await page.screenshot({path:`/tmp/nwc-staff-payment-ready-${width}.png`,fullPage:true});
 const tabs=await page.locator('.payment-channel-tabs').boundingBox();
 const tab=await page.getByRole('tab',{name:'Online',exact:true}).boundingBox();assert(Math.abs(tab.width-tabs.width/2)<1);
 await page.getByRole('button',{name:'Send payment prompt'}).click();
 await page.getByText(/Awaiting payment confirmation/).waitFor();
 assert(await page.getByRole('tab',{name:'Offline',exact:true}).isDisabled());
 assert(await page.getByRole('button',{name:'Send payment prompt'}).isDisabled());
 assert(!(await page.locator('#confirmMarkPaidBtn').isVisible()));
 await page.screenshot({path:`/tmp/nwc-staff-payment-${width}.png`,fullPage:true});
 await page.reload();await page.getByRole('tab',{name:'Online',exact:true}).click();
 await page.getByText(/Awaiting payment confirmation/).waitFor();assert.equal(posts,1);
 assert(await page.getByRole('radio',{name:'MTN',exact:true}).isChecked());
 intent.status='failed';failed=true;
 await page.getByRole('button',{name:'Check payment status'}).click();
 await page.getByRole('button',{name:'Send another prompt'}).waitFor();
 assert(!(await page.getByRole('button',{name:'Send another prompt'}).isDisabled()));
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 assert.deepEqual(errors,[]);console.log(JSON.stringify({width,validation:true,pendingLocked:true,resume:true,failedRetry:true,posts}));
 await page.close();
}} finally {await browser.close();}
