# CustomerPortalApi

This module provides the first versioned API slice for the separate React customer island. It is intentionally isolated from the existing Laravel customer UI and legacy unversioned APIs.

## Current endpoints

The module exposes the following routes under `/api/v1`:

The internal BFF exchange endpoint is mounted separately at `POST /internal/bff/session-exchange`; it is not a browser-facing route and requires the configured service bearer token.

- `GET /healthz`
- `GET /readyz`
- `POST /auth/login`
- `POST /auth/register`
- `POST /auth/verify`
- `POST /auth/logout`
- `GET /session`
- `GET /shipments`
- `GET /shipments/{id}`
- `GET /public/tracking/{trackingNumber}`

Authenticated routes use the existing Laravel web session guard through a portal-specific JSON authentication middleware. Unsafe authenticated requests require the `X-CSRF-Token` header matching the session token. The response envelope includes `data`, optional `meta`, `requestId`, or a structured `error` object.

## Configuration

Set these values per environment. Do not place provider credentials or production secrets in source control.

```dotenv
CUSTOMER_PORTAL_API_ALLOWED_ORIGINS=https://portal.example.com
CUSTOMER_PORTAL_API_MAX_PER_PAGE=50
CUSTOMER_PORTAL_API_RATE_LIMIT=60
CUSTOMER_PORTAL_PUBLIC_TRACKING_RATE=30
CUSTOMER_PORTAL_COOKIE_SECURE=true
CUSTOMER_PORTAL_COOKIE_SAME_SITE=lax
CUSTOMER_PORTAL_BFF_SERVICE_TOKEN=<same-secret-as-the-private-BFF-service-token>
CUSTOMER_PORTAL_BFF_SHARED_SECRET=<separate-signing-secret>
CUSTOMER_PORTAL_BFF_SESSION_HOURS=8
```

The current branch intentionally does not modify the global CORS configuration because the BFF design keeps browser requests same-origin with the React server. Configure the React server with `NWC_BACKEND_ORIGIN=https://laravel-staging.example.com`, `NWC_BACKEND_API_PREFIX=/api`, `NWC_BFF_SERVICE_TOKEN` matching the Laravel value, `NWC_BFF_SHARED_SECRET` matching the Laravel value, and `NWC_BFF_ALLOWED_ORIGIN=https://portal-staging.example.com`. The BFF must remain the only component holding the service token. Configure and test this only in staging before enabling the React island’s HTTP mode.

## Data ownership

Customer shipment queries derive the customer from the authenticated session, resolve the linked Cargo `Client`, and filter by `shipments.client_id` before loading records. The API never accepts a browser-supplied customer ID as authorization proof. Public tracking uses a redacted resource and does not return customer ID, price, or allowed actions.

## Local validation

PHP syntax linting can run without the application database:

```bash
find Modules/CustomerPortalApi tests/Feature -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

Feature tests require the project’s Composer dependencies and an isolated test database. The repository currently references a private Composer distribution for one package, so dependency installation must be completed in the project’s normal development environment or with the authorized package credentials. Never use a production database for automated tests.

## Next implementation slices

The next slices should add the complete frontend adapter contract: profile and password workflows, addresses and recipients, invoices and wallet, notifications, support, returns, pickups, server drafts, quotes, file upload intents/completion, delivery actions, payment intents/webhooks, OpenAPI contract validation, and staging end-to-end tests. These should be added without expanding the legacy route surface.
