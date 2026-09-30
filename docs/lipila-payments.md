# Lipila Customer Portal Payments

Lipila is the default customer portal provider. Existing legacy payment providers
and their settings are retained. Explicit `CUSTOMER_PORTAL_PAYMENT_PROVIDER`
overrides are still honored. Legacy admin checkout is not switched to Lipila.

## Configuration

Keep these values in the deployment secret store / untracked `.env`, never Git:

```dotenv
CUSTOMER_PORTAL_PAYMENT_PROVIDER=lipila
LIPILA_ENABLED=false
LIPILA_BASE_URL=https://api.lipila.dev
LIPILA_SECRET_KEY=
LIPILA_WEBHOOK_SECRET=
LIPILA_CALLBACK_URL=https://admin.newworldcargo.com/api/v1/payments/lipila/webhook
LIPILA_RETURN_URL=https://app.newworldcargo.com/shipments
```

Sandbox and production use separate wallet keys. Confirm the production API
endpoint with Lipila before activation (`https://api.lipila.io` is allowlisted).
The live endpoint returned HTTP 502 during the September 30 integration checks;
the supplied wallet key returned 401 on sandbox. Live collection remains disabled.
Use GET `/api/v1/merchants/balance` for a non-charging credential check.

Register the callback URL in the Lipila wallet's Webhooks settings. The signing
secret must be a base64-encoded 32-byte key. The collection request also supplies
the `callbackUrl` header. Neither key is exposed by the settings page or API.

Deploy the additive migration before enabling:

```sh
php artisan migrate --path=database/migrations/2026_09_30_100000_add_lipila_payment_tracking.php --force
php artisan config:clear
php artisan route:clear
```

Run the standard Laravel scheduler every minute. `payments:reconcile-lipila`
checks up to 50 pending attempts per run, oldest checked first, without issuing
collections. Webhooks and customer polling use the same reconciliation service.
Keep one scheduler per deployment, or use a shared cache for scheduler locks.

On the current server, `/etc/cron.d/nwc-lipila-reconcile` invokes this command
directly with `flock`. The pre-existing logistics scheduler points to an old app
directory and was deliberately left unchanged. Remove the dedicated entry if
the main scheduler is later repaired, to avoid redundant reconciliation runs.

## Behavior And Safeguards

- Invoice ownership, latest bill, amount and currency are checked on the backend.
- Initially supports wholly unpaid, finalized `pending` / `unpaid` invoices.
  Part-paid, refunded, voided and superseded bills are not collected online.
- Mobile money is restricted to ZMW and Zambia mobile numbers; cards support
  ZMW/USD. No implicit currency conversion or mixing of invoice currencies.
- Attempts are durably reserved under shipment/invoice locks before contacting
  Lipila. Repeated submissions resume the existing attempt.
- No automatic POST retry after timeouts, 5xx, unknown results or failed lookups.
  An unknown attempt continues to block a replacement until reconciled.
- Card details are entered only on hosted checkout. Billing contact details are
  passed to Lipila, not card PAN/CVV. Redirects require HTTPS and an approved host.
- Signed callbacks use raw bytes, HMAC-SHA256, constant-time comparison and a
  five-minute timestamp window. The status endpoint independently verifies every
  final outcome. Callback data alone cannot mark a bill paid.
- Settlement uses a locked transaction and a durable succeeded state, so repeated
  callbacks/polls record exactly one installment receipt and update the invoice,
  shipment paid flag, legacy receipt and audit trail together.
- Changed bill/owner/balance or mismatched currency/amount enters `review` without
  overwriting the bill. Search audit logs for `lipila_payment_review`. Financial
  staff must investigate the provider reference before any retry or refund.
- This integration does not initiate refunds or disbursements.

## Verification

```sh
vendor/bin/phpunit --filter 'LipilaPaymentsTest|ShipmentPaymentDocumentsTest'
```

Frontend: `npm run check`, `npm test`, `npm run build`, and
`UAT_BASE_URL=http://localhost:5194 node tests/lipila-checkout-uat.mjs` with Vite running.
Browser UAT covers mobile
and desktop mobile-money pending/success, hosted card handoff, failure, pending
resume and review states using intercepted APIs, not real charges. Complete a
separately authorized sandbox/live end-to-end transaction before enabling live
customer payments. Unit/browser mocks do not prove provider delivery.

Official contract: https://docs.lipila.io/docs/gettingstarted/overview.html
