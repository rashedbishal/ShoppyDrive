-- =============================================================================
-- SHAPPYDRIVE_PRODUCTION_READONLY_CHECKS.sql
-- Companion to docs/SHAPPYDRIVE_PROJECT_HANDOFF_2026-11-03.md (Production checks).
--
-- STATUS (2026-11-03):
--   * Verified: every statement below was executed, exactly as written, on
--     MySQL 8.0.46 against the DISPOSABLE local database
--     `shoppydrive_test_release_20261103` (all 132 migrations applied).
--     All 21 statements ran without error, and a write attempted inside the
--     READ ONLY transaction was refused (SQLSTATE 25006 / error 1792).
--   * NOT yet executed on production. The USER runs these in the production
--     database console; Claude never connects to production.
--   * Read-only by design: only SELECT plus session-only settings. Do not add
--     INSERT/UPDATE/DELETE/DDL, and do not run migrations from here.
--   * No customer PII or credentials: queries return counts, product ids/slugs,
--     order numbers, statuses, prices and quantities only. Never select name,
--     email, phone, address, payload, exception or token columns.
--   * Schema names were checked against the migrated schema (not guessed).
--
-- The block between the two "VERBATIM" markers is the verified file, unchanged.
-- The result interpretation guide follows it, as comments.
-- =============================================================================

-- ----- BEGIN VERBATIM (verified 2026-11-03) ----------------------------------
-- ShoppyDrive — production read-only verification (MySQL 8.0)
-- Every statement is a SELECT, or a session-only setting. Nothing is written.
-- Run in the production DB console, connected to the ShoppyDrive database.

-- 0. Session safety: refuse writes for this transaction, and read TIMESTAMP
--    columns the way Laravel wrote them (Laravel uses the server default zone).
SELECT @@session.time_zone AS session_tz, @@global.time_zone AS global_tz;
SET SESSION time_zone = @@global.time_zone;
START TRANSACTION READ ONLY;

-- 1. Active products with a missing or future publish date (FSA-05).
SELECT
  COALESCE(SUM(published_at IS NULL), 0)            AS active_without_publish_date,
  COALESCE(SUM(published_at > UTC_TIMESTAMP()), 0)  AS active_scheduled_in_future
FROM products
WHERE is_active = 1 AND deleted_at IS NULL;

-- 1b. Only if 1 is non-zero: identifiers to fix (no customer data).
SELECT id, slug, published_at
FROM products
WHERE is_active = 1 AND deleted_at IS NULL
  AND (published_at IS NULL OR published_at > UTC_TIMESTAMP())
ORDER BY id
LIMIT 100;

-- 2. Cart rows FSA-05 will drop at checkout (product live in DB but not publicly visible).
SELECT
  COUNT(*)                                                         AS affected_cart_rows,
  COUNT(DISTINCT ci.cart_id)                                       AS affected_carts,
  COALESCE(SUM(p.is_active = 0), 0)                                AS rows_inactive_product,
  COALESCE(SUM(p.is_active = 1 AND p.published_at IS NULL), 0)     AS rows_active_no_publish_date,
  COALESCE(SUM(p.is_active = 1 AND p.published_at > UTC_TIMESTAMP()), 0) AS rows_active_future_publish
FROM cart_items ci
JOIN products p ON p.id = ci.product_id
WHERE p.deleted_at IS NULL
  AND (p.is_active = 0 OR p.published_at IS NULL OR p.published_at > UTC_TIMESTAMP());

-- 3. Historical order lines whose variant belongs to a different product (FSA-01).
--    Includes trashed orders. Lines whose variant was later deleted cannot be checked.
SELECT
  COUNT(*)                    AS mismatched_order_items,
  COUNT(DISTINCT oi.order_id) AS affected_orders
FROM order_items oi
JOIN product_variants v ON v.id = oi.variant_id
WHERE oi.product_id IS NOT NULL
  AND v.product_id <> oi.product_id;

-- 3b. Only if 3 is non-zero: minimal detail for review (order number, no customer data).
SELECT o.order_number, o.status, o.payment_status, o.created_at,
       oi.id AS order_item_id, oi.product_id, oi.variant_id,
       v.product_id AS variant_belongs_to_product, oi.price, oi.quantity
FROM order_items oi
JOIN product_variants v ON v.id = oi.variant_id
JOIN orders o ON o.id = oi.order_id
WHERE oi.product_id IS NOT NULL
  AND v.product_id <> oi.product_id
ORDER BY o.created_at DESC
LIMIT 200;

-- 4. Cart rows pairing a product with another product's variant (FSA-01).
SELECT
  COUNT(*)                   AS mismatched_cart_rows,
  COUNT(DISTINCT ci.cart_id) AS affected_carts
FROM cart_items ci
JOIN product_variants v ON v.id = ci.variant_id
WHERE v.product_id <> ci.product_id;

-- 4b. Unpaid, unexpired mobile-banking checkouts containing such a pair
--     (after deploy these would fail at payment submission).
SELECT COUNT(DISTINCT pc.id) AS open_pending_checkouts_with_mismatch
FROM pending_checkouts pc
JOIN JSON_TABLE(pc.checkout_data, '$.items[*]'
       COLUMNS (product_id BIGINT PATH '$.product_id', variant_id BIGINT PATH '$.variant_id')) AS it
JOIN product_variants v ON v.id = it.variant_id
WHERE pc.order_number IS NULL
  AND pc.expires_at > UTC_TIMESTAMP()
  AND v.product_id <> it.product_id;

-- 5. Migration status.
SELECT COUNT(*) AS migrations_recorded, MAX(batch) AS latest_batch FROM migrations;

SELECT migration, batch
FROM migrations
WHERE migration >= '2026_10_30_073622'
ORDER BY migration;

-- 5b. MED-06 migration specifically.
SELECT migration, batch
FROM migrations
WHERE migration = '2026_10_31_223219_add_unique_indexes_to_variant_skus_and_attribute_values';

-- 6. Unique indexes on product_variants and attribute_values.
SELECT table_name, index_name,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS index_columns
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name IN ('product_variants', 'attribute_values')
  AND non_unique = 0
GROUP BY table_name, index_name
ORDER BY table_name, index_name;

-- 6b. Only if an index from 6 is missing: duplicates blocking it (counts only).
SELECT
  (SELECT COUNT(*) FROM (SELECT sku FROM product_variants WHERE sku IS NOT NULL
                         GROUP BY sku HAVING COUNT(*) > 1) d)                   AS duplicate_variant_skus,
  (SELECT COUNT(*) FROM (SELECT attribute_id, value FROM attribute_values
                         GROUP BY attribute_id, value HAVING COUNT(*) > 1) d)   AS duplicate_attribute_values;

-- 7. Queue tables exist.
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('jobs', 'failed_jobs', 'job_batches')
ORDER BY table_name;

-- 8. Newsletter batches still processing (run before every deploy).
SELECT COUNT(*) AS newsletter_batches_in_progress
FROM job_batches
WHERE name LIKE 'newsletter:%'
  AND finished_at IS NULL
  AND cancelled_at IS NULL;

-- 8b. Only if 8 is non-zero: progress per batch (no recipients, no payloads).
SELECT id, name, total_jobs, pending_jobs, failed_jobs,
       pending_jobs - failed_jobs AS chunks_still_to_send,
       FROM_UNIXTIME(created_at)  AS queued_at
FROM job_batches
WHERE name LIKE 'newsletter:%'
  AND finished_at IS NULL
  AND cancelled_at IS NULL
ORDER BY created_at;

-- Q1. Queue health: waiting jobs per queue (no payloads returned).
SELECT queue,
       COUNT(*)                        AS jobs_waiting,
       COALESCE(SUM(reserved_at IS NOT NULL), 0) AS currently_reserved,
       COALESCE(SUM(attempts > 0), 0)  AS retried_at_least_once,
       FROM_UNIXTIME(MIN(available_at)) AS oldest_available_at
FROM jobs
GROUP BY queue;

-- Q2. Waiting and failed jobs by job class (class name only, no payload content).
SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) AS job_class, COUNT(*) AS waiting
FROM jobs
GROUP BY job_class
ORDER BY waiting DESC;

SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, '$.displayName')) AS job_class,
       COUNT(*) AS failed, MAX(failed_at) AS last_failed_at
FROM failed_jobs
GROUP BY job_class
ORDER BY last_failed_at DESC;

COMMIT;
-- ----- END VERBATIM ----------------------------------------------------------

-- =============================================================================
-- RESULT INTERPRETATION GUIDE
-- (safe result / what non-zero means / stop the deploy? / corrective action)
-- =============================================================================
--
-- 0  Session safety
--    Safe: session_tz equals global_tz after the SET; START TRANSACTION READ ONLY succeeds.
--    Stop: YES if the READ ONLY transaction cannot be started; run nothing further.
--    Why the SET: products.published_at is TIMESTAMP; Laravel (app timezone UTC)
--    connects with the server default zone (SYSTEM), so comparisons use UTC_TIMESTAMP().
--
-- 1  Active products with missing/future published_at (FSA-05)
--    Safe: 0 and 0, or only launches deliberately scheduled for later.
--    Non-zero: products live by direct link and orderable today become 404 and
--    unbuyable after deploy.
--    Stop: YES if any of them should stay sellable.
--    Action: re-save each product in admin with Active on (sets published_at); list via 1b.
--
-- 2  Cart rows FSA-05 will drop
--    Safe: any count is acceptable (dropping hidden products at checkout is intended).
--    Stop: no. Action: none; fix query-1 products first if they are included.
--
-- 3  Order lines whose variant belongs to another product (FSA-01)
--    Safe: 0 / 0.
--    Non-zero: possible past exploitation of FSA-01 (variant of product B shipped
--    at product A's price) or data corruption.
--    Stop: no (the deploy closes the hole).
--    Action: review with 3b; compare price with that variant's real price; hold
--    unshipped orders. Limitation: lines whose variant was later deleted
--    (variant_id NULL) cannot be checked.
--
-- 4  Mismatched cart variant rows
--    Safe: 0. Stop: no. Action: none; the new code drops them automatically.
--
-- 4b Open unpaid mobile-banking checkouts containing a mismatched pair
--    Safe: 0.
--    Non-zero: if paid after deploy, payment submission fails (paid-but-no-order,
--    an FSA-07 shape).
--    Stop: recommended to wait until they expire (2-hour expiry), or note them
--    for manual reconciliation.
--
-- 5  Migration status
--    Safe: migrations_recorded = 132, and the second query lists exactly these 14:
--      2026_10_30_073622_create_courier_submissions_table
--      2026_10_30_073623_backfill_legacy_courier_submissions
--      2026_10_30_085856_add_returned_consignment_to_courier_submissions_table
--      2026_10_30_085857_preserve_courier_submissions_when_orders_are_deleted
--      2026_10_30_085859_add_unique_consignment_indexes_to_orders_table
--      2026_10_30_134850_add_soft_deletes_to_coupons_table
--      2026_10_30_134851_protect_order_history_foreign_keys
--      2026_10_30_134853_create_order_deletion_audits_table
--      2026_10_30_214609_create_order_refunds_table
--      2026_10_30_214610_backfill_order_refunds_from_return_requests
--      2026_10_30_214611_create_courier_delivery_evidence_tables
--      2026_10_30_214612_make_users_points_balance_signed
--      2026_10_31_193742_add_points_reserved_to_pending_checkouts_table
--      2026_10_31_223219_add_unique_indexes_to_variant_skus_and_attribute_values
--    Lower count: the missing migrations run automatically via `migrate --force`
--    on the next deploy, with no backup (accepted risk C1).
--    Stop: YES if fewer than 132. Review the missing migrations first. The six
--    local commits add no migrations of their own.
--
-- 5b MED-06 migration row
--    Safe: 1 row. Missing: same as 5.
--
-- 6  Unique indexes. Safe result is exactly these 5 rows:
--      attribute_values | attribute_values_attribute_id_value_unique | attribute_id,value
--      attribute_values | PRIMARY                                    | id
--      product_variants | PRIMARY                                    | id
--      product_variants | product_variants_product_color_size_unique | product_id,color_value_id,size_value_id
--      product_variants | product_variants_sku_unique                | sku
--    A missing *_sku_unique or *_attribute_id_value_unique index means MED-06
--    skipped it because duplicates existed (by design).
--    Stop: no.
--    Action: run 6b. Search the production log storage/logs/laravel.log for
--    "MED-06: skipped adding". Read-only server command:
--    `php artisan catalog:check-variant-attribute-duplicates`. Cleaning up is a
--    separate, approved task.
--
-- 7  Queue tables
--    Safe: 3 rows (failed_jobs, job_batches, jobs).
--    Stop: only if job_batches is missing (newsletter sending would error).
--    Action: a reviewed migration; until then, don't send newsletters.
--
-- 8  Newsletter batches in progress (run before EVERY deploy)
--    Safe: 0.
--    Non-zero: a newsletter is mid-delivery; the deploy's `rsync --delete`
--    would wipe its attachments in storage/app/private.
--    Stop: YES if > 0. Follow the newsletter checklist in the handoff document.
--
-- Q1/Q2 Queue health
--    Safe: few or no waiting jobs; oldest_available_at within minutes; failures explainable.
--    A stale oldest_available_at means the cron/schedule:run or app:drain-queue is not running.
--    Action: check `crontab -l` and `php artisan schedule:list` on the server; look
--    at Admin > Failed jobs.
--
-- Queue configuration (server shell, prints only the requested keys; no .env access):
--    php artisan config:show queue.default                -> database
--    php artisan config:show queue.connections.database   -> driver database, connection null,
--                                                            table jobs, queue default,
--                                                            retry_after 90, after_commit false
--    php artisan schedule:list   -> includes "* * * * *  php artisan app:drain-queue"
--                                   and (after deploy) "0 21 * * *  php artisan newsletter:prune-attachments"
--    retry_after (90) must stay above app:drain-queue's 45-second per-job timeout.
-- =============================================================================
