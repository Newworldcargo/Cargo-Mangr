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
                    await page.locator('#branch').selectOption('');
                    await expect(page.locator('#client-id')).toHaveValue('');
                } else {
                    await page.getByRole('button', { name: 'More options' }).click();
                    await expect(page.getByRole('link', { name: 'Download PDF', exact: true })).toBeVisible();
                    await expect(page.locator('.sales-paper')).toContainText('ZMW 120.00');
                    await page.getByText('Record received payment', { exact: true }).click();
                    await expect(page.locator('input[name="amount"]')).toBeVisible();
                }
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${screen} overflows at ${width}`);
                await page.screenshot({ path: `/tmp/nwc-sales-${screen}-${width}.png`, fullPage: true });
                console.log(JSON.stringify({ width, screen, passed: true }));
                await page.close();
            }
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
