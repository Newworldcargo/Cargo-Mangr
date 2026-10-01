import {chromium} from '/home/newworldcargo/web/staging/new-world-cargo-app/node_modules/@playwright/test/index.mjs';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';

const browser = await chromium.launch({args: ['--no-sandbox']});
try {
    for (const width of [320, 390, 1440]) {
        const page = await browser.newPage({viewport: {width, height: 1000}});
        for (const sample of ['otp', 'welcome', 'payment', 'contact', 'report', 'support']) {
            await page.setContent(readFileSync(`/tmp/nwc-email-${sample}.html`, 'utf8'), {waitUntil: 'networkidle'});
            assert.equal(await page.locator('h1').count(), 1);
            assert.ok(await page.locator('img').evaluate(img => img.complete && img.naturalWidth > 0), 'Brand logo must load');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${sample} overflows at ${width}`);
            const button = page.locator('a').filter({hasText: /View receipt|View my shipments/});
            if (await button.count()) {
                assert.equal(await button.evaluate(el => getComputedStyle(el).color), 'rgb(255, 255, 255)');
                assert.ok((await button.boundingBox()).height >= 44);
            }
            await page.screenshot({path: `/tmp/nwc-email-${sample}-${width}.png`, fullPage: true});
        }
        await page.close();
    }
    console.log('18 email previews passed: logo, mobile overflow, headings, button contrast and touch size.');
} finally {
    await browser.close();
}
