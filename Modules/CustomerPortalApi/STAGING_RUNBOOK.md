# Customer Portal API Staging Runbook

This runbook is for the branch `feature/customer-portal-api-v1` and the draft pull request [#25](https://github.com/Newworldcargo/Cargo-Mangr/pull/25). It is intentionally staging-only. Do not run the migration or switch the React island to HTTP mode against production until the acceptance gates pass.

## 1. Prepare staging

Use a sanitized database cloned from production structure with anonymized customer, shipment, address, recipient, and financial records. Do not copy passwords, OTP values, session records, Composer credentials, webhook secrets, private files, or payment-provider tokens.

Install the project dependencies using the normal authorized Composer package credentials. The repository currently has no `composer.lock` in the public source and references a private Composer distribution package, so dependency installation must happen in the project’s controlled development environment.

Set the following non-secret values in the staging environment:

```dotenv
APP_ENV=staging
APP_DEBUG=false
INSTALLATION=true
CUSTOMER_PORTAL_API_ALLOWED_ORIGINS=https://portal-staging.example.com
CUSTOMER_PORTAL_API_MAX_PER_PAGE=50
CUSTOMER_PORTAL_API_RATE_LIMIT=60
CUSTOMER_PORTAL_PUBLIC_TRACKING_RATE=30
CUSTOMER_PORTAL_COOKIE_SECURE=true
CUSTOMER_PORTAL_COOKIE_SAME_SITE=lax
# Keep these two values in the secret manager; do not commit them.
CUSTOMER_PORTAL_BFF_SERVICE_TOKEN=<secret-manager-value>
CUSTOMER_PORTAL_BFF_SHARED_SECRET=<secret-manager-value>
CUSTOMER_PORTAL_BFF_SESSION_HOURS=8
```

Use the project’s existing `APP_KEY`, database, mail, storage, and provider configuration only through the secret manager. Never copy the public repository’s former `.env.example` values into staging. Configure the React server with `NWC_BACKEND_ORIGIN=https://laravel-staging.example.com`, `NWC_BACKEND_API_PREFIX=/api`, `NWC_BFF_SERVICE_TOKEN` matching the Laravel service-token value, `NWC_BFF_SHARED_SECRET` matching the Laravel signing-secret value, and `NWC_BFF_ALLOWED_ORIGIN=https://portal-staging.example.com`. The service token and signing secret must exist only in server-side secret storage.

## 2. Deploy and migrate

Deploy the feature branch to staging using the project’s normal release process. Verify the application reports the expected commit before migrating.

```bash
php artisan migrate:status
php artisan migrate --force
php artisan route:list --path=api/v1
php artisan config:clear
php artisan config:cache
```

The portal migrations add `revision` columns and indexes to `shipments`, `client_addresses`, and `receivers`. Confirm the migration SQL on staging first and take a verified backup before running it. The migrations are additive and use `Schema::hasColumn` guards, but the deployment still needs the project’s normal rollback procedure.

## 3. Server-to-server BFF smoke test

The browser must call only the React server’s same-origin `/api/gateway/v1/...` endpoint. The React server must call Laravel with its private bearer service token. Laravel login or registration should return `X-NWC-BFF-Session` and `X-NWC-BFF-CSRF-Token` response headers; the React server converts them into its HttpOnly portal-session cookie and readable CSRF cookie. For every authenticated request, the React server must first call `POST /internal/bff/session-exchange` with the private service token and portal-session cookie, then forward the resulting customer assertion and CSRF header to `/api/v1/...`.

Run these checks from the React staging origin and inspect only redacted headers/logs:

| Check | Expected result |
|---|---|
| Browser `POST /api/gateway/v1/auth/login` | React server returns the Laravel JSON envelope and sets secure BFF cookies; no service token reaches browser JavaScript. |
| React server to Laravel exchange | `POST /internal/bff/session-exchange` returns `X-NWC-Customer-Assertion` and `X-NWC-BFF-CSRF-Token`; no raw portal token is logged. |
| Browser `GET /api/gateway/v1/session` | React server performs exchange and Laravel returns the authenticated customer. |
| Browser unsafe mutation | React server forwards service token, assertion, and CSRF header; Laravel rejects missing or invalid CSRF with `419`. |
| Expired/revoked portal cookie | Exchange returns `401`; React gateway clears or expires the browser session. |
| Direct browser call to Laravel | Not required for the BFF topology; direct cross-origin access remains disabled. |

The existing direct Laravel browser-session checks remain useful for the Laravel API itself. For unsafe direct calls, echo the readable `nwc_csrf` value in `X-CSRF-Token`.

Expected behavior:

| Check | Expected result |
|---|---|
| `GET /api/v1/healthz` | `200`, `data.status=ok`, and `X-Request-ID`. |
| `GET /api/v1/readyz` | `200` only when staging is ready. |
| Missing session on `GET /api/v1/session` | JSON `401` with `error.code=UNAUTHENTICATED`. |
| Customer login | JSON `200`, session cookie, no token field, and request ID. |
| Unverified customer login | JSON `403` with `CONTACT_UNVERIFIED`. |
| Customer shipment list | Only the authenticated customer’s shipments. |
| Other customer shipment by ID | JSON `404`; do not reveal ownership. |
| Missing CSRF on logout/address/recipient writes | JSON `419` with `CSRF_TOKEN_MISMATCH`. |
| Public tracking | Reduced DTO without customer ID, price, or allowed actions. |
| Wrong origin | No credentialed CORS access. |

## 4. Required security tests

Create two staging test customers and at least one shipment, address, and recipient for each. Test cross-customer reads, updates, deletes, file access, and future workflow actions. Verify that setting `customerId`, `client_id`, `user_id`, or `branch_id` in a request cannot move ownership to another customer.

Test address and recipient updates with an old `revision`/`If-Match` value and expect `409 REVISION_CONFLICT`. Test default-address changes concurrently and verify that at most one active address per customer remains default.

Test login, registration, and verification rate limits with repeated failures. Confirm no password, OTP, session token, payment credential, Composer credential, or raw SQL/provider error appears in application logs or JSON responses.

## 5. React activation gate

Keep the React island in mock mode until the following are attached to the pull request:

- OpenAPI validation output.
- Frontend DTO/Zod compatibility output.
- Feature-test output from the controlled Composer environment.
- Cross-customer authorization test output.
- Browser cookie/CSRF/CORS smoke-test output.
- BFF-to-Laravel session-exchange and assertion output.
- Migration and rollback rehearsal output.
- Staging error-rate and latency observations.

Only after approval should staging set `VITE_NWC_DATA_MODE=http` and the staging API base URL. Production activation requires a separate approval, a backup, a migration window, health checks, monitoring, and a documented rollback.
