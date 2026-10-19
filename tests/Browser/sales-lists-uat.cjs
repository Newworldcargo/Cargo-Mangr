const { chromium, expect } = require(process.env.PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('fs');
const assert = require('assert/strict');
(async () => {
    const browser = await chromium.launch({args:['--no-sandbox']});
    try {
        for (const width of [1440,390]) for (const type of ['invoices','quotations']) {
            const html=fs.readFileSync(`/tmp/nwc-sales-${type}-list.html`,'utf8').replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
            const page=await browser.newPage({viewport:{width,height:900}});
            await page.route('**/*',async route=>{
                const url=new URL(route.request().url());
                const asset=process.cwd()+'/public'+url.pathname;
                if(url.pathname.startsWith('/assets/')&&fs.existsSync(asset))return route.fulfill({path:asset});
                if(url.pathname==='/preview')return route.fulfill({contentType:'text/html',body:html});
                return route.abort();
            });
            await page.goto('https://admin.newworldcargo.com/preview');
            await page.locator('.preloader').evaluateAll(nodes=>nodes.forEach(node=>node.remove()));
            await expect(page.locator('#sales-search')).toBeVisible();
            await expect(page.getByRole('link',{name:'Reload table'})).toBeVisible();
            await expect(page.locator('#sales-per-page')).toBeVisible();
            await expect(page.locator('.btn-action-table').first()).toBeAttached();
            await expect(page.locator('th').last()).toHaveText('Actions');
            assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
            await page.screenshot({path:`/tmp/nwc-sales-${type}-list-${width}.png`,fullPage:true});
            console.log(JSON.stringify({width,type,passed:true}));
            await page.close();
        }
    } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
