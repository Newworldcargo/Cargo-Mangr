const {chromium, expect} = require(process.env.PLAYWRIGHT_MODULE || '@playwright/test');
const fs = require('fs');
const assert = require('assert/strict');
const root = process.cwd() + '/public';
(async () => {
 const browser = await chromium.launch({args:['--no-sandbox']});
 try {
  const html = fs.readFileSync('/tmp/nwc-search-preview.html','utf8');
  const script = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(x=>x[1]).find(x=>x.includes("const searchInput = document.getElementById('globalSearchInput')"));
  assert(script);
  for (const width of [1440,390]) {
   const page = await browser.newPage({viewport:{width,height:950}});
   let requests=0, aborted=0;
   page.on('requestfailed', request=>{if(request.url().includes('/search/live'))aborted++;});
   await page.route('**/*',async route=>{
    const url=new URL(route.request().url());
    if(url.pathname.endsWith('/search/live')) {
     requests++;
     const query=url.searchParams.get('q');
     if(query==='LE') await new Promise(resolve=>setTimeout(resolve,900));
     return route.fulfill({json:{success:true,results:{shipments:{title:'Shipments',hasMore:true,data:[
      {title:query==='LE'?'STALE RESULT':'<img src=x onerror=alert(1)>',subtitle:'Safe text',url:'https://admin.newworldcargo.com/admin/shipments/shipments/1'},
      {title:'Second result',subtitle:'Ref: 2',url:'https://admin.newworldcargo.com/admin/shipments/shipments/2'},
      {title:'Unsafe URL',subtitle:'Excluded',url:'javascript:alert(1)'}
     ]}}}});
    }
    if(url.pathname.startsWith('/assets/') && fs.existsSync(root+url.pathname)) return route.fulfill({path:root+url.pathname});
    if(url.pathname==='/search-preview') return route.fulfill({contentType:'text/html',body:html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'')});
    return route.abort();
   });
   await page.goto('https://admin.newworldcargo.com/search-preview');
   await page.locator('.preloader').evaluateAll(nodes=>nodes.forEach(node=>node.remove()));
   await page.addScriptTag({content:script});
   await page.evaluate(()=>document.dispatchEvent(new Event('DOMContentLoaded')));
   if(width===1440) {
    const input=page.locator('#globalSearchInput');
    const firstRequest=page.waitForRequest(r=>r.url().includes('/search/live'));
    await input.fill('LE');await firstRequest;
    await input.fill('Jane');
    await expect(page.locator('#searchResultsContent')).toContainText('<img src=x onerror=alert(1)>');
    await expect(page.locator('#searchResultsContent img')).toHaveCount(0);
    await expect(page.locator('#searchResultsContent a')).toHaveCount(2);
    await page.waitForTimeout(1000);
    await expect(page.locator('#searchResultsContent')).not.toContainText('STALE RESULT');
    await input.press('ArrowDown');
    await expect(page.locator('#searchResultsContent a').first()).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(page.locator('#searchResultsContent a').nth(1)).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(input).toBeFocused();
    await expect(page.locator('#searchResults')).toBeHidden();
    assert(aborted>=1);assert.equal(requests,2);
    await input.fill('LE2412');
    await expect(page.locator('#searchResults')).toBeVisible();
    await page.screenshot({path:'/tmp/nwc-search-desktop.png',fullPage:false});
   } else {
    await expect(page.locator('#search-page-query')).toBeVisible();
    const box=await page.locator('#search-page-query').boundingBox();
    assert(box.x>=0 && box.x+box.width<=width);
    assert(await page.locator('a[href*="shipments_page=2"]').count()>0);
    await page.screenshot({path:'/tmp/nwc-search-mobile.png',fullPage:false});
   }
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
   console.log(JSON.stringify({width,passed:true,requests,aborted}));
   await page.close();
  }
 } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
