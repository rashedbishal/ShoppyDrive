# ShoppyDrive — Project Handoff (2026-11-03)

**Read this first in any new session.** It assumes **no memory of earlier conversations**. Facts below were verified in the 2026-11-03 session. Re-verify Git and database state before acting.

Read order:
1. `CLAUDE.md`
2. this file
3. `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md`
4. `docs/SHOPPYDRIVE_FULL_SYSTEM_FORENSIC_AUDIT_REPORT_2026-11-02.md`
5. `docs/SHAPPYDRIVE_PRODUCTION_READONLY_CHECKS.sql`

The spellings `SHAPPYDRIVE_*` / `SHOPPYDRIVE_*` are the real filenames. Do not rename them.

---

## 0. Current status at session end (2026-11-03): read this before anything below

**Where sections below conflict with this section, this section wins.** Older sections are kept as the record of how the release got here.

### Deployment: complete, do not repeat it

- `origin/main` moved from `0ef9d9a` to `17a1501` by an approved push. All 7 commits in §2 are live on production.
- GitHub Actions "Deploy to Hostinger" run `41575932713` succeeded: required checks, file sync, post-deploy tasks and both runtime checks.
- `migrate --force` printed "Nothing to migrate", so this deploy ran no migrations.
- The deploy log reported `APP_ENV=production` with `APP_DEBUG=false`, and the health check passed on attempt 1.
- Smoke check, **availability only**: `/`, `/up` and `/products` returned 200, and `/order/SMOKE-NOT-REAL/confirmation` returned 404.
- The tracker status for the deployed findings is `DEPLOYED_UNVERIFIED`. Nothing is `VERIFIED_IN_PRODUCTION`.

### Non-gating workflows that failed on `17a1501` (neither fixed)

- **Run Tests:** one flaky failure, `BuyNowCheckoutTest` ("buy now places an order without ever creating a cart row"). The test buys 2 units of a product with random factory stock from 0 to 500, so it fails when stock is 0 or 1. The same suite passed in the deploy's required checks.
- **Lint:** Prettier fails on `resources/js/pages/admin/homepage/hero-slides/create.tsx`, `resources/js/pages/admin/homepage/hero-slides/edit.tsx` and `resources/js/pages/Home.tsx`. The 7 commits did not change these files.

### Production facts

| Fact | Value | Source |
|---|---|---|
| Host and app folder | `my-kul-h5g-node12.hstgr.io`, `/home/u631918861/websites/j34XMYNEn/public_html` | Owner's SSH output |
| Database | **MariaDB 11.8.9, not MySQL** (Laravel connection name is `mysql`) | Owner's pre-check through Laravel PDO |
| Isolation | `READ-COMMITTED`, `innodb_snapshot_isolation` ON | Owner's pre-check |
| Invalid `pending_checkouts.checkout_data` rows | 0 | Owner's pre-check |
| Tables | 74; a migrated test database has 73, and the extra table is **not identified** | Owner's pre-check |
| Business data | No important business data | Owner's statement |
| Direct `mariadb` CLI login | Failed with ERROR 1045; `php artisan db:show` and Laravel PDO work | Owner's report |

### Not done on production

- **The read-only SQL runner has NOT been executed.** So FSA-05 product visibility impact, the 5 unique indexes, `job_batches` and the 74th table are all unknown.
- **No real COD order, mobile payment or Google login test has been performed.**
- No newsletter has been sent to test the queue, and none should be sent just for that.

### Read-only runner

- **Runner:** `/tmp/claude-1000/-home-octagon-Desktop-ShoppyDrive/61f5993e-7f4c-43c6-b32a-b54beb8e83c8/scratchpad/runner/sd_run_checks.php`, SHA-256 `3c75869445856fb80d19f6dbe171e6b969083ba0c5712944a19a980adf5d7e35`.
- **SQL file:** `docs/SHAPPYDRIVE_PRODUCTION_READONLY_CHECKS.sql`, on Hostinger at `~/sd_checks.sql`, SHA-256 `f429ebedfb0d317ed65ae76f85254f204cd286e50876ccb36c983c3e0e93e106`.
- **Hashes:** the owner reports that both were verified. The local copies were confirmed to match these values.
- **Method:** the direct CLI failed, so the runner uses Laravel's own PDO connection. It validates the file, sends the 22 statements one at a time in order, stops at the first failure, then prints a separate schema report. Do not pass the file to `DB::unprepared()`.
- **Tested:** on the disposable MariaDB under `REPEATABLE-READ`, with tampered files and a forced failure. It has not been run under `READ-COMMITTED`.
- **The runner lives only in that temporary folder, not in the repo.** If it is gone, ask the owner before recreating it.

### Open problem: Hostinger permissions

- **Previous `chmod 600` attempts failed with Permission denied** (owner's report). The cause is not known, so do not assume one.
- The command previously proposed chains `chmod 600 ... && [hash check] && ... || echo "HASH CHECK OR FOLDER CHANGE FAILED - NOT RUN"`. A `chmod` failure therefore skips the runner and prints that message, even when the hash and folder are fine. Do not reuse this command as it stands.
- Ask the owner to run these read-only diagnostics before attempting the runner:

  ```bash
  id; pwd -P; hostname
  ls -ld ~ ~/sd_checks.sql ~/sd_run_checks.php
  stat -c '%U:%G %a %n' ~/sd_checks.sql ~/sd_run_checks.php
  sha256sum ~/sd_checks.sql ~/sd_run_checks.php
  ```

- The runner's guards also require that exact hostname and app folder, and it reads `~/sd_checks.sql`.

### Concurrency evidence at production settings

Run on the disposable MariaDB at `READ-COMMITTED` with snapshot isolation ON. Connections reported those values.

| Check | Result |
|---|---|
| `CartConcurrencyRaceTest` | 4/4 passed |
| `ReturnFullRefundConcurrencyRaceTest` | 1 of 2 passed. The failure is only the assertion at line 239 that a plain read returns stale data (expects 0, gets 600). The locked-read safety assertion passed, and there was no error 1020 |
| Two-buyer COD checkout, 12 rounds | 24 of 24 orders, no error 1020, no deadlocks; the same scenario under `REPEATABLE-READ` gave 12 of 24 |
| Integrity after that checkout | 0 stock mismatches, 0 inventory-log mismatches, no negative stock |

Evidence: `/tmp/claude-1000/-home-octagon-Desktop-ShoppyDrive/61f5993e-7f4c-43c6-b32a-b54beb8e83c8/scratchpad/mariadb/results/rc3/` and `/tmp/claude-1000/-home-octagon-Desktop-ShoppyDrive/61f5993e-7f4c-43c6-b32a-b54beb8e83c8/scratchpad/mariadb/results/rc2/`.

**Deferred by the owner, not verified at `READ-COMMITTED`:**

- the other 5 race files;
- mobile-banking races;
- multi-item opposite-order and coupon races;
- real concurrent `RefundService` calls.

**Known risks from code reading:**

- `CheckoutController.php:307` locks stock in cart order, so opposite-order carts could deadlock.
- An order insert with a `coupon_id` followed by the coupon lock could also deadlock.
- COD and mobile payment have no transaction retry. A failed mobile submission shows a paid customer "Something went wrong. Please contact support."
- The app does not pin the isolation level. Under `REPEATABLE-READ` with snapshot isolation ON, error 1020 occurred in every COD and mobile scenario tested, so a host change could bring it back.

### Disposable MariaDB: keep it

- **Must stay intact until the owner approves cleanup.** Database `shoppydrive_test_mariadb_20261103` on `127.0.0.1:33118`, under `/tmp/claude-1000/-home-octagon-Desktop-ShoppyDrive/61f5993e-7f4c-43c6-b32a-b54beb8e83c8/scratchpad/mariadb/`, with the app copy in `/tmp/claude-1000/-home-octagon-Desktop-ShoppyDrive/61f5993e-7f4c-43c6-b32a-b54beb8e83c8/scratchpad/tree/` (about 2.2 GB in total).
- It was started by the ending session's background shell (pid 75354), so **it may stop when that session ends**. Its data stays on disk unless `/tmp` is cleared.
- It currently runs at `READ-COMMITTED`, with `innodb_print_all_deadlocks` ON. These are runtime settings, so a restart returns to `REPEATABLE-READ` and deadlock logging OFF. Set them again before testing.
- To restart it, only if the owner approves and it has stopped:

  ```bash
  M=/tmp/claude-1000/-home-octagon-Desktop-ShoppyDrive/61f5993e-7f4c-43c6-b32a-b54beb8e83c8/scratchpad/mariadb
  "$M/server/bin/mariadbd" --no-defaults --basedir="$M/server" --datadir="$M/data" --port=33118 --bind-address=127.0.0.1 --socket=m.sock --pid-file="$M/run/mariadbd.pid" --log-error="$M/run/error.log" --skip-name-resolve --max-connections=300 --innodb-buffer-pool-size=512M
  ```

- The existing MySQL database `shoppydrive_test_release_20261103` must also be preserved.

### Working tree at session end

`docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md` and this file have uncommitted changes. **Preserve them.** Do not commit unless the owner explicitly asks. Pushing even a docs-only commit to `main` runs a full deploy.

---

## 1. Project and current objective

ShoppyDrive is a Bangladesh-focused Laravel 13 / Inertia v3 / React 19 ecommerce storefront plus `/admin` back office, hosted on **Hostinger shared hosting**.

- **Deploy trigger:** pushing `main` to GitHub triggers `.github/workflows/deploy.yml`.
  - It runs Pint and the full test suite as a gate.
  - It then rsyncs the app with `--delete` and runs `php artisan migrate --force` on production.
  - There is no backup first (accepted risk C1).

**Current objective:** remediate the findings of the 2026-11-02 full-system forensic audit (FSA-01 to FSA-24), one finding at a time with user approval. Then release the committed fixes **only after** the production read-only checks pass and the user explicitly approves the push.

| Source | Role |
|---|---|
| `docs/SHOPPYDRIVE_FULL_SYSTEM_FORENSIC_AUDIT_REPORT_2026-11-02.md` | **Original audit source of truth** for FSA findings (evidence, severity, reproduction) |
| `docs/SHAPPYDRIVE_FORENSIC_AUDIT_REPORT.txt` | Earlier audit (2026-11-01): CRIT/HIGH/MED/LOW IDs |
| `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md` | **Operational status source of truth** (its "Reconciled status" table at the top) |
| `docs/MASTER_FIXING_TRACKER.md` | Older remediation record (PROMO-LOY-001, PAY-RETURN-001, …) |
| `docs/SHAPPYDRIVE_PRODUCTION_READONLY_CHECKS.sql` | Verified read-only SQL for the production checks (§10) |

## 2. Git state (verified 2026-11-03)

- **Branch:** `main`. It is the only local branch, and the remote has only `main`. No tags. One stash `stash@{0}` (pre-existing, never touched).
- **Remote:** `origin/main` = `17a1501`, level with local `main`. It was `0ef9d9a` before the push. The deploy workflow succeeded on `17a1501` (run `41575932713`).
- **Local:** `main` is 0 ahead and 0 behind `origin/main`. Pushing `main` = automatic Hostinger deployment + production migrations.
- **Working tree:** two uncommitted documentation changes, this file and the tracker (see §0).

These seven commits are now deployed:

| Order | Commit | Message | Findings |
|---|---|---|---|
| 1 | `8d17320` | fix(newsletter): queue newsletter delivery in subscriber chunks (FSA-13) | FSA-13 |
| 2 | `8cb8ff7` | fix(auth): prevent social login account takeover (FSA-09) | FSA-09 |
| 3 | `1d16a71` | fix(orders): prevent admin cancellation of shipped orders (FSA-11) | FSA-11 |
| 4 | `9c840f7` | fix(checkout): secure order confirmation and preserve bundle attribution (FSA-03, FSA-12) | FSA-03, FSA-12 |
| 5 | `985d73b` | fix(security): harden product variants, pricing and visibility (FSA-01, FSA-04, FSA-05) | FSA-01, FSA-04, FSA-05 |
| 6 | `d1f258c` | docs: consolidate ShoppyDrive forensic audit remediation tracking | tracker, `CLAUDE.md`, audit report (resolves FSA-16) |
| 7 | `17a1501` | docs: add ShoppyDrive release handoff for future sessions | this file, SQL file, `CLAUDE.md` |

Each code commit was staged so it contained only its own finding. Each was tested on an exported copy of exactly the staged tree before committing.

## 3. FSA-01 to FSA-24 status

Statuses use the tracker vocabulary. This table was written before the deploy: rows marked committed are now `DEPLOYED_UNVERIFIED`, and the tracker's table is the current source of truth.

| ID | Sev | Finding (short) | Status | Notes |
|---|---|---|---|---|
| FSA-01 | Critical | Variant of product B accepted with product A → A's flash price on B's variant | COMMITTED `985d73b` | `VariantBelongsToProduct` rule; fail-closed in `CartManager::resolvePrice()`/`validItems()`/`lockForCheckout()` and `InventoryService` |
| FSA-02 | Critical | Payment SMS sender never authenticated (forged "payment received" SMS) | **BLOCKED** | Needs the user's real bKash/Nagad SMS sender IDs and a check of whether the forwarder app relays non-provider senders |
| FSA-03 | High | Public order confirmation exposed PII and `guest_token` | COMMITTED `9c840f7` | `OrderConfirmationAccess`: owner or 24h session grant; allow-listed payload; 404; `throttle:order-tracking`; `Order::$hidden` |
| FSA-04 | High | `cost_price` sent to every storefront visitor | COMMITTED `985d73b` | `$hidden` on `Product`/`ProductVariant`; admin edit page uses `makeVisible` |
| FSA-05 | High | Inactive/unpublished products viewable and orderable | COMMITTED `985d73b` | `Product::isPubliclyVisible()`; product page 404; cart drop; add-to-cart refusal; Buy Now; factory now defaults `published_at` |
| FSA-06 | High | Paid order can be admin-cancelled, then never refunded | **BLOCKED** | Business decision: refuse cancel of paid orders, or cancel records the refund |
| FSA-07 | High | Mobile banking: customer can pay and get no order | **BLOCKED** | Design decision: reserve at staging, flagged order, or reconciliation view |
| FSA-08 | High | Landing pages for variant products can't sell but can take payment | **BLOCKED** | Decision: block at admin save, or add a variant picker |
| FSA-09 | Medium | Social login pre-account-takeover | COMMITTED `8cb8ff7` | Links by email only on Google `email_verified: true`; Facebook never links by email; safe claim of unverified accounts. Follow-up decision: Facebook accounts created before the fix |
| FSA-10 | Medium | Reviews auto-approved, open to unverified accounts | **BLOCKED** | Moderation policy decision |
| FSA-11 | Medium | Admin could cancel shipped orders | COMMITTED `1d16a71` | Refused; courier return report is the path. Follow-up decision: lost parcels |
| FSA-12 | Low | Mobile-banking orders dropped `bundle_id` | COMMITTED `9c840f7` | One line in `MobilePaymentController` |
| FSA-13 | Medium | Newsletter synchronous all-BCC send; attachments never deleted | COMMITTED `8d17320` | `Bus::batch` of `SendNewsletterChunk` (≤50 BCC), cleanup via batch `then`, daily `newsletter:prune-attachments`. Optional decision: retry from Admin › Failed jobs (MED-03 allow-list) |
| FSA-14 | Low | Newsletter unsubscribe/status unauthenticated | OPEN | **Do not start until the user changes the priority** |
| FSA-15 | Medium | (= existing tracker observation) Customer cancel after courier booking, unlocked | **BLOCKED** | Business decision; not duplicated |
| FSA-16 | Medium | Tracker didn't reflect `main` | COMMITTED `d1f258c` | — |
| FSA-17 | Low | Encrypted 2FA columns serialized on every page | OPEN | — |
| FSA-18 | Low | Rating breakdown from 50 reviews only | OPEN | — |
| FSA-19 | Low | Unset SMS forwarder token → 500 | OPEN | — |
| FSA-20 | Low | Product media uploads have no size limit | OPEN | — |
| FSA-21 | Low | Landing-page phone validation weaker than checkout | OPEN | — |
| FSA-22 | Low | Guest loses pending payment after logging in | OPEN | — |
| FSA-23 | Low | `payment_method=zinipay` silently treated as mobile banking | OPEN | — |
| FSA-24 | Info | All settings, including secrets, cached in plaintext file cache | OPEN | — |

**Earlier audit items.** Detail and evidence are in the tracker.
- **`DEPLOYED_UNVERIFIED`** (on `origin/main`; deploy succeeded 2026-11-02; production effect unverified): CRIT-01, HIGH-01/02/03, WISHLIST-GUEST, MED-01–10, MED-12–17, MED-18A, LOW-03/04/05/06/08, REF-CAP-TZ-001, INV-STALE-STOCK-001, SEARCH-AUTOCOMPLETE-FT-001, WISHLIST-ADDALL-*.
- **PARTIALLY_FIXED:** MED-11, MED-20, MEDIA-VIDEO-TYPE-001.
- **BLOCKED:** MED-18 (production `SESSION_SECURE_COOKIE`), LP-INVOICE-STATUS-001 (business and security rule).
- **OPEN:** LOW-10 (deferred by the user).
- **NOT_APPLICABLE:** LOW-07.

**Tracker update pending (needs approval):** the tracker does not yet record the 2026-11-03 MySQL results below. In particular:
- SEARCH-AUTOCOMPLETE-FT-001 is now verified on MySQL.
- MED-11's courier-send race suite has now been run on MySQL (15/15).

Until the tracker is updated, this file is the record of those results.

## 4. Test results

**Full SQLite suite** on integrated `main`, in-memory: **1,736 tests: 1,673 passed, 0 failed, 63 skipped**. All 63 skips are MySQL-only tests.

**Static checks:**
- Pint (test mode), ESLint (0 errors, 7 warnings) and `tsc` all pass.
- `vite build` passes.
- Prettier reports 3 pre-existing files (`Home.tsx` and two hero-slide admin pages from upstream UI commits). This is not caused by the remediation, and `lint.yml` doesn't gate deploys.
- The deploy's `config:cache`, `route:cache`, `view:cache` and `event:cache` succeed on the final code.

**MySQL verification** (2026-11-03; MySQL 8.0.46; database `shoppydrive_test_release_20261103`; race tests use real separate processes):

| Suite | Passed | Failed | Skipped |
|---|---|---|---|
| CartConcurrencyRaceTest | 4 | 0 | 0 |
| LoyaltyPointsConcurrencyRaceTest | 12 | 0 | 0 |
| CouponConcurrencyRaceTest | 1 | 0 | 0 |
| CourierSendConcurrencyRaceTest | 15 | 0 | 0 |
| Admin/ProductStockConflictRaceTest | 3 | 0 | 0 |
| ReturnFullRefundConcurrencyRaceTest | 2 | 0 | 0 |
| Admin/VariantAndAttributeUniquenessMysqlTest | 9 | 0 | 1 |
| SearchCaseFoldingMysqlTest | 5 | 0 | 2 |
| SearchSuggestionsTest | 9 | 0 | 3 |
| ProductSearchTest | 10 | 7 | 0 |
| **Total** | **70** | **7** | **6** |

- **Concurrency:** **37/37** concurrency/race tests passed, including the code changed by the six commits (cart filter and pre-order re-check, checkout and mobile payment, admin cancel).
- **Search autocomplete (SEARCH-AUTOCOMPLETE-FT-001): verified on MySQL.** All 9 committed-row tests in `SearchSuggestionsTest`'s MySQL group passed. This was the first completed run; earlier attempts stopped at 700 seconds.
- **Skips:**
  - Case-folding (2) and suggestions (3) are SQLite-only tests, skipped on MySQL by design.
  - The uniqueness race test (1) hard-codes `shoppydrive_test_loyalty` as its target. It could only run by using that database or editing the test; neither was approved, so it was left skipped.

**The 7 `ProductSearchTest` failures are not application defects:**

| Test | Cause |
|---|---|
| `ProductSearchQuery falls back to a plain LIKE query on the test (non-MySQL) connection` | **By design.** Asserts SQLite behavior (no `MATCH`); MySQL correctly uses FULLTEXT |
| `a search consisting only of a common English stopword matches nothing via the fixed query building (documented trade-off, not a crash)` | **By design.** Asserts SQLite's LIKE fallback matches "the"; on MySQL the stopword gap is the documented trade-off |
| `a partial substring term matches a product name mid-word` | **Documented MySQL limitation.** Mid-word matching is not supported on FULLTEXT (`app/Support/ProductSearchQuery.php:29-31`) |
| `a multi-word phrase search matches a product containing that exact phrase` | **Test isolation.** Rows are created inside `RefreshDatabase`'s uncommitted transaction, and InnoDB FULLTEXT can't see uncommitted rows, so results are empty |
| `search results never include inactive or unpublished products` | **Test isolation** (same cause). Behavior verified by `SearchSuggestionsTest` "inactive and unpublished products stay hidden" |
| `the autocomplete endpoint matches products by partial name and by SKU, scoped to active+published` | **Test isolation** (empty result, not an error). Behavior verified by `SearchSuggestionsTest` |
| `the catalog listing page search filters by the same name/sku/short_description rule` | **Test isolation** (same cause) |

None of the 7 contains a SQL error, an HTTP 500, or MySQL error 1191. Optional test hygiene, which needs approval: make `ProductSearchTest` MySQL-aware, and make the uniqueness race test use its environment variable instead of a hard-coded name.

## 5. Disposable MySQL test database

- **Name:** `shoppydrive_test_release_20261103`, local MySQL 8.0.46, utf8mb4 / utf8mb4_unicode_ci.
- **Contents:** created 2026-11-03 with user approval; 73 tables, about 142 test rows.
- **Must not be dropped without explicit user approval.**
- **Other databases untouched:** a before/after snapshot of every database on the server (table counts, approximate rows, last-change times) was identical. That includes `shoppydrive_test_loyalty`, the dev `ShoppyDrive` database and other projects' databases.
- **Running MySQL tests:**
  - Export `DB_CONNECTION=mysql`, `DB_DATABASE=shoppydrive_test_release_20261103` and `DB_URL=`.
  - Set each `*_RACE_TEST_DATABASE` to the same name: `CART`, `LOYALTY`, `COUPON`, `COURIER`, `STOCK`, `RETURN`, `UNIQUE`.
  - Run one test file per process (`php artisan test --compact <file>`).
  - Built-in guards: `Tests\SafeTestDatabase` refuses non-`shoppydrive_test_*` databases; race tests refuse non-local hosts.
  - Before running anything, confirm the resolved database with a read-only tinker check.

## 6. Production verification still required (user runs it; Claude never touches production)

**This section predates the deploy.** The "before the push" gates were not run before it, so treat them as post-deployment checks (see §0). Use `docs/SHAPPYDRIVE_PRODUCTION_READONLY_CHECKS.sql` through the runner in §0. Every statement is verified on the disposable database and not yet run on production. It contains the queries and the full interpretation guide.

- **Mandatory before the push:**
  - Queries 0 (session safety), 1 (active products without a past `published_at`), 5 (132 migrations + the 14 expected names), 7 (`jobs`/`failed_jobs`/`job_batches` exist) and 8 (no newsletter batch in progress) all within their safe results.
  - `php artisan config:show queue.default` = `database`.
  - `php artisan schedule:list` shows `app:drain-queue` every minute.
  - Production mail works.
- **Recommended:** queries 2, 3/3b, 4, 4b, 6/6b and Q1/Q2, reviewed and recorded.
- **After deploy:**
  - the confirmation page returns 404 for a stranger;
  - no `cost_price` in product page source;
  - a test COD checkout works;
  - `/up` is healthy;
  - security headers are present.

## 7. Newsletter deployment risk

`deploy.yml` runs `rsync --delete` without excluding `storage/app/private/*`, so every deploy empties `storage/app/private` on production. FSA-13 stores attachments for newsletters still being delivered there.

- A deploy during delivery makes the remaining chunks fail closed: nothing incomplete is sent, they land in Failed jobs, and those recipients don't get it.
- The first deploy of FSA-13 is safe by construction (no batches can exist yet).
- **Before every later deploy:**
  1. Freeze newsletter sending.
  2. Run query 8 and deploy only if it returns 0.
  3. If not 0, check 8b: wait while chunks remain; for failed-only batches, retry (`php artisan queue:retry all`) or accept the loss.
  4. Confirm Q2 shows no waiting `App\Jobs\SendNewsletterChunk`.
  5. Deploy.
  6. Re-run query 8 afterwards.
- Fixing `deploy.yml` (excluding `storage/app/private/*`) needs separate user approval. **Do not modify `deploy.yml` without it.**

## 8. Accepted risks and pending decisions

- **Accepted risks (preserve; never re-raise as findings):**
  - ADMIN-2FA-001 (declined, final)
  - DEPLOY-SAFETY-001 C1 (no pre-deploy backup)
  - M1 (`StrictHostKeyChecking=no`)
  - 17 moderate npm advisories
  - XSS-SINK-GAP-001 (`Page.content` unpurified, no write path)
  - MED-19, LOW-01, LOW-02, LOW-09
- **Pending user decisions:**
  - FSA-02 (sender IDs + forwarder check)
  - FSA-06, FSA-07, FSA-08, FSA-10, FSA-15
  - LP-INVOICE-STATUS-001
  - FSA-11 lost-parcel rule
  - FSA-09 handling of pre-fix Facebook accounts
  - FSA-13 retry-from-admin
  - MED-18 production `SESSION_SECURE_COOKIE`
  - `deploy.yml` storage exclusion
  - optional test-hygiene fixes (§4)
  - updating the tracker with the 2026-11-03 MySQL results
- **Release readiness (2026-11-03 audit):**
  - Concluded **READY FOR STAGING**, later superseded: production turned out to be MariaDB, and the MySQL results did not transfer directly. See §0 for the evidence at production settings.
  - The release has now been deployed. The production read-only checks (§6) remain outstanding as post-deployment verification.

## 9. Next session — first task (in this order)

**Do not repeat the deployment or any completed work.**

1. Read `CLAUDE.md` and complete its mandatory reading list.
2. Read this file, starting with §0.
3. Read `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md`.
4. Verify Git:
   - `git status --short --branch`
   - `git log --oneline -3`
   - `git rev-list --left-right --count origin/main...main`

   Expect `main` at `17a1501`, 0 ahead / 0 behind, with uncommitted changes only in this file and the tracker. If anything else differs, stop and ask the user.
5. Review the Hostinger permission issue in §0.
6. **Ask the user to run the permission diagnostics in §0 before attempting the read-only runner.** Do not run anything on production yourself.
7. Once that is resolved and the user approves, give them the corrected runner command, then wait for the output.
8. Report which results are verified against the guide in the SQL file: FSA-05 (statements 4 and 5), indexes (14 and 15), `job_batches` (16) and the 74th table. Do not change any product without reporting the findings first.
9. COD, mobile-banking and Google-login smoke tests need the user's approval first. Proposed approach: a low-value COD order that is kept from the courier and then cancelled; the mobile payment page with a made-up transaction ID and no money sent; a test Google account with a verified Gmail address. A real mobile payment needs separate approval.

Do not commit, push, deploy, or delete the disposable MariaDB environment, scratchpad files or `~/sd_checks.sql` without explicit approval of that step.

**FSA-14 must not be started until the user changes the priority.** No application code changes are pending.

## 10. Rules for every session

These rules are also in `CLAUDE.md`:
- Never assume previous conversation memory exists.
- Verify state from Git and the tracker instead.
- Never repeat completed work without new evidence.
- Never push or deploy without explicit approval of that step.
- Never reset, stash, discard or overwrite user changes.
- Never pop/drop `stash@{0}`.
- Preserve all finding IDs, accepted risks and commit history; never renumber.
- The tracker is the operational status source of truth; the forensic report is the original audit source of truth.
- Never run production SQL or migrations, and never connect to production.
- Ask before modifying production configuration or `deploy.yml`.
- MySQL tests only on a verified `shoppydrive_test_*` database. Never drop `shoppydrive_test_release_20261103` or `shoppydrive_test_loyalty` without approval.
