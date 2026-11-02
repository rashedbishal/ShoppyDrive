# PAY-RETURN-001 migration recovery runbook

Covers four migrations: `2026_10_30_214609_create_order_refunds_table`,
`2026_10_30_214610_backfill_order_refunds_from_return_requests`,
`2026_10_30_214611_create_courier_delivery_evidence_tables`,
`2026_10_30_214612_make_users_points_balance_signed`. Use only when a
deploy's `php artisan migrate --force` (run by `deploy.yml`) or a manual
rollback involving one of these four has failed part-way or left an
uncertain state.

> **Before any SCHEMA-CHANGING command below:**
> 1. Take a full database backup (and confirm it restores).
> 2. Get explicit, separate human approval for a manual schema or data repair.
> 3. Put the site in maintenance mode (`php artisan down`).
>
> Commands are labelled **READ-ONLY** (safe to run any time, changes
> nothing) or **SCHEMA-CHANGING** (needs all three steps above — it writes
> to the schema, the `migrations` table, or application data). **No command
> in this runbook automatically deletes, nulls, or otherwise modifies a
> customer, order, refund, or courier evidence record — every such action
> requires a human to look at the specific rows found and decide, by hand.**

## Migration `214609_create_order_refunds_table`

A single `CREATE TABLE` statement — low intrinsic partial-failure risk (one
atomic DDL), but several *inconsistent* states are possible depending on
what else happened around it.

```sql
-- READ-ONLY: does the table exist, and is the migration recorded?
SHOW TABLES LIKE 'order_refunds';
SELECT migration, batch FROM migrations WHERE migration LIKE '%214609%';
```

| State | How to tell | Recovery |
|---|---|---|
| Table exists, migration recorded | Both queries above return a match | Nothing to do |
| Table exists, migration **not** recorded | Table exists, no `migrations` row | See "Recording a migration without running it" below — this is the one case that needs it, and it is **not** a shortcut |
| Migration recorded, table **missing** | `migrations` row exists, `SHOW TABLES` returns nothing | This should not occur under normal Laravel operation (a migration is only recorded after `up()` returns without throwing). Stop and investigate how/why the table was dropped before doing anything else — do not re-run blindly |
| Table exists but its structure doesn't match the migration | `SHOW CREATE TABLE order_refunds;` differs from the migration's `Schema::create` definition | Do not drop and recreate a table that may already hold real refund rows. A targeted, reviewed `ALTER TABLE` to add only the missing/different column(s) is the only acceptable path, after confirming `SELECT COUNT(*) FROM order_refunds;` to know what's at stake |

### Recording a migration without running it — strict requirements

This is a last-resort action, not a convenience. Before doing it:

1. **Complete schema-equivalence check, not a glance.** Run `SHOW CREATE TABLE order_refunds;` (READ-ONLY) and compare it, column by column, type by type, index by index, against the migration file's `Schema::create('order_refunds', ...)` definition. Any difference — a missing column, a different nullability, a different default — means the table is **not** equivalent, and the migration must **not** be marked as run; repair the table structure first.
2. **Correct batch determination, not a guess.** `SELECT MAX(batch) FROM migrations;` (READ-ONLY) tells you the current highest batch. The correct batch for a migration you're manually recording depends on what actually ran alongside it in the real deploy history — check `SELECT migration, batch FROM migrations ORDER BY id DESC LIMIT 20;` (READ-ONLY) and reason about which batch this migration's siblings (`214610`, `214611`, `214612`, and whatever else was in the same deploy) landed in, rather than assuming "the next batch number" is automatically correct.
3. **Explicit human approval**, separate from the backup/maintenance-mode approval above — this specific action (writing directly to the `migrations` table instead of letting Laravel do it) bypasses the one safeguard that normally guarantees "recorded" means "actually ran," so it needs its own sign-off, every time.

```sql
-- SCHEMA-CHANGING (really: a data write to Laravel's own bookkeeping table) — only after all three checks above
INSERT INTO migrations (migration, batch) VALUES ('2026_10_30_214609_create_order_refunds_table', <the batch number you determined and had approved>);
```

**Safe verification:** `SELECT COUNT(*) FROM order_refunds;` (READ-ONLY) — expect `0` immediately after a clean first run, before `214610` backfills it.

## Migration `214610_backfill_order_refunds_from_return_requests`

**A simple "eligible return count vs. backfilled count" comparison is invalid** — if a real (non-backfilled) refund already exists for a given return (because `RefundService` processed it through the new code path before this historical backfill ran), the migration's own `whereNotExists` guard correctly *skips* backfilling that one. A 1:1 count match is not the right success criterion.

```sql
-- READ-ONLY: reconciliation, not a count. Categorizes every eligible source row.
SELECT
  rr.id AS return_request_id, rr.order_id, rr.refund_amount AS source_amount,
  orf.id AS order_refund_id, orf.amount AS refund_amount, orf.is_backfilled,
  CASE
    WHEN orf.id IS NULL THEN 'MISSING'
    WHEN orf.is_backfilled = 1 AND orf.amount = rr.refund_amount THEN 'BACKFILLED_OK'
    WHEN orf.is_backfilled = 0 THEN 'PRE_EXISTING_REAL_REFUND'
    WHEN orf.amount <> rr.refund_amount THEN 'AMOUNT_MISMATCH'
    ELSE 'UNKNOWN'
  END AS reconciliation_status
FROM return_requests rr
LEFT JOIN order_refunds orf ON orf.return_request_id = rr.id
WHERE rr.status = 'refunded' AND rr.refund_amount IS NOT NULL;

-- READ-ONLY: should never return rows (return_request_id is DB-unique on order_refunds)
SELECT return_request_id, COUNT(*) FROM order_refunds
WHERE return_request_id IS NOT NULL GROUP BY return_request_id HAVING COUNT(*) > 1;
```

Only `MISSING` rows are a real problem. `PRE_EXISTING_REAL_REFUND` is correct, expected behavior — do not "fix" it by backfilling over it.

**Partial chunk completion and continuation — DATA-WRITING RETRY. Requires a verified backup and separate explicit human approval, exactly like every other action in this document — being idempotent does not exempt it.** The migration's `whereNotExists` filter is re-evaluated fresh on every run (not a saved cursor), so re-running `php artisan migrate --force` for this specific migration inserts only rows the reconciliation query above would call `MISSING`, and never touches `BACKFILLED_OK` or `PRE_EXISTING_REAL_REFUND` rows — this is genuinely idempotent, unlike `214611` below. **Idempotent is not the same as approval-exempt: it still writes real rows to `order_refunds`, so the same backup-first, explicit-approval-first precondition at the top of this document applies before running it, every time.**

**AMOUNT_MISMATCH or UNKNOWN rows found — STOP, do not act automatically.** Do not edit `order_refunds` or `return_requests` directly. Present the exact rows to a human for investigation and an explicit decision. (Read-only to reach this point; any resolution from here is MANUAL DATA REPAIR — SCHEMA/DATA-CHANGING, requires a verified backup and separate explicit human approval.)

## Migration `214611_create_courier_delivery_evidence_tables`

**Not atomic — 3 separate `CREATE TABLE` statements plus 2 separate backfill loops**, verified directly from the migration's source, each with a *different* resumability story. **Do not assume either backfill loop can be safely resumed by re-running it — their actual insertion guards were checked line by line, and they are not the same.**

```sql
-- READ-ONLY: which of the 3 tables exist
SHOW TABLES LIKE 'courier_verification_readiness';
SHOW TABLES LIKE 'courier_delivery_evidence';
SHOW TABLES LIKE 'order_delivery_verifications';
```

| Component | Order | Detection (READ-ONLY) | Resumable by re-running? |
|---|---|---|---|
| `courier_verification_readiness` table | 1st | `SHOW TABLES LIKE ...` above | N/A — single DDL, exists or doesn't |
| `courier_delivery_evidence` table | 2nd | `SHOW TABLES LIKE ...` above | N/A |
| `order_delivery_verifications` table | 3rd | `SHOW TABLES LIKE ...` above | N/A |
| Pathao/Steadfast order-column backfill (1st backfill loop) | 4th | See below | **No — verified unsafe.** `legacyRow()` is called with `historyId: null` for every row this loop inserts, and `order_status_history_id`'s unique index allows unlimited `NULL`s. **There is no deduplication guard of any kind on this loop.** Re-running it after a partial completion inserts a second, duplicate `legacy_partial` evidence row for every order already processed, silently — no error, no warning |
| `order_status_histories` regex backfill (2nd backfill loop) | 5th, last | See below | **No — but fails loudly instead of duplicating.** This loop passes the real `$row->id` as `historyId`, and `order_status_history_id` **is** unique — re-processing a row already backfilled throws a unique-constraint violation, aborting the chunk. It will not silently duplicate, but a blind re-run still fails rather than cleanly continuing, since there is no saved cursor and `chunkById` always starts from the first matching source row |

```sql
-- READ-ONLY: how far the first (unguarded) loop actually got, per provider
SELECT COUNT(*) AS pathao_backfilled FROM courier_delivery_evidence
  WHERE source='legacy_backfill' AND provider='pathao' AND order_status_history_id IS NULL;
SELECT COUNT(*) AS pathao_eligible FROM orders
  WHERE LOWER(COALESCE(pathao_order_status,'')) LIKE '%partial%';
-- Repeat with provider='steadfast' and steadfast_status for the Steadfast half.

-- READ-ONLY: how far the second (guarded) loop got
SELECT COUNT(*) AS history_backfilled FROM courier_delivery_evidence
  WHERE source='legacy_backfill' AND order_status_history_id IS NOT NULL;
```

### The two loops can legitimately overlap — read this before treating any count mismatch as a failure

Both loops write rows with `source='legacy_backfill'` for the **same underlying real-world events**, from two different signals: the first loop reads the order's own `pathao_order_status`/`steadfast_status` column; the second reads `order_status_histories.notes`. **An order can trigger both.** If an order's status column says "partial" *and* it has a matching history note (a realistic, common case — the status column and the history note are usually written together when a courier reports a partial delivery), that one real event produces **two separate evidence rows** — one from each loop — by the migration's own design (evidence is deliberately append-only and not deduplicated across loops; this is not a bug to fix).

**Why comparing `pathao_backfilled`/`steadfast_backfilled` against `pathao_eligible`/`steadfast_eligible` in isolation can mislead you:** those two queries only tell you about the *first* loop's own coverage — they say nothing about whether a given order's partial event is *also* represented by the second loop, and they cannot, by themselves, tell you whether a shortfall is a genuine gap or simply evidence that hasn't needed a second row yet. The reverse is not true, though: the first loop's eligibility query above is self-contained and *does* reliably tell you whether the first loop itself is complete, because it only depends on the `orders` table, not on what the second loop has or hasn't done. A shortfall here is real and specific to the first loop.

**What to check before declaring either loop incomplete:**
```sql
-- READ-ONLY: orders whose first-loop evidence is missing — the authoritative
-- check for the first loop, independent of anything the second loop did
SELECT o.id, o.pathao_order_status, o.steadfast_status FROM orders o
WHERE (LOWER(COALESCE(o.pathao_order_status,'')) LIKE '%partial%'
        AND NOT EXISTS (SELECT 1 FROM courier_delivery_evidence e WHERE e.order_id=o.id AND e.provider='pathao' AND e.source='legacy_backfill' AND e.order_status_history_id IS NULL))
   OR (LOWER(COALESCE(o.steadfast_status,'')) LIKE '%partial%'
        AND NOT EXISTS (SELECT 1 FROM courier_delivery_evidence e WHERE e.order_id=o.id AND e.provider='steadfast' AND e.source='legacy_backfill' AND e.order_status_history_id IS NULL));
```
Any row returned here is a genuine first-loop gap for that specific order — not an artifact of the second loop's independent coverage. **Distinguishing legitimate overlap from a genuine missing backfill means checking per-order presence/absence (as above), never comparing the two loops' aggregate counts against each other.**

**If the query above returns rows — the first loop did not finish for those orders.** Because it has no dedup guard, **do not re-run the migration or this loop as-is.** Safe recovery requires manually constructing a one-time, reviewed insert that processes only the exact orders the query above returned. **This repair is MANUAL DATA REPAIR — SCHEMA/DATA-CHANGING, requires a verified backup and separate explicit human approval.** It is not a command this runbook can safely hand you as a blind "run this."

**If the second loop's history-backfill count looks incomplete:** re-running the migration (or this specific loop) will throw on the first already-processed row, aborting immediately — this is the loop failing *for* you, not silently (see the eligibility-verification method below for how to confirm what's actually missing, since the raw `history_backfilled` count alone, per M3 below, is not sufficient evidence either way). Resolve by determining the exact `order_status_histories.id` cutoff already reached (`SELECT MAX(order_status_history_id) FROM courier_delivery_evidence WHERE source='legacy_backfill' AND order_status_history_id IS NOT NULL;`, READ-ONLY) and manually continuing from strictly after that point. **This repair is MANUAL DATA REPAIR — SCHEMA/DATA-CHANGING, requires a verified backup and separate explicit human approval** — again, a reviewed, one-time action, never a blind re-run.

**Whether re-running the whole migration is safe, overall: no.** If any of the 3 tables already exists, the relevant `Schema::create` statement fails outright (table already exists). This migration cannot be re-run end-to-end once partially applied — manual, targeted repair (missing tables created individually with the migration's exact DDL, missing backfill rows inserted individually per the above) is required every time, and every one of those repairs is **SCHEMA/DATA-CHANGING — requires a verified backup and separate explicit human approval.** None of it is a scripted one-liner.

### Verifying the second loop's true eligibility — why no exact SQL equivalent is offered

The second loop's real eligibility is **not** just `LOWER(COALESCE(notes,'')) LIKE '%partial%'` — a row also needs `created_at` past one of two hardcoded cutoffs (`MAPPER_EARLIEST`/`LEGACY_WEBHOOK_EARLIEST`) *and* to match one of two PHP `preg_match` patterns (PCRE). **No exact SQL equivalent for the regex portion is provided here, deliberately.** MySQL 8's `REGEXP_LIKE` uses ICU's regex engine, not PCRE — the two are close but not guaranteed identical (anchoring, case-insensitivity handling, and character-class edge cases can differ), and this has not been empirically verified side by side against real data. Presenting a hand-translated SQL regex as if it were authoritative would risk a false "complete" or false "incomplete" verdict based on a dialect mismatch, not a real answer — exactly the kind of approximate evidence this document should not present as fact.

**The safest available verification method: re-run the migration's own exact PHP logic, read-only, via Artisan Tinker — never a SQL re-implementation.**
```bash
# READ-ONLY: counts how many order_status_histories rows the second loop
# would consider eligible, using the exact same conditionals as the
# migration (copied verbatim, not re-expressed in SQL). Writes nothing.
php artisan tinker --execute='
$mapperEarliest = "2026-10-18 12:40:16";
$legacyWebhookEarliest = "2026-10-18 10:19:12";
$eligible = 0;
DB::table("order_status_histories")
    ->whereRaw("LOWER(COALESCE(notes, ?)) LIKE ?", ["", "%partial%"])
    ->orderBy("id")
    ->chunkById(200, function ($rows) use (&$eligible, $mapperEarliest, $legacyWebhookEarliest) {
        foreach ($rows as $row) {
            $created = (string) $row->created_at;
            if ($created >= $mapperEarliest && preg_match("/^(Pathao|Steadfast) reported \"([^\"]*partial[^\"]*)\"/i", (string) $row->notes) === 1) {
                $eligible++;
            } elseif ($created >= $legacyWebhookEarliest && preg_match("/^Steadfast status updated to \"([^\"]*partial[^\"]*)\"\./i", (string) $row->notes) === 1) {
                $eligible++;
            }
        }
    });
echo "eligible: {$eligible}\n";
'
```
Compare this `eligible` count against `history_backfilled` from the query above. **This is authoritative because it runs the same PHP code path the migration itself runs, not an approximation in a different language — it is also read-only: it only counts, it never writes.** If the two counts differ, the gap is real; resolve it via the manual, per-row repair described above, never a blind re-run.

## Migration `214612_make_users_points_balance_signed`

```sql
-- READ-ONLY: required precondition — must be 0 before converting
SELECT COUNT(*) FROM users WHERE points_balance > 2147483647;

-- READ-ONLY: verify the actual current column definition, don't assume it
SELECT COLUMN_TYPE FROM information_schema.columns
WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'points_balance';
```

The precondition matters because unsigned INT's maximum (4,294,967,295)
exceeds signed INT's maximum (2,147,483,647) — a value above that threshold
would be truncated or corrupted by a naive type change.

**Why SUM equality alone is insufficient:** a SUM can mask two offsetting
errors (one value too high, another too low, by the same amount), and —
more importantly — even if it *does* differ, a SUM tells you something is
wrong without telling you **which row**. If the precondition above
genuinely holds, every value is bit-identical in both representations and
the SUM should trivially match regardless — making a SUM check mostly a
sanity check on the precondition, not real proof of row-level
value-preservation.

**Stronger, row-by-row verification:**

```sql
-- READ-ONLY-equivalent snapshot, taken BEFORE migrating (a literal, keyed
-- copy, not an aggregate) — this creates a new, disposable table, not a
-- change to any existing one
CREATE TABLE points_balance_premigration_snapshot AS SELECT id, points_balance FROM users;

-- READ-ONLY, run AFTER migrating: any row returned is a genuine, individually-identified discrepancy
SELECT s.id, s.points_balance AS before_value, u.points_balance AS after_value
FROM points_balance_premigration_snapshot s
JOIN users u ON u.id = s.id
WHERE s.points_balance <> u.points_balance;

-- Cleanup once verification passes — SCHEMA-CHANGING only in the narrow
-- sense of dropping the disposable snapshot table created above, not any
-- production data
DROP TABLE points_balance_premigration_snapshot;
```

**Post-migration verification:** the row-by-row query above returns zero
rows, and the column-type query confirms `int` (signed), not `int unsigned`.

## General recovery requirements

- **Preconditions:** a verified, restore-tested backup; explicit, separate human approval; maintenance mode — for any SCHEMA-CHANGING action in this document, no exceptions.
- **Backup requirements:** a full database backup, taken immediately before attempting any repair, restored to a *separate* disposable database and spot-checked before being trusted.
- **Failure classification:** distinguish "nothing happened yet" (often safe to retry — `214609`, `214610`) from "partially applied with no guard" (`214611`'s first backfill loop — never safe to blindly retry) from "partially applied with a loud guard" (`214611`'s second backfill loop — retry fails safely but doesn't resume) from "data-inconsistent" (`214610`'s `AMOUNT_MISMATCH`/`UNKNOWN`, `214612`'s row-by-row discrepancies).
- **Migration-state inspection:** `php artisan migrate:status` plus the specific detection queries above for each migration — never assume from the `migrations` table alone, since a crashed run may not have recorded accurately.
- **Data-integrity verification:** the reconciliation query (`214610`) and the row-by-row snapshot comparison (`214612`) specifically — neither migration's integrity can be confirmed by a simple count.
- **Safe retry criteria — "idempotent" is not the same as "approval-exempt."** `214609` safe to retry only if the table doesn't yet exist. `214610` is idempotent by design (verified against the migration's own `whereNotExists` guard) and logically safe to retry, **but retrying it still writes real rows to `order_refunds` and therefore still requires the same backup-and-approval gate as everything else in this document — idempotency changes the risk, not the approval requirement.** `214611` **not** safely retryable once any of its 3 tables exists or either backfill loop has started — requires manual, targeted repair every time, each one SCHEMA/DATA-CHANGING. `214612` safe to retry only before it has started (a single statement — it either fully succeeded or never ran).
- **Manual recovery procedures:** per-migration, above — every one of them routes an actual problem to "stop, present the exact rows to a human, wait for an explicit decision," never to an automatic fix.
- **Human approval requirements:** every SCHEMA-CHANGING action, without exception, per this project's standing rule — including the "record a migration without running it" action, which needs its own explicit, separate approval beyond the general backup/maintenance-mode approval.
- **Post-recovery verification:** the specific verification query for whichever migration was repaired, re-run and confirmed clean before considering the incident closed.
- **No automatic deletion, nulling, or modification of any customer, order, refund, or courier evidence record is proposed anywhere in this document.** Every detection query above that finds a problem ends in "a human looks at these specific IDs and decides" — never in a follow-up write.

## Remaining uncertainty, stated plainly

- This runbook has not been exercised against a real partial-failure scenario — it is derived from reading the migration source directly, not from an observed incident.
- The exact, safe "process only the remaining orders" repair for `214611`'s first backfill loop is described in principle above (identify what's already done, process only what isn't) but no ready-made SQL/script for it is provided here — it depends on the specific partial state found, and deliberately is not something to script in advance of knowing that.
- Whether production has ever actually attempted any of these four migrations is unknown from this document alone — always start with the detection queries, never with an assumption.
