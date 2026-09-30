# Lipila Customer Portal Payments

Lipila is the default customer portal provider. Existing legacy payment providers
and their settings are retained. Explicit `CUSTOMER_PORTAL_PAYMENT_PROVIDER`
overrides are still honored, but the portal fails closed for providers without a
verified collection adapter. The old generic bridge cannot mark production
invoices paid. Legacy admin checkout is not switched to Lipila.

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
php artisan migrate --path=database/migrations/2026_09_30_140000_harden_online_payment_attempts.php --force
php artisan migrate --path=database/migrations/2026_09_30_141000_create_payment_webhook_events.php --force
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

## Edge-Case Review (September 30)

- Durable customer-scoped request keys replay the original result, including
  failure. Reusing a key with different details is rejected. Older clients
  without keys retain shipment-scoped pending-attempt protection.
- New invoices cannot bypass an unresolved attempt for the same shipment.
  The cashier's mark-paid endpoint checks this guard under its shipment lock.
- A reassigned customer cannot read or resume the former customer's checkout.
  Invoice/shipment reassignment at settlement is held for review.
- Amounts are parsed as exact decimal minor units; negative, exponent, boolean,
  array, extra-precision and malformed values cannot become successful payments.
- A foreign-currency or previously recorded installment blocks a fresh full-bill
  collection until staff reconcile it. No implicit cross-currency arithmetic.
- Signed webhook IDs and payload hashes are durable. Exact replays are harmless;
  a reused event ID with changed content is rejected. Pending/unreachable status
  checks return 503 so Lipila can retry instead of acknowledging unverified data.
- Late success after a confirmed failure enters review, never silently pays a
  potentially retried bill. Unknown outcomes never trigger an automatic charge.
- Disabling new collection does not disable reconciliation. Each attempt records
  its provider environment; switching environments cannot query the wrong wallet.
- Receipt, invoice, shipment, intent and audit writes roll back together on error.
- The browser no longer keeps a local paid-status override. It reloads invoices
  from the backend; provider status messages and retry permission are returned by
  the backend. Client-supplied amount/currency/success flags are ignored.
- Reconciliation batches are time-bounded, serialized by cron `flock`, with
  per-attempt cache locks and durable database settlement locks.

Scope limits: this is not a certification of every historical gateway callback.
Legacy gateway-specific controllers and refund workflows remain separate and
need their own provider-by-provider review before activation. SQLite tests cover
controlled interleavings and rollback, not a real multi-process MySQL load test.
Live gateway delivery and throughput cannot be certified while its API returns
502. Do not enable collections until connectivity and an authorized end-to-end
payment have been verified. Never clear a pending/review attempt merely to retry.

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
# Staff Mobile-Money Collection

The shipment Mark as Paid modal has Offline and Online tabs. Offline retains the
existing manual workflow. Online collects the full unpaid ZMW bill through Lipila;
the customer approves on their own phone. Partial payments and other currencies
continue through the existing offline workflow.

The additive migration `2026_09_30_150000_add_staff_online_payment_context.php`
stores the bill breakdown and initiating staff member. Existing payment permission
and shipment scope are checked before preparing the bill and again under the
collection transaction lock. Missing conversion rates prevent new bill creation.

An existing bill is displayed as confirmed and cannot be changed during collection.
Pending or unknown outcomes block a new prompt and offline payment. Only verified
failure permits retry. Reopening the modal reads the persisted attempt. Confirmed
settlement records charges and cashier details once, with the receipt base amount
kept separate from extra charges and discounts.

The collection feature remains gated by `LIPILA_ENABLED`. KYC approval does not
itself prove API readiness; verify the live account and an authorized end-to-end
collection before enabling. Browser tests use intercepted payment responses and
must not be interpreted as proof of live mobile-network delivery.
