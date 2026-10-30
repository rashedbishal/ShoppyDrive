# Courier migration recovery runbook (COURIER-SEND-RACE-001/002)

Covers two migrations that change the schema in several steps, and the one
rollback order that can leave the schema partly rolled back. Use it only when
a deploy's `php artisan migrate --force` (run by `deploy.yml`) or a manual
rollback has failed part-way.

> **Before any SCHEMA-CHANGING command below:**
> 1. Take a full database backup (and confirm it restores).
> 2. Get explicit maintenance / deployment approval for a manual schema repair.
> 3. Put the site in maintenance mode (`php artisan down`) so no admin sends
>    an order to a courier or deletes an order mid-repair.
>
> Commands are labelled **READ-ONLY** (safe to run any time) or
> **SCHEMA-CHANGING** (needs all three steps above). Nothing here deletes or
> edits order data or courier history.

## 1. Why a migration can be left half-applied

MySQL and MariaDB commit every DDL statement (`ALTER TABLE`, `CREATE INDEX`,
…) immediately; a failure later in the same migration does **not** undo the
earlier statements. Laravel records a migration in the `migrations` table only
after its whole `up()` succeeds, so a migration that failed part-way is *not*
recorded, and simply re-running `php artisan migrate` would start it again from
the first statement — which can fail because that statement already ran.

The two affected migrations each issue more than one DDL statement:

| Migration | Statements in `up()`, in order |
|---|---|
| `2026_10_30_085857_preserve_courier_submissions_when_orders_are_deleted` | (1) drop FK `courier_submissions_order_id_foreign`; (2) make `courier_submissions.order_id` nullable; (3) add FK `courier_submissions_order_id_foreign` … `ON DELETE SET NULL` |
| `2026_10_30_085859_add_unique_consignment_indexes_to_orders_table` | read-only duplicate preflight; then (1) drop index `orders_pathao_consignment_id_index`; (2) drop index `orders_steadfast_consignment_id_index`; (3) add unique `orders_pathao_consignment_id_unique`; (4) add unique `orders_steadfast_consignment_id_unique` |

The other courier migrations are single-statement or data-only.

## 2. Inspect the actual state first — READ-ONLY

Never repair from assumptions. Run these and compare with the tables in §3/§4.

```sql
-- READ-ONLY: which courier migrations Laravel has recorded
SELECT migration, batch FROM migrations WHERE migration LIKE '2026_10_30_%' ORDER BY id;

-- READ-ONLY: FK on courier_submissions.order_id and its delete rule
SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS rc
WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = 'courier_submissions';

-- READ-ONLY: is order_id nullable?
SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_TYPE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'courier_submissions' AND COLUMN_NAME = 'order_id';

-- READ-ONLY: consignment indexes on orders (NON_UNIQUE = 0 means unique)
SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME LIKE '%consignment_id';

-- READ-ONLY: full definitions, if in doubt
SHOW CREATE TABLE courier_submissions;
SHOW CREATE TABLE orders;
```

```bash
# READ-ONLY: duplicate consignment IDs that would block the unique indexes
php artisan courier:check-consignment-duplicates
```

Expected **fully migrated** state (as verified on the disposable test DB):
FK `courier_submissions_order_id_foreign` with `DELETE_RULE = SET NULL`;
`order_id` nullable; unique indexes `orders_pathao_consignment_id_unique` and
`orders_steadfast_consignment_id_unique`; no `…_consignment_id_index`.

## 3. Repairing `…085857_preserve_courier_submissions_when_orders_are_deleted`

Possible states when it is **not** recorded in `migrations`:

| State | FK `…_order_id_foreign` | `order_id` nullable | Meaning |
|---|---|---|---|
| A — not started | present, `CASCADE` | NO | Nothing to repair: fix the cause, `php artisan migrate`. |
| B — stopped after (1) | **missing** | NO | No FK at all. |
| C — stopped after (2) | **missing** | YES | No FK at all. |

Recommended repair: **return to state A, then let the migration run normally**
(so it is recorded and its rollback guards apply).

```sql
-- SCHEMA-CHANGING (state B or C). Only if no row has a NULL order_id:
--   READ-ONLY check first:  SELECT COUNT(*) FROM courier_submissions WHERE order_id IS NULL;   -- must be 0
ALTER TABLE courier_submissions MODIFY order_id BIGINT UNSIGNED NOT NULL;            -- state C only
ALTER TABLE courier_submissions ADD CONSTRAINT courier_submissions_order_id_foreign
    FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE;
```
```bash
# SCHEMA-CHANGING: re-run; it now starts from state A
php artisan migrate --force
```

If the NULL check is not 0 (rows already detached), do **not** force NOT NULL
and do not delete those rows. Instead complete the migration forward and
record it by hand:

```sql
-- SCHEMA-CHANGING: complete forward
ALTER TABLE courier_submissions MODIFY order_id BIGINT UNSIGNED NULL;               -- if still NOT NULL
ALTER TABLE courier_submissions ADD CONSTRAINT courier_submissions_order_id_foreign
    FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL;
-- record it in the same batch as the courier migrations already recorded
INSERT INTO migrations (migration, batch)
VALUES ('2026_10_30_085857_preserve_courier_submissions_when_orders_are_deleted',
        (SELECT b FROM (SELECT MAX(batch) AS b FROM migrations) x));
```

## 4. Repairing `…085859_add_unique_consignment_indexes_to_orders_table`

If the preflight refused, **nothing was changed** — resolve the listed
duplicates by hand (business decision per order; never by deleting orders or
history), re-run `php artisan courier:check-consignment-duplicates` until it
passes, then `php artisan migrate --force`.

If it failed *after* the preflight (e.g. a duplicate was inserted between the
preflight and the index), each column can be in any of: plain index only;
no index; unique index only. Recommended repair: **return each column to
"plain index only", then let the migration run normally.**

```sql
-- SCHEMA-CHANGING, per column, only for what §2 shows is actually present:
ALTER TABLE orders DROP INDEX orders_pathao_consignment_id_unique;     -- if present
ALTER TABLE orders ADD INDEX orders_pathao_consignment_id_index (pathao_consignment_id);         -- if missing
ALTER TABLE orders DROP INDEX orders_steadfast_consignment_id_unique;  -- if present
ALTER TABLE orders ADD INDEX orders_steadfast_consignment_id_index (steadfast_consignment_id);   -- if missing
```
```bash
php artisan courier:check-consignment-duplicates   # READ-ONLY, must pass
php artisan migrate --force                        # SCHEMA-CHANGING
```

## 5. A refused rollback can leave the unique indexes removed (N2)

`php artisan migrate:rollback` undoes the last batch newest-first:
`…085859` (unique indexes) → `…085857` (FK) → `…085856` (returned-ID columns)
→ `…073623` (backfill) → `…073622` (table). `…085859` has no guard — removing
an index loses no data — so it rolls back (unique indexes replaced by plain
ones, its `migrations` row removed). Then `…085857` **refuses** while real
courier history exists (`source != 'legacy_backfill'`), so the rollback stops
there by design: cascade deletion is *not* restored and no history is at risk.

The result is consistent but loses duplicate-consignment protection. Restore
it — the migration is simply un-recorded, so this is a normal re-run:

```bash
php artisan courier:check-consignment-duplicates   # READ-ONLY, must pass
php artisan migrate --force                        # SCHEMA-CHANGING: re-applies only …085859
```

## 6. Never delete courier history to force a rollback

The rollback guards on `…073622`, `…085856` and `…085857` refuse while
`courier_submissions` holds real send attempts, reconciliation records,
courier-returned consignment IDs or rows of deleted orders. These rows are the
only record of parcels a courier was asked to create, who resolved ambiguous
outcomes and how, and consignments the courier confirmed. Deleting them to
make a rollback pass can hide a live parcel and break later reconciliation.
If a rollback is genuinely required, export the table first, get explicit
approval, and change the guard deliberately in a reviewed commit — never by
editing data in production.
