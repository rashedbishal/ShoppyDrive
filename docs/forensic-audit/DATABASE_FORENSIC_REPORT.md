# Database Forensic Report

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

Checked with read-only `information_schema` queries on the local dev database, plus `EXPLAIN` on a disposable MariaDB 11.8.3. Production was never queried.

| ID | Severity | Priority | Title |
|---|---|---|---|
| DB-002 | MEDIUM | P2 | No retention or pruning for growing tables, including personal data |
| DB-001 | LOW | P2 | Brand delete can hard-delete soft-deleted products through the cascade |
| DB-004 | LOW | P3 | Expired rate-limit rows pile up in the cache table |
| DB-003 | INFO | P3 | Some uniqueness relies on application code only |
| DB-005 | INFO | P3 | Search index and enums depend on the driver being named mysql |

## Checked and clean

- All money columns are `decimal(10,2)`. Critical comparisons use integer poisha.
- `orders.user_id`, `payments.order_id`, `return_requests.*`, `coupon_uses.*`, `order_refunds.order_id` are RESTRICT. All tables have primary keys and use InnoDB with utf8mb4.
- Category, sub-category and brand deletes refuse while live products exist, so the cascades are a backstop issue only (DB-001).
- `received_sms` has a unique key on provider and transaction ID.
- Migrations ran on MariaDB 11.8.3 during race and audit runs without errors.
- The six `2026_11_*` migrations were reviewed. Data loss in the flash limit clear and `per_user_limit` down is documented and intentional.

## Details

### DB-002: No retention or pruning for growing tables, including personal data

incomplete_orders (guest name and phone), received_sms (raw text), activity_log, failed_jobs, job_batches and guest carts grow forever. activity log retention is set to 365 days, but activitylog:clean never runs. If the forwarder phone relays all SMS, unrelated personal messages and OTPs are stored too (filter unverified).

- **Severity / confidence:** MEDIUM / High
- **Files:** routes/console.php; config/activitylog.php:16; app/Console/Commands/ClearResetsCommand.php:20; database/migrations (create_incomplete_orders_table):22
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Decide periods, then schedule pruning.

### DB-001: Brand delete can hard-delete soft-deleted products through the cascade

The brand check ignores trashed products, and the FK cascades. Reviews, stock history and variants of those products would go. No current screen creates trashed products, but older rows may exist.

- **Severity / confidence:** LOW / Medium. Needs a production count
- **Files:** app/Http/Controllers/Admin/BrandController.php:82,100,123; database/migrations (create_products_table):22; docs/forensic-audit/evidence/phase4/schema_checks.txt
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Live delete paths otherwise refuse while products exist.
- **Recommendation (not applied):** Count withTrashed and change the FK to RESTRICT.

### DB-004: Expired rate-limit rows pile up in the cache table

Each new IP or user key leaves rows only removed if read again.

- **Severity / confidence:** LOW / Medium. Production limiter store unverified
- **Files:** config/cache.php:32; .github/workflows/deploy.yml:258
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Schedule a delete of expired rows.

### DB-003: Some uniqueness relies on application code only

coupon_uses per order, cart_items, carts.user_id, flash_sale_items and payments.transaction_id have no unique constraint. Call-site guards and the passing coupon and cart race tests cover the main paths.

- **Severity / confidence:** INFO / High
- **Files:** docs/forensic-audit/evidence/phase4/schema_checks.txt
- **Evidence:** Read-only schema query on dev.
- **Recommendation (not applied):** Add constraints in a planned batch after checking data.

### DB-005: Search index and enums depend on the driver being named mysql

Seven checks test getDriverName() === "mysql". If production used the mariadb driver name, FULLTEXT and enum changes would be skipped.

- **Severity / confidence:** INFO / Medium. Production DB_CONNECTION unverified
- **Files:** app/Support/ProductSearchQuery.php; 7 migrations
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Treat mariadb the same.

