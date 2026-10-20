const { chromium, expect } = require(process.env.PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('fs');
const assert = require('assert/strict');

(async () => {
    const browser = await chromium.launch({ args: ['--no-sandbox'] });
    try {
        for (const width of [1440, 390]) {
            for (const screen of ['form', 'document']) {
                const html = fs.readFileSync('/tmp/nwc-sales-' + screen + '.html', 'utf8');
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                await page.route('**/*', async route => {
                    const url = new URL(route.request().url());
                    const asset = process.cwd() + '/public' + url.pathname;
                    if (url.pathname.startsWith('/assets/') && fs.existsSync(asset)) return route.fulfill({ path: asset });
                    if (url.pathname.endsWith('/sales/customers') && route.request().method() === 'POST') return route.fulfill({ status: 201, json: { id: 42, name: 'New Customer', email: 'new@example.com', branch_id: await page.locator('#branch').inputValue() } });
                    if (url.pathname.endsWith('/sales/customers')) return route.fulfill({ json: [{ id: 1, name: 'Test Customer', email: 'customer@test.example' }] });
                    if (url.pathname === '/sales-preview') return route.fulfill({ contentType: 'text/html', body: html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '') });
                    return route.abort();
                });
                await page.goto('https://admin.newworldcargo.com/sales-preview');
                await page.locator('.preloader').evaluateAll(nodes => nodes.forEach(node => node.remove()));
                await page.addScriptTag({ path: 'public/assets/lte/plugins/jquery/jquery.min.js' });
                await page.addScriptTag({ path: 'public/assets/lte/plugins/bootstrap/js/bootstrap.bundle.min.js' });
                if (screen === 'form') {
                    const script = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(match => match[1]).find(text => text.includes("getElementById('lines')"));
                    assert(script);
                    await page.addScriptTag({ content: script });
                    await page.locator('#branch').selectOption({ label: 'Test Lusaka' });
                    await page.locator('#customer-search').fill('Test');
                    await page.getByRole('button', { name: 'Test Customer customer@test.example' }).click();
                    await expect(page.locator('#client-id')).toHaveValue('1');
                    await page.getByRole('button', { name: 'Add item' }).click();
                    await expect(page.locator('.sales-form-row')).toHaveCount(2);
                    await page.getByRole('button', { name: 'Remove item' }).last().click();
                    await expect(page.locator('.sales-form-row')).toHaveCount(1);
                    await page.locator('#terms').fill('Keep these terms');
                    await page.locator('#add-customer').click();
                    await page.locator('#new-customer-first_name').fill('New');
                    await page.locator('#new-customer-last_name').fill('Customer');
                    await page.locator('#new-customer-email').fill('new@example.com');
                    await page.locator('#new-customer-phone').fill('0971234567');
                    await page.waitForTimeout(350);
                    await page.screenshot({path:`/tmp/nwc-add-customer-${width}.png`});
                    await page.locator('#save-customer').click();
                    await expect(page.locator('#add-customer-modal')).toBeHidden();
                    await expect(page.locator('#client-id')).toHaveValue('42');
                    await expect(page.locator('#customer-search')).toHaveValue('New Customer');
                    await expect(page.locator('#terms')).toHaveValue('Keep these terms');
                    await page.getByRole('tab',{name:'Shipment details',exact:true}).click();
                    await expect(page.locator('#shipment-fields')).toBeVisible();
                    await page.locator('#shipping-receiver_name').fill('Shipment receiver');
                    await page.locator('#shipment-packages input[name$="[quantity]"]').fill('2');
                    await expect(page.locator('#shipment-weight')).toHaveText('Total weight: 1.000 kg');
                    await page.getByRole('button',{name:'Add package',exact:true}).click();
                    await expect(page.locator('[data-package]')).toHaveCount(2);
                    await page.getByRole('button',{name:'Remove package',exact:true}).last().click();
                    await expect(page.locator('[data-package]')).toHaveCount(1);
                    await page.screenshot({path:`/tmp/nwc-sales-shipment-form-${width}.png`,fullPage:true});
                    await page.getByRole('tab',{name:'Plain invoice',exact:true}).click();
                    await expect(page.locator('#shipment-fields')).toBeHidden();
                    await expect(page.locator('#shipping-receiver_name')).toBeDisabled();
                    await page.getByRole('tab',{name:'Shipment details',exact:true}).click();
                    await expect(page.locator('#shipping-receiver_name')).toHaveValue('Shipment receiver');
                    await expect(page.locator('#terms')).toHaveValue('Keep these terms');
                    await page.getByRole('tab',{name:'Plain invoice',exact:true}).click();
                    await page.locator('#branch').selectOption('');
                    await expect(page.locator('#client-id')).toHaveValue('');
                } else {
                    await page.getByRole('button', { name: 'More options' }).click();
                    await expect(page.getByRole('link', { name: 'Download PDF', exact: true })).toBeVisible();
                    await expect(page.locator('.sales-paper')).toContainText('ZMW 120.00');
                    await page.getByRole('button', { name: 'Record received payment', exact: true }).click();
                    await expect(page.locator('input[name="amount"]')).toBeVisible();
                    await page.locator('#payment-modal input[name="amount"]').fill('20');
                    await page.waitForTimeout(350);
                    await page.screenshot({ path: `/tmp/nwc-sales-payment-modal-${width}.png`, fullPage: false });
                    await page.locator('#payment-modal').getByRole('button', {name:'Close',exact:true}).click();
                    await expect(page.locator('#payment-modal')).toBeHidden();
                    await page.getByRole('tab', {name:/Payments/}).click();
                    await expect(page.getByText('No payments recorded.',{exact:true})).toBeVisible();
                    await page.getByRole('tab', {name:/Email/}).click();
                    await expect(page.getByText('No emails scheduled or sent.',{exact:true})).toBeVisible();
                    await page.getByRole('button',{name:'Send document',exact:true}).click();
                    await expect(page.locator('#email-modal')).toBeVisible();
                    await page.locator('#email-modal').getByRole('button',{name:'Cancel',exact:true}).click();
                    await expect(page.locator('#email-modal')).toBeHidden();
                    await page.getByRole('tab',{name:'Activity',exact:true}).click();
                    await expect(page.getByRole('heading',{name:'Document activity'})).toBeVisible();
                    await page.getByRole('tab',{name:'Document',exact:true}).click();
                }
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${screen} overflows at ${width}`);
                await page.screenshot({ path: `/tmp/nwc-sales-${screen}-${width}.png`, fullPage: true });
                console.log(JSON.stringify({ width, screen, passed: true }));
                await page.close();
            }
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
