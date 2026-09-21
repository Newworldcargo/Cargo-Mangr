import { readFile } from 'node:fs/promises';
import { resolve, extname } from 'node:path';
const { chromium, expect } = await import(process.env.PLAYWRIGHT_MODULE || '@playwright/test');
const html = await readFile(process.env.MESSAGING_RENDER_PATH || '/tmp/nwc-messaging-uat.html', 'utf8');
const publicRoot = resolve('public');
const browser = await chromium.launch({ headless: true });
try {
 for (const width of [1440, 390]) {
  const page = await browser.newPage({ viewport: { width, height: 1000 } });
  let saved;
  await page.route('**/*', async route => {
   const request = route.request(); const url = new URL(request.url());
   if (request.method() === 'POST') {
    saved = new URLSearchParams(request.postData());
    return route.fulfill({contentType:'text/html',body:'<p>Saved fixture</p>'});
   }
   if (url.pathname === '/messaging') return route.fulfill({contentType:'text/html',body:html});
   if (['cdnjs.cloudflare.com', 'code.jquery.com', 'unpkg.com', 'fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname)) return route.continue();
   const path = resolve(publicRoot, '.' + url.pathname);
   if (path.startsWith(publicRoot + '/')) {
    try {
     const body = await readFile(path);
     const contentType = {'.css':'text/css','.js':'application/javascript','.svg':'image/svg+xml','.png':'image/png','.woff2':'font/woff2'}[extname(path)] || 'application/octet-stream';
     return route.fulfill({body,contentType});
    } catch {}
   }
   return route.abort();
  });
  await page.goto('http://messaging.test/messaging');
  await expect(page.getByLabel('Enterprise account email')).toHaveValue('test@example.test');
  await expect(page.locator('#mtn-password')).toHaveValue('');
  for (const id of ['sms-enabled','email-enabled','purpose-otp','purpose-customer_notifications','purpose-staff_notifications','purpose-bulk_customers','purpose-bulk_staff']) {
   await page.locator(`label[for="${id}"]`).click();
   await expect(page.locator(`#${id}`)).not.toBeChecked();
  }
  await page.screenshot({path:`/tmp/nwc-messaging-${width}.png`,fullPage:true});
  const overflow = await page.locator('.content-wrapper').evaluate(el => el.scrollWidth > el.clientWidth + 2);
  expect(overflow).toBe(false);
  await page.locator('button[type="submit"]').filter({hasText:'Save settings'}).click();
  await expect(page.getByText('Saved fixture')).toBeVisible();
  expect(saved.getAll('sms_enabled')).toEqual(['0']);
  expect(saved.getAll('email_enabled')).toEqual(['0']);
  expect(saved.getAll('sms_purposes[]')).toEqual([]);
  console.log(`PASS ${width}: settings controls, secret masking, all-off submission, no content overflow (isolated fixtures)`);
  await page.close();
 }
} finally { await browser.close(); }
