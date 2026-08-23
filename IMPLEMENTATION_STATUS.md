# Customer Portal API Implementation Status

## Delivered

The branch `feature/customer-portal-api-v1` now contains an isolated `Modules/CustomerPortalApi` module. It is mounted under `/api/v1` and does not add routes to the existing unversioned root API, Cargo API, CustomerAppAddon API, or Laravel customer UI.

The first vertical slice includes health/readiness endpoints, customer registration, login, verification, logout, current session, customer-owned shipment list/detail, public-safe tracking, request IDs, structured success/problem envelopes, CSRF cookie/header enforcement, customer-context ownership checks, dedicated rate limiting, shipment revision migration, OpenAPI 3.1 documentation, and feature tests.

## Security behavior

The portal resolves the customer from the authenticated Laravel web session and filters shipment queries by the linked Cargo client before loading records. Browser-supplied customer IDs are not used as authorization proof. Public tracking redacts customer ID, price, and allowed actions. The new portal does not use the legacy `remember_token` API-header pattern.

The branch also removes the tracked `auth.json` file and removes secret-looking values from `.env.example`. Credentials that were previously present in the public repository must still be rotated by the repository owner because removing them from the current commit does not erase historical exposure.

## Validation

All new PHP files and feature tests pass `php -l`. The OpenAPI YAML parses successfully. Composer schema validation succeeds with a warning about an existing exact version constraint. `git diff --check` passes and the working tree is clean after commit.

The full Laravel feature suite has not run because the repository has no `composer.lock`, the Composer dependency set includes a private distribution package, and that package could not be downloaded without its authorized package credentials. No production or staging database was used.

## Commit

`0130a56 feat: add isolated customer portal API v1 foundation`

The commit currently exists on the local branch only. It has not been pushed or deployed to the remote repository.

## Next required environment

To continue beyond this first slice, the project’s normal development environment needs PHP, Composer dependencies including the authorized private package, a sanitized test/staging database, the React adapter contract files, and confirmed session/CORS deployment settings. The next implementation slices are profile/password flows, addresses/recipients, invoices/wallet, notifications, support/returns/pickups, drafts/quotes, secure files, payment intents/webhooks, and operational monitoring.
