import {chromium} from '/home/newworldcargo/web/staging/new-world-cargo-app/node_modules/@playwright/test/index.mjs';
import {readFileSync} from 'node:fs';
import assert from 'node:assert/strict';

const root = process.cwd();
const browser = await chromium.launch({args: ['--no-sandbox']});
try {
    for (const width of [390, 1440]) {
        const page = await browser.newPage({viewport: {width, height: 900}});
        let posts = 0;
        await page.route('**/*', async route => {
            const path = new URL(route.request().url()).pathname;
            if (path === '/uat') return route.fulfill({contentType: 'text/html', body: `<!doctype html><html><head>
                <meta name="viewport" content="width=device-width">
                <link rel="stylesheet" href="/assets/lte/css/adminlte.css">
                <script src="/assets/lte/plugins/global/plugins.bundle.js"></script>
                </head><body><button id="open-payment">Open payment</button>
                ${readFileSync('/tmp/nwc-full-payment-modal.html', 'utf8')}
                <script src="/assets/lte/plugins/jquery/jquery.min.js"></script>
                <script src="/assets/lte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
                <script>document.getElementById('open-payment').onclick = () => jQuery('#markPaidModal').modal('show');</script>
                </body></html>`});
            if (path.includes('shipment-online-payment')) {
                if (route.request().method() === 'POST') posts++;
                return route.fulfill({json: {data: {id: 'pending', status: 'processing', network: 'airtel'}, paid: false, available: true, canPrompt: false, canSwitchOffline: false}});
            }
            if (path.endsWith('.js') || path.endsWith('.css')) return route.fulfill({
                contentType: path.endsWith('.js') ? 'application/javascript' : 'text/css', body: readFileSync(root + '/public' + path),
            });
            return route.abort();
        });
        await page.goto('https://admin.newworldcargo.com/uat');
        for (const selector of ['#markPaidModal .modal-header button', '#markPaidModal .modal-footer [data-dismiss="modal"]']) {
            await page.locator('#open-payment').click();
            await page.getByText(/Awaiting payment confirmation/).waitFor();
            await page.waitForFunction(() => document.querySelector('#markPaidModal')?.classList.contains('show'));
            // Wait until Bootstrap's opening transition finishes before closing.
            await page.waitForTimeout(400);
            await page.locator(selector).click();
            await page.locator('#markPaidModal').waitFor({state: 'hidden', timeout: 3000});
            await page.waitForFunction(() => !document.querySelector('.modal-backdrop') && !document.body.classList.contains('modal-open'));
        }
        assert.equal(posts, 0);
        console.log(JSON.stringify({width, closeAndReopen: true, footerClose: true, noPaymentWrites: true}));
        await page.close();
    }
} finally { await browser.close(); }
