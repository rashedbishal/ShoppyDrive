# Session Handover — ShoppyDrive Security Audit & Remediation

**Last updated:** 2026-10-31, when the user **paused** PAY-RETURN-001 after its MySQL validation came back incomplete (§3b). DB-001 Phase 2 is committed locally as `0e4af32` (not pushed). PAY-RETURN-001 status: **`PAUSED` / `IMPLEMENTED_UNCOMMITTED` / `MYSQL_VALIDATION_INCOMPLETE`** — full SQLite suite green, but 6 of 110 MySQL tests failed (stale test call sites, not a confirmed application defect). Nothing has been fixed since; work is paused pending the approvals in §4. **Start at §6.**

> **PC-to-PC transfer checkpoint (2026-10-31, user-authorized):** the whole working tree was committed **as-is** (including the six known MySQL test failures, unfixed) as a WIP commit `wip: checkpoint PAY-RETURN-001 before MySQL validation` on the new branch **`transfer/shoppydrive-pc-migration-20261031`**, based on `0e4af32`, and pushed to `origin` (that branch only). The local and remote `main` were **not** changed: remote `main` is still `569f474`, so the 6 local-only `main` commits (ARCH-001 … DB-001 `0e4af32`) now exist on GitHub **only as ancestors of the transfer branch**. `deploy.yml` triggers only on `push` to `main`; the transfer branch triggers CI only (`tests.yml`, `lint.yml`). **Never merge or push this branch into `main`** — the WIP commit is not release-ready, and any push to `main` deploys to Hostinger and runs `php artisan migrate --force` (see §4 item 8). Where this file still says "uncommitted" about PAY-RETURN-001, read "committed only in that WIP checkpoint, not on `main`".

`docs/REMEDIATION_BACKLOG.md` is the authoritative task list (status, evidence and next action per finding). This file only records the pause state of the current checkout. Read both, then `CLAUDE.md`, before acting.

---

## 1. Working checkout

`/home/octagon/Desktop/ShoppyDrive` (authorized 2026-10-30; see backlog §1). The previous machine's checkout — its unstaged changes, four stashes, `dev` branch and `backup/local-main-677c2aa` — exists only there and must not be recreated here.

## 2. Git state (re-verify read-only before acting)

- **Branch:** `main` @ `0e4af32` (`fix: protect order, financial and customer history from deletion (DB-001)`).
- **`origin/main`:** `569f474` — local `main` is **6 commits ahead, not pushed**: ARCH-001 (`aec14e0`), PROMO-LOY-002 (`5516714`), COURIER-REFRESH-CONN-001 (`8315ea3`), COURIER-SEND-RACE-001/002 (`d6c9350`), merge `b5183f2`, DB-001 Phase 2 (`0e4af32`).
- **Committed locally — DB-001 Phase 2 (`COMPLETED` in the backlog):** `0e4af329fc248756a5048ce5b29488b93757c2db`, 34 files (13 new — incl. the 3 migrations dated `2026_10_30_1348*` — 16 DB-001-only modified files incl. `docs/COURIER_MIGRATION_RECOVERY_RUNBOOK.md` §7, and the DB-001 hunks only of `app/Http/Controllers/Admin/OrderController.php`, `routes/admin.php`, `app/Models/Order.php`, `app/Services/ReferralService.php` and `CLAUDE.md`). The shared files were staged with `git apply --cached` of the saved DB-001 diffs and verified byte-for-byte against the saved DB-001-only versions; the DB-001-only tree passed the SQLite full suite (1108 tests, 1084 passed, 0 failed, 24 skipped) before committing. `docs/REMEDIATION_BACKLOG.md` and this file were deliberately excluded. *(Before the commit: 20 modified + 13 new uncommitted DB-001 files.)*
- **Uncommitted, nothing staged — PAY-RETURN-001 follow-up (`IMPLEMENTED_UNCOMMITTED` in the backlog), layered on top of DB-001:** 23 further modified + 31 new files (4 migrations, `2026_10_30_214609`–`214612`), plus its remaining hunks in the five shared files above and the documentation updates to `docs/REMEDIATION_BACKLOG.md` and this file. It depends on DB-001 code (`admin.mother`, `EnsureMotherAdmin`, `MotherPasswordGate`) — now satisfied by `0e4af32`. Committing it needs separate explicit approval.
- **Totals after the DB-001 commit:** 30 modified, 31 untracked (all PAY-RETURN-001 or its documentation), 0 staged. Re-verified at the pause (2026-10-31): unchanged — 30 modified (27 PAY-RETURN-001 code/test files + `CLAUDE.md`, `docs/REMEDIATION_BACKLOG.md`, this file), 31 untracked, 0 staged; the PAY-RETURN-001 code diff and all 31 untracked files are byte-identical to the post-DB-001 scope audit; the 29 whole DB-001 files have no working-tree change.
- **Totals before the DB-001 commit (2026-10-31):** 46 modified, 44 untracked (13 DB-001 + 31 PAY-RETURN-001), 0 staged. DB-001 preservation was re-verified read-only that day: the 13 DB-001 untracked files match their saved SHA-256, the 16 DB-001-only modified files match the saved DB-001 diff exactly, and the 4 overlapping code files rebuild the pre-PAY-RETURN state exactly with no DB-001 line missing. (Baseline: the previous session's scratchpad `…/55f20c1d-…/scratchpad/pr001-preserve/` under `/tmp/claude-1000/`, if it still exists — `/tmp` is not durable.)
- **`vite.config.ts`:** no working-tree change. **`stash@{0}`** (local `CLAUDE.md` admin-permissions note + `vite.config.ts` LAN setting): pre-existing — never pop, drop or apply without explicit approval.

## 3. Databases

- **Development `ShoppyDrive`** (local MySQL 8.0.46, `127.0.0.1:3306`): migrated to **126 migrations** on 2026-10-31 with user approval (the 15 pending, incl. the 3 DB-001 migrations, as batch 3). The DB001IT integration-test records were removed afterwards with user approval; table counts are back to their pre-test values. Rolling back `protect_order_history_foreign_keys` is intentionally refused while orders/users exist. The 4 PAY-RETURN-001 migrations are **Pending** here (not run on `ShoppyDrive`) — running them needs separate approval.
- **Disposable `shoppydrive_test_loyalty`:** kept for MySQL-only tests (`tests/SafeTestDatabase.php` only allows `shoppydrive_test_*`). Race tests need their own env vars, e.g. `DB_CONNECTION=mysql DB_DATABASE=shoppydrive_test_loyalty COUPON_RACE_TEST_DATABASE=… RETURN_RACE_TEST_DATABASE=… LOYALTY_RACE_TEST_DATABASE=… COURIER_RACE_TEST_DATABASE=… vendor/bin/pest <file>` — without them those files skip. For PAY-RETURN-001 the previous session ran, on this database after verifying its identity: the negative-balance tests, the refund-ledger race, the three new courier-evidence cases, and a rollback + re-apply of all 4 PAY-RETURN-001 migrations (the signed-balance rollback correctly refused while a negative balance existed). **The full MySQL race suites have not been run** against the PAY-RETURN-001 code. *(Superseded 2026-10-31: they have now been run — see §3b; 6 failures.)*
- **`shoppydrive_test_loyalty` now holds 12 leftover rows** from the aborted loyalty case "rolls back the status change, history and refund together … when the REFUND ledger entry fails": `users` 1 (`loyalty-atomicity-race-6ae52af6c8456@example.com`), `orders` 1 (`ORD-ATOMIC-RACE-6ae52af709948`, processing/unpaid), `order_items` 1, `products` 1, `categories` 2, `sub_categories` 1, `activity_log` +5 (the database had 140 rows before the run, 152 after; `cache`/`cache_locks` are empty, so no rate-limit counters remain). **Left in place on the user's instruction** — not deleted. They are harmless: the next MySQL test run's `RefreshDatabase` (`migrate:fresh`) wipes this disposable database.

## 3a. PAY-RETURN-001 verification status (2026-10-31)

- **Full SQLite suite, after the final JSON key-order fix:** 1,225 tests — 1,197 passed, **0 failed**, 28 skipped. All 28 skips are MySQL-only race/search tests (`CourierSendConcurrencyRaceTest` 15, `LoyaltyPointsConcurrencyRaceTest` 8, `ReturnFullRefundConcurrencyRaceTest` 2, `ProductSearchTest` 2, `CouponConcurrencyRaceTest` 1). The known `BuyNowCheckoutTest` flake (backlog §10) did not occur on this run.
- **Static checks (previous session):** Pint, TypeScript, Prettier, ESLint (0 errors, 7 pre-existing warnings), production build and `git diff --check` passed.
- **Mode:** verification-only — automatic courier-based refund eligibility is **not** enabled. Business rules are fixed (backlog → PAY-RETURN-001 follow-up); do not redesign them.
- **Known limitations:** full MySQL race suites not run *(since run — 6 failures, see §3b)*; production preflight (refund backfill counts, partial-delivery evidence, orders delivered without courier evidence — the draft query lives only in the previous session's `/tmp` scratchpad `payreturn/preflight.sql`) not run; after any deploy, Admin › Courier readiness must be attested before any courier-handled refund; deferred items listed in the backlog entry and `PAY-RETURN-001-BATCH-2` remain open; the backlog changelog line for DB-001's commit was intentionally deferred until DB-001 was committed (added after `0e4af32`).
- **Production:** never accessed. Deployment status of every unpushed change is "not deployed".

## 3b. PAY-RETURN-001 MySQL validation (2026-10-31) — INCOMPLETE

Run on disposable `shoppydrive_test_loyalty` only (identity verified first: default connection `mysql`, database `shoppydrive_test_loyalty`, `127.0.0.1:3306`, server `database()` = `shoppydrive_test_loyalty`), 04:57–05:41 Asia/Dhaka, with `DB_CONNECTION=mysql DB_DATABASE=shoppydrive_test_loyalty` and all four `*_RACE_TEST_DATABASE=shoppydrive_test_loyalty`. Code was unchanged by the run. Development `ShoppyDrive` was unaffected (all 69 tables: identical row counts, 196 total, and identical `CHECKSUM TABLE` before/after; still 126 migrations).

| File | Tests | Passed | Failed | Skipped |
|---|---|---|---|---|
| `tests/Feature/ReturnFullRefundConcurrencyRaceTest.php` | 2 | 2 | 0 | 0 |
| `tests/Feature/CouponConcurrencyRaceTest.php` (direct dependency: refunds release coupons) | 1 | 1 | 0 | 0 |
| `tests/Feature/OrderRefundTest.php` | 36 | 35 | **1** | 0 |
| `tests/Feature/Admin/DeliveryVerificationTest.php` | 19 | 19 | 0 | 0 |
| `tests/Feature/CourierDeliveryEvidenceTest.php` | 29 | 29 | 0 | 0 |
| `tests/Feature/CourierSendConcurrencyRaceTest.php` | 15 | 14 | **1** | 0 |
| `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php` | 8 | 4 | **4** | 0 |
| **Total** | **110** | **104** | **6** | **0** |

**Failure 1–5 — stale `markRefunded()` call sites (test code, not fixed).** PAY-RETURN-001 changed `Admin\OrderController::markRefunded(Order $order)` to `markRefunded(Request $request, Order $order, RefundService $refunds)` (requires `refund_amount` + `refund_method`, and an order with collected payment / courier verification). These MySQL-only tests skip on SQLite, so the full SQLite suite never exercised them. Five failing cases come from **four source lines**:

| Source line | Failing case(s) | Error |
|---|---|---|
| `tests/Feature/CourierSendConcurrencyRaceTest.php:398` — `$controller->markRefunded($order)` | "refuses to cancel or mark refunded an order whose courier booking is in flight" | `TypeError`: argument #1 must be `Request`, `Order` given |
| `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php:365` — `controllerUnderRace()->markRefunded($order)` | "rejects a duplicate mark-refunded that was blocked behind the first one…" | `TypeError` (same) |
| `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php:422` — `controllerUnderRace()->markRefunded($order)` | "rolls back the status change, history and refund together … when the REFUND ledger entry fails" | expected "Simulated REFUND ledger failure.", got the `TypeError`; its cleanup never ran (→ the 12 leftover rows in §3) |
| `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php:254` — `'refund' => $controller->markRefunded($order)` inside `LOYALTY_RACE_CONTENDER_SCRIPT` (the contender subprocess; started with arg `'refund'` at lines 380 and 481) | "refunds once when an admin cancellation races a mark-refunded…" ("The contending process never blocked on the Order row lock.") and "retries mark-refunded after a real InnoDB deadlock…" ("markRefunded() never blocked on the customer row.") | the contender crashes with the `TypeError` before reaching any lock |

`LoyaltyPointsConcurrencyRaceTest.php` is part of the committed DB-001 tree (`0e4af32`); fixing it would be a new uncommitted change on top, never a rewrite of that commit.

**Failure 6 — strict-type assertion (test code, not fixed).** `tests/Feature/OrderRefundTest.php:339`, case "a partial refund reverses earned points by the money actually refunded, not the returned units": `->sum('points')` returns the string `'-30'` on MySQL, and `toBe(-30)` is strict. The business result is correct (balance 60, 30 points reversed); application code casts every `sum()` (`(int)` / `RefundCalculator::toPaisa()`), so this is MySQL-only test strictness.

**Consequence:** passing on MySQL — the three PAY-RETURN-001 courier-evidence races, refund serialization on returns, coupon race, delivery verification and courier evidence. **Not yet proven on real MySQL:** whole-order mark-refunded while a courier booking is in flight, duplicate/contending mark-refunded, rollback when the REFUND ledger entry fails, and deadlock retry of mark-refunded through the new `RefundService` path. A rerun after the test fixes could still expose a real locking issue — if so, stop and report, do not change application code without approval.

**Never run on MySQL against PAY-RETURN-001:** `tests/Feature/Admin/CourierVerificationReadinessTest.php`, `tests/Feature/ReturnRequestTest.php`, `tests/Feature/LoyaltyPointsTest.php`, `tests/Feature/Admin/MarkOrderRefundedTest.php`.

**Proposed test-only fix — NOT approved, NOT applied:** (1) update the four source lines above to pass `Request::create('/', 'PATCH', ['refund_amount' => …, 'refund_method' => …])`, the `Order` and a `RefundService`, and set each order up as paid and courier-verified (as `MarkOrderRefundedTest` does via `wholeOrderRefundPayload()`); (2) compare `(int)` of the sum at `OrderRefundTest.php:339`; (3) rerun only `LoyaltyPointsConcurrencyRaceTest.php`, `CourierSendConcurrencyRaceTest.php` and `OrderRefundTest.php` on MySQL (~25–30 min), then decide on the four never-run files.

**Temporary artifacts left in place (on the user's instruction):** 105 `/tmp/fake-courier-*` folders (11 from this run, 94 from earlier runs; request/script logs of the fake courier server); no fake-courier server process is running. Raw results of this run (JUnit XML, outputs, DB snapshots, `run.sh`) are only in this session's scratchpad `/tmp/claude-1000/-home-octagon-Desktop-ShoppyDrive/fe09849e-167c-4310-a86d-8d36e8bc6b5f/scratchpad/mysql-race/` — `/tmp` is not durable; this section is the durable record.

## 4. Pending approvals (each separately)

1. ~~Committing DB-001 Phase 2.~~ Done 2026-10-31: `0e4af32` (local only).
2. Resuming PAY-RETURN-001 at all (paused by the user 2026-10-31).
3. Applying the proposed test-only fixes in §3b and rerunning the affected MySQL files; then whether to run the four never-run files on MySQL.
4. Committing the PAY-RETURN-001 follow-up (its DB-001 dependency is now committed) — not before MySQL validation is complete or the user explicitly accepts the gap.
5. ~~Running the full MySQL race suites~~ — run 2026-10-31 with test-only approval; incomplete (§3b).
6. Deleting the 12 leftover rows in `shoppydrive_test_loyalty` or the `/tmp/fake-courier-*` folders (left in place on the user's instruction).
7. Running the 4 pending PAY-RETURN-001 migrations (`2026_10_30_214609`–`214612`) on the development `ShoppyDrive` database — still **Pending** there.
8. Pushing `main` — this auto-deploys to Hostinger and runs `php artisan migrate --force` with no gate. Before that: run `php artisan courier:check-consignment-duplicates`, the DB-001 orphan checks in `docs/COURIER_MIGRATION_RECOVERY_RUNBOOK.md` (§3–§7) and the PAY-RETURN-001 read-only preflight against production, by the user, with a backup. After it: complete Admin › Courier readiness before any courier-handled refund.
9. Any of the remaining DB-001 low-priority items (backlog → DB-001 → "Remaining low-priority issues").
10. Any other backlog task — one explicitly approved task at a time.

## 5. Testing approach for future remediation (user policy, 2026-10-31)

Targeted SQLite tests first; the full SQLite suite only for broad-impact changes or final acceptance of a major remediation; Pint/TypeScript/ESLint/`git diff --check` as relevant. MySQL tests only when the change affects MySQL-specific SQL, migrations/FKs/constraints, transactions/row locking, concurrency, or MySQL JSON/index/query behavior — then only the smallest relevant file, on a verified `shoppydrive_test_*` database, never `ShoppyDrive` or production. Report every skipped test and why. **Exception learned 2026-10-31:** a changed controller/service signature is MySQL-sensitive for this purpose — the `*ConcurrencyRaceTest.php` files (including their PHP heredoc subprocess scripts) skip on SQLite and call controllers directly, so grep them for call sites whenever a signature changes.

## 6. Exact continuation point

**On the new PC (after the transfer):**
1. `git clone git@github.com:rashedbishal/ShoppyDrive.git && cd ShoppyDrive && git fetch origin && git switch transfer/shoppydrive-pc-migration-20261031`. Read-only re-verification: HEAD is the WIP checkpoint (`wip: checkpoint PAY-RETURN-001 before MySQL validation`), its parent is `0e4af32` (DB-001), working tree clean. A fresh clone's local `main` tracks remote `main` (`569f474`) and does **not** contain this work — stay on the transfer branch; never push it to `main`.
2. **Not carried by git — recreate by hand:** `.env` (credentials; copy securely, never commit), the local MySQL databases (development `ShoppyDrive` at 126 migrations, disposable `shoppydrive_test_loyalty` — create a fresh `shoppydrive_test_*` database for MySQL tests), `stash@{0}` (old PC only: local `CLAUDE.md` admin-permissions note + `vite.config.ts` LAN setting), the `/tmp` scratchpad evidence (raw JUnit/DB snapshots; §3b is the durable record), and generated files (`composer install`, `npm ci`, `php artisan ziggy:generate`, `npm run build` — or `composer setup`, which also runs migrations, so check which database `.env` points at first).
3. The four PAY-RETURN-001 migrations (`2026_10_30_214609`–`214612`) have still not been run on any development database; the 6 MySQL failures in §3b are still present in the checkpoint code.

**On the original PC (before the transfer), for reference:**
1. Read-only re-verification: `git status --short`, `git log -2 --oneline` (HEAD must be `0e4af32`, parent `b5183f2`), `git diff --cached --name-only` (must be empty), `git stash list` (`stash@{0}` only), `git rev-list --count origin/main..main` (6). Expect 30 modified + 31 untracked. If anything differs, stop and report before acting.
2. Do nothing further on PAY-RETURN-001 until the user explicitly un-pauses it.
3. When un-paused, the next step is §4 item 3: the test-only fixes in §3b (four source lines + `OrderRefundTest.php:339`), then the targeted MySQL rerun on a re-verified `shoppydrive_test_loyalty`. No application code changes; if a rerun fails on real locking behavior, stop and report.
4. Only after MySQL validation is complete (or the user accepts the gap): a read-only PAY-RETURN-001 commit-scope audit like the DB-001 one (the PAY-RETURN-001 hunks in `CLAUDE.md` vs. the docs-only hunks, whether `docs/REMEDIATION_BACKLOG.md`/this file go in a separate docs commit), then staging/commit on separate approvals.

---

## Suggested opening prompt for a resuming session

> Resume ShoppyDrive remediation. Read `docs/REMEDIATION_BACKLOG.md`, `docs/SESSION_HANDOVER.md` and `CLAUDE.md`. Re-check git status, `stash@{0}` and how far `main` is ahead of `origin/main`, read-only. Do not commit, push, deploy, migrate, or touch any database until I authorize that specific step.
>
> *(PAY-RETURN-001 specifically, when un-pausing:)* Resume PAY-RETURN-001 from `docs/SESSION_HANDOVER.md` §6. Verify the state read-only first. I approve only the test-only fixes listed in §3b and a targeted MySQL rerun on the verified disposable `shoppydrive_test_loyalty` — no application code changes, no staging, no commit.
