# Admin Global Search

The dropdown uses indexed prefix lookups for shipment codes, receipt numbers and customer names/emails, plus shipment IDs and normalized primary/secondary shipment phone numbers. Exact shipment codes rank first. The result is still a shipment or consignment, not a customer-profile directory.

Zambian local, international and nine-digit phone forms resolve to the same search variants. Additive virtual phone columns remove common formatting characters; the database maintains them even for direct SQL imports. Original phone fields are unchanged. Non-Zambian numbers are not assigned an inferred country code. Multiple numbers embedded in a single legacy field may still require the full search page.

Each dropdown category returns at most three records and a has-more flag. Responses are cached for ten seconds per viewer, category permissions, locale and application URL. Permissions are checked before cache access. Newly changed records can take up to ten seconds to appear in quick search. The full page is uncached and uses 20-result simple pagination per category, without expensive total-count queries.

Quick shipment search deliberately does not fall back to scanning every shipment when there is no indexed match. The full results page preserves substring/multiword searches across the original fields and adds customer names/emails, normalized phones and receipts. Consignment keyword search retains its existing fields. Broad full-page keyword searches still scan data; full-text indexing or a queued external search index remains the next step if measured demand warrants it.

Browser requests use the existing 300 ms debounce plus AbortController and a generation check. Aborting a browser request does not guarantee an already-running database query is cancelled. Result labels are inserted as text, URLs must be same-origin HTTP(S), and keyboard navigation supports arrows and Escape. The full-page search form is usable on mobile.

Access remains the existing company-shared operational scope: use-global-search plus the relevant view-shipments/view-consignments permission. This change does not grant roles or expose standalone user records. A user_id filter resolves actual customer profiles and cannot fall through to ownerless shipments.

## Verification (2026-10-04)

On the production MariaDB dataset, with query-cache reads disabled, three-run median shipment-query timings were:

| Query | Previous (ms) | Indexed dropdown (ms) |
| --- | ---: | ---: |
| LE2412 | 44.52 | 1.91 |
| 0972827372 (no match) | 50.30 | 1.41 |
| Mapalo | 47.52 | 1.82 |
| Missing reference | 41.36 | 1.44 |

These are query timings, not end-to-end browser timings. The old query retrieved ten rows; the new dropdown retrieves up to four to determine whether more than three exist. EXPLAIN confirmed range scans for lookup indexes and primary-key lookups for matched shipments. The additive production migration used INPLACE/LOCK=NONE with a short metadata-lock wait; no shipment, ownership, payment or receipt values were rewritten.

Feature tests cover matching, phone updates, priority, literal wildcards, permissions/cache isolation, query bounds and pagination. Playwright exercises the rendered Blade page at desktop/mobile sizes, including stale-request cancellation, HTML-as-text rendering, unsafe-link rejection and keyboard interaction.

Run `NWC_SEARCH_PREVIEW=1 vendor/bin/phpunit --filter AdminGlobalSearchTest` to generate the fake-data Blade fixture. Then run `node tests/Browser/admin-search-uat.cjs`, setting `PLAYWRIGHT_MODULE` to an installed `@playwright/test` module when it is outside this repository. Browser tests intercept network requests; they do not change production records.
