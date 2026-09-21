# Messaging Audit and Controlled Rollout

## Production boundaries

Do not delete or reset existing data, run migrate:fresh against production, change
the global QUEUE_CONNECTION, replace Twilio credentials, or replay unknown sends.
New sending defaults off, with MESSAGING_ACTIVATION_READY=false as an additional
operator gate. No live MTN account, token, example recipient, or archive credential
was used. The archive must not be committed to Git.

## Findings (21 September 2026)

- Laravel 8.54-compatible application; actual queue default is sync, cache is file.
- This application's database did not have a jobs table. Other hosted applications
  have workers, but they are not this application's messaging workers.
- Several listeners import ShouldQueue without implementing it. GlobalNotification
  and direct OTP SMTP sends execute in request handling. A queued-looking call on
  the sync connection still runs immediately.
- The standalone Twilio bulk helper has no located caller, uses sequential cURL,
  and stores no durable delivery history. Its settings are separate from general
  notification SMS settings and WhatsApp. It remains intact.
- Portal SMS previously used an optional webhook, currently unconfigured.
- Mail remains synchronous outside the explicitly integrated paths below. This
  change is not a claim that every email or third-party channel is now queued.

## Supplied archive and MTN documentation

Read `Rest APi's (2).zip` without modifying it: two Postman collections, one
environment, and JB1/JB2/JB3 spreadsheets. The spreadsheets have respectively
one, two, and four columns for the three upload workflows. Environment credentials
were not printed or imported. The collections date from July 2024.

The archive uses /api/v1 login and SMS routes, with wallet examples on port 32606.
The separately supplied email uses port 32147 and /v1. The downloaded Enterprise
8.2.0 specification uses standard HTTPS and /api/v1. Account-specific production
URLs, MFA/service-account authentication, limits and callback verification must be
confirmed with MTN before activation. Source:
https://cpassmessaging.mtn.zm/api-specs/enterprise.yaml

The implemented adapter uses single-recipient /api/v1/sms/send behind the bulk
outbox. Each recipient therefore has a separate acceptance record and retry state.
Native file/campaign uploads were studied but are not activated. Confirm their
partial-acceptance, idempotency and per-recipient delivery contracts before adding
that optimisation. We do not assume a successful response means delivered.

## Implemented scope

- Separate messaging connection and messaging_jobs table; unchanged global queues.
- SMS-purpose switches: OTP, customer notifications, staff notifications, bulk
  customer service announcements, bulk staff service announcements.
- Optional queued email for GlobalNotification, portal OTP and the new bulk UI.
- Existing notification recipient/role selection remains in place; only the
  selected transport is queued. MTN takes over general SMS only when enabled.
- Backend OTP resend also uses the notifier when new messaging is enabled.
- Bulk recipients are snapshotted by maximum user ID, expanded in chunks of 100,
  and deduplicated by campaign, channel and normalised destination. Staff means
  the existing User::STAFF account category; customers means existing role 4.
- Addresses, content and MTN password are encrypted in storage. Queue payloads
  contain IDs. Password fields never echo stored values. No automatic data cleanup
  is enabled; agree a restricted-access OTP/content retention policy before rollout.
- Known authentication/rate-limit failures retry at most five send attempts.
  Network errors and uncertain acceptance become unknown, never auto-replayed.
  Interrupted processing is also unknown. Read provider history before any resend.
- Expired or superseded OTPs are suppressed. SMS is restricted to valid-looking
  Zambia mobile numbers; formatting validation does not prove a number is active.
- Status accepted means HTTP/provider acceptance (or SMTP submission), not delivery.
  Delivery callbacks, unsubscribe management and promotional campaigns are not
  implemented. The bulk UI is restricted to transactional/service announcements.

## Enablement checklist

1. Review the additive migration SQL. Apply only
   `php artisan migrate --path=database/migrations/2026_09_21_000001_create_messaging_outbox.php --force`.
   Never run unrelated pending migrations as part of this rollout.
2. Keep both messaging switches off. Open /messaging as an administrator. Delegate
   manage-messaging-settings, send-bulk-messages and view-messaging-history through
   existing roles; no staff role receives blanket access from the migration.
3. Confirm MTN endpoint, approved TXN/OTP sender IDs, account/MFA API behaviour,
   billing, throughput, number coverage and live delivery-report contract. Enter
   credentials through the protected settings form, never source control.
4. Review and install deploy/messaging-workers.conf.example with four isolated
   Supervisor workers. Verify PHP paths, Unix permissions, logs, and free memory.
   Ensure this app's existing scheduler calls schedule:run each minute so recovery
   runs every five minutes. Do not change another application's scheduler/worker.
5. Confirm queue reservation timeout (120s) exceeds worker timeout (45s/60s).
   Verify the shared cache lock works under the deployment user. File cache is
   single-server only; use shared Redis/database locking before multiple servers.
6. Set MESSAGING_ACTIVATION_READY=true only after worker verification. Refresh
   config and restart only messaging workers after deployments. Enable one purpose,
   test an explicitly approved internal number, and inspect both local acceptance
   and MTN delivery history. No real broadcast before this passes.
7. Increase volume gradually, observing application latency, queue age, memory,
   DB load, unknown/failed counts and provider throttling. Default 30/min is split
   into three reserved budgets (OTP, notifications, bulk): only 10/min for bulk.
   Messages expire after 24h; capacity planning must account for that bound.

## Operational limitations

The outbox prevents request-time provider fan-out, but no production throughput
guarantee is possible without provider limits and a monitored load test. Email
transport timeouts are ambiguous too, so SMTP exceptions are held for review.
Unrelated direct mail (reports, welcome/contact/support/password-reset paths) is
not silently migrated. Existing webhook/Twilio paths remain available when MTN is
off; turning off optional email queueing returns existing notifications to their
legacy path, not a global email kill switch.

The recovery command can create duplicate queue envelopes after a worker outage;
the atomic outbox claim makes already accepted messages no-ops. Business callers
must supply stable deduplication keys. This cannot guarantee exactly-once delivery
inside an external carrier, which is why uncertain outcomes stop for review.

Laravel queue reference: https://laravel.com/docs/8.x/queues
