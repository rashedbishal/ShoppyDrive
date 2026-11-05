# Performance Audit Report

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

No load testing was done, as the directive forbids it.

## Measurements

| Page (live, compressed) | First byte | Total | Size |
|---|---|---|---|
| Home | 0.43 to 0.92s | 0.47 to 0.96s | 21,155 bytes |
| Listing | 0.43 to 0.44s | 0.47 to 0.48s | 20,624 bytes |
| Product | 0.41 to 0.42s | 0.45 to 0.46s | 17,915 bytes |

Six requests from one location, so a rough guide only. Local test: 101 queries on `/products` with 12 products and 6 variants each. Production build: 11 MB, no source maps. Home JS chain and manifest figures come from the module review of `public/build`.

| ID | Severity | Priority | Title |
|---|---|---|---|
| PERF-001 | MEDIUM | P1 | Product listing runs a query for every variant |
| PERF-003 | MEDIUM | P2 | Icon registry and admin layout load on the customer home page |
| PERF-004 | MEDIUM | P2 | Uploaded images are served at original size |
| PERF-002 | LOW | P2 | Product cards send the whole product record |
| PERF-005 | LOW | P3 | Admin sidebar count scans the orders table |
| PERF-006 | LOW | P2 | Admin SMS poll hits the app every 5 seconds per tab |
| PERF-007 | LOW | P3 | Search scans the table despite the FULLTEXT index |
| PERF-008 | LOW | P3 | Orphaned search cache files until the next deploy |
| PERF-009 | LOW | P3 | Unbounded lists in admin compose screens and Pathao sync |

## Details

### PERF-001: Product listing runs a query for every variant

12 products with 6 variants each took 101 queries on /products. A 30-variant product page costs about 90 extra, and each variant adds flash-sale lookups.

- **Severity / confidence:** MEDIUM / High. Measured
- **Files:** app/Models/ProductVariant.php:37,50-54; app/Http/Controllers/ProductListingController.php:43-49; app/Http/Controllers/HomeController.php:58,131; app/Http/Controllers/ProductController.php:24,90,112
- **Evidence:** docs/forensic-audit/tests/PerformanceAuditTest.php P01 FAILS: 101 queries.
- **Recommendation (not applied):** Eager-load variants.media and resolve flash prices in a batch.

### PERF-003: Icon registry and admin layout load on the customer home page

The home page chain is 75 JS chunks, about 367 KB gzip. Admin layouts are in the main chunk. Lucide dynamic icons account for 1,414 of the 1,723 entries in the 653 KB public manifest.

- **Severity / confidence:** MEDIUM / High (build manifest walked by module reviewer)
- **Files:** resources/js/lib/category-icons.tsx:2; resources/js/pages/Home.tsx:39; resources/js/app.tsx:10,12
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Live manifest size 653,360 bytes confirmed.
- **Recommendation (not applied):** Lazy-load icons and the admin layout.

### PERF-004: Uploaded images are served at original size

A 5 MB photo can show as a small card thumbnail. No resizing library and no srcset.

- **Severity / confidence:** MEDIUM / High for the code. Live sizes not measured
- **Files:** app/Http/Controllers/Admin/ProductController.php; app/Http/Controllers/Admin/BlogController.php:47; app/Http/Requests/Admin/ProductRequest.php:17-18
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Lazy loading is present.
- **Recommendation (not applied):** Generate WebP thumbnails.

### PERF-002: Product cards send the whole product record

Live home HTML is 145 KB (21 KB compressed) and a product page 82 KB (18 KB compressed), with first byte around 0.42s, so this is modest today. Cards need about 10 fields but get all columns plus relations. Ziggy and its script add about 52 KB (estimate). cost_price is correctly hidden, so this is size only.

- **Severity / confidence:** LOW / High for the code. Live split estimated
- **Files:** app/Http/Controllers/HomeController.php:154,162-163; app/Support/CartManager.php; resources/views/app.blade.php:52
- **Evidence:** Live sizes measured 2026-11-05.
- **Recommendation (not applied):** Add a slim card resource and filter Ziggy.

### PERF-005: Admin sidebar count scans the orders table

is_seen has no index, and the count runs on every admin request. The reviewer also suspected whereDate, but MariaDB 11.8 uses the created_at index for it, so that part was dropped as a false positive.

- **Severity / confidence:** LOW / High. EXPLAIN on MariaDB 11.8.3
- **Files:** app/Http/Controllers/Admin/DashboardController.php:30-33; app/Http/Middleware/HandleInertiaRequests.php:168-170
- **Evidence:** docs/forensic-audit/evidence/phase4/explain_mariadb.txt: DATE(created_at) type range; is_seen type ALL. Table nearly empty, so plans show shape only.
- **Recommendation (not applied):** Add an index on is_seen.

### PERF-006: Admin SMS poll hits the app every 5 seconds per tab

Three tabs make 2,160 requests an hour even when hidden.

- **Severity / confidence:** LOW / High for the code. Host limits unverified
- **Files:** resources/js/hooks/useSmsPolling.ts:13,104; resources/js/layouts/admin-layout.tsx:592
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Pause when hidden and lengthen the interval.

### PERF-007: Search scans the table despite the FULLTEXT index

MATCH alone uses the index, but adding OR sku LIKE gives a full scan.

- **Severity / confidence:** LOW / High for plan shape. EXPLAIN on a nearly empty table
- **Files:** app/Support/ProductSearchQuery.php:70,85; app/Http/Controllers/SearchController.php:48-52
- **Evidence:** docs/forensic-audit/evidence/phase4/explain_mariadb.txt: with OR type ALL; without OR type fulltext.
- **Recommendation (not applied):** Split the query.

### PERF-008: Orphaned search cache files until the next deploy

Each distinct matched query leaves a file. The version-stamp flush leaves old files. This narrows the claim made by my own search fix that junk cannot fill the disk.

- **Severity / confidence:** LOW / Medium
- **Files:** app/Support/SearchSuggestionCache.php:49,58,65-67
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Sweep expired files.

### PERF-009: Unbounded lists in admin compose screens and Pathao sync

Newsletter compose loads all active products with media. Pathao sync loads all in-flight orders and calls one by one with 30s timeouts.

- **Severity / confidence:** LOW / High
- **Files:** app/Http/Controllers/Admin/NewsletterController.php:72-75; app/Console/Commands/SyncPathaoOrderStatuses.php:37-44
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Use search pickers and capped batches.

