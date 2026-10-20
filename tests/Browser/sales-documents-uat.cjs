const { chromium, expect } = require(process.env.PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('fs');
const assert = require('assert/strict');

(async () => {
    const browser = await chromium.launch({ args: ['--no-sandbox'] });
    try {
        for (const width of [1440, 390]) {
            for (const screen of ['form', 'document', 'settings', 'shipping-rates']) {
                const html = fs.readFileSync('/tmp/nwc-sales-' + screen + '.html', 'utf8');
                const page = await browser.newPage({ viewport: { width, height: 1000 } });
                await page.route('**/*', async route => {
                    const url = new URL(route.request().url());
                    const asset = process.cwd() + '/public' + url.pathname;
                    if (url.pathname.startsWith('/assets/') && fs.existsSync(asset)) return route.fulfill({ path: asset });
                    if (url.pathname.endsWith('/sales/customers') && route.request().method() === 'POST') return route.fulfill({ status: 201, json: { id: 42, name: 'New Customer', email: 'new@example.com', branch_id: await page.locator('#branch').inputValue() } });
                    if (url.pathname.endsWith('/sales/customers')) return route.fulfill({ json: [{ id: 1, name: 'Test Customer', email: 'customer@test.example' }] });
                    if (url.pathname.endsWith('/sales/catalogue')) return route.fulfill({json:{currency:'ZMW',profile:{legal_name:'Test Cargo'},agents:[{id:1,name:'Sales Admin'}],taxes:[{id:1,label:'Test VAT',basis_points:1600,effective_from:'2000-01-01',effective_until:null}],services:[{id:11,name:'Road freight',unit:'kg',price_minor:11600,tax_id:1,tax_mode:'inclusive'}]}});
                    if (url.pathname.endsWith('/sales/preview')) {
                        const qty=Number(route.request().postData()?.match(/name="items\[0\]\[quantity\]"\r\n\r\n([^\r]+)/)?.[1]??1);
                        return route.fulfill({json:{currency:'ZMW',subtotal_minor:10000*qty,discount_minor:0,tax_minor:1600*qty,total_minor:11600*qty,items:[{total_minor:11600*qty}]}});
                    }
                    if (url.pathname === '/sales-preview') return route.fulfill({ contentType: 'text/html', body: html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '') });
                    return route.abort();
                });
                await page.goto('https://admin.newworldcargo.com/sales-preview');
                await page.locator('.preloader').evaluateAll(nodes => nodes.forEach(node => node.remove()));
                await page.addScriptTag({ path: 'public/assets/lte/plugins/jquery/jquery.min.js' });
                await page.addScriptTag({ path: 'public/assets/lte/plugins/bootstrap/js/bootstrap.bundle.min.js' });
                if (screen === 'form') {
                    const scripts = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(match => match[1]).filter(text => text.includes("getElementById('lines')"));
                    assert.equal(scripts.length,2);
                    for(const script of scripts) await page.addScriptTag({ content: script });
                    await page.locator('#branch').selectOption({ label: 'Test Lusaka' });
                    await page.getByRole('searchbox',{name:'Search services'}).fill('Road');
                    await page.getByRole('searchbox',{name:'Search services'}).press('ArrowDown');
                    await page.keyboard.press('Enter');
                    await expect(page.locator('[name="items[0][description]"]')).toHaveValue('Road freight');
                    await expect(page.locator('[name="items[0][unit_price]"]')).toHaveValue('116.00');
                    await expect(page.locator('[name="items[0][tax_mode]"]')).toHaveValue('inclusive');
                    await expect(page.locator('[data-total="total_minor"]')).toHaveText('ZMW 116.00');
                    await expect(page.locator('.invoice-line-amount')).toHaveText('ZMW 116.00');
                    await page.locator('[name="items[0][quantity]"]').fill('2');
                    await expect(page.locator('[data-total="total_minor"]')).toHaveText('ZMW 232.00');
                    await expect(page.locator('.invoice-line-amount')).toHaveText('ZMW 232.00');
                    const rowBounds=await page.locator('#lines').boundingBox(),totalBounds=await page.locator('#live-totals').boundingBox();
                    assert(totalBounds.y>=rowBounds.y+rowBounds.height,'Totals must follow the item table');
                    await page.locator('[name="items[0][quantity]"]').fill('1');
                    await page.locator('#payment-terms').selectOption('net30');
                    const issueDate=await page.locator('#issued').inputValue();const dueDate=new Date(issueDate+'T12:00:00Z');dueDate.setUTCDate(dueDate.getUTCDate()+30);
                    await expect(page.locator('#due')).toHaveValue(dueDate.toISOString().slice(0,10));
                    await page.locator('#customer-search').fill('Test');
                    await expect(page.getByRole('button', { name: 'Test Customer customer@test.example' })).toBeVisible();
                    await page.locator('#customer-search').press('Enter');
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
                } else if(screen==='shipping-rates') {
                    const script=[...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(match=>match[1]).find(text=>text.includes("getElementById('shipping-bands')"));
                    assert(script);await page.addScriptTag({content:script});
                    await expect(page.locator('.shipping-band')).toHaveCount(2);
                    await page.getByRole('button',{name:'Add band',exact:true}).click();
                    await expect(page.locator('.shipping-band')).toHaveCount(3);
                    await page.locator('[name="tiers[2][from]"]').fill('5');
                    await page.locator('[name="tiers[2][rate]"]').fill('365');
                    await page.getByRole('button',{name:'Remove band',exact:true}).last().click();
                    await expect(page.locator('.shipping-band')).toHaveCount(2);
                    await page.locator('[name="currency"]').selectOption('ZMW');
                    await page.locator('[name="active"][type="checkbox"]').uncheck();
                    await expect(page.locator('[name="active"][type="checkbox"]')).not.toBeChecked();
                } else if(screen==='settings') {
                    await page.getByRole('tab',{name:'Tax categories',exact:true}).click();
                    await expect(page.locator('#tax-settings')).toBeVisible();
                    await page.getByRole('tab',{name:'Services',exact:true}).click();
                    await expect(page.locator('#service-settings')).toBeVisible();
                    await expect(page.locator('#service-settings')).toContainText('Service test');
                    await page.getByRole('tab',{name:'Billing profile',exact:true}).click();
                    await expect(page.locator('[name="legal_name"]')).toHaveValue('Configured Company');
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
                const overflow=await page.evaluate(()=>[...document.querySelectorAll('body *')].filter(node=>node.getBoundingClientRect().right>innerWidth+1).map(node=>({tag:node.tagName,id:node.id,cls:node.className,right:node.getBoundingClientRect().right})).slice(0,12));
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${screen} overflows at ${width}: ${JSON.stringify(overflow)}`);
                await page.screenshot({ path: `/tmp/nwc-sales-${screen}-${width}.png`, fullPage: true });
                console.log(JSON.stringify({ width, screen, passed: true }));
                await page.close();
            }
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
