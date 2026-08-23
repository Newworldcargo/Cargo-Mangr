# Customer Portal API Completion Status

## Current branch

The implementation is isolated on `feature/customer-portal-api-v1` in draft pull request [#25](https://github.com/Newworldcargo/Cargo-Mangr/pull/25). The latest published commit is `6bfeac5`. The `main` branch and production server have not been modified.

## Implemented API surface

The Laravel module `Modules/CustomerPortalApi` is mounted under `/api/v1` and is independent from the existing Blade/customer UI and legacy module APIs. It currently includes:

| Area | Implemented behavior |
|---|---|
| HTTP foundation | JSON success/problem envelopes, request IDs, content contract, portal CSRF cookie/header, customer rate limits, JSON authentication failures, and explicit customer ownership context. |
| Account | Session login, registration, verification, verification resend, logout, profile read/update, current-password verification, and password change. |
| Customer data | Customer-owned shipments, public tracking redaction, addresses, recipients, invoices derived from existing shipment transactions, notifications, and reference data. |
| Shipment operations | Server-side allowed-action calculation, controlled cancellation, delivery detail read/update, proof-of-delivery read, server-side drafts, expiring quote snapshots, and revision checks. |
| Service workflows | Support case list/create/detail, return request list/create/detail/cancel, and pickup current/create/cancel. |
| Financial foundation | Wallet and append-only ledger tables, customer wallet reads, wallet history, and invoice/receipt DTO mapping using integer minor-unit money. |
| Files and payments | Private file metadata/upload-intent and completion primitives with fail-closed storage checks; payment-intent persistence and status polling with fail-closed provider configuration. |
| Documentation and migrations | OpenAPI 3.1 contract, additive revision/workflow/wallet/file/payment/idempotency migrations, staging runbook, and source-level validation artifacts. |

## Verification performed

PHP syntax lint passes for all portal PHP files. The OpenAPI contract parses successfully with 42 paths and 39 schemas. Composer manifest validation and Git whitespace/diff checks pass. No production database was connected during validation.

The full Laravel feature suite and runtime route table still require the project’s authorized private Composer dependency and a sanitized isolated staging/test database. Static lint cannot prove framework boot, SQL compatibility, middleware order, browser cookie behavior, or real authorization outcomes.

## Deliberate fail-closed behavior

Payment creation returns `PAYMENT_PROVIDER_NOT_CONFIGURED` with HTTP `503` until a real payment provider adapter and webhook verifier are configured. File upload intent returns `DEPENDENCY_UNAVAILABLE` until the private object-storage disk exposes a signed upload operation. Quote amounts currently remain zero with a `pending_operations_pricing` assumption because official rates, taxes, currencies, and delivery pricing were not supplied. These values must not be treated as production pricing.

## Remaining production gates

Before merge or production activation, the team must install the authorized dependencies, run migrations against a sanitized staging database, run the full feature and contract suites, configure CORS/session settings, supply official pricing and operations policies, configure the file scanner/object store, choose and implement the payment gateway/webhook flow, and run the staged React end-to-end smoke tests. Production activation must remain a controlled, reversible release.
